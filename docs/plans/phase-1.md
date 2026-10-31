# Phase 1 plan — Foundation: tooling, CI/CD, hosting → 0.1.0

> **Status: APPROVED** by the owner 2026-10-09 · **Updated 2026-10-16** with the hosting
> session's findings (see "Changes since approval" below). No application code yet.
> The §12 adjustments are applied to PLAN.md, CLAUDE.md and DOMAIN-MODEL.md in the same PR.

### Changes since approval (2026-10-09 → 2026-10-30)

| Where | Change | Why |
|---|---|---|
| §3.1, §10 | PHP target **8.5**; CI also tests **8.4** as the minimum | Owner, 2026-10-09: 8.5 has a year more support |
| §5 | Public `/health` = summary only; doctor details CLI-only until Phase 3 (Q5) | Don't publish server details |
| §8 | Iteration 1.2 includes automatic Let's Encrypt certificates | Free host certificates never renew (R10) |
| §9 | `acme.sh` 3.1.6 added as a server tool (✅ D18) | R10 |
| §10 | `shared/.well-known` in the server layout; deploys **poll** `/health` for the new version; `755`/`644` permissions | Findings R1, R11 |
| §11 | R1–R4 marked **passed**; new R10 (certificates) and R11 (validation files) — both handled | Hosting spike |
| §13 | New setup help articles: turning on SSH, automatic certificates | Hosting session |
| §15 | Q1–Q6 answered | Owner, 2026-10-09 |
| — | **Project renamed ChurchOS → ChapelCore** (D19, 2026-10-17); packages `@chapelcore/*` / `chapelcore/*`; server folders, databases and deploy keys renamed to match | Trademark finding, 2026-10-16 |
| — | Hosting details recorded in [docs/runbooks/hosting.md](../runbooks/hosting.md) | — |
| §10 | `contrast` placeholder job added now; PHP checks are named `test-php (8.5)` and `test-php (8.4)` (iteration 1.1, 2026-10-18) | Rulesets never need changing again |
| §4 | pnpm workspace also covers `libs/shared/*` | `libs/shared/contract` is one level shallower than `libs/*/*/*` |
| §6 | Prettier formats code files only, not Markdown | Owner, 2026-10-18: avoid rewrapping every doc |
| §9, R6 | TypeScript pinned to **6.0.3** | TS 7 doesn't work with `vue-tsc` or `typescript-eslint` yet |
| §3.1 | pnpm **12.14.0** (latest stable) | Owner, 2026-10-18 |
| — | Old branches with unique commits kept as `archive/branch/<name>` tags, then all old branches deleted | Owner, 2026-10-18 (P6) |
| §4, §6.1 | `openapi.json` instead of `openapi.yaml` | Owner, 2026-10-20 (1.3 D6): no YAML parser needed to test it |
| §5 | Reference code always written with its hyphen; field limits for `ClientErrorReport`; `LogEntry` ids and timestamp precision fixed in DOMAIN-MODEL 0.3 | Owner, 2026-10-20 (1.3 D1–D5) |
| §8 | Iteration 1.2's setup help articles move to 1.9 and are written together | Owner, 2026-10-19 |
| §6.2, §8 | Iteration 1.4 detailed: DOMAIN-MODEL 0.4 (health, doctor and redaction rules), shared redaction cases, review per checkpoint (§8.1) | Owner, 2026-10-22 (1.4 E1–E6, Q-G) |
| §6.1, §6.2 | The PHP core requires the contract as a local Composer path package (runtime `ContractValidator`), pinned to the DOMAIN-MODEL version (`0.4.0`) through the path repository's `versions` option, so `check-pins` needs no exception | Owner, 2026-10-22 |
| §6.2, §6.3, §8.1 | The core has its own PSR-3 `JsonLogger`, which writes one redacted `LogEntry` JSON line per call; Laravel uses it as a custom log channel. It replaces the planned `RedactingLogger` decorator and Monolog processor | Owner, 2026-10-26: a decorator can't redact the exception or control the shape of the line |
| §8.1 | Checkpoints 4 and 5 each split in two (4a/4b, 5a/5b) | Owner, 2026-10-26: smaller reviews |
| §8.1 | Checkpoint 8 added: redaction rule 2b (passwords in URLs) | Owner, 2026-10-31 |

