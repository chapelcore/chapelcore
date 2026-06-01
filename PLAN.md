# ChurchOS — Master Build Plan

**Version:** 0.1.0 (pre-release — "Kootenai" targets 1.0.0)  
**Last updated:** 2026-05-28 (Phase 5 in progress)  
**Target deployment:** libbynaz.org (prototype → multi-church)

---

## How to use this document

Each phase is a **fully working, deployable state** of the application. You never move to the next phase until:

1. All tests for the current phase pass (`turbo test`)
2. The feature branch has been merged: `feature/phase-N-*` → `dev`
3. A commit message documents what was completed

TDD rule: **write the failing test first, then write the code to make it pass.** No exceptions.

---

## Repository layout

```
churchos/
├── apps/
│   ├── web/                  Nuxt 4 — public church website
│   ├── admin/                Nuxt 4 — staff admin dashboard
│   └── api/                  FastAPI (Python 3.12) — REST API
│       ├── app/
│       │   ├── main.py
│       │   ├── models/
│       │   ├── routers/
│       │   ├── schemas/
│       │   ├── services/
│       │   └── dependencies/
│       ├── tests/
│       ├── alembic/
│       └── requirements.txt
├── packages/
│   ├── ui/                   Shared Vue component library
│   ├── types/                Shared TypeScript definitions
│   ├── config/               Tailwind design tokens
│   ├── maps/                 Pluggable map component
│   └── office-info/          Service times, hours, contact info
├── docs/                     VitePress documentation
├── .github/
│   └── workflows/
│       ├── ci.yml
│       ├── deploy-staging.yml
│       └── deploy-production.yml
├── turbo.json
├── pnpm-workspace.yaml
├── version.json              { "version": "0.1.0", "codename": "Kootenai" }
└── CHANGELOG.md
```

---

## Branch & environment model

```
feature/* ──► dev ──► staging ──► main (production)
                │         │           │
                │         │           └── Cloudflare Pages (prod) + Railway (prod API)
                │         └── Cloudflare Pages (staging) + Railway (staging API)
                └── PR checks only (lint, type-check, test, build)
```

- `main` — production, protected, requires CI + PR approval
- `staging` — QA environment, mirrors prod config
- `dev` — integration branch, feature branches land here
- `feature/phase-N-description` — all implementation work

---

## Phase index

| # | Name | Branch | Status |
|---|------|--------|--------|
| 0 | Repo & tooling | `feature/phase-0-repo-setup` | ✅ complete |
| 1 | Design system | `feature/phase-1-design-system` | ✅ complete |
| 2 | Public website | `feature/phase-2-public-site` | ✅ complete (mock data — wired to API in Phase 12) |
| 3 | Auth & database | `feature/phase-3-supabase-auth` | ✅ complete |
| 4 | Admin dashboard | `feature/phase-4-admin-dashboard` | ✅ complete |
| 5 | Prayer board | `feature/phase-5-prayer-board` | ✅ complete |
| 5b | Prayer board completion | `feature/phase-5b-prayer-completion` | ✅ complete |
| 6 | Connector framework | `feature/phase-6-connectors` | 🔜 next |
| 7 | Gloo AI integration | `feature/phase-7-gloo-ai` | 🔲 pending |
| 8 | Giving module | `feature/phase-8-giving` | 🔲 pending |
| 9 | Member directory | `feature/phase-9-directory` | 🔲 pending |
| 10 | Auth providers | `feature/phase-10-auth-providers` | 🔲 pending |
| 11 | Advanced connectors | `feature/phase-11-advanced-connectors` | 🔲 pending |
| 12 | Polish & hardening | `feature/phase-12-hardening` | 🔲 pending |

---

## Phase 0 — Repo & Tooling

**Branch:** `feature/phase-0-repo-setup`  
**Goal:** A working monorepo where every workspace package can be linted, type-checked, tested, and built from the root.

### Deliverables

| File | Purpose |
|------|---------|
| `pnpm-workspace.yaml` | Declares all workspace packages |
| `turbo.json` | Pipeline: lint → type-check → test → build with caching |
| `package.json` (root) | Workspace root — no app code, only tooling deps |
| `apps/web/package.json` | Nuxt 4 app stub |
| `apps/admin/package.json` | Nuxt 4 app stub |
| `apps/api/requirements.txt` | FastAPI + pytest deps |
| `apps/api/app/main.py` | FastAPI app with `/health` endpoint |
| `apps/api/tests/test_health.py` | **First test** — GET /health returns 200 + version |
| `packages/ui/package.json` | Vue component library stub |
| `packages/types/package.json` | TypeScript types stub |
| `packages/config/package.json` | Tailwind config stub |
| `version.json` | `{ "version": "0.1.0", "codename": "Kootenai" }` |
| `CHANGELOG.md` | Stub |
| `.github/workflows/ci.yml` | PR CI: install → lint → type-check → test → build |
| `.github/workflows/deploy-staging.yml` | Push to `staging` → deploy |
| `.github/workflows/deploy-production.yml` | Push to `main` → deploy |
| `.gitignore` | Node, Python, env files |
| `.env.example` (per app) | Documents required env vars, never committed |

