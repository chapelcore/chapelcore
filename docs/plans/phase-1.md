# Phase 1 plan — Foundation: tooling, CI/CD, hosting → 0.1.0

> **Status: APPROVED** by the owner 2026-10-09 · **Updated 2026-10-16** with the hosting
> session's findings (see "Changes since approval" below). No application code yet.
> The §12 adjustments are applied to PLAN.md, CLAUDE.md and DOMAIN-MODEL.md in the same PR.

### Changes since approval (2026-10-09 → 2026-11-21)

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
| §5, §6.4 | Clients trim error reports to 8 KB: stack lines from the end, then the message; the TS core does it | Owner, 2026-10-31 |
| §6.3, §7, §8.2, §9, §11 | Iteration 1.5 detailed: decisions E1–E7, seven checkpoints (§8.2), the MariaDB CI image, risks R12–R15 | Owner, 2026-11-01 |
| §8 | From 1.5 on, the owner types the logic code from a worked example; Claude writes the plumbing and reviews the owner's diff | Owner, 2026-11-01: to understand how the code fits together |
| §6.3, PLAN D20 | Phase 1 tests MariaDB only; PostgreSQL gets its CI job and adapters in Phase 3, before 1.0 | Owner, 2026-11-01 (1.5 E4): "supported" means tested |
| §6.4, §7, §8.3, §9, §11 | Iteration 1.6 detailed: decisions E1–E7, six checkpoints (§8.3), `@eslint/js` approved, risks R16–R18 | Owner, 2026-11-17 |
| §5, DOMAIN-MODEL §5.8 | Clients redact a `ClientErrorReport`, then trim it; characters are code points | Owner, 2026-11-17 (1.6 E4, E5) |
| §5, §6.3, §7, DOMAIN-MODEL §4, §5.8 | New error code `BAD_REQUEST`; every other 4xx gets it and 413 always gets `PAYLOAD_TOO_LARGE`; failed queries log their SQL without the bound values, and rule 2h removes a database's own error message; redaction gives `[unredactable]` when a pattern can't be applied and keeps UUIDs whole | Owner, 2026-11-18: code and plan review findings |
| §6.4, §7, §11, DOMAIN-MODEL §5.8 | Checkpoint 5: `reportClientError()` answers with its own `SendResult`, without cookies and with `keepalive`; the caller passes `occurredAt`; texts are capped at twice their limit before redacting; a path is redacted one segment at a time (PR #110); the user agent is redacted too; risk R19 | Owner, 2026-11-20 |
| §6.4, §6.5, §7, §8.4, §9, §10, §11 | Iteration 1.7 detailed: decisions E1–E8, seven checkpoints (§8.4), `createErrorReporter()` in the TS core, `@nuxt/test-utils` and three audit overrides approved, Node 24.15 or later, risks R20–R25 | Owner, 2026-11-21 |
| §10, CLAUDE.md step 8 | Until the Phase 2 theme, the apps use only allow-listed system colors, and the `contrast` job fails on any color of their own (1.7 E6) | Owner, 2026-11-21 |

---

## 1. What you'll be able to see when Phase 1 is done

- `https://api.libbynaz.org/health` and `https://api.test.libbynaz.org/health` answer
  with the version (0.1.0 "pre-release"), the database status, and the cron heartbeat.
- `https://libbynaz.org/admin` shows a simple ChapelCore Admin page reading that
  status from the API. The rest of libbynaz.org is **unchanged** (the old site stays).
- `https://test.libbynaz.org` shows a "coming soon" placeholder with the church's name (§6.5 E5) and the admin.
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
| `ClientErrorReport` | Browser error sent by the admin or site: `app Enum{admin\|web}`, `appVersion`, `path` (no query string), `message`, `stack List<String>` (trimmed, redacted), `userAgent`, `occurredAt`. Max 8 KB; the client trims (DOMAIN-MODEL §5.8). |
| Endpoint | `POST {apiBase}/diagnostics/client-errors` → `202`, no body. Public, rate-limited (10 per minute per IP), size-limited. |
| Reference code format | 8 characters from Crockford Base32 (no I, L, O, U), always written as `XXXX-XXXX`, hyphen included. The same text in `meta.requestId`, the `X-Request-Id` header and the log entry. |
| Phase 1 error codes | `BAD_REQUEST`, `NOT_FOUND`, `METHOD_NOT_ALLOWED`, `VALIDATION_FAILED`, `PAYLOAD_TOO_LARGE`, `RATE_LIMITED`, `INTERNAL_ERROR`, `SERVICE_UNAVAILABLE` |
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
Laravel is wiring only: every rule lives in the PHP core (CLAUDE.md step 6).

- **Skeleton:** `laravel/framework` 13.42.0 from the official `laravel/laravel` 13.11.0
  template, **trimmed** to an API: no Sail (Docker), Tinker, Pail, Pao, Faker, Mockery or
  Collision; no Vite, Tailwind, `package.json`, views or web routes; no users, jobs or
  sessions migrations. Fewer dependencies, nothing Phase 1 needs.
- **Our packages:** requires `chapelcore/core` and, for tests, `chapelcore/contract` as
  Composer path packages, pinned through the path repository's `versions` option as the
  core pins the contract. Locally they are symlinks; the deploy build (1.9) copies them
  with `COMPOSER_MIRROR_PATH_REPOS=1` (R14).
- **Routes:** `GET /health`, `POST /diagnostics/client-errors`. The prefix comes from
  `CHAPELCORE_API_PREFIX`: empty gives `api.libbynaz.org/health`, `api` gives
  `example.org/api/health`, so the **same build** works in both layouts (D16). No sessions
  or cookies in Phase 1; the routes are stateless.
- **Request ID:** middleware gives every request a new reference code from the core's
  `RequestIdGenerator`, returned as `X-Request-Id` and `meta.requestId` and added to every
  log line of that request. An incoming `X-Request-Id` is ignored, so callers can't plant
  codes in our logs (E3).
- **Errors:** every error becomes an `ErrorEnvelope` with the reference code: 404
  `NOT_FOUND`, 405 `METHOD_NOT_ALLOWED`, 413 `PAYLOAD_TOO_LARGE` (also a body over PHP's
  `post_max_size`), 422 `VALIDATION_FAILED`, 429 `RATE_LIMITED` (with `Retry-After`), 500
  `INTERNAL_ERROR`, and 503 `SERVICE_UNAVAILABLE` in maintenance mode. Any other 4xx is 400
  `BAD_REQUEST` (e.g. a malformed `Host` header); any other 5xx is `INTERNAL_ERROR`.
  A failed query is logged with `?` in place of its bound values
  (`mask_bindings_in_exception_messages`, on every connection), and the database's own
  message after its SQLSTATE and error code is redacted (DOMAIN-MODEL §5.8, rule 2h). Always JSON, never a stack trace or HTML error
  page, even with debug on (R13).
- **Logging:** the default log channel is a custom channel built on the core's
  `JsonLogger`: daily JSON files in `storage/logs`, every entry redacted, the request's
  reference code added. If Laravel's log manager needs a Monolog logger, a small Monolog
  handler passes each record to `JsonLogger` instead (R12).
- **Health:** `/health` runs the core's `HealthService` with three Laravel adapters: a
  database ping on the default connection, the heartbeat file, and `StorageProbe` on
  `storage/`. A `down` result is HTTP 503 with the same `Envelope<HealthStatus>`, not an
  error envelope.
- **Heartbeat:** `php artisan chapelcore:heartbeat` (cron, every minute) writes the time to
  a file in `storage/`, not the database, so a database outage doesn't also fail the
  `scheduler` check (E1).
- **Client errors:** the raw body goes to the core's `ClientErrorReportParser`. An accepted
  report is logged on the `diagnostics.client` channel at `warning` and answered 202. The
  rate limit allows 10 reports per minute per client IP, using Laravel's built-in limiter on
  the database cache store (no Redis on shared hosting); its `cache` table is the app's first
  migration. Trusted proxies come from `CHAPELCORE_TRUSTED_PROXIES` (empty by default), so
  the limit is keyed on the real client IP.
- **CORS:** Laravel's built-in `HandleCors`, with exact origins from
  `CHAPELCORE_CORS_ORIGINS` (comma-separated), credentials allowed, no wildcards. Empty in
  the same-domain layout.
- **Environment:** `CHAPELCORE_ENV=dev|test|production` (default `dev`) names the
  environment for the doctor; Laravel's own `APP_ENV` stays Laravel's (E2).
- **Doctor:** `php artisan chapelcore:doctor` runs the core's nine Phase 1 checks with
  Laravel adapters (configuration, the database's and tables' character sets, certificate
  expiry dates read from the hosts in `CHAPELCORE_TLS_HOSTS`). Plain-text output; exit code 1
  when any check is `down`, which the deploy relies on. A `--json` option can come with the
  Phase 3 diagnostics screen (E5).
- **Database:** the MariaDB connection is forced to `utf8mb4` / `utf8mb4_unicode_ci`.
  Phase 1 tests MariaDB only; PostgreSQL support and its CI job come in Phase 3 (E4, PLAN D20).
- **Version:** read from `version.json` through the core's `VersionReader`; the path comes
  from config and defaults to the repository root.
- **Checks:** Pint with the Laravel preset; PHPStan at max level with Larastan, the same bar
  as the core (E6).

### 6.4 `libs/ts/native/core`
The npm package `@chapelcore/core`: plain TypeScript (strict, ESM), no framework and no
run-time dependencies. `tsc` builds it to `dist/` (JavaScript plus `.d.ts`), and its
`exports` point there, so any tool can use it (E2).

- **Generated types:** `json-schema-to-typescript` writes one interface per contract schema
  to `src/generated/`. The generate script then writes `src/types.ts`, which exports each
  one wrapped in `DeepReadonly<…>`, so code that changes a response doesn't compile
  (universal rule 8, E1). Nothing in either file is written by hand. A test fails when
  regenerating would change them, and every valid fixture is type-checked against its type.
- **`ApiClient`:** `fetch` with a timeout (10 s by default, `AbortSignal.timeout`), JSON
  parsing and `ErrorEnvelope` handling. A call that reads data sends cookies
  (`credentials: 'include'`, E6) and returns a `FetchResult<T>`; no call ever throws.
  A `FetchResult` is `ok` with `data` and `meta`, or `error` with
  a kind (`api`, `network`, `timeout`, `invalid-response`), the HTTP status (`null` when no
  reply arrived), the error code, message and fields, and the reference code. Each endpoint
  lists the HTTP statuses that carry data. `getHealth()` accepts 200 and 503, because a 503
  is an `Envelope<HealthStatus>` reporting `down` (E3). An `ErrorEnvelope` is an `api` error
  at any status (maintenance mode answers 503 with one); data at another status, or any
  other body, is `invalid-response`. The timeout also covers reading the body. The
  reference code is `meta.requestId`, else a valid `X-Request-Id` header, else `null`. The
  client checks only the shape (`data` + `meta`, or `error` + `meta` with a text code and
  message and a list of messages per field). Unknown fields, enum values and error codes
  are tolerated (universal rules 5 and 6); ajv stays in the tests.
  `reportClientError()` posts a report and answers with a `SendResult`: `ok` with the
  reference code (a 202 has no body, so it comes from the header), or the same `error` as a
  `FetchResult`. It sends no cookies (`credentials: 'omit'`): the endpoint is public and has
  no session.
- **`redactText()`, `redactPath()` and `redactContext()`:** the DOMAIN-MODEL §5.8 rules
  (text, path and context), pure functions that run every shared case in
  `libs/shared/contract/redaction`.
- **`clientErrorReport()`:** builds a report from whatever was thrown and what the caller
  supplies (app, version, path, user agent and `occurredAt`), so it uses no browser
  globals. It follows §5.8: it builds the fields, cuts each text to twice its limit, redacts
  `message`, the stack lines and the user agent, and `path` one segment at a time (E4), then
  trims to the field limits and 8 KB in the §5.8 order. It counts code points (E5) and
  measures UTF-8 bytes with `TextEncoder`. It returns `null`, and never throws, when the
  report can't be made valid or can't fit. `reportClientError()` sends it with
  `keepalive: true`, so a report sent as the page unloads still arrives.
- **`createErrorReporter()`** (iteration 1.7, 1.7 E4): what both apps share about reporting
  browser errors. The caller passes the client, the app, its version, an event target (the
  page's `window`), the clock, the path and the user agent, so the core still uses no browser
  globals. It listens for `error` and `unhandledrejection`, and `report()` takes errors from
  elsewhere (Vue's hooks). It sends at most 5 reports per page load (the API allows 10 a
  minute); the same error (message and first stack line) only once, and a repeat answers with
  the first one's reference code; it never throws, even when the caller's callback does; a
  failed send is not itself reported. An `error` event without an error object (a
  cross-origin "Script error.") uses the event's message. Events are read by their shape, so
  it runs where `ErrorEvent` doesn't exist. `stop()` removes the listeners.
- **Lint:** one root `eslint.config.mjs` (`@eslint/js` recommended plus typescript-eslint
  strict and type-aware). The core and the contract's TypeScript tests each get a `lint`
  script (E7).

### 6.5 `apps/admin/nuxt` and `apps/web/nuxt`
Two Nuxt 4 apps, built static by `nuxt generate` to `.output/public/`, with no Node server on
the host (E1). Plain styling in the browser's own system colors; the theme arrives in Phase 2
(E6). Neither app holds business rules: the API client, redaction, report building and the
error reporter are in `@chapelcore/core` (CLAUDE.md step 6).

- **Packages:** `@chapelcore/admin` and `@chapelcore/web`, each depending on `@chapelcore/core`
  (`workspace:*`), `nuxt` and `vue`. Scripts: `dev`, `build` (`nuxt generate`), `type-check`
  (`nuxt typecheck`), `lint`, `test`; `postinstall: nuxt prepare` writes `.nuxt/`. Tests live
  in `test/nuxt/` (mounted in Nuxt) and `test/unit/` (plain TypeScript), where Nuxt's own
  tsconfigs find them.
- **Build settings** (E2): `CHAPELCORE_API_BASE` (`{apiBase}`) is required by `nuxt generate`,
  which stops with a clear message when it's unset; `dev`, `nuxt prepare`, the type check and
  the tests use `http://localhost:8000`. `CHAPELCORE_SITE_NAME` (web only) is optional, default
  `ChapelCore` (E5). A relative base such as `/api` (D16 same-origin layout) is resolved
  against the page's origin at run time, since `ApiClient` takes an absolute URL.
  `nuxt.config.ts` reads `version.json` (repo root) for the version and codename; nothing is
  hardcoded (universal rule 12). Each app's `.env` is gitignored, with a tracked
  `.env.example`. turbo.json lists both settings in the `build` task's `env` (turbo passes
  no other variables) and `.env` and `version.json` in its `inputs`, so a changed value is a
  cache miss; `lint` and `dev` wait for the core's build (`dependsOn: ["^build"]`). CI's
  `test-js` job runs `pnpm build` with `CHAPELCORE_API_BASE=http://localhost:8000`.
- **Admin** (`ssr: false`, `app.baseURL: '/admin/'`): one page, "ChapelCore Admin" (E3).
  - On load it calls `getHealth()`. A summary line says the result in words; it is a
    `role="status"` region that is always on the page, so a screen reader announces each new
    result, and only the summary. Below it: each check in the API's order (universal rule 7)
    with its status and message; the admin's own version (from `version.json`) and the
    API's, with the codename when there is one, and "The admin and the API are on different
    versions" when they differ; the API address the admin uses, so a wrong build setting is
    visible.
  - States: **checking** ("Checking the API…"); **ok** ("All systems OK"); **degraded**
    ("Working, with warnings"); **down** ("The API reports a problem"); **unreachable**
    (network or timeout: "Can't reach the API", with what to check); **error** (an `api` or
    `invalid-response` error: its safe message and the reference code when there is one). An
    unknown status, overall or for one check, shows neutrally as "Unknown status" (universal
    rule 5). A reply whose `data` isn't shaped like a `HealthStatus` (the client checks only
    the envelope) shows the `invalid-response` view instead of failing. Status is never shown
    by color: words, with ✓ / ! / ✕ hidden from screen readers. A "Check again" button repeats
    the call; nothing polls by itself.
  - What the page shows for a `FetchResult<HealthStatus>` comes from `healthView()`, a plain
    TypeScript function in `app/utils/health-view.ts` with no Vue imports, so every state is
    tested without mounting anything.
