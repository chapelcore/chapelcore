# ChapelCore — Domain Model (language-agnostic)

> Status: **Approved** by the owner 2026-10-09 (PR #80) · Created: 2026-10-07 · Model version: **0.4** (2026-10-22, amended 2026-10-31)
> **This document is normative.** Every frontend, backend and library, in any
> language, implements the objects, names, types, rules and interactions defined
> here. If code disagrees with this document, the code is wrong, or the document
> must be changed first through the process in §10.
> Related: [PLAN.md](../../PLAN.md) · [CLAUDE.md](../../CLAUDE.md)
> Modelled on devfolio's `docs/design/DOMAIN-MODEL.md`.

Sections marked *(outline)* hold placeholders that are completed in the phase that
builds them, at that phase's plan review.

---

## 1. Purpose & how to use this document

ChapelCore will have more than one backend (Laravel first, FastAPI later) and possibly
more than one frontend. They stay interchangeable only if they share **one model**:
the same objects, field names, types, nullability rules, enums, invariants and flows.

| When you… | Use this document to… |
|---|---|
| Build a module | Add its objects (§6) at plan review, before any code |
| Add a backend | Implement §4–§7 in that language's core library, following the naming map (§8) |
| Add a frontend | Consume the transport objects only; never invent fields |
| Add a content source (e.g. a sermon feed) | Write a **source adapter** that maps the provider's data into the domain object (§3.1) |
| Change the model | Follow §10: this document first, then schemas, then code |

**Machine-readable enforcement:** `libs/shared/contract/schemas/*.schema.json` and
`openapi.json` (OpenAPI 3.1, which uses the same JSON Schema 2020-12 dialect; Phase 1).
TypeScript types are generated from the schemas. Each backend's contract suite validates
real HTTP responses against them. JSON Schema has no generics, so each endpoint has its
own response schema (e.g. `HealthResponse` = `Envelope<HealthStatus>`). This document
and the schemas change in the same PR.

---

## 2. Notation

| Notation | Meaning | JSON |
|---|---|---|
| `String` | UTF-8 text; may be empty only if the invariant says so | string |
| `Integer` | Whole number ≥ 0 unless stated | number (integer) |
| `Boolean` | true/false | boolean |
| `Uuid` | RFC 9562 UUID, lowercase, hyphenated | string |
| `Slug` | `^[a-z0-9]+(-[a-z0-9]+)*$` | string |
| `Key` | Dotted identifier `^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$` (e.g. `sermons.sync`) | string |
| `Email` | RFC 5322 address, stored lowercase | string |
| `Url` | Absolute `https://` URL unless stated | string |
| `Timestamp` | Instant in UTC: `YYYY-MM-DDTHH:MM:SS`, optionally followed by a 3- or 6-digit fraction, always ending in `Z` (`2026-10-07T17:00:00Z`, `2026-10-07T17:00:00.123Z`). Never an offset such as `+00:00`. ChapelCore producers always send the 3-digit (millisecond) fraction (`2026-10-07T17:00:00.123Z`); consumers accept all three forms | string |
| `SemVer` | Semantic Versioning 2.0.0 version (`1.0.0`, `1.1.0-rc.1`), matched by the official semver.org regular expression | string |
| `RefCode` | Reference code: 8 uppercase Crockford Base32 characters (digits and letters except I, L, O, U) written as two groups of four with a hyphen, `^[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}$` (`7F3K-92QD`). The same text everywhere: JSON, the `X-Request-Id` header, log files and what people see | string |
| `ErrorCode` | Stable machine code in `UPPER_SNAKE`, `^[A-Z][A-Z0-9]*(_[A-Z0-9]+)*$` | string |
| `LocalDate` | Calendar date `YYYY-MM-DD` with **no time zone** (e.g. the day a sermon was preached) | string |
| `LocalTime` | Wall-clock time `HH:MM` (24 h) in the church's time zone | string |
| `TimeZone` | IANA zone name (`America/Denver`) | string |
| `Weekday` | `Enum{mon\|tue\|wed\|thu\|fri\|sat\|sun}` | string |
| `RichText` | Sanitized HTML subset (allowed tags listed in Phase 4) | string |
| `List<T>` | Ordered sequence; **order is meaningful** | array |
| `Map<K,V>` | Unordered key/value pairs | object |
| `T?` | Nullable. The field is **always present**; absence is `null` | value or `null` |
| `Enum{a\|b}` | One of the listed lowercase values | string |

### Universal rules (apply to every object)

1. **Names:** transport (JSON) fields are `camelCase`; storage (SQL) columns are
   `snake_case`; in-code names follow the language or framework standard (§8).
2. **Nullability:** every field is always present in JSON. Optional values are
   `null`, never omitted.
3. **Lists are never null.** Empty is `[]`.
4. **No empty-string nulls.** Use `null` on a `T?` field, never `""`.
5. **Enums:** producers emit only defined values. Consumers **tolerate unknown
   values** (render neutrally, never crash).
6. **Unknown fields:** consumers ignore them; producers never emit fields not in this model.
7. **Ordering:** lists are returned in display order. Clients don't re-sort domain lists.
8. **Immutability:** domain and transport objects are values; use readonly or frozen types.
9. **Time:** instants are stored and sent in UTC (`Timestamp`). Things people
   experience as a calendar day or a wall-clock time use `LocalDate` / `LocalTime`
   plus the church's `TimeZone`. Never send a date as midnight UTC: it shows as the
   previous day in Montana.
10. **Secrets never leave the server.** Secret settings are write-only in transport
    (§5.4).
11. **Personal data is marked** in each object table (🔒) and follows the security
    rules in CLAUDE.md.
12. **Runtime truth:** version and stack fields come from `version.json` and runtime
    introspection, never literals.

---

## 3. Where the model lives (layers)

| Layer | Lives in | Contains | May depend on |
|---|---|---|---|
| **Specification** | this doc + `libs/shared/contract` | Prose spec, JSON Schemas, OpenAPI | nothing |
| **Core library** | `libs/<lang>/native/core` | Domain objects, ports (interfaces), services (business rules), source adapters, serializer | spec only (no framework) |
| **Framework adapter** | `apps/api/laravel` (later `apps/api/fastapi`) | Routing, HTTP, auth wiring, migrations, repository implementations of core ports, scheduler heartbeat. **No business rules.** | core library |
| **Client library** | `libs/ts/native/core` | Generated types, API client (timeout, errors, `FetchResult`) | transport objects |
| **Presentation** | `libs/ts/vue/ui`, `apps/web/nuxt`, `apps/admin/nuxt` | Components and pages using the shared theme | client library |

### 3.1 The three shapes of the same data

| Shape | Purpose | Sermon example |
|---|---|---|
| **Source** | What an external provider gives us | A Logos RSS `<item>` + the sermon page's metadata |
| **Storage** | Relational integrity, queries | `sermons`, `sermon_series`, `sermon_overrides` |
| **Transport** | What clients consume (resolved read models) | One `Sermon` object with overrides applied |

**Source adapters** (core library) convert *Source → Domain*. **Repositories**
(framework adapter, implementing a core port) convert *Domain ↔ Storage*. The
**serializer** (core library) converts *Domain → Transport*, once, in one place.

**Override rule** (same in every language): *a staff edit replaces the synced value;
otherwise the synced value is used; otherwise the empty value* (`null` or `[]`).
Syncs never overwrite staff edits.

---

## 4. Operational domain (every response)

**Paths:** every endpoint path in this document is relative to the deployment's API
base URL, `{apiBase}` (e.g. `https://api.libbynaz.org` or `https://example.org/api`).

### Envelope\<T\> — every successful response

| Field | Type | Notes |
|---|---|---|
| `data` | `T` | The payload |
| `meta` | `ResponseMeta` | |

### ErrorEnvelope — every error response

| Field | Type | Notes |
|---|---|---|
| `error.code` | `ErrorCode` | e.g. `PRAYER_NOT_FOUND` |
| `error.message` | `String` | Human-readable, safe to show |
| `error.fields` | `Map<String, List<String>>` | Validation messages per field (`{}` if none) |
| `meta` | `ResponseMeta` | |

**Error codes defined so far** (each phase adds its own; codes never change meaning):

| Code | HTTP | When |
|---|---|---|
| `NOT_FOUND` | 404 | No such route or resource |
| `METHOD_NOT_ALLOWED` | 405 | Route exists, method doesn't |
| `VALIDATION_FAILED` | 422 | Input rejected; details in `error.fields` |
| `PAYLOAD_TOO_LARGE` | 413 | Body over the endpoint's limit |
| `RATE_LIMITED` | 429 | Too many requests from this client |
| `INTERNAL_ERROR` | 500 | Unexpected failure; never includes a stack trace |
| `SERVICE_UNAVAILABLE` | 503 | A dependency (e.g. the database) is down |
| `AUTH_*` | 401/403/419 | See the auth contract (§5.0) |

### ResponseMeta

| Field | Type | Notes |
|---|---|---|
| `requestId` | `RefCode` | The request's **reference code**. The same value is sent in the `X-Request-Id` header and written to every log entry for the request |
| `modelVersion` | `String` | This document's version, `^\d+\.\d+$` (`0.4`) |
| `servedBy` | `ServedBy` | |
| `page` | `PageInfo?` | Present on paginated lists |

### ServedBy

| Field | Type | Example |
|---|---|---|
| `backend` | `String` | `laravel` |
| `backendVersion` | `String` | Framework version, from package metadata |
| `language` | `String` | `php` |
| `languageVersion` | `String` | `8.5.x` (runtime) |

### PageInfo

| Field | Type |
|---|---|
| `page` | `Integer` (≥ 1) |
| `perPage` | `Integer` (1–100) |
| `total` | `Integer` |

### VersionInfo

| Field | Type | Example |
|---|---|---|
| `version` | `SemVer` | `1.0.0` |
| `codename` | `String?` | `Kootenai` |

### HealthStatus — `GET {apiBase}/health`

| Field | Type | Notes |
|---|---|---|
| `status` | `Enum{ok\|degraded\|down}` | Worst of the checks |
| `version` | `VersionInfo` | |
| `checks` | `List<HealthCheck>` | Phase 1: `database`, `scheduler`, `storage`, in that order (rules below) |

- HTTP status: **200** when `ok` or `degraded`, **503** when `down`. Deploy checks rely on this.
- The endpoint is **public**, so check messages are generic ("Database unavailable"). Server
  details (versions, paths, extensions) belong to the doctor report (§5.8), which is never public.
- `status` is the worst of the checks (`down` > `degraded` > `ok`). When a check is `ok`, its
  `message` is `null`.

| Check | ok | Not ok | Status when not ok | Message when not ok |
|---|---|---|---|---|
| `database` | A trivial query succeeds | Connection or query fails | `down` | "Database unavailable" |
| `scheduler` | Last cron heartbeat ≤ 5 minutes ago | Older than 5 minutes, or never seen | `degraded` | "Scheduled tasks have not run recently" |
| `storage` | The storage folder is writable | Not writable | `down` | "Storage not writable" |

### HealthCheck

| Field | Type |
|---|---|
| `name` | `Key` |
| `status` | `Enum{ok\|degraded\|down}` |
| `message` | `String?` (never contains secrets; `null` when `ok`) |

---

## 5. Core platform domain (Phase 3)

### 5.0 Auth contract (Phase 3) *(outline, ✅ D7 / D13)*

The contract is the same for every backend. Each backend implements it with its
framework's own vetted auth (Laravel: Sanctum + Fortify; Django: `contrib.auth` +
allauth MFA; Drupal: core user + TFA; FastAPI: session middleware + a CSRF library).

