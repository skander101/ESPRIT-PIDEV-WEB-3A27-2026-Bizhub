# BizHub – Business Hub Platform

## Overview
This project was developed as part of the **PIDEV – 3rd Year Engineering Program** at **Esprit School of Engineering** (Academic Year **2025–2026**).

BizHub is a comprehensive **web platform** built with **Symfony 6.4 (PHP 8.2)** for a unified business hub ecosystem. The platform brings together user management, e-learning formations, project/investment workflows, marketplace transactions, community features, and review systems.

It supports multiple user roles (ex: **Admin**, **Formateur**, **Startup/Participant/Investor**) with role-based access and dedicated experiences.

📚 For a curated list of bundles/APIs used in this project, see [LIBRAIRIES.md](LIBRAIRIES.md).

## Features

### Authentication & User Management
- Secure authentication with role-based access control.
- Email verification flows.
- Optional 2FA / TOTP flows.
- OAuth / SSO integrations (Google/Auth0) depending on configured keys.

### E-Learning Module
- Formation listing and details (title, description, dates, online/offline, cost).
- Participation management (enrollment lifecycle).
- PDF generation for participation/certificates (depending on configured PDF tooling).
- Optional recommendation engine integration (see “FastAPI recommender”).

### Reviews System (UsersAvis)
- User reviews and ratings.
- Admin moderation / management flows.

### Marketplace Module
- Product/service media handling (uploads).
- Order/payment workflow integrations.
- Messaging/notifications integrations (Twilio) depending on configured keys.
- Shipping / tracking integrations (Shippo) depending on configured keys.

### Investment & Deals
- Deal workflow orchestration.
- Payment integration (Stripe) depending on configured keys.
- Signature workflow integration (Yousign) depending on configured keys.
- AI-assisted services (OpenAI / Groq) available if keys are provided.

### Community & Extra Modules
- Community spaces and interactions.
- Face verification utilities (Face++) depending on configured keys.

## Tech Stack

### Frontend
- Twig templates
- Bootstrap-based UI (includes an admin template; see [templates/back/README.md](templates/back/README.md))
- Stimulus + Turbo (Symfony UX)

### Backend
- Symfony 6.4 (PHP 8.2)
- Doctrine ORM + Doctrine Migrations
- Symfony Form + Validator
- Symfony HttpClient (for external APIs)

### Database
- MySQL / MariaDB (default setup)

### Optional Recommender (Microservice)
- FastAPI + Uvicorn
- SQLAlchemy + PyMySQL
- pandas / numpy / scikit-learn

## Architecture
The platform follows a typical Symfony layered structure:

- **Entity Layer**: domain entities in `src/Entity/`
- **Repository Layer**: database queries in `src/Repository/`
- **Service Layer**: business logic & integrations in `src/Service/`
- **Controller Layer**: HTTP controllers in `src/Controller/` (attribute-based routing)
- **View Layer**: Twig templates in `templates/`
- **Public Layer**: web entry + static assets in `public/`

For recommendations, the Symfony app can call an **optional FastAPI service** (in `recommender/`) that connects to the same MySQL database and exposes:

- `GET /health`
- `POST /recommendations/{user_id}` → `{ "formation_ids": [...] }`

## Contributors
- skander101 (Developer)
- bouzid20 (Developer)
- fatmabgl (Developer)
- iramtrabelsi3 (Developer)
- Youssefsellami1 (Developer)

## Academic Context
Developed at **Esprit School of Engineering – Tunisia**

PIDEV – 3A | 2025–2026

## Getting Started

### 1) Clone the repository
```bash
git clone <your-repo-url>
cd ESPRIT-PIDEV-WEB-3A27-2026-Bizhub-master
```

### 2) Install dependencies
```bash
composer install
```

### 3) Configure your environment
Create a local override file `.env.local` (recommended) and set at least:

```dotenv
APP_SECRET=change_me
DATABASE_URL="mysql://root:@127.0.0.1:3306/BizHub"
```

### 4) Database setup
```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

You can also import the SQL dump `BizHub(9).sql` into MySQL and then run migrations if needed.

### 5) Run the application
```bash
symfony server:start
```

If you don’t have Symfony CLI:

```bash
php -S 127.0.0.1:8000 -t public
```

Open: http://127.0.0.1:8000

---

## Optional: FastAPI recommender (E-learning)

1) Configure the recommender env:

```bash
cd recommender
cp .env.example .env
```

Set `DATABASE_URL` in `recommender/.env` (same as Symfony).

2) Install and run:

```bash
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
python -m uvicorn app.main:app --host 127.0.0.1 --port 8765
```

3) Connect Symfony to it (optional but recommended):

```dotenv
RECOMMENDATIONS_API_URL=http://127.0.0.1:8765
```

---

## Acknowledgments
Thanks to **Esprit School of Engineering** for providing the academic framework and resources for this project, and to the PIDEV program for encouraging practical software development.

<details>
<summary>🧪 Developer commands</summary>

```bash
php bin/phpunit
vendor/bin/phpstan analyse
php bin/console debug:router
```

</details>