- **Error reporting** (both apps, E4): a client plugin creates one `createErrorReporter()`
  (§6.4) with the page's `window` and Nuxt's `vue:error` and `app:error` hooks, and builds
  each report from `location.pathname`, `navigator.userAgent` and the current time. In the
  admin, an unexpected error brings a notice: "Something went wrong. Reference: XXXX-XXXX";
  "Something went wrong. (It could not be reported.)" when the report wasn't sent; "(Not
  reported: too many errors on this page.)" over the limit. A "Test error reporting" button
  throws "Test error N" (N counts the clicks), so each click is a new report until the limit;
  this proves the chain behind the done criterion "a forced error shows a reference code that
  finds the log entry". Both apps have their own `app/error.vue` for fatal errors (it shows
  the reference code when the report was sent, in system colors; Nuxt's default error pages
  bring colors of their own). Otherwise the web reports silently.
- **Web** (`ssr: true`, prerendered): one page, "*site name* — new website coming soon", with
  the version in the footer. Deployed to **test only** (1.9). The text is in the HTML, so it
  shows without JavaScript.
- **Both:** `lang="en"`, a title and `<meta name="robots" content="noindex">` in
  `nuxt.config.ts` (`app.head`), so they are in the static HTML (the admin is never indexed;
  the web placeholder is only on test, where 1.9 also sends `X-Robots-Tag`); telemetry and
  Nuxt DevTools off; no web fonts.