- **Flows:** CSRF bootstrap · sign in · 2FA challenge · sign out · current user ·
  password reset · invite acceptance · 2FA enrol/disable (requires password
  re-confirmation) · admin reset of another user's 2FA (audited)
- **Endpoints** (under `{apiBase}/auth/`): `GET csrf`, `POST sign-in`,
  `POST two-factor/challenge`, `POST sign-out`, `GET me` (→ `Envelope<User>`),
  `POST password/forgot`, `POST password/reset`, `POST invites/{token}/accept`,
  `POST two-factor`, `DELETE two-factor`, `POST password/confirm`
- **Error codes:** `AUTH_INVALID_CREDENTIALS`, `AUTH_TWO_FACTOR_REQUIRED`,
  `AUTH_TWO_FACTOR_INVALID`, `AUTH_THROTTLED`, `AUTH_CSRF_MISMATCH`,
  `AUTH_SESSION_EXPIRED`, `AUTH_PASSWORD_CONFIRMATION_REQUIRED`, `AUTH_FORBIDDEN`
  (each backend maps its native responses, e.g. Laravel's 419, onto these)
- **Cookies:** session = `__Host-chapelcore_session`, set by the API host only (Secure,
  HttpOnly, SameSite=Lax, Path=/, no Domain).
- **CSRF (✅ D16):** `GET csrf` returns `Envelope<{csrfToken: String}>`. The client holds
  the token in memory and sends it as the `X-CSRF-TOKEN` header on every
  state-changing request. No CSRF cookie is read by page scripts, so the same flow
  works in both API layouts. Each backend is configured to accept this header
  (Laravel and Django both support a header token).
- **Session rules:** ID regenerated on sign-in and on role change; idle and absolute
  timeouts (values set in Phase 3).
- **Passwords:** stored as standard hash strings; canonical form **Argon2id (PHC
  format)**, bcrypt accepted. A documented **user export/import format** lets a church
  move between backends without password resets. CI tests that hashes produced by one
  language verify in the others.
- **Objects:** `User` (§5.1), `TwoFactorEnrollment` (enabled, confirmedAt; the secret is
  never transported), `RecoveryCode` (shown once, then stored only hashed),
  `ExternalIdentity` (provider, subject, linkedAt — Phase 10)
- **Authorization** (role ranks, "2FA required for staff+") lives in each language's
  native core, never in framework code.

### 5.1 User

| Field | Type | Notes |
|---|---|---|
| `id` | `Uuid` | |
| `email` | `Email` 🔒 | Unique |
| `displayName` | `String` | |
| `role` | `Role` | |
| `status` | `Enum{active\|invited\|disabled}` | |
| `twoFactorEnabled` | `Boolean` | |
| `createdAt` | `Timestamp` | |
| `lastSignInAt` | `Timestamp?` | |

**Role** = `Enum{superadmin|admin|staff|member|guest}`, ranked in that order.
Invariant: there is always at least one active `superadmin`.

### 5.2 SiteProfile (singleton)

| Field | Type | Notes |
|---|---|---|
| `churchName` | `String` | |
| `timeZone` | `TimeZone` | Default `America/Denver` |
| `email` | `Email?` | Public contact |
| `phone` | `String?` | |
| `address` | `PostalAddress?` | *(outline: fields defined in Phase 3)* |
| `serviceTimes` | `List<ServiceTime>` | |
| `socialLinks` | `List<SocialLink>` | |

**ServiceTime:** `label: String`, `weekday: Weekday`, `time: LocalTime`, `note: String?`
**SocialLink:** `network: Enum{facebook|youtube|instagram|x|other}`, `url: Url`

### 5.3 Module

| Field | Type | Notes |
|---|---|---|
| `key` | `Key` | `prayer`, `sermons`, `events`, `scheduler`, … |
| `name` | `String` | |
| `description` | `String` | |
| `kind` | `Enum{system\|feature}` | System modules cannot be disabled |
| `enabled` | `Boolean` | |
| `version` | `String` | Module's own semver |
| `settings` | `List<ModuleSetting>` | In the manifest's order |

### 5.4 ModuleSetting

| Field | Type | Notes |
|---|---|---|
| `key` | `Key` | e.g. `sermons.logos_channel_id` |
| `label` | `String` | |
| `type` | `Enum{string\|integer\|boolean\|url\|email\|enum\|secret\|schedule}` | Drives the admin form |
| `value` | `any?` | **Always `null` for `secret`.** Secrets are write-only |
| `isSet` | `Boolean` | Lets the admin show "configured" for secrets |
| `required` | `Boolean` | |
| `options` | `List<String>` | For `enum`; otherwise `[]` |
| `help` | `String?` | |

Modules declare their settings in a **manifest** in code; storage holds only values.

### 5.5 AuditEntry

| Field | Type | Notes |
|---|---|---|
| `id` | `Uuid` | |
| `occurredAt` | `Timestamp` | |
| `actorId` | `Uuid?` | `null` = system / scheduler |
| `action` | `Key` | e.g. `prayer.approved`, `user.role_changed` |
| `subjectType` | `String` | |
| `subjectId` | `String?` | |
| `details` | `Map<String, any>` | Never contains secrets or full personal data |

### 5.6 Help, release notes & tours (Phase 3) *(outline)*

- `HelpArticle`: slug, title, audience `Enum{public|member|staff|admin}`, moduleKey?,
  screenKeys `List<Key>` (the screens whose "Help" link opens it), body `RichText`,
  sections `List<HelpSection>` (slug, heading; tour steps link to these),
  sinceVersion, updatedInVersion. Source shape: a Markdown file in `docs/help/` with
  front-matter; loaded into storage on deploy.
- `ReleaseNote`: version, codename?, releasedOn `LocalDate`, items `List<ReleaseNoteItem>`
- `ReleaseNoteItem`: title, summary, audience, moduleKey?, helpArticleSlug?, tourKey?
- `ReleaseNoteDismissal`: userId, version, dismissedAt. Hides the dashboard banner
  for that user and release only.
- `Tour`: key, title, audience, moduleKey?, steps `List<TourStep>`
- `TourStep`: anchor `Key` (a stable `data-tour` id on a UI element, never a CSS
  selector), screenKey, title, body, articleSection `Slug?` (the help-article section
  that "Read this instead" opens). Invariant: tours and the help panel open only from
  a user action.

### 5.7 Scheduled tasks (Phase 3, release 0.4.0) *(outline — owner request 2026-10-07)*

- `TaskType`: key (e.g. `sermons.sync`), moduleKey, name, description, defaultSchedule
- `Schedule`: id, taskKey, enabled, recurrence `Recurrence`, timeZone, ends
  `Enum{never|afterRuns|onDate}` + value, retry `RetryPolicy`, nextRunAt?, lastRunAt?
- `Recurrence` (discriminated by `kind`):
  - `interval`: every `Integer` × `Enum{minute|hour|day|week}`
  - `timesPerPeriod`: `count` × `Enum{day|week}`, spaced evenly or at listed `LocalTime`s
  - `weekly`: `days: List<Weekday>`, `at: LocalTime` (e.g. Mon 18:00; Mon–Fri 16:00)
  - `monthly`: day of month or "first Monday", `at: LocalTime`
  - `cron`: raw expression (advanced)
- `RetryPolicy`: maxAttempts, backoffMinutes
- `TaskRun`: id, scheduleId, attempt, status `Enum{running|succeeded|failed|skipped}`,
  startedAt, finishedAt?, message?
- Port `RecurrenceCalculator.nextOccurrence(recurrence, timeZone, after): Timestamp?`
  is pure and must give identical results in every language. The contract suite
  includes shared fixtures, including the daylight-saving changeovers.

### 5.8 Diagnostics (Phase 1 + Phase 3) *(✅ D14; Phase 3 parts are outline)*

#### LogEntry (Phase 1): one line of the server's JSON log file

| Field | Type | Notes |
|---|---|---|
| `id` | `Uuid` | |
| `occurredAt` | `Timestamp` | |
| `level` | `Enum{debug\|info\|notice\|warning\|error\|critical\|alert\|emergency}` | PSR-3 / RFC 5424 |
| `channel` | `Key` | e.g. `app`, `diagnostics.client` |
| `message` | `String` | Redacted |
| `requestId` | `RefCode?` | `null` outside a request (e.g. cron) |
| `context` | `Map<String, any>` | Always redacted; `{}` if none |
| `exception` | `ExceptionInfo?` | |

#### ExceptionInfo (Phase 1)

| Field | Type | Notes |
|---|---|---|
| `type` | `String` | Exception class or type name |
| `message` | `String` | Redacted (see Redaction below). Never empty: an exception with no message is written as `(no message)` |
| `file` | `String?` | Server path; only ever in logs and the doctor, never in a response |
| `line` | `Integer?` | ≥ 1 |
| `trace` | `List<String>` | One frame per line, most recent first. Frames carry file, line and function only, never argument values |
| `previous` | `ExceptionInfo?` | The exception that caused this one |

#### DoctorCheck (Phase 1)

Same fields as `HealthCheck` (§4), plus:

| Field | Type | Notes |
|---|---|---|
| `fix` | `String?` | Plain-language instruction for fixing the problem; `null` when `status` is `ok` |

The command line has it in Phase 1, and the signed-in admin from Phase 3. It is **never**
exposed on a public endpoint, so `message` may name versions, paths and extensions.

Phase 1 checks, in this order:

| Name | ok when | Otherwise | Example `fix` |
|---|---|---|---|
| `php.version` | PHP ≥ 8.4 | `down` | "Set PHP to 8.5 in cPanel → PHP Tweaks → Account settings." |
| `php.extensions` | All required extensions are loaded: `bcmath ctype curl dom fileinfo filter hash intl mbstring openssl pdo session sodium tokenizer xml xmlwriter zip`, plus the PDO driver for the configured database (`pdo_mysql` or `pdo_pgsql`) | A missing required extension → `down`; only OPcache missing → `degraded` | "Enable intl in cPanel → PHP Tweaks → Extensions." |
| `php.argon2` | `PASSWORD_ARGON2ID` is defined | `degraded` (passwords fall back to bcrypt) | "Ask your host for PHP built with Argon2 (libargon2)." |
| `database.charset` | The database and its tables use `utf8mb4` (MariaDB) or `UTF8` (PostgreSQL) | `down` | "Convert the database to utf8mb4; see the hosting runbook §6." |
| `storage.writable` | Same rule as the health check `storage` | `down` | Names the folder and the permission to set |
| `scheduler.heartbeat` | Same rule as the health check `scheduler` | `degraded` | "Add the cron line from the hosting runbook §8." |
| `app.url` | The URL starts with `https://` (not checked in `dev`) | `down` | "Set APP_URL to the site's https:// address." |
| `app.debug` | Debug is off in `production` (not checked elsewhere) | `down` | "Set APP_DEBUG=false in the server's .env." |
| `tls.certificate` | The certificate expires in ≥ 30 days (not checked in `dev`) | < 30 days → `degraded`; < 7 days or expired → `down` | "Check the acme.sh cron line; see the hosting runbook §7." |

- A check that isn't run in the current environment reports `ok` with the message
  "Not checked in {env}" (e.g. "Not checked in dev").
- A mail settings check is added when mail exists (Phase 3).

#### ClientErrorReport (Phase 1): a browser error sent by the admin or site

`POST {apiBase}/diagnostics/client-errors` → `202`, no body. Public, rate-limited (10 per
minute per client IP). The whole body is at most **8 KB** (`PAYLOAD_TOO_LARGE` above that);
a field outside its limits gives `VALIDATION_FAILED`. Clients trim to the limits before
sending; the server redacts before logging.

| Field | Type | Limits |
|---|---|---|
| `app` | `Enum{admin\|web}` | |
| `appVersion` | `SemVer` | |
| `path` | `String` | Starts with `/`; no `?` or `#` (no query string or fragment); ≤ 2048 characters |
| `message` | `String` | 1–1000 characters |
| `stack` | `List<String>` | ≤ 50 lines, each ≤ 500 characters; `[]` if unknown |
| `userAgent` | `String` | ≤ 512 characters |
| `occurredAt` | `Timestamp` | The browser's clock |

#### Redaction (Phase 1)

One pure function, the same in every language. It never changes its input, and running it
twice gives the same result as running it once. It applies to `LogEntry.message`,
`LogEntry.context`, and `ExceptionInfo.message` at every level of `previous`.

**1. By key** (in `context`, at any depth, including inside lists). Lowercase the key and
remove `_`, `-` and `.`. If the result *contains* any of `password`, `passwd`, `secret`,
`token`, `apikey`, `authorization`, `cookie`, `session`, `csrf` or `prayer`, the whole value
(whatever its type) becomes `"[redacted]"`.

**2. In text** (`message`, and every other string value in `context`), in this order:

| # | Pattern | Becomes |
|---|---|---|
| a | `Bearer` (any case) + whitespace + a token | `Bearer [redacted]` |
| b | A password in a URL: `scheme://`, a user (may be empty), `:`, the password, `@` | `scheme://user:[redacted]@` (the user, host and port are kept) |
| c | A URL query value whose parameter name matches rule 1 | `?token=[redacted]` (the name is kept) |
| d | An email address | `[email]` |
| e | A standalone run of 32+ hex characters, or of 32+ base64/base64url characters that contains a digit, an uppercase and a lowercase letter | `[token]` |
| f | A North American phone number (`406-293-1234`, `(406) 293 1234`, `406.293.1234`) | `[number]` |
| g | A run of 7 or more digits | `[number]` |

Numbers and booleans under keys that don't match rule 1 are kept as they are. Modules add
keys to rule 1 in their own phase.

**Shared cases:** `libs/shared/contract/redaction/*.json`. Each file is
`{ "description", "kind": "text" | "context", "input", "expected" }` and is validated by
`schemas/redaction-case.schema.json`. They are the definitive examples. PHP runs them from
1.4, TypeScript from 1.6. When this table and a case disagree, both are corrected in the
same PR.

#### Phase 3 *(outline)*

- `TroubleshootingReport`: generatedAt, version `VersionInfo`, servedBy `ServedBy`,
  modules `List<{key, enabled, version}>`, nonSecretSettings `Map<Key, any>`,
  doctor `List<DoctorCheck>`, recentErrors `List<LogEntry>`, userDescription `String?`.
  Rendered as Markdown for copying.
- `RetentionPolicy`: diagnosticDays (default 30), auditDays (default 365)

**Invariant (redaction):** no `LogEntry`, `TroubleshootingReport` or `AuditEntry` ever
contains a password, token, secret setting value, prayer text, or personal data
beyond a user's id. Contract fixtures test this in every language.

---

## 6. Module domains

### 6.1 Pages & content blocks (Phase 4) *(outline)*

`Page` (id, slug, title, seoTitle?, seoDescription?, status `draft|published`,
blocks, publishedAt?), `ContentBlock` (discriminated by `type`: `richText`, `hero`,
`image`, `callToAction`, `serviceTimes`, `contact`, `embed`, `scripture`, …),
`NavigationItem`. Defined in full at Phase 4 plan review.

### 6.2 Prayer requests (Phase 5) *(outline)*

`PrayerRequest`: id, body, submitterName? 🔒, submitterEmail? 🔒, isAnonymous,
status `Enum{pending|approved|rejected|answered|archived}` (**always created
`pending`**), screening (`ScreeningLabel?`: provider, verdict, reason, never shown
publicly), prayerCount, submittedAt, moderatedAt?, moderatedBy?, updates
`List<PrayerUpdate>`. Public read model `PublicPrayerRequest` omits every 🔒 and
moderation field.

### 6.3 Sermons (Phase 6) *(outline — revisit at plan review, per owner)*

Known inputs: what you publish to Logos (title, speaker, description, sermon cover,
mp3), what Logos generates (mp4 with timed slides, transcript), the RSS feed (title,
description, link, date, mp3, duration) and the sermon page (cover, speaker,
series, passage).
Draft shape: `Sermon` (id, slug, title, speaker?, description?, preachedOn
`LocalDate`, series `SermonSeriesRef?`, passages `List<String>`, coverImageUrl?,
audioUrl?, videoUrl?, transcriptUrl?, durationSeconds?, source `SourceRef`),
`SermonSeries` (id, title, coverImageUrl?), `SourceRef` (provider `Enum{logos|manual}`,
externalId, url).

### 6.4 Events (Phase 7) *(outline)*

`Event` with `LocalDate`/`LocalTime` + `TimeZone`, location, optional recurrence
(reuses §5.7 `Recurrence`).

### 6.5 Knowledge sources for the AI help assistant (Phase 11) *(outline)*

- `KnowledgeSource`: id, kind `Enum{helpArticles|siteContent|sermonTranscripts|page|section|sitemap}`
  (✅ D17), label, url `Url?` (required for `page`, `section`, `sitemap`), pathPrefix
  `String?` (`section`/`sitemap` filter), maxPages `Integer?` (required for `section`/`sitemap`), enabled, refresh `Schedule?`, lastIndexedAt?,
  status `Enum{ok|failed|pending}`
- `AssistantAnswer`: text, citations `List<Citation>` (never empty unless
  `outOfScope = true`), outOfScope `Boolean`
- `Citation`: title, url, sourceId
- Invariant: answers are generated only from passages retrieved from enabled sources
  in our own index. No live web access, no personal data. Fetching honours `robots.txt`
  and never leaves the host of the source URL.

---

## 7. Interactions *(outline — filled in Phase 1)*

- 7.1 Browser → API request (cookie session, CSRF, Envelope / ErrorEnvelope)
- 7.2 Admin publish → static site rebuild (PLAN D8)
- 7.3 Scheduler heartbeat → due tasks → TaskRun
- 7.4 Source sync (feed → adapter → domain → repository, applying the override rule)

---

## 8. Naming & type mapping per language

| Concept | JSON | SQL | PHP core | Laravel adapter | TypeScript | Python (later) |
|---|---|---|---|---|---|---|
| Object | — | table, `snake_case` plural | `final readonly class Sermon` | Eloquent model `Sermon` (storage only, never returned directly) | `interface Sermon` | `class Sermon(BaseModel)`, frozen |
| Field | `preachedOn` | `preached_on` | `$preachedOn` | `$model->preached_on` | `preachedOn` | `preached_on` + camel alias |
| Nullable | `null` | `NULL` | `?T` | nullable column | `T \| null` | `T \| None` |
| List | `[]` | child table | `list<T>` (PHPDoc) | relation | `readonly T[]` | `tuple[T, ...]` |
| Enum | `"pending"` | `VARCHAR` + CHECK | backed `enum PrayerStatus: string` | cast to the core enum | string union | `StrEnum` |
| LocalDate | `"2026-10-04"` | `DATE` | value object `LocalDate` | `date` cast → core `LocalDate` | `string` (branded) | `datetime.date` |
| Timestamp | ISO 8601 Z | `TIMESTAMP` (UTC) | `DateTimeImmutable` (UTC) | `immutable_datetime` cast | `string` | `datetime` (UTC) |
| Port | — | — | `interface SermonRepository` | `EloquentSermonRepository implements …` | `interface` | `Protocol` |

Serialization to camelCase happens **once**, in the core serializer. Laravel API
Resources, if used, call the core serializer and never rename fields themselves.

---

## 9. Conformance

An implementation conforms when:

1. Its core library's unit tests cover every invariant in this document.
2. The **contract suite** passes against its running API: every response validates
   against the schemas, and the shared fixtures (e.g. recurrence, redaction) produce identical results.
3. The **end-to-end suite** passes with the real frontend against it.

---

## 10. Changing the model

1. **Propose:** a PR edits this document first, with the reason. Classify the change:
   - **Additive** (new optional field, endpoint or enum value) → minor (`0.1` → `0.2`)
   - **Breaking** (rename, removal, type, nullability or meaning change) → major, served
     under a new path prefix (`/api/v2/…`) until every client migrates
2. **Same PR:** update the schemas and OpenAPI; regenerate the TS types.
3. **Then, per language:** update each core library and its tests (tests first).
4. **Gate:** CI runs the contract suite against every backend.
5. **Record:** add a row to the changelog below.

### Model changelog

| Version | Date | Change |
|---|---|---|
| 0.4 | 2026-10-22 | Phase 1 core rules, decided before implementation: health check rules and messages (`message` is `null` when ok); producers send millisecond timestamps; the doctor's Phase 1 checks with their statuses and fixes; redaction rules and shared redaction cases; `ExceptionInfo.message` is never empty (`(no message)`); trace frames have no arguments. Additive, so minor. 2026-10-31: redaction rule 2b removes passwords in URLs |
| 0.3 | 2026-10-20 | Phase 1 contract details, decided before any implementation: `Timestamp` precision; new `SemVer`, `RefCode` (reference code always written with its hyphen) and `ErrorCode` types; `modelVersion` format; full tables for `LogEntry` (`id` is a `Uuid`), `ExceptionInfo`, `DoctorCheck` and `ClientErrorReport` (with field limits); OpenAPI written as `openapi.json`; one response schema per endpoint. Some of these tighten 0.2, but nothing implemented 0.2 yet, so this is classed as minor |
| 0.2 | 2026-10-16 | Phase 1 additions: error-code table, reference-code format, health HTTP status and check names, public-health vs doctor rule, `ClientErrorReport` and its endpoint; runtime example PHP 8.5 |
| 0.1 | 2026-10-07 | Notation, universal rules, operational objects, core platform objects; module outlines. 2026-10-08: nested repo paths; help, release notes, tours and knowledge-source outlines. 2026-10-09: auth contract; scheduled tasks moved to §5.7; diagnostics §5.8. 2026-10-09: paths relative to `{apiBase}`; CSRF via endpoint; knowledge source kinds |
