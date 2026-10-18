# ChapelCore — CLAUDE.md

> **Status: Approved** by the owner 2026-10-09 (PR #80)

Project conventions for AI-assisted and human development.
**This file is the single source of truth for how we work.**

| Doc | Owns |
|---|---|
| [PLAN.md](PLAN.md) | Roadmap, phases, done criteria, open decisions |
| [docs/design/DOMAIN-MODEL.md](docs/design/DOMAIN-MODEL.md) | What the data looks like, in every language (normative) |
| [CHANGELOG.md](CHANGELOG.md) | What shipped |

---

## What this is

ChapelCore (formerly ChurchOS) is a modular, open-source church CMS. It is built first for Libby Church of
the Nazarene, where it will replace https://libbynaz.org, and is designed so any
church can self-host it on inexpensive cPanel shared hosting.

**State of the repo (2026-10-16):** Phase 1 in progress. Its detailed plan is
[docs/plans/phase-1.md](docs/plans/phase-1.md); hosting is set up (see
[docs/runbooks/hosting.md](docs/runbooks/hosting.md)); no application code yet.
The project was renamed from ChurchOS to ChapelCore on 2026-10-17 (PLAN D19). The project is restarting as
**Nuxt + Laravel**. The previous FastAPI + Supabase build is preserved at git tag
`archive/fastapi-0.x`, and its code is still in the working tree until Phase 1 clears
it. Do not extend that code. Restore pieces from the tag only when a phase plan says so:

```bash
git checkout archive/fastapi-0.x -- apps/api     # example: bring the FastAPI app back
```

---

## Workflow (non-negotiable)

Every phase and feature follows these steps in order. Nothing is skipped because a
change "looks small".

1. **Plan review before development.** Expand the phase from PLAN.md into a detailed
   plan (domain objects, endpoints, screens, tests, dependencies, risks) and present
   it to the owner. No branch and no code until the owner approves.
2. **Docs change first.** Agreed adjustments go into PLAN.md, DOMAIN-MODEL.md and the
   contract schemas *before* implementation, in a reviewed PR.
3. **Branch + failing tests.** Create `feature/phase-N-description` from `dev`. Write
   the tests for the iteration (unit, contract, end-to-end including UI behaviour),
   commit them, and confirm they fail for the expected reason.
4. **Show before writing.** Before creating or changing any file (tests, application
   code, config, build or CI files), show the owner the complete code with an
   explanation of what it does, why it exists, and how it connects. Write it only after
   approval. This includes adding a dependency.
5. **Dependency approval.** Every new third-party package (Composer, npm, pip, GitHub
   Action) needs explicit owner approval, with: what it does, why the language or
   framework built-ins aren't enough, maintenance and security status, and licence.
6. **Native first.** Build in the plain language library (`libs/<lang>/…`) first.
   Framework code (Laravel, Nuxt, …) stays a thin adapter: routing, HTTP, storage
   wiring, configuration. No business rules in framework code.
7. **Test against the real backend.** Unit tests may use test doubles, but every
   phase also has end-to-end tests where a real browser drives the real frontend
   against the real API and a real database. A feature isn't working until those pass.
8. **Contrast gate.** UI changes must pass the automated WCAG AA contrast check in
   light and dark mode.
9. **CI/CD for everything that can be automated.** Lint, static analysis, tests,
   builds, deploys, migrations and post-deploy health checks run in GitHub Actions.
   Manual steps are temporary and written up as a runbook.
   - **Never merge on red.** Every CI job is a required check in the branch rulesets
     ([runbook](docs/runbooks/github-repository.md)); a failing check is fixed, not bypassed.
   - **Pin versions.** Every dependency and tool (linters, formatters, test runners,
     GitHub Actions) is pinned by lockfile or exact version, so a passing build can't
     start failing by itself. Upgrades arrive as their own reviewed PRs.
10. **Done means live.** A phase is complete only when it has been verified by the
    owner in the UI on test.libbynaz.org **and** released to libbynaz.org. Every done
    criterion is checked off at release; ✅ is never set with open items.
11. **Docs move with code.** Any change to behaviour, schema, commands or env vars
    updates the relevant doc and `CHANGELOG.md` (`## [Unreleased]`) in the same PR.
    Docs describe what the code *does*, not what it is meant to do.
12. **Help ships with the feature** (from Phase 3 on). Every user-facing change adds
    or updates its help article in `docs/help/`, adds an entry to that release's
    release notes, and, when a task has several steps, a "Show me how" guided tour.
    Tours only start when someone clicks them. No "new" badges.
13. **Commit often** on the feature branch: one working, tested unit per commit.

---

## Coding standards

Follow the established standard of **the layer the file belongs to**. Where a
framework's convention differs from its language's, framework code follows the
framework, and plain-language libraries follow the language. Formatters and linters
enforce this in CI; nothing is formatted by hand.

| Layer | Standard | Enforced by (pending approval) |
|---|---|---|
| Plain PHP (`libs/php/native/*`) | PSR-4 autoloading, **PER Coding Style 2.0**, strict types, `final readonly` value objects | Pint (`per` preset), PHPStan (max level) |
| Laravel (`apps/api/laravel`) | Laravel conventions (e.g. Eloquent attributes are `snake_case` column names, while plain PHP properties are `camelCase`) | Pint (`laravel` preset), PHPStan + Larastan |
| Plain TypeScript (`libs/ts/native/core`) | `strict` tsconfig, ESM, no framework imports | ESLint (typescript-eslint), Prettier |
| Vue (`libs/ts/vue/ui`) | Official Vue style guide (priority A–C), `<script setup lang="ts">` | eslint-plugin-vue |
| Nuxt (`apps/web/nuxt`, `apps/admin/nuxt`) | Nuxt directory and auto-import conventions | @nuxt/eslint |
| Python (later) | PEP 8, typed | ruff, mypy |
| SQL | `snake_case`, plural table names | migration review |
| JSON (transport) | `camelCase` fields, always-present nullables | contract schemas |

Every file starts with a short header comment: what it does, why it exists, and how
it connects.

---

## Stack (target)

| Layer | Technology |
|---|---|
| Public site | Nuxt (Vue 3), statically generated |
| Admin | Nuxt (Vue 3), static SPA |
| API | Laravel on **PHP 8.5** (8.4 minimum supported), over `libs/php/native/core` |
| Database | MariaDB 11.4 (default); PostgreSQL supported via config |
| Auth | Laravel session auth via Sanctum (cookie-based SPA auth) |
| Tests | PHPUnit · Vitest · contract suite · end-to-end browser tests |
| Monorepo | pnpm workspaces + Turborepo; Composer for PHP |
| CI/CD | GitHub Actions → SSH deploy to Namecheap |
| Hosting | Namecheap Stellar Business (cPanel, PHP selector, MariaDB, SSH, cron) |

Nothing in this table is scaffolded yet. Phase 1 builds it.

---

## Repository layout (target)

See [PLAN.md §2](PLAN.md#2-architecture-at-a-glance). Two rules:

- **Apps:** `apps/<role>/<framework>`, e.g. `apps/web/nuxt`, `apps/admin/nuxt`, `apps/api/laravel`.
- **Libraries:** `libs/<language>/<platform>/<name>`, where `native` means no framework,
  e.g. `libs/php/native/core`, `libs/ts/native/core`, `libs/ts/vue/ui`. Language-neutral
  assets live in `libs/shared/` (`contract`, `theme`). The folder states what a library
  may depend on.

---

## Environments & hosting

| Env | Site + admin | API | Deployed from |
|---|---|---|---|
| dev | `localhost` | `localhost:8000` | working branch |
| test | `https://test.libbynaz.org` | `https://api.test.libbynaz.org` | `staging` (automatic) |
| prod | `https://libbynaz.org` | `https://api.libbynaz.org` | `main` (automatic) |

Per environment: `/` = public site, `/admin` = admin. The API runs on an `api.`
subdomain (Libby) or at `/api` on the same domain; the layout is configuration
(PLAN D16), and every API path is written relative to `{apiBase}`. Laravel code
lives outside `public_html`. TLS certificates are Let's Encrypt, renewed automatically by
`acme.sh` from its own cron line (PLAN D18; the one exception to the single-cron rule until
the Phase 3 scheduler exists). Background work runs from one cPanel cron entry (every
minute) that invokes the ChapelCore scheduler. Secrets live only in server-side `.env`
files and GitHub Actions secrets, never in git.

---

## Git workflow & releases

- `feature/phase-N-description` or `fix/short-description`, cut from `dev`
- `feature/*` → `dev` (CI) → `staging` (test, every two weeks) → `main` (prod, after owner sign-off)
- `main`, `staging`, `dev` are protected: PRs only, all CI jobs required
- Hotfix: `fix/*` from `main`, merged back into `staging` and `dev`
- Release PR (`staging` → `main`) bumps `version.json`, finalises CHANGELOG, and is tagged `vX.Y.Z`
- No orphan work: every branch is merged, or deleted with a note in its PR

Commit messages: Conventional Commits (`feat(prayer): …`, `fix(api): …`, `docs: …`).

---

## Versioning

Semantic versioning. Release codenames follow Kootenai River Valley geography (rivers,
creeks, lakes and mountains), one per minor release: **1.0.0 Kootenai** (cutover) → 1.1.0 Cabinet → 1.2.0 Fisher →
1.3.0 Quartz → … → 2.0.0 Yaak (first breaking change). Version map: PLAN.md §2.

`version.json` is the only place the version is written. The site footer, the admin
badge and `GET {apiBase}/health` read it; they never hardcode it.

---

## RBAC roles

`superadmin → admin → staff → member → guest`. Authorization rules live in
`libs/php/native/core`; Laravel policies call them. Every protected endpoint is
checked server-side. Frontend guards are UX only.

---

## Security requirements (always enforce)

- **Sessions:** server-side sessions in a host-only `__Host-` cookie on the API host
  (Secure, HttpOnly, SameSite=Lax, no Domain). Never set cookies on `.libbynaz.org`,
  so production cookies can't reach test. No credentials in `localStorage`,
  `sessionStorage` or JS-readable cookies.
- **CSRF:** the token comes from `GET {apiBase}/auth/csrf`, is held in memory, and is
  sent as the `X-CSRF-TOKEN` header on every state-changing request
- **Auth contract:** every backend implements the auth contract in DOMAIN-MODEL §5.0
  using its framework's own vetted auth. No hand-written session, token or crypto code.
- **Passwords:** PHP `password_hash` (Argon2id/bcrypt); login throttling; two-factor
  sign-in for staff and above (PLAN D9)
- **Least privilege:** the browser never talks to the database. All data access
  goes through the API.
- **Secrets at rest:** secret settings encrypted (Laravel encrypted casts), never
  returned to the browser, and masked in the admin
- **Personal data:** column-level encryption for directory data (Phase 9); no bulk
  export endpoint for directory or giving records
- **Rate limiting** on every public write endpoint, keyed on the real client IP
  (proxy-aware)
- **Prayer requests are `pending` until a person approves them.** Automation may only label.
- **Card data never touches our servers.** Stripe.js handles all card input.
- **Dependencies:** `composer audit` and `pnpm audit` run in CI; findings block release
- **CORS:** only the environment's own site origin is allowed, with credentials; no
  wildcards (not needed at all in the same-origin layout)
- **Database character set:** `utf8mb4` everywhere (the host default is `latin1`)
- **Logging:** only through the logging port (PSR-3 in PHP). Passwords, tokens, secret
  values, prayer text and personal data are redacted before anything is written. Users
  see a reference code, never a stack trace.

---

## Design system

The original Kootenai palette is being restored in Phase 2 (drift analysis in
PLAN.md). Until then, the authoritative references are:

- Original palette: `/home/pj/projects/churchos-v7-archive/packages/config/tailwind.config.ts`
- Archived tokens: `packages/config/src/tokens.css` (in the archive tag; shows the drift)

Principles that carry over: forest green (primary), Kootenai teal (secondary),
stained-glass gold (accent), teal-tinted charcoal (dark surfaces), warm river stone
(light surfaces). Fonts: Cinzel (display), Lora (body, scripture), DM Sans (UI).
Components use **semantic** tokens, never raw palette values, so another church can
rebrand by editing tokens only.

---

## Domain model

[docs/design/DOMAIN-MODEL.md](docs/design/DOMAIN-MODEL.md) is normative. If code
disagrees with it, the code is wrong, or the document is changed first through its
change process. Transport JSON is `camelCase` with always-present nullables; storage
is `snake_case`; in-code names follow the language or framework standard above.

---

## Commands

None yet. Phase 1 adds and verifies them, then records them here. Do not document a
command until it has been run successfully.