### First test (TDD anchor)

```python
# apps/api/tests/test_health.py
def test_health_returns_200_with_version(client):
    response = client.get("/health")
    assert response.status_code == 200
    data = response.json()
    assert data["status"] == "ok"
    assert data["version"] == "0.1.0"
```

This test is written **before** `main.py` has a `/health` route. It fails first. Then we implement the route to make it pass.

### Done criteria
- [ ] `pnpm install` exits 0 from root
- [ ] `turbo build` exits 0
- [ ] `turbo lint` exits 0  
- [ ] `turbo test` exits 0 (API health test passes)
- [ ] All files committed on `feature/phase-0-repo-setup`

---

## Phase 1 — Design System

**Branch:** `feature/phase-1-design-system`  
**Goal:** A shared Tailwind token config and a typed Vue component library that both `apps/web` and `apps/admin` can consume.

### Deliverables

| File | Purpose |
|------|---------|
| `packages/config/tailwind.config.ts` | All color tokens, font families, custom utilities |
| `packages/config/index.ts` | Re-exports config for consumption by apps |
| `packages/ui/src/components/` | Vue SFCs: Button, Card, Badge, FormInput, ScriptureCallout, Container, Section |
| `packages/ui/src/index.ts` | Barrel export of all components |
| `packages/ui/src/types.ts` | Shared prop type definitions |
| `packages/ui/vitest.config.ts` | Vitest setup for component tests |
| `packages/ui/src/components/__tests__/` | Tests for each component |
| `apps/web/nuxt.config.ts` | Extends `packages/config` Tailwind config |
| `apps/admin/nuxt.config.ts` | Extends `packages/config` Tailwind config |

### Design tokens

```typescript
// Colors
forest:   { 500: '#2d6a4f', 600: '#23553f' }   // Primary
kootenai: { 500: '#3a7d8c' }                    // Secondary
gold:     { 500: '#c9962b' }                    // Accent
charcoal: { 900: '#0a1012' }                    // Dark surface
stone:    { 50:  '#faf8f5' }                    // Light bg

// Fonts
display: ['Cinzel', 'serif']        // h1, h2
body:    ['Lora', 'serif']          // body copy, scripture
ui:      ['DM Sans', 'sans-serif']  // nav, buttons, labels
```

### Component classes (utility layer)
`btn-primary`, `btn-secondary`, `btn-ghost`, `co-card`, `co-card-featured`,  
`co-container`, `co-section`, `scripture-callout`,  
`badge-forest`, `badge-kootenai`, `badge-gold`, `form-input`, `form-label`

### First test (TDD anchor)

```typescript
// packages/ui/src/components/__tests__/Button.test.ts
import { mount } from '@vue/test-utils'
import Button from '../Button.vue'

describe('Button', () => {
  it('renders btn-primary class by default', () => {
    const wrapper = mount(Button, { props: { variant: 'primary' } })
    expect(wrapper.classes()).toContain('btn-primary')
  })
})
```

### Done criteria
- [ ] All component tests pass (`turbo test --filter=@churchos/ui`)
- [ ] `apps/web` and `apps/admin` can import from `@churchos/ui` and resolve tokens
- [ ] Dark mode toggle works via `@nuxtjs/color-mode` class strategy
- [ ] All committed on `feature/phase-1-design-system`

---

## Phase 2 — Public Website

**Branch:** `feature/phase-2-public-site`  
**Goal:** Statically generated public site with homepage, sermons listing, about page, and contact form. Lighthouse ≥ 90 at this stage.

### Pages
- `/` — Homepage: hero, upcoming events, latest sermon, scripture callout
- `/sermons` — Sermon listing (static data for now)
- `/sermons/[slug]` — Individual sermon
- `/about` — About the church
- `/contact` — Contact form (no backend yet — mailto fallback)

### Done criteria
- [ ] `nuxt generate` produces static HTML for all routes
- [ ] All pages render correct title, meta description
- [ ] Component integration tests pass
- [ ] Lighthouse performance ≥ 90 on homepage