- **Colors** (E6): only these CSS system colors, which browsers keep readable in light and
  dark mode: `Canvas`, `CanvasText`, `LinkText`, `VisitedText`, `ButtonFace`, `ButtonText`,
  `ButtonBorder`, `Field`, `FieldText` (plus `currentColor`, `transparent`, `inherit`), with
  `color-scheme: light dark`. `GrayText` (about 3.9:1 on white), `Mark`, `Highlight`,
  `ActiveText` and the rest are not allowed. `scripts/check-colors.mjs` fails when a `<style>`
  block or `style` attribute in a `.vue` file, or a `.css` or `.ts` file under `apps/*/nuxt`,
  has any other color (hex, `rgb()`, `hsl()`, `oklch()`, named colors). The `contrast` CI job
  runs it until Phase 2 replaces it with the token-pair contrast gate.
- **Lint** (E7): each app has its own `eslint.config.mjs`: `@nuxt/eslint`'s `withNuxt()` (Vue
  style guide rules from eslint-plugin-vue) plus the *rules* of typescript-eslint's strict
  type-aware set and the root's `restrict-template-expressions` override, with the project
  service reading `.vue` files. Adding typescript-eslint's whole configs would replace the
  Vue parser. ESLint 10 uses the nearest config file, so the apps' configs replace the root
  one in their folders.
