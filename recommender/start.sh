#!/bin/bash
cd /home/maindude/Downloads/ESPRIT-PIDEV-WEB-3A27-2026-Bizhub-master/recommender
source venv/bin/activate
nohup python -m uvicorn app.main:app --host 127.0.0.1 --port 8765 > recommender.log 2>&1 &
echo $! > recommender.pid
echo "FastAPI service started on http://127.0.0.1:8765"