---

## Phase 3 — Supabase Auth & Database

**Branch:** `feature/phase-3-supabase-auth`  
**Goal:** Working auth (email/password + magic link), RBAC roles, and database migrations.

### Key decisions
- Access tokens: **memory only** (Pinia store, never localStorage)
- Refresh tokens: **HttpOnly cookie** via Supabase SSR helpers
- JWT verified server-side on every protected FastAPI endpoint
- RLS enabled on every table from day one

### RBAC roles
`superadmin` → `admin` → `staff` → `member` → `guest`

### Core tables (Alembic migrations)
- `profiles` — extends `auth.users`, stores role, church_id, display_name
- `churches` — church registry for multi-church (church_id, slug, name, config)
- `sermons` — sermon content
- `events` — calendar events

### Done criteria
- [ ] Login/logout flow works end-to-end
- [ ] JWT middleware rejects unauthenticated requests to protected routes
- [ ] RLS policies tested with each role
- [ ] Alembic migrations run cleanly (`alembic upgrade head`)

---

## Phase 4 — Admin Dashboard

**Branch:** `feature/phase-4-admin-dashboard`  
**Goal:** Authenticated staff interface for managing sermons and events.

### Features
- Sermon CRUD (create, edit, publish, archive)
- Event CRUD
- Basic media upload to Backblaze B2
- Role-gated: `staff` minimum

### Done criteria
- [ ] All CRUD operations tested (API + UI)
- [ ] Role gate enforced — guests/members redirected
- [ ] File uploads stored in B2, URLs persisted in DB

---

## Phase 5 — Prayer Board

**Branch:** `feature/phase-5-prayer-board`  
**Goal:** Public prayer request submission with AI moderation and Redis rate limiting.

### Flow
1. Visitor submits prayer request (no auth required)
2. Redis rate limit checked — **3 submissions / IP / hour** (Upstash Redis)
3. Anthropic Claude moderates content → `approved` or `rejected`
4. Request stored with `status` field; submitter always receives 201 (dignity-preserving)
5. Approved requests visible to **members+** (not fully public — requires auth)
6. Staff can view pending/rejected queue at `GET /prayer-requests/pending`
7. Staff approves or rejects via `PATCH /prayer-requests/{id}`

### API endpoints
| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| POST | `/prayer-requests` | none | Submit (rate-limited, AI-moderated) |
| GET | `/prayer-requests` | member+ | Approved list |
| GET | `/prayer-requests/pending` | staff+ | Moderation queue |
| PATCH | `/prayer-requests/{id}` | staff+ | Approve or reject |

### Key implementation files
- `apps/api/tests/test_prayer_requests.py` — 20 tests (written first)
- `apps/api/alembic/versions/d4e5f6a7b8c9_create_prayer_requests_table.py`
- `apps/api/app/schemas/prayer_request.py` — PrayerRequestCreate / Read / Moderate
- `apps/api/app/crud/prayer_requests.py` — Supabase CRUD
- `apps/api/app/dependencies/rate_limit.py` — `_redis_incr` + `check_rate_limit`
- `apps/api/app/dependencies/ai_moderation.py` — `moderate_prayer_request`
- `apps/api/app/routers/prayer_requests.py`

### Required env vars (new in Phase 5)
```bash
UPSTASH_REDIS_URL=rediss://...upstash.io:6380
UPSTASH_REDIS_TOKEN=...
ANTHROPIC_API_KEY=...
```

### Done criteria
- [x] 20 API tests written first (TDD) and passing
- [x] Rate limiting dependency implemented (patchable _redis_incr)
- [x] AI moderation dependency implemented (patchable, fail-open)
- [x] Alembic migration created for prayer_requests table
- [ ] Alembic migration applied in Supabase (`alembic upgrade head` or manual stamp)
- [ ] Railway env vars set: UPSTASH_REDIS_URL, UPSTASH_REDIS_TOKEN, ANTHROPIC_API_KEY
- [ ] Public submission form added to apps/web
- [ ] Admin moderation queue page added to apps/admin
- [ ] Feature branch merged → dev → staging → main

---

## Phase 5b — Prayer Board Completion

**Branch:** `feature/phase-5b-prayer-completion`  
**Goal:** Complete the prayer board experience — public board, email notifications, updates, and answered tracking.

