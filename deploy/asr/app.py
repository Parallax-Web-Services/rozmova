"""
Reference ASR service implementing the Rozmova wire contract.

    POST /transcribe   multipart: audio, language
    -> {"text": "...", "engine": "..."}

faster-whisper is the default because it stands up in minutes on a CPU droplet.
It is not the accuracy choice for Ukrainian: on the Common Voice benchmark the
NeMo Conformers lead at roughly 4% WER while Whisper-family models trail well
behind. Swap MODEL for a NeMo FastConformer once the pipeline is running --
that is what the SpeechToText interface exists for, and nothing in PHP changes.
"""

import os

from fastapi import FastAPI, File, Form, HTTPException, UploadFile

MODEL = os.environ.get("ASR_MODEL", "large-v3")
DEVICE = os.environ.get("ASR_DEVICE", "cpu")
COMPUTE = os.environ.get("ASR_COMPUTE_TYPE", "int8")

app = FastAPI()
_model = None


def model():
    global _model
    if _model is None:
        from faster_whisper import WhisperModel

        _model = WhisperModel(MODEL, device=DEVICE, compute_type=COMPUTE)
    return _model


@app.get("/health")
def health():
    return {"ok": True, "engine": f"faster-whisper/{MODEL}"}


@app.post("/transcribe")
async def transcribe(audio: UploadFile = File(...), language: str = Form("uk")):
    payload = await audio.read()

    if not payload:
        raise HTTPException(status_code=400, detail="empty audio")

    path = f"/tmp/{audio.filename or 'audio'}"
    with open(path, "wb") as handle:
        handle.write(payload)

    try:
        segments, _info = model().transcribe(path, language=language, vad_filter=True)
        text = " ".join(segment.text.strip() for segment in segments).strip()
    finally:
        os.path.exists(path) and os.unlink(path)

    if not text:
        # Better a 4xx the caller can see than an empty transcript it publishes.
        raise HTTPException(status_code=422, detail="no speech recognised")

    return {"text": text, "engine": f"faster-whisper/{MODEL}"}
