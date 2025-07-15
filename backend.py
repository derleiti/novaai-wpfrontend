import base64
import io
import json
import os
import logging
from typing import List, Optional
from fastapi import FastAPI, HTTPException, File, UploadFile, Form, Request
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from PIL import Image
import requests
import uvicorn

# === Konfiguration ===
OLLAMA_BASE_URL = "http://localhost:11434"
STABLE_DIFFUSION_URL = "http://127.0.0.1:7860"
SESSION_PATH = "/tmp/ollama_sessions"
MAX_CONTEXT_MESSAGES = 20

# === Logging ===
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(title="KI-Backend API", version="1.1.1")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"]
)

# === Modelle ===
class ChatMessage(BaseModel):
    role: str
    content: str

class VisionRequest(BaseModel):
    prompt: str
    image: str
    model: str = "llava:latest"

class ImageGenerateRequest(BaseModel):
    prompt: str
    negative_prompt: Optional[str] = ""
    steps: Optional[int] = 20
    cfg_scale: Optional[float] = 7.0
    width: Optional[int] = 512
    height: Optional[int] = 512
    seed: Optional[int] = -1
    sampler_name: Optional[str] = "Euler a"

# === Hilfsfunktionen ===
def load_session(session_id: str) -> List[dict]:
    path = os.path.join(SESSION_PATH, f"{session_id}.json")
    if os.path.exists(path):
        with open(path, "r") as f:
            return json.load(f)
    return []

def save_session(session_id: str, messages: List[dict]):
    os.makedirs(SESSION_PATH, exist_ok=True)
    path = os.path.join(SESSION_PATH, f"{session_id}.json")
    with open(path, "w") as f:
        json.dump(messages[-MAX_CONTEXT_MESSAGES:], f)

# === Endpunkte ===
@app.get("/")
async def root():
    return {
        "status": "healthy",
        "version": "1.1.1",
        "endpoints": {
            "chat": "/chat",
            "image": "/image/generate",
            "vision": "/vision"
        }
    }

@app.post("/chat")
async def chat(request: Request):
    try:
        data = await request.json()
        session_id = data.get("session_id", "")
        prompt = data.get("prompt", "")
        model = data.get("model", "mixtral:8x7b")

        if not prompt or not session_id:
            raise HTTPException(status_code=400, detail="Prompt oder session_id fehlt")

        # Session-Kontext laden
        messages = load_session(session_id)
        messages.append({"role": "user", "content": prompt})

        # Sprache bei erster Nachricht bestimmen
        if len(messages) == 1:
            first_prompt = prompt.lower()
            if any(word in first_prompt for word in ["hello", "please", "what", "how", "can you", "explain", "tell me", "who", "where", "why", "english"]):
                system_msg = {
                    "role": "system",
                    "content": "You are a helpful, friendly AI assistant that speaks English by default."
                }
            else:
                system_msg = {
                    "role": "system",
                    "content": "Du bist ein hilfreicher, freundlicher KI-Assistent und antwortest auf Deutsch."
                }
            messages.insert(0, system_msg)

        payload = {
            "model": model,
            "messages": messages[-MAX_CONTEXT_MESSAGES:],
            "stream": False,
            "options": {
                "temperature": 0.7,
                "top_p": 0.9,
                "top_k": 40
            }
        }

        res = requests.post(f"{OLLAMA_BASE_URL}/api/chat", json=payload, timeout=180)
        res.raise_for_status()
        result = res.json()

        # KI-Antwort speichern
        assistant_msg = result.get("message", {})
        messages.append(assistant_msg)

        save_session(session_id, messages)

        return result

    except requests.exceptions.RequestException as e:
        logger.error(f"Ollama-Fehler: {e}")
        raise HTTPException(status_code=503, detail="Ollama Service nicht erreichbar")
    except Exception as e:
        logger.error(f"Chat-Fehler: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/image/generate")
async def generate_image(request: ImageGenerateRequest):
    try:
        payload = {
            "prompt": request.prompt,
            "negative_prompt": request.negative_prompt,
            "steps": request.steps,
            "cfg_scale": request.cfg_scale,
            "width": request.width,
            "height": request.height,
            "seed": request.seed,
            "sampler_name": request.sampler_name,
            "batch_size": 1,
            "n_iter": 1,
            "save_images": False,
            "send_images": True,
            "alwayson_scripts": {}
        }

        res = requests.post(f"{STABLE_DIFFUSION_URL}/sdapi/v1/txt2img", json=payload, timeout=180)
        res.raise_for_status()
        result = res.json()

        return {
            "images": result.get("images", []),
            "parameters": result.get("parameters", {}),
            "info": json.loads(result.get("info", "{}"))
        }

    except Exception as e:
        logger.error(f"Stable Diffusion Fehler: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/vision/upload")
async def vision_upload(
    prompt: str = Form(...),
    model: str = Form("llava:latest"),
    file: UploadFile = File(...)
):
    try:
        image_data = base64.b64encode(await file.read()).decode("utf-8")
        payload = {
            "model": model,
            "prompt": prompt,
            "images": [image_data],
            "stream": False,
            "options": {"temperature": 0.7}
        }

        res = requests.post(f"{OLLAMA_BASE_URL}/api/generate", json=payload, timeout=120)
        res.raise_for_status()
        return res.json()

    except Exception as e:
        logger.error(f"Vision Fehler: {e}")
        raise HTTPException(status_code=500, detail=str(e))

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)
# forced change
