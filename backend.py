import base64
import io
import json
import logging
from typing import List, Optional, Dict, Any
from fastapi import FastAPI, HTTPException, File, UploadFile, Form, Request
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import requests
import uvicorn
from PIL import Image

# Setup logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(title="KI-Backend API", version="1.0.1")

# Improved CORS settings
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # In production, specify actual origins
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "DELETE", "OPTIONS"],
    allow_headers=["*"],
    expose_headers=["*"]
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
    return {
        "status": "healthy", 
        "message": "KI-Backend API läuft",
        "version": "1.0.1",
        "endpoints": {
            "chat": "/chat",
            "vision": "/vision", 
            "image_generate": "/image/generate",
            "models": {
                "chat": "/chat/models",
                "image": "/image/models"
            }
        }
    }

@app.post("/chat")
async def chat(request: Request):
    try:
        data = await request.json()
        logger.info(f"Chat request received: {len(data.get('messages', []))} messages")

        if "messages" not in data:
            prompt = data.get("prompt", "")
            context = data.get("context", [])
            messages = context + [{"role": "user", "content": prompt}]
        else:
            messages = data["messages"]

        if not messages:
            raise HTTPException(status_code=400, detail="Keine Nachrichten bereitgestellt")

        model = data.get("model", "mixtral:8x7b")
        stream = data.get("stream", False)

        ollama_payload = {
            "model": model,
            "messages": messages,
            "stream": stream,
            "options": {
                "temperature": 0.7,
                "top_p": 0.9,
                "top_k": 40
            }
        }

        logger.info(f"Sending request to Ollama: model={model}, messages={len(messages)}")

        response = requests.post(
            f"{OLLAMA_BASE_URL}/api/chat",
            json=ollama_payload,
            headers={"Content-Type": "application/json"},
            timeout=180  # 3 minutes
        )

        if response.status_code != 200:
            logger.error(f"Ollama API error: {response.status_code} - {response.text}")
            raise HTTPException(status_code=response.status_code, detail=f"Ollama API Fehler: {response.text}")

        result = response.json()
        logger.info("Chat response received successfully")
        return result

    except requests.exceptions.ConnectionError as e:
        logger.error(f"Connection error to Ollama: {e}")
        raise HTTPException(status_code=503, detail="Ollama Service nicht erreichbar. Stelle sicher, dass Ollama läuft.")
    except requests.exceptions.Timeout as e:
        logger.error(f"Timeout error: {e}")
        raise HTTPException(status_code=504, detail="Request Timeout - Ollama antwortet nicht rechtzeitig.")
    except Exception as e:
        logger.error(f"Unexpected error in chat: {e}")
        raise HTTPException(status_code=500, detail=f"Unerwarteter Fehler: {str(e)}")

@app.post("/vision")
async def vision_analyze(request: VisionRequest):
    try:
        logger.info(f"Vision analysis request: model={request.model}")
        
        try:
            image_data = base64.b64decode(request.image)
            # Validate image
            img = Image.open(io.BytesIO(image_data))
            logger.info(f"Image loaded: {img.size}, format: {img.format}")
        except Exception as e:
            logger.error(f"Image validation error: {e}")
            raise HTTPException(status_code=400, detail="Ungültiges oder korruptes Bild")

        ollama_payload = {
            "model": request.model,
            "prompt": request.prompt,
            "images": [request.image],
            "stream": False,
            "options": {
                "temperature": 0.7
            }
        }

        response = requests.post(
            f"{OLLAMA_BASE_URL}/api/generate",
            json=ollama_payload,
            headers={"Content-Type": "application/json"},
            timeout=120
        )

        if response.status_code != 200:
            logger.error(f"Ollama Vision API error: {response.status_code} - {response.text}")
            raise HTTPException(status_code=response.status_code, detail=f"Ollama Vision API Fehler: {response.text}")

        result = response.json()
        logger.info("Vision analysis completed successfully")
        return result

    except requests.exceptions.ConnectionError:
        logger.error("Connection error to Ollama for vision")
        raise HTTPException(status_code=503, detail="Ollama Service nicht erreichbar. Stelle sicher, dass Ollama läuft.")
    except requests.exceptions.Timeout:
        logger.error("Timeout error in vision analysis")
        raise HTTPException(status_code=504, detail="Vision-Analyse Timeout")
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Unexpected error in vision: {e}")
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
        logger.error(f"Error in vision upload: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/image/generate")
async def generate_image(request: ImageGenerateRequest):
    try:
        logger.info(f"Image generation request: {request.prompt[:50]}...")
        
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
            timeout=180  # 3 minutes
        )

        if response.status_code != 200:
            logger.error(f"Stable Diffusion API error: {response.status_code} - {response.text}")
            raise HTTPException(status_code=response.status_code, detail=f"Stable Diffusion API Fehler: {response.text}")

        result = response.json()
        logger.info("Image generation completed successfully")

        return {
            "images": result.get("images", []),
            "parameters": result.get("parameters", {}),
            "info": json.loads(result.get("info", "{}"))
        }

    except requests.exceptions.ConnectionError:
        logger.error("Connection error to Stable Diffusion")
        raise HTTPException(status_code=503, detail="Stable Diffusion WebUI nicht erreichbar. Läuft sie auf Port 7860?")
    except requests.exceptions.Timeout:
        logger.error("Timeout in image generation")
        raise HTTPException(status_code=504, detail="Bildgenerierung hat zu lange gedauert (Timeout)")
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Unexpected error in image generation: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/image/models")
async def get_sd_models():
    try:
        response = requests.get(f"{STABLE_DIFFUSION_URL}/sdapi/v1/sd-models", timeout=10)
        if response.status_code == 200:
            return response.json()
        else:
            logger.error(f"Could not get SD models: {response.status_code}")
            raise HTTPException(status_code=503, detail="Konnte Modelle nicht abrufen")
    except requests.exceptions.ConnectionError:
        logger.error("Could not connect to Stable Diffusion for models")
        raise HTTPException(status_code=503, detail="Stable Diffusion WebUI nicht erreichbar")
    except Exception as e:
        logger.error(f"Error getting SD models: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/chat/models")
async def get_ollama_models():
    try:
        response = requests.get(f"{OLLAMA_BASE_URL}/api/tags", timeout=10)
        if response.status_code == 200:
            return response.json()
        else:
            logger.error(f"Could not get Ollama models: {response.status_code}")
            raise HTTPException(status_code=503, detail="Konnte Modelle nicht abrufen")
    except requests.exceptions.ConnectionError:
        logger.error("Could not connect to Ollama for models")
        raise HTTPException(status_code=503, detail="Ollama Service nicht erreichbar")
    except Exception as e:
        logger.error(f"Error getting Ollama models: {e}")
        raise HTTPException(status_code=500, detail=str(e))

# Health check for individual services
@app.get("/health/ollama")
async def health_ollama():
    try:
        response = requests.get(f"{OLLAMA_BASE_URL}/api/tags", timeout=5)
        return {"status": "healthy" if response.status_code == 200 else "error", "url": OLLAMA_BASE_URL}
    except:
        return {"status": "unreachable", "url": OLLAMA_BASE_URL}

@app.get("/health/stable-diffusion")
async def health_sd():
    try:
        response = requests.get(f"{STABLE_DIFFUSION_URL}/sdapi/v1/sd-models", timeout=5)
        return {"status": "healthy" if response.status_code == 200 else "error", "url": STABLE_DIFFUSION_URL}
    except:
        return {"status": "unreachable", "url": STABLE_DIFFUSION_URL}

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000, log_level="info")