- **Local development:** the web on `http://localhost:3000`, the admin on
  `http://localhost:3001/admin/`, the API on `localhost:8000`, whose local `.env` sets
  `CHAPELCORE_CORS_ORIGINS=http://localhost:3000,http://localhost:3001`. Build the core once
  before the first `dev` (runbook).

**Decisions (owner, 2026-11-21):**
- **E1** Static output: the admin is an SPA, the web is prerendered; both by `nuxt generate`.
- **E2** Build settings are environment variables read in `nuxt.config.ts`. The API address is
  required only by `nuxt generate`, so a production build can never quietly point at
  localhost, while install, lint, type check and tests run without it. Nuxt's `NUXT_PUBLIC_*`
  variables were not used: a static build has no run time to override them.
- **E3** The status wording lives in the admin (`healthView()`): it is text for this one page,
  not a domain rule. The Phase 3 Diagnostics screen can move it to a shared library.
- **E4** The error reporter lives in `@chapelcore/core`: the limit, the duplicate rule and "never
  report a failed report" are rules both apps share.
- **E5** The web text uses `CHAPELCORE_SITE_NAME`, default "ChapelCore"; Libby's test build sets
  "Libby Church of the Nazarene" (1.9). This settles the difference between §1 and the
  earlier §6.5 summary.
- **E6** Contrast until Phase 2: allow-listed system colors only, checked by `check-colors` in
  the `contrast` job (CLAUDE.md step 8 note).
- **E7** One ESLint config per app (above).
- **E8** Unit tests for `healthView()` and the reporter; component tests with `@nuxt/test-utils`
  in jsdom, with `useApiClient()` replaced by a stub, since the client is tested against a
  real server in the core. The real backend is checked in the "see it yourself" steps of
  checkpoints 3 and 4 and by 1.8's Playwright suite, which also checks the web page without
  JavaScript.

---

## 7. Tests (written first, in each iteration)

