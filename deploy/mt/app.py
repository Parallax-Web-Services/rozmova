"""
Reference translation service implementing the Rozmova wire contract.

    POST /translate    {"text": "...", "from": "uk", "to": "en"}
    -> {"text": "...", "engine": "..."}

NLLB-200 distilled 600M runs on CPU and is adequate for batch archive work.
For speech transcripts, where fluency matters more than throughput, a local
instruct model behind the same contract is the upgrade path.
"""

import os

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, ConfigDict, Field

MODEL = os.environ.get("MT_MODEL", "facebook/nllb-200-distilled-600M")

CODES = {"uk": "ukr_Cyrl", "ru": "rus_Cyrl", "en": "eng_Latn"}

app = FastAPI()
_pipe = None


class Request(BaseModel):
    # "from" is reserved in Python, so the wire field arrives by alias.
    model_config = ConfigDict(populate_by_name=True)

    text: str
    src: str = Field(default="uk", alias="from")
    to: str = "en"


def pipe(src: str, tgt: str):
    global _pipe
    from transformers import pipeline

    return pipeline("translation", model=MODEL, src_lang=src, tgt_lang=tgt)


@app.get("/health")
def health():
    return {"ok": True, "engine": MODEL}


@app.post("/translate")
def translate(request: Request):
    src = CODES.get(request.src)
    tgt = CODES.get(request.to)

    if src is None or tgt is None:
        raise HTTPException(status_code=400, detail=f"unsupported pair {request.src}->{request.to}")

    if not request.text.strip():
        raise HTTPException(status_code=400, detail="empty text")

    out = pipe(src, tgt)(request.text, max_length=1024)
    text = (out[0].get("translation_text") or "").strip()

    if not text:
        raise HTTPException(status_code=422, detail="empty translation")

    return {"text": text, "engine": MODEL}
