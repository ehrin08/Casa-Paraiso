"""Local XLM-RoBERTa sentiment endpoint speaking the Hugging Face text-classification format."""

import os

from fastapi import FastAPI
from pydantic import BaseModel
from transformers import pipeline

CHECKPOINT = os.environ.get("SENTIMENT_CHECKPOINT", "cardiffnlp/twitter-xlm-roberta-base-sentiment")

app = FastAPI()
classifier = pipeline("text-classification", model=CHECKPOINT, tokenizer=CHECKPOINT, top_k=None, truncation=True)


class Request(BaseModel):
    inputs: str
    options: dict | None = None


@app.get("/health")
def health() -> dict:
    return {"status": "ok", "checkpoint": CHECKPOINT}


@app.post("/")
def classify(request: Request) -> list:
    # Comments are never logged; only scores are returned.
    return [classifier([request.inputs])[0]]