| Suite | Runs | Examples |
|---|---|---|
| **Contract self-tests** | TS (ajv) and PHP (opis) | every valid fixture passes, every invalid one fails, **in both languages** |
| **PHP core unit** | PHPUnit | value-object invariants; serializer camelCase + always-present nulls; worst-status rule; request-ID format; redactor strips a password, a token, an email, gives `[unredactable]` when a pattern can't be applied, keeps standard UUIDs whole and removes a database's own error message (rule 2h); doctor gives a fix for a missing extension |
| **Laravel feature** | PHPUnit + real MariaDB; every response checked against its schema | `/health` 200 and schema-valid; DB down → 503 `down`; stale heartbeat → `degraded`; unknown route → `NOT_FOUND` envelope with reference; wrong method → `METHOD_NOT_ALLOWED`; maintenance mode → `SERVICE_UNAVAILABLE`; thrown exception → `INTERNAL_ERROR`, no stack trace, log entry has the same reference and no secrets; client error accepted (202) and logged, oversized → `PAYLOAD_TOO_LARGE`, invalid → `VALIDATION_FAILED` with field messages, 11th in a minute → `RATE_LIMITED`; a body over `post_max_size` → `PAYLOAD_TOO_LARGE`; a malformed `Host` → `BAD_REQUEST`; 401/403/419/418 → 400 `BAD_REQUEST` (provisional until Phase 3); a 4xx is not logged; a failed query's log line has no bound values, and a duplicate entry or wrong-type value never reaches the log; every connection masks query values; both route layouts; CORS allows only the configured origin; tables are utf8mb4; doctor exits 1 when a check is `down` |
| **TS unit** | Vitest; outputs checked against the contract schemas with ajv | types match the schemas, and every valid fixture fits its type; `redactText()`, `redactPath()` and `redactContext()` pass every shared case, don't change their input and give the same result when run twice; API client: success, error envelope, health 503 as data, timeout, network failure, non-JSON reply with the header's reference code, unknown fields ignored; `reportClientError()`: posts exactly the measured JSON with `keepalive` and no cookies, 202 is `ok` with the header's reference code, error envelopes and other replies, timeout, network failure; report building: every report valid against the schema, `Name: message`, what else can be thrown, the stack without the repeated message, lone surrogates, `null` for an invalid app, version, path or time; redaction of every text, with a pattern cut by the cap never sent in part and a very long text still quick; report trimming: each step in order, an emoji at the cut, exactly 8192 bytes sent and 8193 trimmed at steps 2 and 3 (with characters JSON escapes), `null` when it can't fit |
| **TS unit, the reporter** | Vitest | `createErrorReporter()`: a thrown error and an unhandled rejection from the given target are reported; `report()` from outside; a sixth report on one page isn't sent; the same error twice is sent once and the repeat gets the first reference code; a failed send isn't reported and nothing throws, even from the callback; an `error` event without an error object uses its message; the callback gets the reference code or `null`; `stop()` removes the listeners; every report sent is valid against the schema |
| **Component** | Vitest + `@nuxt/test-utils` + Vue Test Utils, in jsdom | `healthView()`: ok, degraded, down, an unknown overall status, an unknown check status, network, timeout, an `api` error with and without a reference code, an invalid response, data that isn't a `HealthStatus`; the admin page: checking, then each state, the checks in the API's order, both versions, a `null` codename, the "different versions" note, the API address, "Check again" calls the API again, the error notice (sent, not sent, over the limit, a repeat), the test button counts its clicks, `error.vue` with and without a reference code; the web page: the site name (default and set) and the version from `version.json`; `wrapper.html()` used at least once (it runs the overridden `js-beautify`) |
| **Repository scripts** | `node --test` | `check-pins` also requires exact versions in `pnpm-workspace.yaml` `overrides` and a decision for every `allowBuilds` entry; `check-colors` finds hex, `rgb()`, `hsl()`, `oklch()` and named colors in styles, allows the listed system colors, and ignores words in page text |
| **Contract suite** | against the **running** API | every real response validates against its schema |
| **End-to-end** | Playwright, real admin build + real Laravel + real MariaDB | admin shows version and "All systems OK"; with the DB stopped it shows the database problem; a forced error shows a reference code that appears in the log file; the report's `keepalive` request with its CORS preflight works in Chromium and Firefox (R19); the web page's text shows with JavaScript off |
| **Deploy smoke** | after each deploy | `/health` returns 200 and the expected version, or the deploy rolls back |

---

## 8. Iterations

Each iteration is its own branch from `dev` (`feature/phase-1-N-description`) and its
own PR, so reviews stay small. In each: I show the tests, you approve, I write them and
show them failing; then I show the code, you approve, I write it and the tests pass.

**From 1.5 on (owner, 2026-11-01):** I still write the tests and the plumbing (skeleton,
dependencies, configuration, CI). For the logic (middleware, controllers, adapters,
commands) I show the complete code as a worked example with its explanation, and **you
type it in**. You run the tests, I review your diff and run the checks, and then it's
committed. Hints instead of worked examples come after the prayer board and the first
sermons iteration ship.