---

## 1. What you'll be able to see when Phase 1 is done

- `https://api.libbynaz.org/health` and `https://api.test.libbynaz.org/health` answer
  with the version (0.1.0 "pre-release"), the database status, and the cron heartbeat.
- `https://libbynaz.org/admin` shows a simple ChapelCore Admin page reading that
  status from the API. The rest of libbynaz.org is **unchanged** (the old site stays).
- `https://test.libbynaz.org` shows a "ChapelCore — coming soon" placeholder and the admin.
- Every merge to `staging` deploys test automatically. Every merge to `main` waits for
  your **Approve** click, then deploys production. A broken deploy rolls itself back.
- Any error shows a reference code (e.g. `7F3K-92QD`) that finds the exact log entry.
- `php artisan chapelcore:doctor` checks the server and explains any problem in plain words.
- Setup help articles and a hosting runbook let someone else repeat the setup.

No church features yet. Phase 1 builds and proves the foundations every later
phase stands on.

## 2. Scope

**In:** repo reset and toolchain · contract schemas for the operational objects · PHP
core (operational objects, health, request IDs, redaction, doctor checks) · Laravel API
(health, error envelopes, logging, browser-error intake, doctor command, both API
layouts) · TS core (generated types, API client) · admin and web placeholders · contract
and end-to-end tests in CI · deploy pipelines with rollback · hosting setup ·
GitHub settings P1–P6 · first help articles and runbooks.

**Out (later phases):** design system and theme (Phase 2) · sign-in, users, settings,
modules (Phase 3) · admin Diagnostics screen and log purge (Phase 3, 0.4.0) · any
church content.

## 3. Before we start (owner)

