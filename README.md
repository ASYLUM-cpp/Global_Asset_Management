# GAM — Global Asset Management System

A full-stack enterprise digital asset management platform built with **Laravel 12**, **Vue 3**, and **Inertia.js**. Features AI-powered classification (GPT-4o-mini via OpenRouter), a 6-stage processing pipeline, 945+ taxonomy synonym rules, role-based access control, and integrations with Google Drive, BookStack, Trilium Notes, and CKAN.

---

## Tech Stack

| Layer | Technologies |
|---|---|
| **Backend** | PHP 8.4, Laravel 12, Inertia.js 2, Sanctum, Spatie Permission/Activity Log |
| **Frontend** | Vue 3 (Composition API), Tailwind CSS 4.2, Vite 7, ApexCharts, Lucide/Remix Icons |
| **Database** | MySQL 8.0 |
| **Cache/Queue** | Redis 7 (cache, session, queue driver) |
| **Search** | Meilisearch 1.12 via Laravel Scout |
| **AI** | OpenRouter API → GPT-4o-mini (vision + text classification) |
| **Infrastructure** | Docker Compose (10 services), Nginx, Supervisor, PHP-FPM |
| **Media Tools** | Ghostscript, ImageMagick, Inkscape, Poppler, FFmpeg, LibreOffice |

---

## Quick Start (Docker)

### Prerequisites

- Docker Desktop (with Docker Compose v2)
- 4 GB+ RAM allocated to Docker

### 1. Clone & Configure

```bash
cd gam
cp .env.docker .env
```

Edit `.env` and fill in your API keys:
- `APP_KEY` — run `docker exec gam-app php artisan key:generate` after first start
- `OPENAI_API_KEY` — your OpenRouter API key
- `BOOKSTACK_TOKEN_ID` / `BOOKSTACK_TOKEN_SECRET` — from BookStack admin
- `TRILIUM_TOKEN` — from Trilium Settings → ETAPI
- `CKAN_API_KEY` — from CKAN user profile

### 2. Start All Services

```bash
docker compose up -d
```

This starts 10 containers: app, horizon (queue worker), mysql, redis, meilisearch, bookstack, trilium, ckan, ckan-db, ckan-solr, soketi.

### 3. First-Time Setup

The entrypoint automatically handles: composer install, APP_KEY generation, storage link, and migrations.
After the first boot, seed the database with roles, users, and taxonomy:

```bash
# Seed database (creates admin user, roles, taxonomy rules)
docker exec gam-app php artisan db:seed

# Build frontend assets (requires Node.js on host)
npm install
npm run build
```

### 4. Access

| Service | URL |
|---|---|
| **GAM App** | http://localhost:8000 |
| **BookStack** | http://localhost:6875 |
| **Trilium Notes** | http://localhost:8081 |
| **CKAN** | http://localhost:5000 |
| **Meilisearch** | http://localhost:7700 |
| **Horizon (Queues)** | http://localhost:8000/horizon |

### Default Login (seeded users)

| Role | Email | Password |
|---|---|---|
| **Admin** | ali@company.com | password |
| Food Team | sara@company.com | password |
| Media Team | james@company.com | password |
| Marketing Team | maria@company.com | password |

---

## Frontend Build (Optional)

The compiled frontend is included in `public/build/`. To rebuild after making changes:

```bash
npm install
npm run build
```

Then copy to the container:

```bash
docker cp public/build gam-app:/var/www/html/public/build
docker exec gam-app bash /var/www/html/deploy-refresh.sh
```

---

## Project Structure

```
app/
├── Http/Controllers/
│   ├── PageController.php        # All page routes (dashboard, assets, notes, etc.)
│   ├── ActionController.php      # All write actions (upload, review, CRUD)
│   ├── ApiController.php         # REST API endpoints
│   └── GoogleDriveController.php
├── Jobs/
│   ├── ProcessAssetPipeline.php  # 6-stage orchestration
│   ├── AiTagAsset.php            # GPT-4o-mini vision classification
│   ├── GeneratePreview.php       # Multi-format thumbnail generation
│   ├── ApplyTaxonomy.php         # Synonym normalization
│   └── DriveImportJob.php        # Google Drive file import
├── Models/                       # 9 Eloquent models
└── Services/
    └── TaxonomyService.php       # Taxonomy prompt builder + synonym expansion

resources/js/
├── Pages/                        # 18 Vue 3 pages
│   ├── Dashboard.vue
│   ├── Assets.vue
│   ├── Upload.vue
│   ├── Review.vue
│   ├── Documents.vue
│   ├── Notes.vue
│   ├── Analytics.vue
│   └── ...
├── Components/                   # Reusable Vue components
└── Layouts/
    └── AppLayout.vue             # Main sidebar layout

config/gam.php                    # GAM-specific config (groups, roles, taxonomy)
docker-compose.yml                # 10-service Docker Compose
Dockerfile                        # Multi-stage build (composer + node + app)
deploy-refresh.sh                 # Post-deployment cache flush helper
```

---

## Key Features

1. **AI-Powered Classification** — Computer vision via GPT-4o-mini with 15-group taxonomy
2. **6-Stage Processing Pipeline** — Hash → Preview → AI Tag → Taxonomy → Index → Production
3. **Multi-Format Preview** — 20+ file types (images, PDFs, videos, Office docs, vectors)
4. **Role-Based Access Control** — 4 roles with group-based asset visibility
5. **Full-Text Search** — Meilisearch with synonym expansion
6. **Google Drive Integration** — OAuth 2.0 file browser + batch import
7. **Knowledge Base** — BookStack (documents/SOPs) + Trilium (team notes)
8. **Version Management** — File versions with SHA-256 integrity + rollback
9. **Audit Trail** — Spatie Activity Log on all model changes
10. **Analytics Dashboard** — Time-range KPIs, trend charts, CSV export

---

## Documentation

- [GAM_PROJECT_REPORT.md](GAM_PROJECT_REPORT.md) — Comprehensive technical report with code examples
- [FRONTEND_DOCUMENTATION.md](FRONTEND_DOCUMENTATION.md) — Frontend architecture and component documentation

---

## REST API

| Method | Endpoint | Purpose |
|---|---|---|
| `POST` | `/api/assets/import` | Upload file (500MB max) |
| `GET` | `/api/assets/status/{id}` | Poll pipeline status |
| `GET` | `/api/assets/{id}` | Asset detail + tags + versions |
| `GET` | `/api/assets` | Paginated list (role-scoped) |
| `GET` | `/api/user` | Authenticated user info |

Auth: `Authorization: Bearer <sanctum-token>`

---

## License

Proprietary — Internal use only.