| # | Iteration | Delivers | Needs you for |
|---|---|---|---|
| 1.1 | **Repo reset & toolchain** | Old tree removed; workspace skeleton; pinned Node/pnpm/PHP tool versions; new `ci.yml` with stable job names; `dependabot.yml`; GitHub P2–P6 | Approving dependencies (§9); updating rulesets to new check names |
| 1.2 | **Hosting spike + setup** | Prove the risky hosting assumptions on **test** with a throwaway `hello.php` (§11), then do the real setup; **automatic Let's Encrypt certificates for all five hostnames, including www (R10)**; `hosting.md` runbook; setup help articles *(moved to 1.9, owner 2026-10-19)* | The cPanel session |
| 1.3 | **Contract** | Schemas, fixtures, OpenAPI; self-tests in both languages. *Built 2026-10-21: 18 schemas, 103 fixtures, `openapi.json`, 223 self-tests each in TypeScript and PHP; `composer audit` joined CI here, not in 1.4* | Review |
| 1.4 | **PHP core** | Everything in §6.2, in eight checkpoints (§8.1). *Built 2026-10-30: 414 tests (452 after the 2026-11-20 redaction, error and path fixes) on PHP 8.4 and 8.5, PHPStan at max level; the core's Pint, PHPStan, PHPUnit and `composer audit` run in CI* | Review |
| 1.5 | **Laravel API** | Everything in §6.3, in seven checkpoints (§8.2). *Built 2026-11-13: Laravel 13.42.0, trimmed; `/health`, client-error intake, the heartbeat and the doctor; 109 tests (123 after the 2026-11-20 fixes) against MariaDB 11.4 on PHP 8.4 and 8.5, PHPStan at max level with Larastan* | Typing the logic code; review |
| 1.6 | **TS core** | Everything in §6.4, in six checkpoints (§8.3). *Built 2026-11-21: `@chapelcore/core` with types generated from the contract, `ApiClient`, redaction and client error reports; 203 tests on Node 24, the API client's against a real local HTTP server; ESLint (typescript-eslint, strict and type-aware) on the core and the contract's TypeScript tests* | Typing the logic code; review |
| 1.7 | **Admin + web placeholders** | Everything in §6.5, in seven checkpoints (§8.4) | Typing the logic code; review |
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

### 8.2 Iteration 1.5 checkpoints

Each checkpoint is one commit: its tests are written and seen failing first, then the
plumbing is written and the logic is typed in by you (§8).

| # | Checkpoint | You type | See it yourself |
|---|---|---|---|
| 0 | Docs PR: this plan | — | Read the PR |
| 1 | Skeleton, trimmed; `composer.json`; Pint and PHPStan configs; CI jobs with the MariaDB service; Dependabot; `.env.example` | — (a guided tour of the folders instead) | `php artisan about` lists our packages; CI is green |
| 2 | Request ID, JSON-only errors, the `JsonLogger` log channel | The middleware, the exception rendering, the log channel | `curl localhost:8000/nope` → `NOT_FOUND` with a reference code you then find in the log |
| 3 | `/health`, the heartbeat command, utf8mb4, both route layouts | The controller, the three adapters, the provider wiring, the command | **`/health` in your browser**; stop MariaDB → `down` |
| 4 | Client-error intake, rate limit (cache migration), CORS | The controller, the rate limiter | `curl` a report → 202 and a log line; the 11th → 429 |
| 5 | `chapelcore:doctor` with its Laravel adapters | The charset and certificate adapters, the command | Run it; with `APP_URL=http://…` it reports `down` with its fix and exits 1 |
| 6 | Docs: runbook (local database, `.env`, `artisan serve`), CLAUDE.md commands, CHANGELOG; PR ready | — | Full CI-equivalent run |

### 8.3 Iteration 1.6 checkpoints

Each checkpoint is one commit: its tests are written and seen failing first, then the
plumbing is written and the logic is typed in by you (§8).

| # | Checkpoint | You type | See it yourself |
|---|---|---|---|
| 0 | Docs PR: this plan, the DOMAIN-MODEL §5.8 clarification | — | Read the PR |
| 1 | Skeleton: `package.json`, the build and test tsconfigs, Vitest, the root ESLint config and `lint` scripts, the `check:pins` additions | — | `pnpm lint` runs ESLint; `pnpm --filter @chapelcore/core build` makes `dist/` |
| 2 | Generated types: the generate script, `src/generated/`, `src/types.ts`, the drift test, fixtures type-checked | — (generated code) | Change one schema description: the test fails until you regenerate |
| 3 | `redactText()` and `redactContext()` with the shared cases | The redactor | A `node` one-liner redacts a sentence with an email and a token |
| 4 | `ApiClient` and `FetchResult` | The client | Against `artisan serve`: health `ok`; stop MariaDB and it's `down`; a wrong base URL gives a network error |
| 5 | `clientErrorReport()` and `reportClientError()` | Both functions | Send a report to the local API and find its log line, with the email redacted |
| 6 | Docs: runbook §7, CLAUDE.md commands, CHANGELOG, the 1.6 row; PR ready | — | Full CI-equivalent run |

### 8.4 Iteration 1.7 checkpoints

Each checkpoint is one commit: its tests are written and seen failing first, then the
plumbing is written and the logic is typed in by you (§8).

