import base64
import io
import json
from typing import List, Optional, Dict, Any
from fastapi import FastAPI, HTTPException, File, UploadFile, Form, Request
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import requests
import uvicorn
from PIL import Image

app = FastAPI(title="KI-Backend API", version="1.0.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

OLLAMA_BASE_URL = "http://localhost:11434"
STABLE_DIFFUSION_URL = "http://127.0.0.1:7860"

class ChatMessage(BaseModel):
    role: str
    content: str

class ChatRequest(BaseModel):
    messages: List[ChatMessage]
    model: str = "mixtral:8x7b"
    stream: bool = False

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

@app.get("/")
async def health_check():
    return {"status": "healthy", "message": "KI-Backend API läuft"}

@app.post("/chat")
async def chat(request: Request):
    try:
        data = await request.json()

        if "messages" not in data:
            prompt = data.get("prompt", "")
            context = data.get("context", [])
            messages = context + [{"role": "user", "content": prompt}]
        else:
            messages = data["messages"]

        model = data.get("model", "mixtral:8x7b")
        stream = data.get("stream", False)

        ollama_payload = {
            "model": model,
            "messages": messages,
            "stream": stream
        }

        response = requests.post(
            f"{OLLAMA_BASE_URL}/api/chat",
            json=ollama_payload,
            headers={"Content-Type": "application/json"}
        )

        if response.status_code != 200:
            raise HTTPException(status_code=response.status_code, detail=f"Ollama API Fehler: {response.text}")

        return response.json()

    except requests.exceptions.ConnectionError:
        raise HTTPException(status_code=503, detail="Ollama Service nicht erreichbar. Stelle sicher, dass Ollama läuft.")
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/vision")
async def vision_analyze(request: VisionRequest):
    try:
        try:
            image_data = base64.b64decode(request.image)
        except:
            raise HTTPException(status_code=400, detail="Ungültiges Base64 Bild")

        ollama_payload = {
            "model": request.model,
            "prompt": request.prompt,
            "images": [request.image],
            "stream": False
        }

        response = requests.post(
            f"{OLLAMA_BASE_URL}/api/generate",
            json=ollama_payload,
            headers={"Content-Type": "application/json"}
        )

        if response.status_code != 200:
            raise HTTPException(status_code=response.status_code, detail=f"Ollama Vision API Fehler: {response.text}")

        return response.json()

    except requests.exceptions.ConnectionError:
        raise HTTPException(status_code=503, detail="Ollama Service nicht erreichbar. Stelle sicher, dass Ollama läuft.")
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/vision/upload")
async def vision_analyze_upload(
    prompt: str = Form(...),
    file: UploadFile = File(...),
    model: str = Form("llava:latest")
):
    try:
        contents = await file.read()
        image_base64 = base64.b64encode(contents).decode('utf-8')

        vision_req = VisionRequest(
            prompt=prompt,
            image=image_base64,
            model=model
        )

        return await vision_analyze(vision_req)

    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/image/generate")
async def generate_image(request: ImageGenerateRequest):
    try:
        sd_payload = {
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

        response = requests.post(
            f"{STABLE_DIFFUSION_URL}/sdapi/v1/txt2img",
            json=sd_payload,
            headers={"Content-Type": "application/json"},
            timeout=120
        )

        if response.status_code != 200:
            raise HTTPException(status_code=response.status_code, detail=f"Stable Diffusion API Fehler: {response.text}")

        result = response.json()

        return {
            "images": result.get("images", []),
            "parameters": result.get("parameters", {}),
            "info": json.loads(result.get("info", "{}"))
        }

    except requests.exceptions.ConnectionError:
        raise HTTPException(status_code=503, detail="Stable Diffusion WebUI nicht erreichbar. Läuft sie auf Port 7860?")
    except requests.exceptions.Timeout:
        raise HTTPException(status_code=504, detail="Bildgenerierung hat zu lange gedauert (Timeout)")
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/image/models")
async def get_sd_models():
    try:
        response = requests.get(f"{STABLE_DIFFUSION_URL}/sdapi/v1/sd-models")
        if response.status_code == 200:
            return response.json()
        else:
            raise HTTPException(status_code=503, detail="Konnte Modelle nicht abrufen")
    except:
        raise HTTPException(status_code=503, detail="Stable Diffusion WebUI nicht erreichbar")

@app.get("/chat/models")
async def get_ollama_models():
    try:
        response = requests.get(f"{OLLAMA_BASE_URL}/api/tags")
        if response.status_code == 200:
            return response.json()
        else:
            raise HTTPException(status_code=503, detail="Konnte Modelle nicht abrufen")
    except:
        raise HTTPException(status_code=503, detail="Ollama Service nicht erreichbar")

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)
