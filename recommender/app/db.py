"""SQLAlchemy engine from DATABASE_URL (same as Symfony .env)."""

from __future__ import annotations

import os
from functools import lru_cache
from urllib.parse import quote_plus

from sqlalchemy import create_engine
from sqlalchemy.engine import Engine


def _normalize_mysql_url(url: str) -> str:
    if url.startswith("mysql://"):
        return "mysql+pymysql://" + url[len("mysql://") :]
    return url


@lru_cache
def get_engine() -> Engine:
    raw = os.environ.get("DATABASE_URL", "").strip()
    if not raw:
        raise RuntimeError("DATABASE_URL is not set for the recommender service.")
    url = _normalize_mysql_url(raw)
    return create_engine(url, pool_pre_ping=True, pool_recycle=3600)