| # | Checkpoint | You type | See it yourself |
|---|---|---|---|
| 0 | Docs PR: this plan, the CLAUDE.md step 8 note | — | Read the PR |
| 1 | Skeleton: both apps' `package.json`, `nuxt.config.ts` (version, build settings, head), tsconfig, ESLint and Vitest configs, `.env.example`, `.nuxtrc`; `allowBuilds` and `overrides` in pnpm-workspace.yaml, and `check-pins` checks them; `engines` 24.15 or later; turbo `env`, `inputs` and `dependsOn`; CI builds every package; the `contrast` job runs `check-colors`; a smoke test per app (it shows the version) | — | `pnpm build` makes both `.output/public/`, and without `CHAPELCORE_API_BASE` the admin's build stops with its message; `pnpm --filter @chapelcore/admin dev` shows the empty page at `localhost:3001/admin/`; a color in a `.vue` style fails `check-colors` |
| 2 | `createErrorReporter()` in the core | The reporter | A `node` one-liner: the same error twice, one report reaches `artisan serve` and its log |
| 3 | The admin status page: `healthView()`, `useApiClient()`, the page | `healthView()`, the composable, the page | With `artisan serve`: "All systems OK"; stop MariaDB → the database is down; stop the API → "Can't reach the API"; an old heartbeat → warnings |
| 4 | Error reporting in both apps: the plugin, the admin's notice, the test button, `error.vue` | The plugin, the notice | Click "Test error reporting": a reference code appears and you find it in the API's log; DevTools shows the preflight and the 202 (R19), in Chrome and Firefox |
| 5 | The web placeholder page | The page | `localhost:3000` shows the name and the version; "View source" has the text |
| 6 | Docs: runbook §8 (the apps: building the core first, `.env`, the dev servers, the local CORS origins), CLAUDE.md state, stack and commands, CHANGELOG, the header comments of `eslint.config.mjs`, `ci.yml` and `turbo.json`, the 1.7 row; PR ready | — | Full CI-equivalent run |

For the next iterations: 1.8 serves the admin's `.output/public/` under `/admin/` (its
`baseURL`), for example by copying it into an `admin/` folder for PHP's built-in server;
1.9 deploys `.output/public/` only (`nuxt generate` also leaves a `dist` link, which stays out
of the archive) and sends `X-Robots-Tag: noindex` on test.

---

## 9. Dependencies for approval

All open source and actively maintained; versions are today's latest and will be
pinned exactly. **Nothing is added until you approve it.**

### PHP (Composer) — ✅ approved by owner 2026-10-09
| Package | Version | Used for | Why not built-in | License |
|---|---|---|---|---|
| `laravel/framework` (+ its own dependencies) | 13.35 | the API framework | the agreed stack (D-series) | MIT |
| `psr/log` | 3.0.2 | standard logging interface for the core | it *is* the PHP standard (PSR-3); tiny; Laravel already includes it | MIT |
| `phpunit/phpunit` *(dev)* | 13.4 | PHP tests | PHP has no built-in test runner | BSD-3 |
| `laravel/pint` *(dev)* | 1.32 | formatter (PER and Laravel presets) | enforces the coding standards | MIT |
| `phpstan/phpstan` *(dev)* | 2.3 | static analysis | catches type errors before runtime | MIT |
| `larastan/larastan` *(dev)* | 3.13 | PHPStan rules that understand Laravel | PHPStan alone misreads Laravel's magic | MIT |
| `opis/json-schema` *(dev)* | 2.6 | validate PHP output against the contract in tests | PHP has no JSON Schema validator | Apache-2.0 |

### JavaScript (npm) — ✅ approved by owner 2026-10-09
| Package | Version | Used for | Why not built-in | License |
|---|---|---|---|---|
| `nuxt` + `vue` | **latest stable at install time** (1.7 installs Nuxt 4.6.4 and Vue 3.5.45: 3.5.46 was less than a day old, which pnpm 12 refuses, R25) | admin and site | the agreed stack | MIT |
| `turbo` | 2.11 | runs tasks across the monorepo | kept from the old setup (owner request) | MIT |
| `typescript` | **6.0.3**. TS 7 is not usable yet (R6, ✅ Q2) | type checking | — | Apache-2.0 |
| `vue-tsc` *(dev)* | 3.3 | type-checks `.vue` files | `tsc` can't read `.vue` files | MIT |
| `vitest` *(dev)* | 5.0 | JS unit and component tests | Node's test runner can't handle Vue components | MIT |
| `@nuxt/test-utils` *(dev)*, ✅ approved 2026-11-21 | 4.4.0 | mount Nuxt pages and components in Vitest, with Nuxt's auto-imports, plugins and runtime config; run in jsdom (its default is `happy-dom`) | `@vue/test-utils` alone can't resolve Nuxt's auto-imports; it is Nuxt's official test package | MIT |
| `@vue/test-utils` *(dev)* | 2.5 | mount Vue components in tests | official Vue tool | MIT |
| `jsdom` *(dev)* | 30.1 (1.7 installs 31.0.1, the latest stable; it needs Node 24.15 or later) | browser environment for component tests | Node has no DOM. Chosen over `happy-dom`, which had critical security alerts | MIT |
| `eslint`, `@nuxt/eslint`, `typescript-eslint`, `eslint-plugin-vue` *(dev)* | 10.12 / 1.17 / 8.71 / 10.11 (1.6 installs `eslint` 10.15.0 and `typescript-eslint` 8.73.0, the latest stable) | linting per the coding standards | — | MIT |
| `@eslint/js` *(dev)*, ✅ approved 2026-11-17 | 10.0.1 | ESLint's own recommended rules | since ESLint 10 the core package no longer includes them; typescript-eslint's rules don't cover general JavaScript mistakes | MIT |
| `prettier` *(dev)* | 3.9 | formatting | — | MIT |
| `@playwright/test` *(dev)* | 1.64 | end-to-end tests in a real browser | ✅ D6 | Apache-2.0 |
| `ajv` + `ajv-formats` *(dev)* | 8.20 / 3.0 | validate JSON against the contract in TS tests | JS has no JSON Schema validator | MIT |
| `json-schema-to-typescript` *(dev)* | 16.0 | generate TS types from the schemas | keeps types and contract identical | MIT |
| `@types/node` *(dev)* — ✅ approved 2026-10-21 | 24.19 (matches Node 24) | type descriptions for Node's built-in modules (`node:fs`, `node:path`) used by the tests; no runnable code | TypeScript doesn't know Node's built-ins without it | MIT |