### Features
- `site_config` admin settings page — stores `prayer_chain_email` and future connector settings; encrypted at rest
- Email notification on submission → `prayer_chain_email` via configured email connector (SMTP by default)
- Public approved prayer board on `apps/web` — visible without login (approved only)
- Staff can post updates on a prayer request (new `prayer_updates` table)
- Staff can mark a request as answered (`is_answered = true`) from the admin queue
- Historical archive view (answered/closed requests) in admin
- Member can mark a prayer as prayed for (`prayer_count` increment)

### New tables
- `public.prayer_updates` — UUID PK, prayer_request_id FK, body TEXT, created_by UUID, created_at

### Done criteria
- [ ] `prayer_chain_email` configurable in admin settings, stored in `site_config`
- [ ] Email sent on approved submission
- [ ] Public prayer board page on apps/web
- [ ] Staff can add updates to a request
- [ ] Staff can mark request as answered
- [ ] Answered requests visible in historical archive

---

## Phase 6 — Connector Framework

**Branch:** `feature/phase-6-connectors`  
**Goal:** A plugin-style connector system where each integration category has a built-in default and optional third-party providers. Churches with no external accounts get a fully functional CMS out of the box.

### Connector categories

| Category | Built-in (default) | Third-party options |
|---|---|---|
| Email | SMTP relay (any provider) | MS365 Graph, Google Gmail |
| Calendar | ChurchOS `church_events` table | Outlook, Google Calendar, iCal |
| Documents | ChurchOS `pages` table | Google Docs, MS365, OnlyOffice, Nextcloud |
| Storage | Backblaze B2 | Google Drive, OneDrive, S3 |
| Auth | Email + magic link (Supabase) | Microsoft Entra, Google OAuth, Apple |

### Architecture
- Each connector category defines a standard Python interface (e.g., `send_email(to, subject, body)`)
- The active connector for each category is set in `site_config` and read at runtime
- Swapping connectors requires only a config change — no code changes
- Admin settings UI has a "Connectors" page with setup instructions per provider

### Admin settings UI
- Connector selection per category (dropdown)
- Provider-specific config fields (API keys, tenant IDs, etc.) — stored encrypted in `site_config`
- Test button per connector (sends test email, creates test calendar event, etc.)

### Phase 6 deliverables
- Connector interface definitions (Python ABCs for API, TypeScript interfaces for frontend)
- Built-in connectors: SMTP email, ChurchOS calendar, ChurchOS pages, Backblaze B2
- MS365 connectors: Graph API email, Outlook calendar sync (two-way)
- Admin settings UI: Connectors page
- `site_config` table with encrypted connector settings

### Done criteria
- [ ] Connector interfaces defined and documented
- [ ] SMTP email connector working (replaces hardcoded SMTP in Phase 5b)
- [ ] MS365 email connector via Graph API
- [ ] Outlook two-way calendar sync (events ↔ church_events)
- [ ] Admin settings page — Connectors section
- [ ] All connectors tested with mock providers

---

## Phase 7 — Gloo AI Integration

**Branch:** `feature/phase-7-gloo-ai`  
**Goal:** Gloo AI as primary faith-context provider with Grok as fallback. Plugs into the connector framework from Phase 6.

### Config (via admin settings UI)
- Gloo client ID, client secret, tradition setting
- Grok API key (fallback)
- Provider chain: Gloo → Grok → fail-open

### Done criteria
- [ ] Gloo API called for primary moderation via AI connector interface
- [ ] Fallback to Grok on Gloo error
- [ ] Both providers tested with fixture responses
- [ ] Configurable via admin settings UI (no env var required)

---

## Phase 8 — Giving Module

**Branch:** `feature/phase-8-giving`  
**Goal:** Stripe-powered giving with zero card data on our servers. (Renumbered from Phase 7.)

### Rules (non-negotiable)
- Stripe.js handles all card input in the browser
- Our API only sees Stripe payment intents / events
- No bulk export of giving records

### Done criteria
- [ ] Stripe webhook endpoint tested with Stripe CLI
- [ ] Successful and failed payments handled
- [ ] Admin can see giving summary (not bulk export)

---

## Phase 9 — Member Directory

**Branch:** `feature/phase-9-directory`  
**Goal:** Opt-in member directory, PII encrypted at rest. (Renumbered from Phase 8.)

### Rules
- Member role minimum — never public
- PII encrypted AES-256, column-level
- No bulk export endpoint
- Consent required before listing

### Done criteria
- [ ] Directory only accessible with `member` JWT
- [ ] PII columns encrypted in DB, decrypted in service layer
- [ ] No unauthenticated route returns directory data

---

## Phase 10 — Auth Providers

