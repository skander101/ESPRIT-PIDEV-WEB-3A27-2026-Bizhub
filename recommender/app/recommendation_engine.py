"""
Hybrid recommender: collaborative (user-user cosine on participation vectors)
+ content-based (TF-IDF on title/description), popularity & recency boosts.
"""

from __future__ import annotations

import numpy as np
import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
from sklearn.neighbors import NearestNeighbors
from sqlalchemy import text
from sqlalchemy.engine import Engine


def _load_frames(engine: Engine) -> tuple[pd.DataFrame, pd.DataFrame]:
    formations = pd.read_sql(
        text(
            """
            SELECT formation_id, title, description, cost, en_ligne,
                   start_date, end_date
            FROM formation
            """
        ),
        engine,
    )
    participation = pd.read_sql(
        text(
            """
            SELECT user_id, formation_id, participation_status, payment_status, created_at
            FROM participation
            """
        ),
        engine,
    )
    return formations, participation


def recommend_for_user(engine: Engine, user_id: int, max_results: int = 16) -> list[int]:
    formations, participation = _load_frames(engine)
    if formations.empty:
        return []

    formations["formation_id"] = formations["formation_id"].astype(int)
    if participation.empty:
        participation = pd.DataFrame(columns=["user_id", "formation_id"])
    else:
        participation["user_id"] = participation["user_id"].astype(int)
        participation["formation_id"] = participation["formation_id"].astype(int)

    engaged = set(
        participation.loc[participation["user_id"] == user_id, "formation_id"].tolist()
    )

    collab_scores = np.zeros(len(formations))
    all_fids = formations["formation_id"].astype(int).tolist()
    if not participation.empty:
        up = participation.groupby(["user_id", "formation_id"]).size().reset_index(name="cnt")
        pivot = up.pivot(index="user_id", columns="formation_id", values="cnt").fillna(0)
        pivot = pivot.reindex(columns=all_fids, fill_value=0.0)
        if user_id not in pivot.index:
            pivot.loc[user_id] = 0.0
        pivot = pivot.astype(float)
        if pivot.shape[0] > 1:
            n_neighbors = min(8, max(1, pivot.shape[0] - 1))
            nn = NearestNeighbors(metric="cosine", algorithm="brute", n_neighbors=n_neighbors)
            nn.fit(pivot.values)
            uidx = int(pivot.index.get_loc(user_id))
            dist, idx = nn.kneighbors([pivot.iloc[uidx].values], return_distance=True)
            for j, other_pos in enumerate(idx[0]):
                other_uid = int(pivot.index[other_pos])
                if other_uid == int(user_id):
                    continue
                w = float(1.0 - dist[0][j])
                row = pivot.iloc[other_pos]
                for fid, val in row.items():
                    if float(val) <= 0:
                        continue
                    mask = formations["formation_id"] == int(fid)
                    collab_scores[mask.values] += w

    texts = (
        formations["title"].fillna("")
        + " "
        + formations["description"].fillna("")
    ).tolist()
    vec = TfidfVectorizer(max_features=120, min_df=1)
    X = vec.fit_transform(texts)

    user_mask = participation["user_id"] == user_id if not participation.empty else pd.Series([], dtype=bool)
    past_fids = participation.loc[user_mask, "formation_id"].unique().tolist() if not participation.empty else []
    if past_fids:
        idx_rows = formations.index[formations["formation_id"].isin(past_fids)].tolist()
        prof = np.asarray(X[idx_rows].mean(axis=0))
        content_scores = cosine_similarity(X, prof).ravel()
    else:
        content_scores = np.ones(len(formations)) * 0.25

    if not participation.empty:
        pop = participation.groupby("formation_id").size().reindex(formations["formation_id"]).fillna(0).values
    else:
        pop = np.zeros(len(formations))
    pop_n = pop / (pop.max() + 1e-6)

    start_dt = pd.to_datetime(formations["start_date"], errors="coerce")
    recency = (start_dt - start_dt.min()).dt.days.fillna(0).values
    recency_n = recency / (recency.max() + 1e-6)

    hybrid = (
        1.2 * collab_scores / (collab_scores.max() + 1e-6)
        + 1.4 * content_scores
        + 0.6 * pop_n
        + 0.35 * recency_n
    )

    df = formations.copy()
    df["score"] = hybrid
    df = df[~df["formation_id"].isin(engaged)]
    df = df.sort_values("score", ascending=False)

    return [int(x) for x in df["formation_id"].head(max_results).tolist()]
