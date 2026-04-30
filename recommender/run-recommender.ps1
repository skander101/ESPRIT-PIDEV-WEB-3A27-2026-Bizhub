# BizHub — lance le service FastAPI de recommandations (Windows / PowerShell)
#
# Utilise un environnement virtuel recommender\.venv pour éviter WinError 32
# (fichier sklearn verrouillé par un autre processus / installation globale).
#
# Usage : cd recommender  puis  .\run-recommender.ps1
#
# Si l'erreur persiste : fermez Jupyter, autres terminaux Python, puis réessayez
# ou supprimez le dossier .venv et relancez ce script.

$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

if (-not (Get-Command py -ErrorAction SilentlyContinue)) {
    Write-Host "Installez Python (py install 3) ou https://www.python.org/downloads/" -ForegroundColor Red
    exit 1
}

$venvDir = Join-Path $PSScriptRoot ".venv"
$pipExe = Join-Path $venvDir "Scripts\pip.exe"
$pythonExe = Join-Path $venvDir "Scripts\python.exe"

if (-not (Test-Path $pythonExe)) {
    Write-Host "Création de l'environnement virtuel .venv ..." -ForegroundColor Cyan
    py -3 -m venv $venvDir
}

Write-Host "Installation des dépendances dans .venv (isolé du Python global) ..." -ForegroundColor Cyan
& $pipExe install --upgrade pip
& $pipExe install -r requirements.txt

if (-not (Test-Path ".env")) {
    Write-Host ""
    Write-Host "Créez recommender\.env avec DATABASE_URL=... (copie depuis Symfony .env / .env.local)." -ForegroundColor Yellow
    Write-Host "  Copy-Item .env.example .env" -ForegroundColor Gray
    Write-Host "  notepad .env" -ForegroundColor Gray
    Write-Host ""
}

Write-Host "Démarrage : http://127.0.0.1:8765  (Ctrl+C pour arrêter)" -ForegroundColor Cyan
& $pythonExe -m uvicorn app.main:app --host 127.0.0.1 --port 8765