**Branch:** `feature/phase-10-auth-providers`  
**Goal:** Add Microsoft and Google OAuth as configurable login providers via Supabase Auth. Map external groups to ChurchOS roles.

### Architecture
Supabase Auth is the consistent auth layer regardless of provider. The OAuth flow is always:
> Login button → Supabase validates → JWT issued → ChurchOS roles applied

Per-deployment, admins configure which providers are enabled via the admin settings UI.

### Features
- Microsoft Entra ID (Azure AD) — OAuth2/OIDC via Supabase
- Google OAuth — via Supabase
- Apple Sign In — via Supabase
- Group → role mapping: MS groups or Google Workspace groups → ChurchOS roles (staff, admin, etc.)
- Admin settings: enable/disable providers, configure client ID/secret

### Done criteria
- [ ] Microsoft login working via Supabase OAuth
- [ ] Google login working via Supabase OAuth
- [ ] Group membership read from provider → role assigned in profiles table
- [ ] Admin settings UI — Auth Providers section
- [ ] Existing email/magic link auth still works alongside OAuth providers

---

## Phase 11 — Advanced Connectors

**Branch:** `feature/phase-11-advanced-connectors`  
**Goal:** Expand the connector framework (Phase 6) with Google Workspace, OnlyOffice, Nextcloud, and Zoho integrations.

### Connectors
- **Google Workspace** — Gmail (email), Google Calendar (two-way sync), Google Drive (storage), Google Docs (documents)
- **OnlyOffice** — document editing and real-time collaboration
- **Nextcloud** — self-hosted open-source option (calendar, storage, documents)
- **Zoho** — popular with nonprofits; email, calendar, CRM
- **iCal** — read-only calendar import from any iCal-compatible source

### Done criteria
- [ ] Google Workspace email + calendar connector
- [ ] OnlyOffice document connector
- [ ] Nextcloud connector
- [ ] All connectors selectable from admin settings UI
- [ ] Each connector has setup documentation with step-by-step instructions

---

## Phase 12 — Portability & Polish

**Branch:** `feature/phase-12-hardening`  
**Goal:** Production-ready and easy for any church to self-host. Lighthouse ≥ 95, security audit, full documentation.

### Portability
- `.env.example` fully documented for every required account and key
- Setup guide: step-by-step from GitHub clone → live deployment
- Design tokens (colors, fonts, logo) configurable via `site_config` without code changes
- One-click Railway deploy button in README

### Hardening
- Lighthouse ≥ 95 (performance, accessibility, best practices, SEO)
- Sentry error tracking in all apps
- Security audit: OWASP Top 10 pass
- RLS policies on all remaining unprotected tables
- VitePress documentation: setup, deployment, connector configuration, contributing

### Done criteria
- [ ] A new church can go from GitHub clone to live site following only the docs
- [ ] Lighthouse ≥ 95 on all public pages
- [ ] Security audit passed
- [ ] Version `1.0.0` ("Kootenai") tagged and released

---

## Security requirements (always active)

- Access tokens: memory only, never localStorage
- Refresh tokens: HttpOnly cookies only
- JWT verified server-side on every protected endpoint
- RLS active on all sensitive Supabase tables
- PII: AES-256 column-level encryption
- Rate limiting: Redis on all write endpoints
- Prayer submissions: AI-moderated before going public
- Directory: `member` role minimum, never public
- No bulk export endpoint for directory or giving records
- Stripe.js handles all card input — card data never touches our API

---

## Key env vars (never committed)

```bash
# Supabase (note: Supabase renamed keys in 2025 — anon→publishable, service→secret)
SUPABASE_URL=
SUPABASE_SERVICE_KEY=      # "secret key" in Supabase dashboard
SUPABASE_JWT_SECRET=
NUXT_PUBLIC_SUPABASE_URL=
NUXT_PUBLIC_SUPABASE_ANON_KEY=   # "publishable key" in Supabase dashboard

# Redis (Upstash)
UPSTASH_REDIS_URL=
UPSTASH_REDIS_TOKEN=

# Backblaze B2
B2_KEY_ID=
B2_APPLICATION_KEY=
B2_BUCKET_NAME=

# AI
GLOO_CLIENT_ID=
GLOO_CLIENT_SECRET=
GLOO_TRADITION=evangelical
ANTHROPIC_API_KEY=

# App
CHURCH_SLUG=libby-naz
CHURCH_NAME=Libby Church of the Nazarene
```

---

## Versioning

Semantic versioning. Current: `0.1.0` pre-release.  
Release `1.0.0` is named **"Kootenai"**.  
Version is visible in: site footer · admin topbar badge · `GET /health` response.
