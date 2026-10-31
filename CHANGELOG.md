# Changelog

All notable changes to ChapelCore are documented here.
Follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) format,
and [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Every PR that changes behavior, schema, commands, or environment variables adds
its entry to `## [Unreleased]` under one of Added / Changed / Fixed / Removed.

---

## [Unreleased]

### Added
- **DOMAIN-MODEL 0.4** (iteration 1.4 docs): health check rules and messages, the
  doctor's nine Phase 1 checks with statuses and fixes, redaction rules, millisecond
  timestamps from producers, `(no message)` for empty exception messages
- Contract: `redaction-case` schema and 17 shared redaction cases in `redaction/`,
  checked by new self-tests in TypeScript and PHP; `openapi.json` and fixtures at model 0.4
- **Contract** (`libs/shared/contract`, iteration 1.3): 18 JSON Schemas for DOMAIN-MODEL
  0.3 (notation types, operational objects, diagnostics), 103 valid and invalid fixtures,
  and `openapi.json` (OpenAPI 3.1) for `GET /health` and `POST /diagnostics/client-errors`.
  Self-tests run in TypeScript (ajv, Vitest) and PHP (opis, PHPUnit) and must agree.
  Published inside the monorepo as `@chapelcore/contract` (npm) and `chapelcore/contract`
  (Composer)
- CI: the `contract` job runs both self-test suites; `test-php (8.5)` and `(8.4)` run the
  PHP suite; `lint` checks PHP formatting with Pint; `audit` runs `composer audit`
- Shared CI action `.github/actions/setup-php`; Dependabot updates for Composer
- `pnpm check:pins` (`scripts/check-pins.mjs`): fails CI if any dependency isn't an exact
  stable version or any GitHub Action isn't pinned to a commit SHA
- New CI (`.github/workflows/ci.yml`) with the jobs `lint`, `static-analysis`,
  `test-php (8.5)`, `test-php (8.4)`, `test-js`, `contract`, `e2e`, `contrast`, `audit`.
  `contract`, `e2e`, `contrast` and the PHP steps are placeholders until their iterations
- `.github/actions/setup-js`: shared Node + pnpm setup for CI jobs
- Dependabot version updates for npm and GitHub Actions (weekly, grouped, into `dev`,
  7-day cooldown)
- Workspace root: Node 24 (`.nvmrc`), pnpm 12.14.0, Turborepo 2.11.9, Prettier 3.9.10,
  `.editorconfig`, `.gitattributes`
- `docs/runbooks/development.md`: local setup
- `docs/plans/phase-1.md`: approved detailed plan for Phase 1 (foundation), updated with
  the hosting session's findings
- `docs/runbooks/hosting.md`: how the Namecheap hosting is set up (SSH, PHP 8.5, domains,
  folder layout, databases, Let's Encrypt certificates, cron)
- Hosting (no code): `test.libbynaz.org`, `api.test.libbynaz.org` and `api.libbynaz.org`
  set up; Let's Encrypt certificates via `acme.sh` on all five hostnames, renewing
  automatically (PLAN D18)
- RLS migration `h8i9j0k1l2m3` — enables RLS on `site_config` (admin-only),
  `announcements` and `pages` (public read published / staff write), and
  `sermon_sync_logs` (staff read only)
- `tests/test_rls_migration.py` — 17 contract tests asserting the RLS migration
  SQL grants and policies
- Branding assets: `docs/libby-logo-v3.svg` and `docs/inkscape-logo-instructions.md`
- `CLAUDE.md` — "Commands", "Environment variables", "Versioning", "PR flow",
  and "CI/CD" sections; per-app env var tables sourced from `app/config.py` and
  both `nuxt.config.ts` files
- `apps/admin/.env.example` — previously missing

### Changed
- DOMAIN-MODEL 0.4 amended: redaction rule 2b removes the password from URLs such as
  `mysql://user:password@host` (the user and host are kept); shared case `text-url-password`
- Dependabot no longer proposes major upgrades of `@types/node` (it follows the Node
  line in `.nvmrc`) or `typescript` (TypeScript 7 gets its own reviewed PR)
- Contract: a `HealthCheck` whose status is `ok` must have a `null` message
- Workflow (CLAUDE.md step 4): from iteration 1.4 on, code is reviewed per checkpoint
  (one commit each, tests first)
- DOMAIN-MODEL 0.3: Phase 1 contract details (timestamp precision, reference-code format
  with hyphen, `SemVer`/`ErrorCode` types, full `LogEntry`, `ExceptionInfo`, `DoctorCheck`
  and `ClientErrorReport` tables with field limits); the contract's OpenAPI file is
  `openapi.json`
- Required status checks in the `dev`, `staging` and `main` rulesets now use the new
  CI job names (runbook P2)
- **Renamed the project ChurchOS → ChapelCore** (PLAN D19): the name "ChurchOS" collided
  with a registered US trademark and other church software. Docs, package names
  (`@chapelcore/*`, `chapelcore/*`), the session cookie name, the `chapelcore:doctor` /
  `chapelcore:heartbeat` commands, server folders, database names and deploy-key names
  updated; the repository moves to `chapelcore/chapelcore`
- PHP target is **8.5** (8.4 minimum supported) in PLAN.md, CLAUDE.md and DOMAIN-MODEL.md
- DOMAIN-MODEL 0.2: error-code table, reference-code format, health HTTP status and check
  names, public-health vs doctor rule, `ClientErrorReport`
- PLAN: D14 adjusted (doctor details never public); D18 (certificates) approved; D19
  (project name) recorded as open
- **Project restart (approved 2026-10-09):** ChurchOS is being re-planned as
  Nuxt + Laravel with plain-language core libraries, a module system, and
  test → production releases. The FastAPI + Supabase build is preserved at tag
  `archive/fastapi-0.x`. `PLAN.md` and `CLAUDE.md` rewritten;
  `docs/design/DOMAIN-MODEL.md` added. Decisions D1–D15 recorded in `PLAN.md`
  (layout, hosting, releases, auth contract, help, diagnostics, scheduler)
- `CLAUDE.md` is now the single source of truth for project conventions;
  `churchos-cowork-instructions.md` reduced to a pointer, with its roadmap
  content folded into `PLAN.md`
- Design system section now defers to `packages/config/src/tokens.css` instead
  of restating hex values
- Test-suite tables corrected against actual runs: API 137 tests (10 files),
  admin 91 (10 files), web 53, `packages/ui` 33
- `apps/api/.env.example` and `apps/web/.env.example` rewritten to match the
  variable names the code actually reads

### Fixed
- Contract test tools: PHPUnit 13.4.3 → 13.4.5, for security advisory
  [GHSA-9vw2-f4vp-x7gf](https://github.com/sebastianbergmann/phpunit/security/advisories/GHSA-9vw2-f4vp-x7gf)
  (code execution by other local users through a shared temporary folder; fixed in
  13.4.4, and 13.4.5 corrects 13.4.4's temporary-folder change). `composer audit` passes again
- CI: pinned `ruff==0.15.15` in `apps/api/requirements.txt`. The unpinned `ruff>=0.4.0`
  resolved to 0.16.10, whose new default rules failed 176 checks in the archived code
- Tailwind v4 dark mode — added the explicit
  `@variant dark (&:where(.dark, .dark *));` override to both apps' `main.css`
  so `dark:` utilities follow the `.dark` class from `@nuxtjs/color-mode`
  instead of the OS `prefers-color-scheme` media query
- `CoCard` / `CoCardFeatured` — card titles now use dark-mode-aware colors
- `test_rls_migration.py` — removed unused imports flagged by ruff (F401)

### Removed
- The FastAPI + Supabase tree (`apps/`, `packages/`, old guides) and its Cloudflare and
  Railway deploy workflows; preserved at git tag `archive/fastapi-0.x`
- Stale env vars from `.env.example` files that nothing reads:
  `SUPABASE_PUBLISHABLE_KEY`, `SUPABASE_SECRET_KEY`, `REDIS_URL`, `CHURCH_NAME`,
  `ENVIRONMENT`, `GLOO_*` (now stored in `site_config`, not env)

---

## [0.4.0] — 2026-06-01 "Kootenai" pre-release

### Phase 7 — Gloo AI Integration

#### Added
- `connectors/base/ai.py` — `AiConnector` ABC with `moderate(body)` abstract method
- `connectors/providers/ai/gloo.py` — `GlooAiConnector`: OAuth2 client credentials flow to `https://api.gloo.chat/oauth/token`; sends prayer text + tradition context to `/v1/moderate`; fail-open on any exception
- `connectors/providers/ai/grok.py` — `GrokAiConnector`: xAI `grok-3-mini` via OpenAI-compatible endpoint `https://api.x.ai/v1/chat/completions`; church-context system prompt; fail-open if no API key
- `connectors/registry.py` — `get_ai_connector()` factory; reads `ai_provider` from `site_config`; fallback chain: Gloo → Grok → fail-open
- `dependencies/ai_moderation.py` — `moderate_prayer_request(body)` refactored to delegate to `get_ai_connector()` from registry
- Admin Settings UI — AI Moderation section: provider selector (Grok / Gloo), Gloo fields (Client ID, Client Secret, Theological Tradition), Grok API key field
- `site_config` keys: `ai_provider`, `gloo_client_id`, `gloo_client_secret`, `gloo_tradition`, `grok_api_key`
- `tests/test_ai_connectors.py` — tests for Grok, Gloo, fallback chain, and `ai_moderation` dependency

#### Architecture
- AI moderation is now fully provider-swappable via `site_config` with no code changes
- Fail-open at every level: Gloo exception → try Grok; Grok exception → approve submission; Redis unavailable → skip rate limit
- `@pinia/nuxt 0.11.3` pinned for Nuxt 4 compatibility (admin app is `ssr: false`)

---

## [0.3.0] — 2026-06-01 "Kootenai" pre-release

### Phase 6 — Connector Framework

#### Added
- `connectors/base/email.py` — `EmailConnector` ABC with `send_email()` and `send_prayer_notification()` abstract methods
- `connectors/providers/email/smtp.py` — `SmtpEmailConnector` (default; env-var driven: `SMTP_HOST`, `SMTP_USER`, `SMTP_PASSWORD`)
- `connectors/providers/email/ms365.py` — `Ms365EmailConnector`: Microsoft Graph API, OAuth2 client credentials
- `connectors/registry.py` — `get_email_connector()` factory; reads `email_provider` from `site_config`; fallback to SMTP on misconfiguration
- Admin Settings UI — Email Connector section: provider dropdown (SMTP / Microsoft 365), MS365 credential fields (Tenant ID, Client ID, Client Secret, Sender)
- `site_config` keys: `email_provider`, `ms365_tenant_id`, `ms365_client_id`, `ms365_client_secret`, `ms365_sender`
- RLS policies added via migration `h8i9j0k1l2m3`: `site_config` (admin-only), `announcements` + `pages` (public read published / staff write), `sermon_sync_logs` (staff read only)
- `tests/test_connectors.py` — 14 tests covering EmailConnector ABC, SMTP, MS365, and registry fallback behavior

#### Architecture
- Email delivery is now provider-swappable via `site_config` with no code changes
- Unknown or misconfigured provider always silently falls back to SMTP — email delivery is never interrupted by a config error

---

## [0.2.1] — 2026-05-29 "Kootenai" pre-release

### Phase 5b — Prayer Board Completion

#### Added
- Admin Settings page (`pages/settings/index.vue`) — Prayer chain email configuration; save/load via `GET|PATCH /site-config`
- `GET /prayer-requests/public` endpoint — approved requests only; PII stripped via `PrayerRequestPublic` schema
- Public prayer board at `apps/web/app/pages/prayer/board.vue` — displays approved, unanswered requests; no login required
- `PATCH /prayer-requests/{id}/answered` endpoint — staff+; marks request as answered
- Admin Prayer Queue — Active tab showing approved unanswered requests with Mark Answered action
- Additional Vitest coverage for Settings and Prayer Queue pages (83 total admin tests)

---

## [0.2.0] — 2026-05-29 "Kootenai" pre-release

### Phase 5 — Prayer Board

#### Added
- `public.prayer_requests` table — UUID PK, canonical schema (body, name, email, ai_score, ai_reason, moderated_at/by, is_answered, prayer_count, expires_at); RLS enabled
- `public.church_events` — migrated PK from VARCHAR to UUID
- API: `POST /prayer-requests` (public, rate-limited 3/hr/IP, Grok AI moderated)
- API: `GET /prayer-requests` (member+), `GET /prayer-requests/pending` (staff+), `PATCH /prayer-requests/{id}` (staff+)
- Rate limiting via Upstash Redis (`UPSTASH_REDIS_URL`, `UPSTASH_REDIS_TOKEN`); fail-open
- AI moderation via xAI Grok (`GROK_API_KEY`); fail-open; Phase 6 will add Gloo as primary
- `apps/web/app/pages/prayer.vue` — public prayer submission form; Prayer added to nav and footer
- `apps/admin/app/pages/prayer/index.vue` — staff moderation queue; Prayer added to admin sidebar
- 52 new tests (20 API + 10 web + 12 admin + layout updates); all passing

#### Architecture
- Documented ChurchOS as single-tenant portable CMS (Phase 9 = portability, not multi-tenancy)
- UUID PK convention established for all new tables going forward

---

## [0.1.0] — 2026-05-28 "Kootenai" pre-release

### Phase 4 — Admin Dashboard

#### Added
- `apps/admin` — Full admin dashboard Nuxt 4 application
- `layouts/default.vue` — Persistent sidebar with ChurchOS brand, nav, version badge; topbar with user identity and sign-out
- `pages/index.vue` — Dashboard landing page with quick-nav cards to Sermons and Events
- `pages/sermons/index.vue` — Sermon list table with Published/Draft badges and edit links
- `pages/sermons/[id]/edit.vue` — Sermon PATCH form; preserves Logos-synced fields
- `pages/events/index.vue` — Event list table with Published/Draft badges and edit links
- `pages/events/[id]/edit.vue` — Event PATCH form with all fields and toggles
- `apps/api/app/dependencies/rbac.py` — `require_role()` dependency factory (guest → superadmin)
- Sermon and Event schemas, models, CRUD, and routers
- Alembic migrations for `sermons` and `church_events` tables
- `CHURCH_ID` env var support; guard returns `[]` when not configured

#### Fixed
- Replaced `python-jose` with `PyJWT>=2.8.0` (incompatible with `cryptography>=42` on Railway Python 3.13)
- Removed unused `import pytest` flagged by ruff F401
- `pnpm-lock.yaml` stale `@railway/cli` specifier removed
- `IndexError` in `main.py` when resolving parent paths in Railway container

#### Infrastructure
- Railway env vars configured: `CHURCH_ID`, `SUPABASE_URL`, `SUPABASE_SERVICE_KEY`, `SUPABASE_JWT_SECRET`
- API live: https://churchos-production-c6ae.up.railway.app
- `/sermons` returning live Logos-synced data in production
- `/events` returning `[]` (no events created yet — expected)

---

### Phase 3 — Supabase Auth & Database

#### Added
- Supabase Auth integration with JWT verification (PyJWT, HS256)
- `public.profiles` table with RBAC roles
- `public.churches` table (`id: "default"`, name: "Libby Church of the Nazarene")
- `public.sermons` table (Logos-synced, VARCHAR PKs, RLS enabled)
- `public.church_events` table
- `GET /me` endpoint — returns authenticated user's profile
- Row Level Security on `profiles` and `sermons`

---

### Phase 2 — Public Website

#### Added
- Public-facing Nuxt 4 site: homepage, sermons, about, contact
- Static generation via Cloudflare Pages (Nitro cloudflare-pages preset)
- Currently uses mock sermon/event data (live API wiring deferred to Phase 10)

---

### Phase 1 — Design System

#### Added
- Tailwind CSS v4 design tokens: forest, kootenai, gold, charcoal, stone palettes
- Font stack: Cinzel (display), Lora (body/scripture), DM Sans (UI)
- Shared component classes: btn-primary, btn-secondary, btn-ghost, co-card, etc.
- Dark mode via @nuxtjs/color-mode (class strategy)
- `packages/ui` shared component library

---

### Phase 0 — Repo & Tooling

#### Added
- pnpm workspaces + Turborepo monorepo scaffold
- GitHub Actions CI: turbo pipeline + pytest pipeline
- Cloudflare Pages deployment (production + staging)
- Railway.app deployment (FastAPI backend)
- Branch protection on main, staging, dev (PRs + CI required)
- `version.json` — semantic versioning visible in footer, admin topbar, `/health`
- `GET /health` → `{"status":"ok","version":"0.1.0","codename":"Kootenai"}`
