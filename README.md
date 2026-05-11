# BizHub

BizHub is a **Symfony 6.4** web app (PHP **8.2**) built for the ESPRIT PIDEV 3A 2025/2026 project.

It mixes classic web features (auth, dashboards, workflows) with integrations (payments, signature, messaging) and an **optional AI recommender** service for e-learning.

### 🔗 Quick Links

- 📚 Libraries & APIs inventory: [LIBRAIRIES.md](LIBRAIRIES.md)
- 🧩 Admin template notes: [templates/back/README.md](templates/back/README.md)
- 🗃️ SQL dump (optional import): `BizHub(9).sql`

---

## ✨ What you can do with BizHub

- 🔐 **Auth & Security**: email verification, OAuth (Google/Auth0), optional 2FA/TOTP
- 💼 **Investissement**: deals workflow (Stripe payments + Yousign signing)
- 🛒 **Marketplace**: products/media uploads, notifications (Twilio), shipping/tracking (Shippo)
- 🎓 **E-learning**: formations + participation + personalized recommendations
- 👥 **Community & Reviews**: community features and user feedback
- 🧠 **AI helpers**: optional integrations (OpenAI/Groq) depending on env keys

---

## 🚀 Quick Start (local dev)

> You only need PHP + MySQL to run the Symfony app.

### 1) Install PHP dependencies

```bash
composer install
```

### 2) Configure your local env

Create `.env.local` (not committed) and set at least:

```dotenv
APP_SECRET=change_me
DATABASE_URL="mysql://root:@127.0.0.1:3306/BizHub"
```

### 3) Create DB + run migrations

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### 4) Start the server

```bash
symfony server:start
```

If you don’t have Symfony CLI:

```bash
php -S 127.0.0.1:8000 -t public
```

Open: http://127.0.0.1:8000

---

## 🤖 Optional: AI formation recommender (FastAPI)

BizHub can call a small FastAPI service that returns recommended formation IDs.

### ✅ Start it

```bash
cd recommender
cp .env.example .env
```

Set the recommender DB URL (same DB as Symfony):

```dotenv
DATABASE_URL="mysql://root:@127.0.0.1:3306/BizHub"
```

Then:

```bash
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
python -m uvicorn app.main:app --host 127.0.0.1 --port 8765
```

### 🔍 Endpoints

- `GET  /health` → should return `{ "status": "ok" }`
- `POST /recommendations/{user_id}` with `{ "max": 16 }`

### 🔌 Connect Symfony → FastAPI

Add to `.env.local`:

```dotenv
RECOMMENDATIONS_API_URL=http://127.0.0.1:8765
```

> Note: some code paths also default to `http://127.0.0.1:8765` directly, so keeping the service on **127.0.0.1:8765** is the smoothest local setup.

---

## 🔌 Integrations (optional)

BizHub supports several external services. You can enable them by providing keys in `.env.local`:

- 💳 Stripe
- ✍️ Yousign
- 💬 Twilio (WhatsApp/SMS)
- 🧠 OpenAI / Groq
- 🙂 Face++ (face verification)
- 📰 GNews
- 📦 Shippo

If a key is missing, the related feature may be disabled or behave in “dev mode”.

---

<details>
<summary>🧪 Dev tools (tests, quality)</summary>

Run tests:

```bash
php bin/phpunit
```

Static analysis:

```bash
vendor/bin/phpstan analyse
```

List routes:

```bash
php bin/console debug:router
```

</details>

<details>
<summary>⚙️ “Where do env vars live?”</summary>

- Defaults are in `.env`
- Your machine overrides should go in `.env.local`
- Many parameters are wired through `config/services.yaml`

For production, prefer real environment variables or Symfony secrets.

</details>