### 3.1 Your development machine (WSL)
Phase 1 needs the same versions locally that production and CI use.
**PHP target: 8.5** (supported until end of 2029; 8.4's bug-fix support ends with 2026).
CI also runs the PHP tests on **8.4 as the oldest supported version**, so other
churches on slower hosts can still install ChapelCore. I'll write exact
commands in the developer runbook; in short:

| Tool | Have (2026-10-18) | Need | Why |
|---|---|---|---|
| PHP | 8.5.11 ✅ | **8.5** + extensions | Production target (owner, 2026-10-09); PHPUnit 13 requires 8.4+ |
| Composer | 2.10.2 | 2.10.x (CI uses 2.10.3) | OK |
| Node | 24.22.0 ✅ | **24 LTS** (pinned in `.nvmrc`) | Current LTS; Nuxt supports it |
| pnpm | 12.14.0 ✅ | pinned in `package.json` (via Corepack) | So everyone uses the same version |
| MariaDB | 11.4.13 ✅ | **11.4** | Same as the host, for real-backend tests |

⚖ **Q1:** OK to install these directly in WSL (needs `sudo`)? The alternative is
Docker, which adds a large tool and doesn't match shared hosting. I recommend direct installs.

### 3.2 A hosting session together (iteration 1.2) — ✅ largely done 2026-10-10 → 2026-10-16
About an hour in cPanel: switch PHP to 8.5 (if not already done) and set its extensions, create the subdomains and databases, add
the deploy SSH key and the cron entry. I'll guide each step and write it down as we go.

---

## 4. The repository after Phase 1

```
chapelcore/
├── apps/
│   ├── api/laravel/          Laravel 13: thin adapter (routes, middleware, logging, commands)
│   ├── admin/nuxt/           Nuxt 4 SPA: placeholder status page
│   └── web/nuxt/             Nuxt 4 static: "coming soon" placeholder
├── libs/
│   ├── shared/contract/      schemas/*.schema.json · fixtures/ · openapi.json
│   ├── php/native/core/      Composer package chapelcore/core (no framework)
│   └── ts/native/core/       npm package @chapelcore/core (generated types + API client)
├── e2e/                      Playwright tests against the real stack
├── docs/
│   ├── design/DOMAIN-MODEL.md
│   ├── plans/phase-1.md
│   ├── help/setup/           first setup help articles
│   └── runbooks/             github-repository.md · hosting.md · development.md · release.md
├── scripts/                  check-pins.mjs (+ test): every dependency and Action pinned
├── .github/workflows/        ci.yml · deploy.yml
├── .github/actions/setup-js/ shared Node + pnpm setup for CI jobs
├── .github/dependabot.yml
├── .nvmrc · package.json · pnpm-workspace.yaml · pnpm-lock.yaml · turbo.json
├── prettier.config.mjs · .prettierignore · .editorconfig · .gitattributes
├── version.json · CHANGELOG.md · CLAUDE.md · PLAN.md
```

Removed from the working tree (kept in tag `archive/fastapi-0.x`, ✅ D5): the old
`apps/api`, `apps/web`, `apps/admin`, `packages/*`, `DEVELOPER-GUIDE.md`,
`churchos-user-guide.md`, `churchos-cowork-instructions.md`. Kept: `docs/libby-logo-v3.svg`
and `docs/inkscape-logo-instructions.md` (branding assets for Phase 2).

---

## 5. Contract and domain-model work (docs first)

Objects already in DOMAIN-MODEL §4 that Phase 1 implements: `Envelope<T>`,
`ErrorEnvelope`, `ResponseMeta`, `ServedBy`, `VersionInfo`, `HealthStatus`,
`HealthCheck`, plus `DoctorCheck` and `LogEntry` / `ExceptionInfo` (§5.8).

**Additions needed (go into DOMAIN-MODEL before any code):**

| Addition | Detail |
|---|---|
| `ClientErrorReport` | Browser error sent by the admin or site: `app Enum{admin\|web}`, `appVersion`, `path` (no query string), `message`, `stack List<String>` (trimmed, redacted), `userAgent`, `occurredAt`. Max 8 KB. |
| Endpoint | `POST {apiBase}/diagnostics/client-errors` → `202`, no body. Public, rate-limited (10 per minute per IP), size-limited. |
| Reference code format | 8 characters from Crockford Base32 (no I, L, O, U), always written as `XXXX-XXXX`, hyphen included. The same text in `meta.requestId`, the `X-Request-Id` header and the log entry. |
| Phase 1 error codes | `NOT_FOUND`, `METHOD_NOT_ALLOWED`, `VALIDATION_FAILED`, `PAYLOAD_TOO_LARGE`, `RATE_LIMITED`, `INTERNAL_ERROR`, `SERVICE_UNAVAILABLE` |
| Health HTTP status | `200` when `ok` or `degraded`, `503` when `down` (the deploy check relies on this) |
| Health check names | `database`, `scheduler` (cron heartbeat seen in the last 5 minutes), `storage` (writable) |
| Public health vs doctor | **Adjustment to D14:** the public `/health` only says *which* checks pass, with generic messages. The detailed doctor report (PHP extensions, paths, config) is **CLI-only** in Phase 1 and moves into the admin (signed-in) in Phase 3. Publishing server details on a public URL would help attackers. |

---

## 6. What gets built (by layer)

### 6.1 `libs/shared/contract`
- JSON Schemas (draft 2020-12) for each object in §5, with `additionalProperties: false`
  and every field `required` (always-present nullables, DOMAIN-MODEL rule 2)
- `fixtures/valid/*.json` and `fixtures/invalid/*.json` per schema
- `openapi.json` (OpenAPI 3.1) for the two endpoints, referencing the schemas
- `redaction/*.json`: shared redaction cases (DOMAIN-MODEL §5.8), checked against
  `schemas/redaction-case.schema.json`; every core library runs them

### 6.2 `libs/php/native/core` (plain PHP 8.4+, no framework)
- `final readonly` value objects for every §5 object, with invariants checked in constructors
- `Serializer`: domain → transport JSON, camelCase, nulls always present (the only
  place field names are converted)
- `HealthService` port + aggregation rule (overall status = worst check)
- `HealthCheck` port, so each adapter supplies its own checks
- `RequestId` generator and formatter (Crockford Base32)
- `Redactor`: the redaction rules in DOMAIN-MODEL §5.8, applied to log messages, log
  context and exception messages. Pure function; runs every shared case in
  `libs/shared/contract/redaction`.
- `JsonLogger`: a PSR-3 logger that writes one redacted `LogEntry` per call, as a JSON
  line, to a daily file `chapelcore-YYYY-MM-DD.log` (UTC date). A failed write never
  throws. `LogEntryFactory` redacts and converts whatever a log context holds
  (exceptions, dates, objects).
- `Doctor`: runs the nine Phase 1 checks listed in DOMAIN-MODEL §5.8 and returns results
  with plain-language fixes. The checks are small classes here; the Laravel adapter only
  supplies values (config, DB connection, certificate expiry date).
- `ClientErrorReportParser`: reads the body of `POST {apiBase}/diagnostics/client-errors`.
  Over 8 KB → `PAYLOAD_TOO_LARGE` before decoding; otherwise `VALIDATION_FAILED` with
  messages per field. Laravel only sends the `ApiError` it carries.
- Requires `chapelcore/contract` as a local Composer path package; its tests validate
  every serialized object against the contract schemas.
- `VersionReader`: reads `version.json`

### 6.3 `apps/api/laravel` (thin adapter)
- Laravel 13 skeleton, **trimmed**: remove Sail (Docker), Tinker, Pail, Faker, Mockery
  and Collision. Fewer dependencies, nothing Phase 1 needs.
- Routes: `GET /health`, `POST /diagnostics/client-errors`. Route prefix from config,
  so the **same build** works as `api.libbynaz.org/health` or `example.org/api/health` (D16).
- Middleware: request ID, CORS allow-list from config (no wildcards), JSON-only responses
- Exception handler: every error becomes an `ErrorEnvelope` with a reference code;
  never a stack trace or HTML error page, even with debug on
- Logging: a custom log channel using the core `JsonLogger` (daily JSON log files, every
  entry redacted, the request ID added)
- `php artisan chapelcore:doctor` (exit code ≠ 0 on failure, used by the deploy) and
  `chapelcore:heartbeat` (run by cron every minute; records the time for the `scheduler` check)
- MariaDB connection forced to `utf8mb4` / `utf8mb4_unicode_ci`
- Rate limiting via Laravel's built-in limiter on the database cache store (no Redis on shared hosting)

### 6.4 `libs/ts/native/core`
- TypeScript types **generated** from the schemas. CI fails if regenerating changes
  them, so types can never drift from the contract.
- `ApiClient`: `fetch` with timeout, JSON parsing, `ErrorEnvelope` handling, and a
  `FetchResult<T>` (`ok` with data, or `error` with code + reference). No framework imports.

### 6.5 `apps/admin/nuxt` and `apps/web/nuxt`
- **Admin** (static SPA): one page, "ChapelCore Admin", showing version, API status and
  each health check, with clear wording for "can't reach the API" and the reference
  code when there's an error. Reports its own browser errors to the API.
- **Web** (static): "Libby Church of the Nazarene — new website coming soon" + version.
  Deployed to **test only**.
- Both read `version.json` at build time and the API base URL from build config.
- Plain styling only; the theme arrives in Phase 2.

---

## 7. Tests (written first, in each iteration)

| Suite | Runs | Examples |
|---|---|---|
| **Contract self-tests** | TS (ajv) and PHP (opis) | every valid fixture passes, every invalid one fails, **in both languages** |
| **PHP core unit** | PHPUnit | value-object invariants; serializer camelCase + always-present nulls; worst-status rule; request-ID format; redactor strips a password, a token, an email; doctor gives a fix for a missing extension |
| **Laravel feature** | PHPUnit + real MariaDB | `/health` 200 and schema-valid; DB down → 503 `down`; unknown route → `NOT_FOUND` envelope with reference; thrown exception → `INTERNAL_ERROR`, no stack trace, log entry has the same reference and no secrets; client error accepted (202), oversized → `PAYLOAD_TOO_LARGE`, 11th in a minute → `RATE_LIMITED`; both route layouts; CORS allows only the configured origin; tables are utf8mb4 |
| **TS unit** | Vitest | API client: timeout, non-JSON reply, error envelope, success |
| **Component** | Vitest + Vue Test Utils | admin page shows ok / degraded / down / unreachable states |
| **Contract suite** | against the **running** API | every real response validates against its schema |
| **End-to-end** | Playwright, real admin build + real Laravel + real MariaDB | admin shows version and "All systems OK"; with the DB stopped it shows the database problem; a forced error shows a reference code that appears in the log file |
| **Deploy smoke** | after each deploy | `/health` returns 200 and the expected version, or the deploy rolls back |

---

## 8. Iterations

Each iteration is its own branch from `dev` (`feature/phase-1-N-description`) and its
own PR, so reviews stay small. In each: I show the tests, you approve, I write them and
show them failing; then I show the code, you approve, I write it and the tests pass.

| # | Iteration | Delivers | Needs you for |
|---|---|---|---|
| 1.1 | **Repo reset & toolchain** | Old tree removed; workspace skeleton; pinned Node/pnpm/PHP tool versions; new `ci.yml` with stable job names; `dependabot.yml`; GitHub P2–P6 | Approving dependencies (§9); updating rulesets to new check names |
| 1.2 | **Hosting spike + setup** | Prove the risky hosting assumptions on **test** with a throwaway `hello.php` (§11), then do the real setup; **automatic Let's Encrypt certificates for all five hostnames, including www (R10)**; `hosting.md` runbook; setup help articles *(moved to 1.9, owner 2026-10-19)* | The cPanel session |
| 1.3 | **Contract** | Schemas, fixtures, OpenAPI; self-tests in both languages. *Built 2026-10-21: 18 schemas, 103 fixtures, `openapi.json`, 223 self-tests each in TypeScript and PHP; `composer audit` joined CI here, not in 1.4* | Review |
| 1.4 | **PHP core** | Everything in §6.2, in eight checkpoints (§8.1). *Built 2026-10-30: 414 tests on PHP 8.4 and 8.5, PHPStan at max level; the core's Pint, PHPStan, PHPUnit and `composer audit` run in CI* | Review |
| 1.5 | **Laravel API** | Everything in §6.3 | Review |
| 1.6 | **TS core** | Generated types + API client | Review |
| 1.7 | **Admin + web placeholders** | §6.5 | Review |
| 1.8 | **Contract suite + e2e in CI** | Playwright against the real stack | Review |
| 1.9 | **Deploy pipeline** | `deploy.yml`; GitHub environments `test` / `production` with Approve gate (P1); rollback demonstrated on test; the setup help articles from §13 | Approving the first production deploy |
| 1.10 | **Release 0.1.0** | `dev` → `staging` (verify on test) → `main` (Approve) → tag `v0.1.0` | UI check on test, then Approve |


### 8.1 Iteration 1.4 checkpoints

Each checkpoint is one commit: its tests are shown, written and seen failing, then its
code is shown and written (CLAUDE.md step 4).

| # | Checkpoint | See it yourself |
|---|---|---|
| 0 | Docs PR: DOMAIN-MODEL 0.4, redaction cases, this plan | Read the PR |
| 1 | Skeleton: `composer.json` (contract as a pinned path package), configs, `ContractValidator`, CI | `composer test` → "No tests executed!"; PHPStan runs |
| 2 | Model + Serializer | A one-liner prints a health response as JSON |
| 3 | Request IDs, clock, version reader, health service and probes | Five reference codes; status changes as one probe fails |
| 4 | **4a:** Redactor, ExceptionInfo, LogEntry · **4b:** UUIDs, LogEntryFactory, JsonLogger | A redacted log line written to a file you `cat` |
| 5 | **5a:** Doctor, report, PHP checks · **5b:** the other six checks | A doctor report with a missing extension and its fix |
| 6 | ClientErrorReport parser | An oversized report gives field messages |
| 7 | Docs, CHANGELOG, commands; PR | Full CI-equivalent run |
| 8 | Redaction rule 2b: passwords in URLs | A connection error logged with its password hidden |

---

## 9. Dependencies for approval

All open source and actively maintained; versions are today's latest and will be
pinned exactly. **Nothing is added until you approve it.**

### PHP (Composer) — ✅ approved by owner 2026-10-09
| Package | Version | Used for | Why not built-in | Licence |
|---|---|---|---|---|
| `laravel/framework` (+ its own dependencies) | 13.35 | the API framework | the agreed stack (D-series) | MIT |
| `psr/log` | 3.0.2 | standard logging interface for the core | it *is* the PHP standard (PSR-3); tiny; Laravel already includes it | MIT |
| `phpunit/phpunit` *(dev)* | 13.4 | PHP tests | PHP has no built-in test runner | BSD-3 |
| `laravel/pint` *(dev)* | 1.32 | formatter (PER and Laravel presets) | enforces the coding standards | MIT |
| `phpstan/phpstan` *(dev)* | 2.3 | static analysis | catches type errors before runtime | MIT |
| `larastan/larastan` *(dev)* | 3.13 | PHPStan rules that understand Laravel | PHPStan alone misreads Laravel's magic | MIT |
| `opis/json-schema` *(dev)* | 2.6 | validate PHP output against the contract in tests | PHP has no JSON Schema validator | Apache-2.0 |

### JavaScript (npm) — ✅ approved by owner 2026-10-09
| Package | Version | Used for | Why not built-in | Licence |
|---|---|---|---|---|
| `nuxt` + `vue` | **latest stable at install time** (today Nuxt 4.6.1 / Vue 3.5.43) | admin and site | the agreed stack | MIT |
| `turbo` | 2.11 | runs tasks across the monorepo | kept from the old setup (owner request) | MIT |
| `typescript` | **6.0.3**. TS 7 is not usable yet (R6, ✅ Q2) | type checking | — | Apache-2.0 |
| `vue-tsc` *(dev)* | 3.3 | type-checks `.vue` files | `tsc` can't read `.vue` files | MIT |
| `vitest` *(dev)* | 5.0 | JS unit and component tests | Node's test runner can't handle Vue components | MIT |
| `@vue/test-utils` *(dev)* | 2.5 | mount Vue components in tests | official Vue tool | MIT |
| `jsdom` *(dev)* | 30.1 | browser environment for component tests | Node has no DOM. Chosen over `happy-dom`, which had critical security alerts | MIT |
| `eslint`, `@nuxt/eslint`, `typescript-eslint`, `eslint-plugin-vue` *(dev)* | 10.12 / 1.17 / 8.71 / 10.11 | linting per the coding standards | — | MIT |
| `prettier` *(dev)* | 3.9 | formatting | — | MIT |
| `@playwright/test` *(dev)* | 1.64 | end-to-end tests in a real browser | ✅ D6 | Apache-2.0 |
| `ajv` + `ajv-formats` *(dev)* | 8.20 / 3.0 | validate JSON against the contract in TS tests | JS has no JSON Schema validator | MIT |
| `json-schema-to-typescript` *(dev)* | 16.0 | generate TS types from the schemas | keeps types and contract identical | MIT |
| `@types/node` *(dev)* — ✅ approved 2026-10-21 | 24.19 (matches Node 24) | type descriptions for Node's built-in modules (`node:fs`, `node:path`) used by the tests; no runnable code | TypeScript doesn't know Node's built-ins without it | MIT |

### GitHub Actions (pinned to exact commit SHAs, with the version in a comment) — ✅ approved 2026-10-09
`actions/checkout`, `actions/setup-node`, `pnpm/action-setup`, `actions/upload-artifact`
(Playwright reports) — official GitHub/pnpm actions. `shivammathur/setup-php` (MIT) —
the standard way to install PHP 8.5/8.4 + extensions in CI. **No third-party deploy action:**
deploys use the runner's own `ssh`, `scp` and `tar`.

### Things deliberately *not* added
Docker/Sail · Redis · a static-file server (CI serves the built admin with PHP's
built-in server) · Mockery and Faker (PHPUnit's own test doubles suffice for now) ·
Tailwind and UI libraries (Phase 2) · any deploy service.

---

## 10. CI and deploy design

**CI jobs** (these exact names become the required checks in all three rulesets):
`lint`, `static-analysis`, `test-php (8.5)`, `test-php (8.4)`, `test-js`, `contract`, `e2e`,
`contrast` (placeholder until Phase 2), `audit`.
CI runs on **PHP 8.5** + MariaDB 11.4 to mirror the host, plus `test-php` on **PHP 8.4** (minimum supported). `audit` runs `composer audit` and
`pnpm audit`; findings fail the build (CLAUDE.md security rule).

**Server layout (same for test and prod):**
```
~/chapelcore/prod/
├── releases/<commit>/     api/ (Laravel + vendor) · admin/ (static) · web/ (static)
├── shared/.env            created once by hand (runbook); never in git
├── shared/storage/        logs, cache — linked into each release
├── shared/.well-known/    certificate-validation files — linked into every release's docroots
│                          (web/.well-known, api/public/.well-known) so renewals survive deploys
└── current → releases/<commit>
api.libbynaz.org      document root → ~/chapelcore/prod/current/api/public
libbynaz.org/admin    → ~/chapelcore/prod/current/admin      (the rest of public_html untouched)
test.libbynaz.org     document root → ~/chapelcore/test/current/web  (+ /admin)
api.test.libbynaz.org document root → ~/chapelcore/test/current/api/public
cron (each env):  * * * * *  php …/current/api/artisan chapelcore:heartbeat (schedule:run later)
```

**Deploy steps** (`deploy.yml`): build in CI (Composer without dev packages, Nuxt
static builds with that environment's API URL) → upload one archive over SSH →
unpack into `releases/<commit>` → link `shared/` → `chapelcore:doctor` → `migrate --force`
→ switch `current` (atomic rename) → **poll** `/health` for up to 3 minutes until it
reports the new version (PHP on the host caches the old symlink target for up to
2 minutes) → if it never does, switch `current` back, poll until the old version answers,
and fail the job. Keep the last 5 releases. Files are written with `755`/`644` permissions.

**Migrations must be backward compatible** with the previous release
(add first, remove later), so switching code back after a failed deploy is always safe.

**GitHub environments** (P1): `test` (deploys from `staging` only) and `production`
(deploys from `main` only, **waits for your Approve**). Each holds its own SSH host,
port, user, key and known-hosts entry.

---

## 11. Risks and how we handle them

| # | Risk | Mitigation |
|---|---|---|
| R1 | cPanel may not allow a subdomain's document root to be a **symlink** (needed for atomic `current` switching) | ✅ **Spike passed 2026-10-11.** Finding: after a switch, PHP keeps serving the old release for up to ~2 min (realpath cache, not configurable on shared hosting) → deploy and rollback poll `/health` for the expected version; migrations stay backward compatible |
| R2 | The **command-line PHP** on the server may not be the 8.5 chosen in the PHP selector | ✅ **Spike passed 2026-10-11:** after setting PHP Tweaks → Account settings → 8.5, both command-line and web PHP are 8.5.11, with all 19 extensions and Argon2id |
| R3 | Server is **LiteSpeed**, not Apache | ✅ **Spike passed 2026-10-11:** Laravel-style `.htaccess` rewrites work; `/admin` through a symlink works |
| R4 | SSH from GitHub Actions blocked or on an unusual port | ✅ **Spike passed 2026-10-16:** a GitHub-hosted runner verified the server's pinned ED25519 fingerprint, logged in with the test deploy key on port **21098** (shell enabled in cPanel → Manage Shell), read PHP 8.5.11 and wrote a file. Spike branch, secrets and file removed afterwards |
| R5 | Argon2id missing from the host's PHP build | Doctor check; Phase 3 falls back to bcrypt as already planned |
| R6 | **TypeScript 7** (latest, a rewrite) may not yet work with `vue-tsc`/Nuxt | ✅ **Resolved 2026-10-18.** TS 7.0.2 breaks `vue-tsc` 3.3.13 (`'./lib/tsc' is not defined by "exports"`), and `typescript-eslint` 8.71 accepts only TS `<6.1`. Pinned **6.0.3**, which type-checks `.vue` files correctly; installed in 1.6. Revisit when both support 7 |
| R7 | Putting `/admin` inside the old site's `public_html` | Only a new `admin` folder is added; a deploy test checks the old home page still returns 200 |
| R8 | Shared-hosting limits (process count, memory) during deploy | Builds happen in CI, never on the server; the server only unpacks and switches |
| R9 | A deploy runs while a cron tick runs | Heartbeat is read-only on code; `current` switch is a single atomic rename |
| R10 | **Certificates don't renew.** Namecheap's free StandardSSL is a one-time, one-year promo per domain (support, 2026-10-12). `libbynaz.org` expires **2026-12-21**; `api.*` in Apr 2027; `test.libbynaz.org` has none | **Let's Encrypt, renewed automatically by cron** (Namecheap support confirms it's supported). Namecheap has no built-in tool and won't troubleshoot third-party certificates, so: **`acme.sh` 3.1.6** (✅ D18, approved 2026-10-16; GPL-3.0, runs from `~/.acme.sh`, installs into cPanel via its `cpanel_uapi` hook, renews daily from its own cron line). ✅ **Done 2026-10-16:** all five hostnames on Let's Encrypt (to 2027-01-14), auto-renewing ~mid-December. Doctor check warns 30 days before any certificate expires |
| R11 | The host's SSL tools write validation files into each docroot's `.well-known/`, which would vanish on the next deploy | `shared/.well-known/` linked into every release (§10) |

---

## 12. Plan adjustments to record before coding (docs PR)

1. DOMAIN-MODEL: the §5 additions (`ClientErrorReport`, the endpoint, reference-code
   format, Phase 1 error codes, health HTTP status and check names).
2. PLAN D14 note: public health = summary only; the full doctor report is CLI-only
   until the Phase 3 admin screen.
3. PLAN Phase 1: per-iteration branches (`feature/phase-1-N-*`), the trimmed Laravel
   skeleton, local prerequisites, the required-check names, and "migrations are
   backward compatible".
4. Runbook: rulesets updated to the new check names (P2) as part of 1.1.
5. PHP target **8.5**, minimum supported **8.4**: update CLAUDE.md (Stack, coding standards),
   PLAN.md (host facts, Phase 1 hosting tasks) and DOMAIN-MODEL §8 examples.

## 13. Help articles and runbooks produced

- `docs/help/setup/`: choose PHP 8.5 in cPanel (and its extensions) · turn on SSH (Manage Shell) · set up automatic Let's Encrypt certificates · create an API subdomain · create the
  database · add the ChapelCore cron job · add a deploy SSH key · run the doctor and read
  its output · find an error by its reference code
- `docs/runbooks/`: `development.md` (local setup) · `hosting.md` · `release.md` (how a
  release moves test → prod, and how to roll back)

## 14. Done when (0.1.0)

- [ ] All CI jobs green and required in all three rulesets
- [ ] `staging` merge deploys test automatically; `https://api.test.libbynaz.org/health` → 200, version 0.1.0
- [ ] `main` merge waits for Approve, then deploys; `https://api.libbynaz.org/health` → 200, version 0.1.0
- [ ] `https://libbynaz.org/admin` shows the status page; the old site's home page is unchanged
- [ ] A deliberately failing deploy on test rolls back automatically (shown once)
- [ ] `chapelcore:doctor` passes on test and prod; a broken setting is reported with its fix
- [ ] A forced error on test shows a reference code that finds the log entry, with no secrets in it
- [ ] Contract suite and e2e pass against the real stack in CI
- [ ] Runbooks and setup help articles let someone repeat the setup
- [ ] CHANGELOG updated, `version.json` = 0.1.0, tag `v0.1.0`, Phase 1 marked ✅ in PLAN.md

## 15. Questions for you

- **Q1:** ✅ Install PHP 8.5, MariaDB 11.4 and Node 24 directly in WSL (2026-10-09)
- **Q2:** ✅ Try TypeScript 7; if it doesn't work, pin the newest 6.x (2026-10-09)
- **Q3:** ✅ All of §9 approved: PHP, JavaScript and GitHub Actions. "Latest" means the latest **stable/official** release, never pre-releases (2026-10-09)
- **Q4:** ✅ One branch (multiple commits) and one PR per iteration (2026-10-09)
- **Q5:** ✅ D14 adjustment: public health summary only; doctor details CLI-only until Phase 3 (2026-10-09)
- **Q6:** ✅ Hosting session started 2026-10-09, done step by step with screenshots