**pnpm settings, ✅ approved 2026-11-21 (1.7):** three `overrides`, each limited to one parent
and used only at build or test time: `readdir-glob>minimatch` and `editorconfig>minimatch` →
11.0.9, `js-beautify>glob` → 14.0.6. They replace versions with advisories GHSA-g3hv-75wg-m249
and GHSA-3g3m-2fjr-6pw4 (Nitro's zip tool, which a static build doesn't run, and Vue Test
Utils' HTML formatter). Both are a major version above what the parent asks for; each is
removed when its parent ships a fixed version (licenses ISC and BlueOak-1.0.0).
`allowBuilds: { esbuild: false }`: pnpm 12 requires a decision for every install script, and
esbuild's only checks its binary, which comes from its platform package. Node **24.15 or
later** (`engines`), which Nuxt 4.6.4 and jsdom 31 require; `.nvmrc` stays `24`.

### GitHub Actions (pinned to exact commit SHAs, with the version in a comment) — ✅ approved 2026-10-09
`actions/checkout`, `actions/setup-node`, `pnpm/action-setup`, `actions/upload-artifact`
(Playwright reports) — official GitHub/pnpm actions. `shivammathur/setup-php` (MIT) —
the standard way to install PHP 8.5/8.4 + extensions in CI. **No third-party deploy action:**
deploys use the runner's own `ssh`, `scp` and `tar`.

### CI service image — ✅ approved by owner 2026-11-01 (1.5 E7)
| Image | Version | Used for | Why | License |
|---|---|---|---|---|
| `mariadb` (Docker Official Image) | 11.4 patch matching the host, pinned by exact tag and digest | the real database for `test-php` | Ubuntu's packages are 10.11; MariaDB's own apt repository is slower and harder to pin | GPL-2.0 |

### Things deliberately *not* added
Docker/Sail · Redis · a static-file server (CI serves the built admin with PHP's
built-in server) · Mockery and Faker (PHPUnit's own test doubles suffice for now) ·
Tailwind and UI libraries (Phase 2) · any deploy service.

---

## 10. CI and deploy design

**CI jobs** (these exact names become the required checks in all three rulesets):
`lint`, `static-analysis`, `test-php (8.5)`, `test-php (8.4)`, `test-js`, `contract`, `e2e`,
`contrast` (the color check from 1.7, §6.5 E6, until Phase 2's contrast gate), `audit`.
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
| R12 | Laravel's log manager may expect a Monolog logger, not the core's PSR-3 `JsonLogger` | Tested first in 1.5 checkpoint 2; fallback: a small Monolog handler that passes each record to `JsonLogger` |
| R13 | Laravel renders some errors as HTML before our handler runs (early boot, maintenance mode) | JSON forced for every request; a test for every error code |
| R14 | Composer links path packages instead of copying them, so the links would break in the deploy archive | The 1.9 deploy build sets `COMPOSER_MIRROR_PATH_REPOS=1` |
| R15 | The Laravel tests need MariaDB running on the development machine | The development runbook covers starting it and creating the test database and user (owner runs the `sudo` steps) |
| R16 | `json-schema-to-typescript` may name or merge types differently across the schemas' cross-file references, and it formats with its own copy of Prettier | Checked first in 1.6 checkpoint 2; fallback: generate with `format: false`, then format with our pinned Prettier |
| R17 | The PHP and TypeScript redactors drift apart | The shared cases decide. Any difference found gets fixed in both languages and added as a case in the same PR (DOMAIN-MODEL §5.8) |
| R18 | Client and server count the 8 KB differently | Both measure UTF-8 JSON bytes; a test sends exactly 8192 bytes, and 8193 is trimmed |
| R19 | A browser may refuse a `keepalive` request that needs a CORS preflight (`Content-Type: application/json` to the `api.` subdomain) | Checked by hand in 1.7 checkpoint 4 in Chrome and Firefox (the admin on `localhost:3001` and the API on `localhost:8000` are different origins, so the preflight happens), then automated in 1.8's Playwright suite; fallback: send it as `text/plain`, which needs no preflight (the server reads the raw body) |
| R20 | pnpm 12 refuses to install when a package's install script has been neither allowed nor denied | Found in the 1.7 spike: `allowBuilds` decides each one (esbuild: denied) |
| R21 | Nuxt's large build-time dependency tree brings `pnpm audit` findings that upgrading can't fix | Exact `overrides`, each limited to one parent, with its reason and when to remove it (§9); fallback `auditConfig.ignoreGhsas` with the reason, as for picomatch |
| R22 | A static build bakes in the API address, so a wrong one ships an admin that talks to the wrong API | `CHAPELCORE_API_BASE` is required by `nuxt generate`; the admin shows the address it uses; 1.9's smoke test opens the deployed admin |
| R23 | `vue-tsc` or `@nuxt/eslint` don't work with TypeScript 6.0.3 and typescript-eslint 8.73 | Spike passed inside the workspace: the type check is clean, and with the rules-only config the type-aware rules report errors in `.vue` files |
| R24 | The public "Test error reporting" button could be used to fill the logs | The API's limit (10 a minute per client IP) and the reporter's 5 per page load; reports are redacted on both sides |
| R25 | pnpm 12 refuses packages published less than a day ago | Install the previous patch; Dependabot's 7-day cooldown brings the new one in its own PR |

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
