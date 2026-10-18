# ChapelCore — Master Build Plan

> **Status: Approved** by the owner 2026-10-09 (PR #80) · Drafted 2026-10-07
> This plan replaces the FastAPI + Supabase plan, which is preserved at git tag
> [`archive/fastapi-0.x`](https://github.com/pj1227/churchos/tree/archive/fastapi-0.x).
> Items marked **⚖ Decision** need your approval before the phase that depends on them starts.

This file owns the **roadmap**: what each phase delivers and when it is done.
[CLAUDE.md](CLAUDE.md) owns **how we work** (workflow, standards, security rules).
[docs/design/DOMAIN-MODEL.md](docs/design/DOMAIN-MODEL.md) owns **what the data looks like**.
None of the three restates the others.

---

## 1. Direction

ChapelCore (formerly ChurchOS) is a modular, open-source church CMS. It is built first for Libby Church
of the Nazarene, where it replaces https://libbynaz.org, and is designed so any
church can self-host it on inexpensive shared hosting.

| Principle | What it means in practice |
|---|---|
| **Cheap hosting first** | The reference deployment is cPanel shared hosting (PHP + MariaDB). Nothing may require a long-running process, Node on the server, or a paid platform. |
| **Native libraries first** | Each language gets a plain core library (PHP, TypeScript, later Python) with no framework dependency. Framework code (Laravel, Nuxt, later FastAPI) is a thin adapter on top. |
| **One domain model** | Every backend and frontend implements [DOMAIN-MODEL.md](docs/design/DOMAIN-MODEL.md). JSON Schemas enforce it, and contract tests check every backend over HTTP. |
| **Modules** | Features ship as modules that an admin enables and configures. Every module includes its own admin screens. |
| **Vertical slices** | A phase delivers one feature through every layer: contract → core → API → admin → public site → deploy. No phase leaves "wire it up later" work. |
| **Done means live** | A phase is done only when it is running in production, after verification on test. |

---

## 2. Architecture at a glance

### Repository layout (✅ D1, approved 2026-10-08)

```
chapelcore/
├── apps/                         apps/<role>/<framework>
│   ├── web/nuxt/                 Public site (statically generated)     @chapelcore/web-nuxt
│   ├── admin/nuxt/               Admin dashboard (static SPA)           @chapelcore/admin-nuxt
│   └── api/laravel/              REST API (thin adapter over the core)  @chapelcore/api-laravel
│   # later: api/fastapi/ (restored from archive/fastapi-0.x), web/blade/, …
├── libs/                         libs/<language>/<platform>/<name>
│   ├── shared/                   no language
│   │   ├── contract/             JSON Schemas + openapi.yaml (enforces the domain model)
│   │   └── theme/                Design tokens + component CSS (any frontend)
│   ├── php/
│   │   ├── native/core/          Plain PHP: domain objects, ports, services, adapters   chapelcore/core
│   │   └── laravel/…             Only when Laravel code must be shared between apps
│   └── ts/
│       ├── native/core/          Plain TS: generated types, API client                  @chapelcore/core
│       ├── vue/ui/               Vue components (Vue only, no Nuxt)                     @chapelcore/vue-ui
│       └── nuxt/…                Only when Nuxt code must be shared between apps
├── e2e/                          Browser tests against a real running stack
├── docs/
│   ├── design/                   DOMAIN-MODEL.md, design decisions
│   ├── help/                     Help articles (Markdown), loaded into the Help module on deploy
│   └── runbooks/                 Hosting and release procedures
├── .github/workflows/
├── turbo.json · pnpm-workspace.yaml · version.json · CHANGELOG.md
```

- **The folder states what a library may depend on.** `native` imports no framework;
  `vue` may import Vue; `laravel` may import Laravel. Adding Python later is
  `libs/python/native/core` + `apps/api/fastapi`.
- Workspace globs stay uniform: `apps/*/*` and `libs/*/*/*` (pnpm), `libs/php/*/*` (Composer).
- PHP packages are managed by Composer. Each one also gets a small `package.json`
  so Turborepo can run its lint and test tasks with everything else.

### Environments

| Env | Site + admin | API | Deployed from | Purpose |
|---|---|---|---|---|
| dev | `localhost:3000` / `:3001` | `localhost:8000` | your working branch | Development; full stack runs locally |
| test | `test.libbynaz.org` | `api.test.libbynaz.org` | `staging` branch (automatic) | Release candidate; you verify in the UI here |
| prod | `libbynaz.org` | `api.libbynaz.org` | `main` branch (automatic) | Live site |

### Hosting topology (Namecheap Stellar Business — ✅ D2, amended by ✅ D16)

ChapelCore supports **two API layouts**, chosen per deployment by configuration. Every
frontend reads the API base URL from its config, and every contract path is relative
to that base.

| Layout | Site | Admin | API base URL | Use when |
|---|---|---|---|---|
| **API subdomain** (Libby) | `https://libbynaz.org/` | `https://libbynaz.org/admin` | `https://api.libbynaz.org` | Default. The subdomain's document root is Laravel's `public/` folder, the standard cPanel setup, and the API can move servers with a DNS change |
| **Same-origin path** | `https://example.org/` | `https://example.org/admin` | `https://example.org/api` | Hosts where adding a subdomain is awkward. No CORS needed |

- Laravel code lives **outside** `public_html`; only its `public/` entry point is exposed.
- **Subdomain layout rules** (enforced by tests):
  - CORS allows only the environment's own site origin (e.g. `https://libbynaz.org`), with credentials; no wildcards. Preflight answers are cached (`Access-Control-Max-Age`).
  - The session cookie is **host-only on the API host** and is never set on `.libbynaz.org`, so production sign-ins can't reach test.libbynaz.org.
  - The CSRF token comes from `GET {apiBase}/auth/csrf` in the response body. The frontend keeps it in memory and sends it in the `X-CSRF-TOKEN` header; no cookie is shared across subdomains.
- CI's end-to-end tests run the subdomain layout (Libby's). A contract test covers the
  same-origin layout's routing.
- Deploys are **atomic**: GitHub Actions builds, uploads a new release directory over
  SSH, runs `php artisan migrate --force`, switches a `current` symlink, checks
  `{apiBase}/health`, and rolls back if that fails.
- Background work runs from **one cPanel cron entry** (every minute) that invokes
  the ChapelCore scheduler. The scheduler decides what is actually due (see Phase 3, release 0.4.0).
- Host facts (verified in the hosting session, 2026-10-10 to 2026-10-16; details in
  [docs/runbooks/hosting.md](docs/runbooks/hosting.md)): **PHP 8.5** on both the command line
  and the web server (8.4 is the minimum ChapelCore supports); MariaDB **11.4.13** (server
  charset `latin1`, so we force `utf8mb4`); LiteSpeed web server; SSH on port 21098;
  symlinked document roots work; **Let's Encrypt certificates** on every hostname, renewed
  automatically by `acme.sh` (✅ D18). Namecheap's free certificates are one-time and never renew.

### Release model

```
feature/phase-N-* ──► dev ──(release PR, every 2 weeks)──► staging ──(after UI sign-off)──► main
                       CI only                             test.libbynaz.org              libbynaz.org + git tag
```

- **Cadence (✅ D3): two weeks.** Work that isn't ready waits for the next release.
- Unfinished modules may ship to production **disabled**. The module switch doubles
  as a feature flag.
- Hotfixes: `fix/*` from `main` → `main`, then merged back into `staging` and `dev`.

### Versioning (✅ D4)

Semantic versioning with Kootenai River Valley codenames per minor release.

| Version | Codename | Delivered by |
|---|---|---|
| 0.1.0 – 0.4.x | — (pre-release) | Phases 1–3 (not public; the old site stays live) |
| **1.0.0** | **Kootenai** | Phase 4 — the cutover that replaces libbynaz.org |
| 1.1.0 | Cabinet | Phase 5 — Prayer board |
| 1.2.0 | Fisher | Phase 6 — Sermons |
| 1.3.0 | Quartz | Phase 7 — Events |
| 1.4.0+ | TBD: local rivers, creeks, lakes and mountains (e.g. Mount Snowy, Treasure Mountain, Libby Creek, Koocanusa, Purcell) | Phases 8+ |
| 2.0.0 | Yaak | Reserved for the first breaking change |

`version.json` is the single source. The site footer, admin badge and `GET {apiBase}/health`
read it at build or run time. They never hardcode it, which was a defect in the archived build.

---

## 3. Module system

A **module** is a self-contained feature bundle:

| Part | Example (Sermons) |
|---|---|
| Domain objects (in DOMAIN-MODEL.md) | `Sermon`, `SermonSeries` |
| Storage (migrations) | `sermons`, `sermon_series` tables |
| API routes | `GET {apiBase}/sermons`, `PATCH {apiBase}/admin/sermons/{id}` |
| Admin screens | Sermon list, edit, sync status |
| Public pages / blocks | `/sermons`, `/sermons/{slug}`, "Latest sermon" block |
| Settings (typed, validated) | source = `logos`, channel ID = `13608627`, sync schedule |
| Permissions | who may view and edit, by role |
| Scheduled task types | `sermons.sync` |

- Modules ship **inside this repo**. They are switched on per deployment, not
  installed from a marketplace. That avoids the security risk of third-party
  plugins and keeps this simpler than Drupal.
- **System modules** are always on (users & roles, site settings, help, scheduled tasks, diagnostics).
  **Feature modules** can be toggled (prayer, sermons, events, …).
- The module manifest and settings are domain objects (DOMAIN-MODEL §5), so every
  backend implements them the same way.

---

## 4. How every phase runs

The rules live in [CLAUDE.md → Workflow](CLAUDE.md#workflow-non-negotiable). The sequence:

1. **Plan review.** The phase section below is expanded into a detailed plan
   (domain objects, endpoints, screens, tests, dependencies) and presented for review.
2. **Docs first.** Agreed changes are applied to PLAN.md, DOMAIN-MODEL.md and the
   contract schemas, then merged.
3. **Branch + failing tests.** `feature/phase-N-*` is created. Tests (unit, contract,
   end-to-end) are shown, explained, approved, written, and committed failing.
4. **Build in reviewed steps.** Each piece of code is shown and explained before it is
   written, then made to pass its tests.
5. **CI green → `dev`.**
6. **Help ships with the feature.** Help articles, the release-notes entry, and a
   guided tour where a task has several steps, all written in the same PR (from Phase 3 on).
7. **Release to test** on the next release. You verify in the UI on test.libbynaz.org,
   including the help, release notes and tours.
8. **Release to prod.** Tag, update CHANGELOG, mark the phase ✅.

**Standard done criteria for every feature phase** (added to each phase's own list):
real-backend e2e tests pass · contrast gate passes · help articles, release notes and
any tours written and verified on test · CHANGELOG updated · live on prod.

---

## 5. Phase index

| # | Phase | Version | Status |
|---|---|---|---|
| 0 | Archive & re-plan | — | 🔄 in progress |
| 1 | Foundation: tooling, CI/CD, hosting | 0.1.0 | 🔲 |
| 2 | Design system (theme restoration) | 0.2.0 | 🔲 |
| 3 | Core platform: auth, users, settings, modules, scheduler, diagnostics, help | 0.3.0 + 0.4.0 | 🔲 |
| 4 | Site content & cutover | **1.0.0 Kootenai** | 🔲 |
| 5 | Prayer board module | 1.1.0 Cabinet | 🔲 |
| 6 | Sermons module | 1.2.0 Fisher | 🔲 |
| 7 | Events module | 1.3.0 Quartz | 🔲 |
| 8 | Giving module | 1.4.0 | 🔲 |
| 9 | Member directory module | 1.5.0 | 🔲 |
| 10 | External login providers | 1.6.0 | 🔲 |
| 11 | Integrations | 1.7.0 | 🔲 |
| 12 | Portability & second backend | 1.8.0 | 🔲 |

Phases 6 and 7 may swap if a temporary sermon embed (Phase 4) covers sermons long
enough. Phases 8–12 are outlines and will be planned in detail when they come up.

---

## Phase 0 — Archive & re-plan

**Goal:** Preserve the FastAPI build and agree the new plan before any code.

- [x] Push all unpushed branches; tag `archive/fastapi-0.x` at `main` (2026-10-07)
- [x] Owner reviews and approves PLAN.md, CLAUDE.md, DOMAIN-MODEL.md (2026-10-09, PR #80)
- [x] Decisions D1–D17 resolved and recorded in §Decisions (2026-10-09)
- [x] CI green on PR #80 (ruff pinned to 0.15.15)
- [x] **Gate — owner:** Railway auto-deploy disconnected from this repo
  ([runbook](docs/runbooks/github-repository.md) N0). Done by owner 2026-10-09; final
  proof is that the first Phase 1 merge to `main` creates no `railway-app[bot]` deployment.
- [x] **Gate — owner:** GitHub settings N1–N6 applied (2026-10-09, plus N0b, N7, N8) (required checks, merge methods,
  classic protection removed, auto-delete branches, security features, read-only token)
- [ ] `docs/replan` merged to `dev`

---

## Phase 1 — Foundation: tooling, CI/CD, hosting → 0.1.0

**Goal:** An empty but real system deployed to test and prod by CI, which proves
every pipe end to end: `GET https://api.libbynaz.org/health` answers from Laravel via
the PHP core, and an `/admin` placeholder reads it.

**Detailed plan:** [docs/plans/phase-1.md](docs/plans/phase-1.md) (✅ approved 2026-10-09,
updated with the hosting findings). It owns the iterations, tests, dependencies and
risks; this section is the summary. Each iteration gets its own branch
(`feature/phase-1-N-description`) and PR.

**Repo**
- Clear the FastAPI-era app code from the working tree (it stays in the archive tag — ✅ D5)
- Scaffold the target layout (§2): pnpm + Turborepo + Composer workspaces
- Formatters, linters and static analysis per language (CLAUDE.md → Coding standards); CI fails on violations
- `version.json` read at build/run time by every app

**Contract & core**
- `libs/shared/contract`: schemas for the operational objects (Envelope, ErrorEnvelope, HealthStatus, VersionInfo)
- `libs/php/native/core`: those objects + `HealthService` port, pure PHP, unit-tested
- `libs/ts/native/core`: types generated from the schemas, API client with timeout + error handling

**Apps**
- `apps/api/laravel`: `GET {apiBase}/health` via the core; MariaDB connection check; both API layouts (D16) supported by configuration
- `apps/admin/nuxt`: placeholder page showing version + API health (proves admin → API wiring)
- `apps/web/nuxt`: placeholder, built but **not** deployed to `/` (the old site stays live)

**Diagnostics foundation (✅ D14)**
- Structured daily log files on the server (they work even when the database is down),
  written through PHP's standard logger interface (PSR-3), so the core stays framework-neutral
- A **reference code** per request, returned in every response's `meta.requestId` and
  shown on error pages ("Something went wrong. Reference: 7F3K-92QD")
- **Redaction:** passwords, tokens, secrets, prayer text and personal data never reach
  the logs (unit tests prove it)
- Browser errors from the admin and site are reported to the API (rate-limited, no personal data)
- **Doctor checks** as an SSH command (`php artisan chapelcore:doctor`): PHP version and
  extensions, Argon2 support, `utf8mb4`, writable folders, cron heartbeat seen recently,
  mail settings, app URL, certificate expiry ≥ 30 days. Each failure explains the fix in
  plain language. **Adjustment to D14 (2026-10-09):** the public `GET {apiBase}/health`
  reports only *which* checks pass, with generic messages. The detailed doctor report
  stays command-line only until the signed-in admin Diagnostics screen (Phase 3), so
  server details are never published.

**Testing**
- PHPUnit for PHP, Vitest for TS
- Contract suite: validates real HTTP responses from a running API against the schemas
- End-to-end harness: a browser test opens the real admin, against the real API and a real MariaDB (✅ D6: Playwright)

**CI/CD**
- CI on PRs (required checks: `lint`, `static-analysis`, `test-php`, `test-js`, `contract`, `e2e`, `audit`), with **PHP 8.5** + MariaDB 11.4 service containers to mirror the host, plus `test-php` on **PHP 8.4** (minimum supported)
- Every tool and dependency pinned (lockfiles, exact versions, Actions by commit SHA)
- Deploy workflows: `staging` → test.libbynaz.org + api.test.libbynaz.org, `main` → libbynaz.org (`/admin` only) + api.libbynaz.org
- Atomic release + migrate + health check + automatic rollback. The health check **polls** until the new version answers, because the host's PHP caches the old release for up to 2 minutes. Migrations stay backward compatible with the previous release.
- `shared/.well-known` linked into every release, so certificate renewals survive deploys

**Hosting (done with you, documented as a runbook)**
- ✅ PHP 8.5 with all required extensions (cPanel → PHP Tweaks → **Account settings**)
- ✅ Subdomains `test.libbynaz.org`, `api.libbynaz.org`, `api.test.libbynaz.org` (API
  document roots → Laravel `public/` through the `current` symlink)
- ✅ Let's Encrypt certificates on all five hostnames, auto-renewing (✅ D18)
- ✅ Two databases (`utf8mb4_unicode_ci`, separate users) and the test deploy SSH key
- ✅ Hosting spike: symlink switching, PHP version, LiteSpeed rewrites, GitHub Actions → server over SSH
- ⏳ Production deploy key, ChapelCore cron entry, server-side `.env` files (never in git): with the deploy pipeline
- **First setup help articles** in `docs/help/setup/`, written while we do this together:
  e.g. "Choose PHP 8.5 in cPanel", "Turn on SSH", "Set up automatic Let's Encrypt certificates", "Create an API subdomain on cPanel", "Create the
  database", "Add the ChapelCore cron job", "Add a deploy SSH key". Numbered steps, plain
  language, and links to the provider's own instructions. They read well on GitHub
  before ChapelCore is installed and become part of the Phase 12 setup guide.

**Done when**
- [ ] CI green on a PR, with every job required
- [ ] Merge to `staging` deploys test automatically; `https://api.test.libbynaz.org/health` returns 0.1.0
- [ ] Merge to `main` deploys prod; `https://api.libbynaz.org/health` returns 0.1.0, and the existing site is untouched
- [ ] A failed health check after deploy rolls back automatically (demonstrated once on test)
- [ ] Hosting runbook and the setup help articles let someone repeat the setup
- [ ] CORS allows only the site's own origin, and the session cookie is host-only on the API (tested on test)
- [ ] `chapelcore:doctor` passes on test and prod, and a deliberately broken setting is reported with its fix
- [ ] A forced error on test shows a reference code that finds the matching log entry

---

## Phase 2 — Design system (theme restoration) → 0.2.0

**Goal:** Restore the original Kootenai theme, make contrast failures impossible to
merge, and give both apps the shared look.

- **Drift analysis first.** Compare the original palette
  (`churchos-v7-archive/packages/config/tailwind.config.ts`: full 50–950 scales, a
  teal-tinted charcoal, stone-100 background) with the archived `tokens.css` (charcoal
  50–600 and stone 300–950 dropped; charcoal-700 and stone-100 shifted). Present
  findings and a proposed palette for approval.
- `libs/shared/theme`: tokens + **semantic** tokens (`surface`, `surface-raised`,
  `text`, `text-muted`, `card-header-bg`, `card-header-text`, …) for light and dark.
  Components use semantic tokens only.
- **Contrast gate in CI:** every approved foreground/background pair is checked
  against WCAG AA (4.5:1 body text, 3:1 large text and UI). Plus accessibility checks
  on rendered pages in the e2e suite.
- **Visual drift check:** Playwright screenshots of `/design` and the admin shell,
  compared on every PR. An unintended theme change shows up as a failed comparison to
  review, not a surprise later.
- `libs/ts/vue/ui`: Vue components (button, card, badge, form controls, scripture
  callout, container, section), each tested
- Fonts: Cinzel / Lora / DM Sans. Self-hosted vs Google Fonts is a privacy and
  performance decision to make at plan review.
- `/design` reference page (noindex); admin shell layout (sidebar, topbar, version badge)

**Done when:** the contrast gate passes in light and dark mode; `/design` and the admin
shell are verified on test and live on prod (`/admin`, `/design` only).

---

## Phase 3 — Core platform → 0.3.0 + 0.4.0

**Goal:** Everything modules depend on, all manageable in the admin. Shipped as two
releases so each stays a reviewable size (✅ D15).

### Release 0.3.0 — Sign-in, users, settings, modules, audit

- **Auth follows the ChapelCore auth contract** (✅ D7, D13; DOMAIN-MODEL §5.0). It is
  implemented here with Laravel's own session auth: Sanctum (SPA cookie mode) and Fortify
  (2FA, password reset, password confirmation), both first-party packages needing
  dependency approval. Thin ChapelCore controllers sit in front of Fortify, so responses
  match the contract (camelCase, our error codes) rather than Laravel's defaults.
- Session cookie: host-only `__Host-` cookie on the API host (Secure, HttpOnly,
  SameSite=Lax, no Domain), so test and prod can never share a session. Browsers still
  send it from libbynaz.org because the site and the API are the same site (one
  registrable domain). Session ID regenerated on sign-in and role change; idle and
  absolute timeouts enforced on the server.
- CSRF token from `GET {apiBase}/auth/csrf`, held in memory, sent as `X-CSRF-TOKEN`
  (✅ D16). We don't use Laravel's suggested `.libbynaz.org` cookie domain.
- Sanctum's list of stateful (cookie-using) sites names the environment's site origin.
  `Referrer-Policy` must stay `strict-origin-when-cross-origin` or looser (Sanctum
  needs the Origin/Referer header). E2E tests guard both.
- Passwords hashed with PHP's native `password_hash`: **Argon2id** (confirm the host's
  PHP has Argon2 support in Phase 1; fall back to bcrypt with a 64-character password cap);
  login throttling; password reset and email verification
- Two-factor sign-in (authenticator-app codes + one-time recovery codes) required for staff
  and above; optional for members (✅ D9). An admin can reset another person's 2FA
  (audited), because recovery matters more than the method for an older congregation.
  Passkeys are a possible later addition.
- Roles `superadmin → admin → staff → member → guest`. The authorization rules live in
  the PHP core; Laravel policies only call them. Enforced in the API.
- Admin: sign in/out, password reset (email), user list, invite user, change role,
  disable user
- Site settings (church name, timezone `America/Denver`, contact info, service times,
  social links) — typed, validated, editable in the admin
- Module registry: list modules, enable/disable, edit module settings (schema-driven
  forms); secret settings encrypted at rest and never returned to the browser
- Audit log: who changed what, when; viewable by admins
- Email: Laravel mail over the host's SMTP, for password reset and invites

**Done when (0.3.0):** you can sign in to `libbynaz.org/admin`, invite a staff user,
change site settings, and see each action in the audit log, all verified on test first.
Role checks are covered by e2e tests against the real API.

### Release 0.4.0 — Scheduled tasks, diagnostics, help

**Scheduled tasks (system module; moved from Phase 6, ✅ D15)**

- **Heartbeat vs schedule:** the single cPanel cron entry ticks every minute. That is
  only the heartbeat. Each task's own schedule decides whether it runs on a tick.
- Recurrence options (stored as data, defined in DOMAIN-MODEL):
  every N minutes/hours/days/weeks · N times per day/week · specific weekdays at a
  time (e.g. every Monday 18:00, weekdays 16:00) · monthly · advanced cron expression
- Ends: never · after N runs · on a date
- Retry policy: max attempts + back-off. Overlap protection (a task never runs twice at once).
- Times entered in the church's timezone and stored in UTC
- Admin: list tasks, enable/disable, edit schedule with a human-readable preview
  ("Every weekday at 4:00 PM — next run Tue Oct 13, 4:00 PM"), **Run now**, run
  history with status and messages
- Recurrence logic lives in the PHP core (pure, heavily unit-tested); Laravel only
  provides the heartbeat command and storage
- First tasks: `logs.purge`, `audit.purge`, `sessions.cleanup`, `password_resets.expire`

**Diagnostics (system module, ✅ D14)**
- Admin **Diagnostics** screen: browse and filter recent errors, look up a reference code,
  view doctor results
- Log detail level setting, plus **"Turn on detailed logging for 1 hour"**, which
  switches itself off
- Retention settings: diagnostic logs (default 30 days) and audit entries (default
  1 year), purged by the scheduler
- **"Create troubleshooting report"**: one Markdown document with versions, enabled
  modules and non-secret settings, doctor and health results, recent redacted errors
  with stack traces, and a "What were you trying to do?" box. Shown in full before
  copying, so the person sees exactly what they share. Made to paste into an AI agent
  or a GitHub issue.

**Help module (system)**: the basis every later phase adds to
  - Help articles, written as Markdown in `docs/help/` alongside the code and loaded
    into the module on deploy. Searchable. Written so that each tour step matches a
    section of the article.
  - **One Help button, same place on every page** (admin here; public pages from
    Phase 4): a labelled "? Help" button at the right of the page title. It opens the
    **help panel** for that page (✅ D12):
    - Desktop/tablet: slides in from the right and leaves the page visible, so people
      can follow the steps while reading. Phone: a full-screen sheet with a large Close button.
    - Inside: the article for this page, a **"Show me how"** button when a tour exists,
      related articles, help search, and "Open as full page" (for printing or sharing).
    - Never opens by itself. Esc or Close returns focus to where the person was.
  - **Release notes**: "What's new in this release" for every version. A subtle,
    dismissible banner on the admin dashboard links to them. It appears once per
    user per release and never covers content.
  - **Guided tours on request**: "Show me how" (from the help panel or next to a task)
    starts a step-by-step walkthrough. Each step is a small pop-up box pointing at one
    control. Tours **never start on their own**. Large text, plain language,
    Back / Next / Close on every step, plus **"Read this instead"**, which ends the tour
    and opens the help panel at the matching section. Usable by keyboard and screen
    reader. Built as our own small Vue component (no tour library), with tours defined
    as data next to the help articles.

**Done when (0.4.0):** a task scheduled in the admin runs on the expected tick on test
(and recurrence fixtures pass, including daylight-saving changes); logs older than the
retention period are purged; a troubleshooting report is generated from a forced error;
the help articles, the 0.4.0 release notes and a "Show me how: invite a user" tour work.
All verified on test, then live on prod.

---

## Phase 4 — Site content & cutover → 1.0.0 "Kootenai"

**Goal:** The new ChapelCore site replaces the current libbynaz.org, with everything on
it editable in the admin and the restored theme applied.

- **Inventory first:** list every page, section and asset on the current site, and
  agree what moves over. (The current site renders client-side, so this is done in a
  browser together.)
- Pages module: pages made of ordered **content blocks** (rich text, hero, image,
  call-to-action, service times, contact, embed, scripture). Draft/publish. SEO title
  and description per page.
- Navigation and footer managed in the admin
- **Temporary sermons:** a Logos embed block configured with the channel ID
  (`13608627`). New functionality; replaced in Phase 6.
- Contact form → email to a configured address (rate-limited)
- **Public help:** the same Help button and help panel on public pages that have help
  (prayer board, contact, later giving), "Show me how" tours where a public task has
  several steps (e.g. submitting a prayer request), and an FAQ block the church edits
  in the admin. An optional, subtle "What's new" note on the home page for
  visitor-facing changes.
- 404 page, sitemap, robots.txt, redirects from any old URLs
- Static generation (✅ D8): publishing in the admin triggers a GitHub Actions rebuild
  and deploy (about 2–4 minutes). Frequently changing data (prayer board, upcoming
  events, latest sermon) is fetched live from the API when the page loads. The admin
  preview shows changes instantly.
- Lighthouse check in CI (target ≥ 90 now, ≥ 95 by Phase 12)

**Cutover:** verify everything on test.libbynaz.org → release to prod (`/` switches to
the new site) → keep the old site's files for a quick rollback for one release.

**Done when:** libbynaz.org serves ChapelCore; every page is editable in the admin; the
old site's files are retired after one stable release.

---

## Phase 5 — Prayer board module → 1.1.0 "Cabinet"

**Goal:** Visitors submit prayer requests; nothing becomes public without staff approval.

- Submission form (public), rate-limited per real client IP, with spam protection
  (honeypot field + timing check — native, no third-party service)
- Every submission is stored as **`pending`**. Optional AI pre-screen only *labels*
  submissions for staff (provider pluggable, off by default); it never publishes.
- Admin queue: approve, reject (with private reason), edit for privacy before
  publishing, mark answered, post updates
- Public board (approved only; never email or moderation data) and optional
  members-only view
- Prayer-chain email on approval (configurable list)
- Expiry / archive of old requests (a scheduled task, `prayer.archive`)
- Submitter always sees the same confirmation, whatever happens to the request

**Done when:** a request submitted on test appears in the admin queue, is approved,
reaches the public board and the prayer-chain email. Proven by an e2e test against the
real stack, then on prod.

---

## Phase 6 — Sermons module → 1.2.0 "Fisher"

**Goal:** Sermons from Logos appear on the site with full detail, replacing the
Phase 4 embed. The Sermon model will be revisited at plan review.

- Source adapter (PHP core): Logos RSS feed
  `https://sermons.logos.com/api/channels/13608627/feed` → domain `Sermon`
  (title, description, date, audio, duration, link)
- Enrichment adapter: each sermon's public page provides the cover image, speaker,
  series, passage, and possibly transcript/video. **Undocumented and fragile:** it must
  fail soft and never block the feed import.
- Admin edits (speaker, series, passage, cover) are never overwritten by a sync
- Sync task `sermons.sync` with a default schedule (e.g. Sundays + Mondays); configurable
- Public: sermon list with search and series filter, sermon detail with audio player
  (and video/transcript if available), "Latest sermon" block for pages
- Sermon dates are calendar dates (the feed's midnight-GMT timestamps would otherwise show the previous day in Montana)

**Done when:** the sync runs on schedule on prod, the embed is retired, and admin
overrides survive a re-sync (tested).

---

## Phase 7 — Events module → 1.3.0 "Quartz"

- Events with date/time (timezone-aware), location, description, image, recurring events
  (reusing the Phase 3 recurrence model)
- Admin CRUD; public calendar/list and detail pages; "Upcoming events" block
- iCal feed export (subscribe from phone calendars)

---

## Phases 8–12 — outline

| # | Phase | Notes carried over |
|---|---|---|
| 8 | **Giving** | Stripe.js holds all card input; webhook signature verified; members see only their own history; no bulk export. Settings via module config. Two "Show me how" tours: setting up giving (admin) and giving a tithe or offering (visitor/member). |
| 9 | **Member directory** | Member role minimum, never public; consent before listing; per-field visibility; personal data encrypted at rest (column-level); every read audited; no bulk export. |
| 10 | **External login providers** | Microsoft, Google, Apple via Laravel Socialite (Google is built in; Microsoft and Apple are community-maintained SocialiteProviders, so each needs dependency approval). Group → role mapping lives in the native core. Implements the auth contract's `ExternalIdentity`. |
| 11 | **Integrations** | Email providers (MS365 Graph, Gmail), calendar sync (Google/Outlook), AI moderation providers (Gloo, Grok), storage (B2/S3). Each one is a source/sink adapter behind a core port. **AI help assistant** (see below), which can also read a troubleshooting report alongside the help docs to suggest fixes, only when the person clicks to send it. |
| 12 | **Portability & second backend** | New-church setup guide and installer; Lighthouse ≥ 95; security review; restore FastAPI as `apps/api/fastapi` + `libs/python/native/core`, passing the same contract suite. |

### AI help assistant (Phase 11) — answers only from approved sources

- **Approved sources only**, managed in the admin as a list of *knowledge sources* (✅ D17):
  ChapelCore help articles · the church's own published content (pages, FAQ, sermon
  transcripts, events) · external resources an admin adds **by URL** (e.g. official
  Church of the Nazarene pages, a hosting provider's help center). Each source can be
  switched on or off. External sources come in three kinds:
  - **Page:** one URL, indexed as-is
  - **Section:** pages under a URL path on the same site (e.g. a provider's cPanel
    knowledge base), up to a page limit set by the admin
  - **Sitemap:** pages listed in a site's `sitemap.xml`, filtered by path, up to a page limit
  A bare index page such as `https://www.namecheap.com/help-center/` is added as a
  *section* or *sitemap* source, not as a single page, because the index itself holds
  only links. Fetching follows each site's `robots.txt`, stays on the site that was
  added, and is built with PHP's own HTTP and HTML tools (no crawler package).
- **No web search, ever.** External URLs are fetched once by our server, stored and
  indexed in our own database, and refreshed on a schedule (Phase 3 scheduler). At
  question time the assistant only sees passages retrieved from that index.
- Every answer **cites its sources** (links). If the sources don't cover the question,
  it says so and points to the contact page instead of guessing.
- It never takes actions and never sees personal data (prayer requests, directory, giving).
- **Honest limit:** the *knowledge* stays in our ecosystem, but generating the wording
  still calls an AI model at the configured provider. Only the question and the retrieved
  passages are sent, and we choose a provider whose terms exclude training on and
  retaining that data. A self-hosted model is a possible later option.

**Why not train our own model?** (recorded 2026-10-09)

| Approach | Verdict |
|---|---|
| Train a model from scratch | Millions of dollars and specialist teams. Not realistic and not needed. |
| Fine-tune an open model | Teaches style, not reliable facts. Our facts (sermons, events, help) change weekly, and each retraining run costs hundreds to thousands of dollars plus GPU hosting. |
| **Retrieve from our own sources, then have an existing model write the answer** (chosen) | The knowledge, index, instructions and test questions are ours. Only the model that writes the wording is rented, and the provider can be swapped. |

- **Where it lives:** the index sits in the church's own MariaDB (built-in full-text
  search is enough at church scale; vector search needs MariaDB 11.7+). The assistant
  code is in the API behind an AI-provider connector. The model is called through the
  provider's API only when someone asks a question.
- **Self-hosting the model** can't run on shared hosting. It would need a separate server
  (tens of dollars a month for a slow CPU-only one, hundreds for a GPU). It stays a later
  option for churches that want everything on their own hardware.
- **Rough cost** for ~3,000 tokens in and ~300 out per question, at 500 questions a month
  (Anthropic list prices, 2026-10): Claude Haiku 5.5 ≈ $0.25/month, Claude Sonnet 5.5
  ≈ $4.50/month, Claude Opus 5.5 ≈ $9/month. The model is chosen in Phase 11 by testing
  against a set of real help questions, and the provider's data terms are confirmed then.

---

## Lessons carried over from the archived build

These are the failure modes the new workflow is designed to prevent:

| What happened | Prevented by |
|---|---|
| Admin app never reached the API; unit tests passed because every `$fetch` was mocked | E2E tests against the real stack are a done criterion for every phase |
| Prayer requests auto-published when AI moderation was unavailable | Pending-first design; AI can only label |
| Phases marked ✅ with unchecked done criteria | "Done means live", and the checklist is reviewed at release |
| Docs described intended behaviour, not actual behaviour | Docs change first at plan review, and the e2e tests prove the behaviour |
| Theme drift and unreadable card headers | Semantic tokens + CI contrast gate |
| Migrations applied by hand; a migration silently never applied | Deploy pipeline runs migrations and health-checks every release |
| Version hardcoded in footer/badge, never bumped | Read from `version.json`; bumped as part of the release PR |
| Unmerged fixes and local-only branches | Release checklist includes "no orphan branches" |

---

## Decisions

All decisions approved by the owner (2026-10-08 to 2026-10-09). New decisions are added here as ⚖ until approved.

| # | Decision | Proposed | Needed by |
|---|---|---|---|
| D1 | Repo layout | ✅ Nested: `apps/<role>/<framework>`, `libs/<language>/<platform>/<name>` (approved 2026-10-08) | Phase 1 |
| D2 | URL layout | ✅ One domain per env for site and admin (approved 2026-10-08); API placement amended by D16 | Phase 1 |
| D3 | Release cadence | ✅ Every two weeks (approved 2026-10-08) | Phase 1 |
| D4 | Version map | ✅ 0.x pre-cutover; **1.0.0 Kootenai = cutover**; one codename per minor release, drawn from local rivers, creeks and mountains (approved 2026-10-08) | Phase 1 |
| D5 | FastAPI code in the working tree | ✅ Remove; it stays in the archive tag (approved 2026-10-09) | Phase 1 |
| D6 | E2E browser test tool | ✅ Playwright (approved 2026-10-09). No Storybook for now: the `/design` page, component tests and Playwright screenshot comparisons cover it | Phase 1 |
| D7 | Auth (Laravel) | ✅ Sanctum SPA cookie auth + Fortify for 2FA/reset, behind contract controllers (approved 2026-10-09) | Phase 3 |
| D8 | Rebuild the static site on publish | ✅ Rebuild via GitHub Actions on publish; live API data for frequently changing parts; instant admin preview (approved 2026-10-09) | Phase 4 |
| D9 | Two-factor sign-in | ✅ Required for staff and above; optional for members (approved 2026-10-09) | Phase 3 |
| D10 | Help experience | ✅ Release-notes banner + on-request "Show me how" tours; no "new" badges, no automatic tours (approved 2026-10-08) | Phase 3 |
| D11 | AI help assistant sources | ✅ Approved knowledge sources only (own content + admin-added URLs, indexed locally); no web search (approved 2026-10-08) | Phase 11 |
| D12 | Help panel + tour blend | ✅ (approved 2026-10-09): one "? Help" button per page → right-side help panel (full-screen on phones); tours offer "Read this instead"; articles offer "Show me how" | Phase 3 |
| D13 | Auth across stacks | ✅ (approved 2026-10-09): a ChapelCore **auth contract** in the domain model (objects, flows, endpoints, error codes, cookie rules, password-hash export format). Each backend implements it with its framework's vetted built-in auth. No external identity provider. Independently reviewed 2026-10-09 | Phase 3 |
| D14 | Diagnostics module | ✅ Logging + reference codes + redaction + doctor checks (Phase 1); admin Diagnostics screen, retention purge and troubleshooting report (Phase 3) (approved 2026-10-09) | Phase 1 |
| D15 | Scheduler placement | ✅ Scheduled tasks move from Phase 6 to Phase 3; Phase 3 ships as 0.3.0 + 0.4.0 (approved 2026-10-09) | Phase 3 |
| D16 | API layouts | ✅ Two configurable layouts: API subdomain (Libby: `api.libbynaz.org`, `api.test.libbynaz.org`) or same-origin `/api`. Host-only session cookie on the API host; CSRF token via `GET {apiBase}/auth/csrf` in a header; explicit CORS allow-list (approved 2026-10-09) | Phase 1 |
| D17 | Knowledge source kinds | ✅ Page, section (URL-path crawl with a page limit) and sitemap; `robots.txt` respected; same-site only (approved 2026-10-09) | Phase 11 |
| D18 | Certificates | ✅ Let's Encrypt via `acme.sh` 3.1.6 from the account's cron, installed into cPanel with its `cpanel_uapi` hook; Namecheap's free certificates are one-time only (approved 2026-10-16) | Phase 1 |
| D19 | Project name | ✅ **ChapelCore** (decided 2026-10-17; formerly ChurchOS). "ChurchOS" collided with a registered US trademark (CHURCHOS, a church-consulting firm) and an unrelated church app; ChurchCore and MinistryOS are used by other church products. Packages `@chapelcore/*` (npm) and `chapelcore/*` (Composer); repo `chapelcore/chapelcore` | Phase 1.1 |
