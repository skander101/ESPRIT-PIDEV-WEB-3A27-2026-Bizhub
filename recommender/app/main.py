"""FastAPI recommender — POST /recommendations/{user_id}"""

from __future__ import annotations

import os

from dotenv import load_dotenv
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

from app.db import get_engine
from app.recommendation_engine import recommend_for_user

load_dotenv()

app = FastAPI(title="BizHub Formation Recommender", version="1.0.0")


class RecommendRequest(BaseModel):
    max: int = Field(default=16, ge=4, le=48)


@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/recommendations/{user_id}")
def recommendations(user_id: int, body: RecommendRequest | None = None):
    if user_id <= 0:
        raise HTTPException(status_code=422, detail="Invalid user_id")
    b = body or RecommendRequest()
    try:
        engine = get_engine()
    except RuntimeError as e:
        raise HTTPException(status_code=500, detail=str(e)) from e
    ids = recommend_for_user(engine, user_id, max_results=b.max)
    return {
        "formation_ids": ids,
        "engine": "hybrid_sklearn_v1",
        "user_id": user_id,
    }


if __name__ == "__main__":
    import uvicorn

    port = int(os.environ.get("PORT", "8765"))
    uvicorn.run("app.main:app", host="0.0.0.0", port=port, reload=False)
