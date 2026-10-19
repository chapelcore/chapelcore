# Runbook — GitHub repository settings

> **Maintainer runbook** for `chapelcore/chapelcore` (formerly `pj1227/churchos`). It is not part of the ChapelCore product docs.
> **Owner:** Joel Cossins · **Last reviewed:** 2026-10-18 (Phase 1 iteration 1.1, P2–P6)
> **Review:** at every release PR (`staging` → `main`), and in full once a quarter.
> Update "Current state" and tick items as you go, and note the date of each review in
> the log at the bottom.

What this covers: branch rules, required checks, merge settings, security features,
Actions permissions, environments and secrets, and anything connected to the repo
from outside (Railway, Cloudflare).

---

## 1. Current state (audit 2026-10-09)

| Area | Setting today | Problem |
|---|---|---|
| Visibility | **Public** | Free security features are available but switched off (see §2) |
| Rulesets | `protect-main`, `protect-staging`, `protect-dev`: no deletion, no force-push, PR required, 0 approvals, all merge methods allowed | **No required status checks**, so a PR with failing CI can be merged |
| Classic branch protection | On `main` and `staging`, duplicating the rulesets, also with no required checks | Two systems doing one job |
| Auto-delete merged branches | Off | ~35 stale branches have built up |
| Secret scanning / push protection | Off | A pasted key could be committed without warning |
| Dependabot alerts / security updates | Off | No notice of vulnerable dependencies |
| Workflows | `ci.yml` (PRs + pushes to `dev`); `deploy-staging.yml` (push to `staging` → Cloudflare Pages `churchos-staging`; Railway step is a no-op); `deploy-production.yml` (push to `main` → Cloudflare Pages `churchos` + `railway up`) | Old stack; replaced in Phase 1 |
| Outside integrations | **Cloudflare Workers and Pages GitHub app** builds `churchos` and `churchos-staging` on its own (N0b). **Railway GitHub app** auto-deployed `main` to Railway project "responsible-spontaneity" (28 deployments, last 2026-07-20), *in addition to* the workflow's `railway up` | ~~Every production merge deploys the API twice~~ Fixed 2026-10-09 (N0). The workflow's `railway up` remains until Phase 1 (P3) |
| Secrets | `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_API_TOKEN`, `RAILWAY_TOKEN`. Workflows also reference `STAGING_/PROD_SUPABASE_*`, which don't exist and resolve to empty values | Old stack |
| Environments | `churchos-staging (Production)` (Cloudflare), `responsible-spontaneity / production` (Railway) | Leftovers |
| Hosting (for reference) | `libbynaz.org` and `test.libbynaz.org` → Namecheap server `162.0.215.160` (LiteSpeed web server, Namecheap DNS). `api.libbynaz.org` not created yet | — |

---

## 2. Do now (before Phase 1)

Each item says what to change, what it does, and where to find it.

### ☑ N0 — Disconnect Railway's auto-deploy (owner, before any Phase 1 code) — done 2026-10-09
- **What it does:** stops Railway deploying this repo on its own whenever `main` changes.
- **Why now:** Phase 1 removes `apps/api`. Without this, the first merge to `main`
  makes Railway try to deploy a repo with no API.
- **How to check which project it is:** in Railway, open project
  **"responsible-spontaneity"** → its service → **Settings → Source**. If it shows
  `pj1227/churchos`, that's this one. The production URL
  `churchos-production-c6ae.up.railway.app` should belong to it. A devfolio project
  would show a devfolio repo instead.
- **Where:** Railway → the project → service → Settings → Source → **Disconnect**.
  Keep the service running or delete it as you prefer: libbynaz.org doesn't use it.
- **GitHub side:** profile → Settings → Applications → Installed GitHub Apps → Railway →
  Configure → Repository access. Keep `pj1227/devfolio` (it uses Railway); remove
  `pj1227/churchos`. Railway then receives no events from this repo.

### ☑ N0b — Remove this repo from the Cloudflare GitHub App — done 2026-10-09 (app uninstalled)
- **What it does:** stops Cloudflare Pages building this repo on its own. The
  "Cloudflare Pages: churchos" and "Cloudflare Pages: churchos-staging" checks on
  commits come from the **Cloudflare Workers and Pages** GitHub App, separately from
  the workflows' `wrangler pages deploy`, so Cloudflare was also deploying twice.
- **Why now:** once Phase 1 removes the old apps, those automatic builds would fail
  on every push. Nothing on libbynaz.org uses the Cloudflare sites.
- **Where:** profile → Settings → Applications → Installed GitHub Apps → **Cloudflare
  Workers and Pages** → Configure → Repository access → remove `pj1227/churchos`, keeping
  any other repo that still uses it → **Save**.
  If `churchos` is the app's only repo, GitHub won't allow an empty list: scroll to
  **Danger zone → Uninstall** instead.

### ☑ N1 — Require CI to pass before merging — done 2026-10-09
- **What it does:** the Merge button stays disabled until the listed checks are green.
  This enforces the CLAUDE.md rule "all CI jobs required".
- **Where:** Settings → Rules → Rulesets → open **protect-dev** → tick **Require status
  checks to pass** → **Add checks**, and add both:
  - `Lint · Type-check · Build · Test (JS/TS)`
  - `Test (pytest)`

  Leave "Require branches to be up to date before merging" **off** for now. Save, then
  repeat for **protect-staging** and **protect-main**.
- **Note:** a check only appears in the search after it has run in the last 7 days.
  Both ran on PR #80. These names change in Phase 1 (see P2).

### ☑ N2 — Set merge methods per branch — done 2026-10-09
- **What it does:** keeps the long-lived branches in sync.
  - **protect-dev:** allow **Squash** only. One tidy commit per feature.
  - **protect-staging** and **protect-main:** allow **Merge commit** only. Release
    merges keep their shared history, so later releases merge cleanly. Squashing
    between long-lived branches makes them drift apart and conflict.
- **Where:** each ruleset → **Require a pull request before merging** → **Allowed
  merge methods**.

### ☑ N3 — Remove the duplicate classic branch protection — done 2026-10-09
- **What it does:** leaves the rulesets as the single source of branch rules.
- **When:** after N1 and N2, so there's never a gap.
- **Where:** Settings → Branches → Branch protection rules → delete the rules for
  `main` and `staging`.

### ☑ N4 — Automatically delete merged branches — done 2026-10-09 (rebase merging also turned off)
- **What it does:** deletes a feature branch once its PR merges, so nothing is left behind.
  `dev` and `staging` are safe because their rulesets block deletion. Confirm on the
  first `dev` → `staging` release PR that `dev` survives.
- **Where:** Settings → General → Pull Requests → tick **Automatically delete head branches**.

### ☑ N5 — Turn on the free security features — done 2026-10-09 (plus private vulnerability reporting)
- **What they do:**
  - **Secret scanning + push protection:** refuses a push that contains a recognisable
    key or token, before it becomes public.
  - **Dependabot alerts:** warns when a dependency has a known vulnerability.
  - **Dependabot security updates:** opens PRs that fix those vulnerabilities. They
    still go through CI and your review.
- **Where:** Settings → Advanced Security (called "Code security" on some accounts).

### ☑ N7 — Pause Dependabot *security updates* until Phase 1 (keep alerts on) — done 2026-10-09
- **Finding (2026-10-09):** turning on N5 raised **97 open alerts** (14 critical, 48 high,
  28 medium, 7 low), all in the archived JavaScript stack (89 in `pnpm-lock.yaml`;
  e.g. `nuxt`, `vite`, `vitest`, `happy-dom`, `brace-expansion`). Dependabot opened its
  first fix PR, #81, **against `main`**, because security-update PRs always target the
  default branch. That would skip `dev` and test.
- **What to do:** close #81 without merging (Phase 1 deletes that code and its
  lockfile). Turn **Dependabot security updates** off (Settings → Advanced Security)
  so it doesn't open more PRs against `main`. Keep **Dependabot alerts** on, so the
  alerts stay visible. Turn security updates back on in Phase 1 with the new
  lockfiles (P4).
- **No impact on libbynaz.org:** the live site isn't built from this repo.
- **Resumed 2026-10-18 (iteration 1.1):** security updates turned back on, and the
  repository's **default branch changed to `dev`**, so security-update PRs now land on
  `dev` like every other change (owner decision). The rulesets name their branches
  explicitly, so the switch didn't change any protection.

### ☑ N8 — Shut down the old live services (moved earlier from P3) — done 2026-10-09
- The old FastAPI API is still publicly reachable on Railway
  (`churchos-production-c6ae.up.railway.app`) and holds the Supabase service key. The old
  Cloudflare Pages sites are built from the vulnerable dependencies above. Nothing uses
  either.
- **What to do:** stop or delete the Railway service and delete the Cloudflare Pages
  projects `churchos` and `churchos-staging`. Then, in the old Supabase project, either
  pause it or rotate its keys (secret key and JWT secret), since copies of the old keys
  exist in Railway and past `.env` files.

### ☑ N6 — Read-only default token for workflows — already set (verified 2026-10-09)
- **What it does:** workflows get read-only access unless a workflow asks for more.
  The current workflows already declare what they need.
- **Where:** Settings → Actions → General → Workflow permissions → **Read repository
  contents and packages permissions**. Leave "Allow GitHub Actions to create and
  approve pull requests" **unticked**.

---

## 3. Phase 1 (planned with the Phase 1 plan; listed here so nothing is forgotten)

### ☐ P1 — Deployment environments
- Create environments **`test`** and **`production`** (Settings → Environments).
- Each one holds its own deploy secrets (SSH host, port, user, private key, known
  hosts) for the Namecheap account, so a test workflow can never use production
  credentials.
- **Deployment branches:** `test` ← `staging` only; `production` ← `main` only.
- **`production` → Required reviewers: `pj1227`.** Each production deploy waits for
  you to click **Approve**. That's your release button after signing off on test.

### ☑ P2 — Update the required checks — done 2026-10-18 (via `gh`, on PR #83)
- Replace the N1 check names with the new CI jobs in all three rulesets. Exact names:
  `lint`, `static-analysis`, `test-php (8.5)`, `test-php (8.4)`, `test-js`, `contract`,
  `e2e`, `contrast`, `audit`.
- Order matters: the new checks must have run once (on the iteration 1.1 PR) before
  they can be required, and that PR can't merge until the old names are removed.
- **Done:** all three rulesets require the 9 names (GitHub Actions app), the old two are
  gone, and every other rule was verified unchanged against a backup taken first.

### ☑ P3 — Retire the old stack (services themselves: see N8) — done 2026-10-18
- Delete the secrets `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_API_TOKEN`, `RAILWAY_TOKEN`.
- Delete the environments `churchos-staging (Production)` and
  `responsible-spontaneity / production`.
- ~~Delete the Cloudflare Pages projects and the Railway service~~ done early in N8.
  Delete the now-empty Railway project `responsible-spontaneity` if it still exists.
- **Done:** the 3 secrets and 2 environments deleted via `gh` (the repo now has none;
  P1 creates `test` and `production`). Railway project deleted by the owner. The old
  deploy workflows were removed in PR #83.

### ☑ P4 — Dependabot version updates — done 2026-10-18 (PR #83)
- `.github/dependabot.yml`: npm and GitHub Actions (including the local composite
  action), weekly on Monday (America/Denver), one grouped PR per ecosystem, into `dev`,
  with a 7-day cooldown (releases younger than a week are skipped). Composer entries are
  added in iterations 1.4 and 1.5, when the PHP packages exist.
- Dependabot security updates are back on (see N7); they target the default branch,
  now `dev`.

### ☑ P5 — Which Actions may run — done 2026-10-18 (owner, at organisation level)
- Organization → Settings → Actions → General → **Allow chapelcore, and select
  non-chapelcore, actions and reusable workflows**:
  - ☑ Allow actions created by GitHub
  - ☐ Allow actions by Marketplace verified creators (off on purpose)
  - Allowed: `pnpm/action-setup@*, shivammathur/setup-php@*`
  - ☑ **Require actions to be pinned to a full-length commit SHA**
- This matches the dependency-approval rule: a newly approved Action is added to this
  list in the same PR that first uses it.

### ☑ P6 — Branch cleanup — done 2026-10-18
- Branches whose commits exist nowhere else are kept as tags named
  `archive/branch/<name>`; then every old branch is deleted, on GitHub and locally.
  Only `dev`, `staging` and `main` remain.
- **Done:** 7 annotated tags pushed (`chore/supabase-keepalive`,
  `docs/claude-md-consolidation`, `feature/docs-phase-7`, `feature/phase-4-admin-dashboard`,
  `feature/phase-5b-prayer-completion` (was local only), `feature/phase-8a-site-content-cms`,
  `fix/rls-recursion-and-site-config`), each verified to point at its branch tip. Then
  32 branches deleted on GitHub and 32 locally.

---

## 3a. Repository home (2026-10-18)

- The repo now lives in the **`chapelcore` organisation** (Free plan, owner `pj1227` as
  admin, membership hidden): `chapelcore/chapelcore`, public. The old `pj1227/churchos`
  URL redirects. Moved with the ChurchOS → ChapelCore rename (PLAN D19).
- **A transfer resets secret scanning and push protection to off.** They were re-enabled
  straight after the move; check them after any future transfer.
- Rulesets, Dependabot alerts, private vulnerability reporting, the read-only workflow
  token, auto-delete of merged branches and open PRs all carried over.
- Org-level settings now also apply. When doing P5 (which Actions may run), set it at
  **organisation** level: Organization → Settings → Actions → General.
- **Stay public:** on the Free plan a private repo loses rulesets, unlimited Actions
  minutes, push protection, environment approvals and Pages.

## 4. Review checklist (every release PR)

- [ ] Required checks in all three rulesets match the CI job names
- [ ] No new secrets or environments you don't recognise
- [ ] No new outside integrations (Settings → GitHub Apps / Integrations)
- [ ] Dependabot and secret-scanning alerts are empty or handled
- [ ] No stale branches left from merged work

## 5. Review log

| Date | By | Notes |
|---|---|---|
| 2026-10-09 | Claude Code | Initial audit (§1); recommendations N0–N6, P1–P6 |
| 2026-10-09 | Claude Code | Found the Cloudflare GitHub App also building this repo (N0b). The red ✕ on `main` (d20348d) is Railway's own deploy cancelled by the simultaneous workflow deploy |
| 2026-10-18 | Owner + Claude Code | Repo transferred to `chapelcore/chapelcore` (org created by owner); secret scanning + push protection re-enabled after the transfer reset them; local remote updated |
| 2026-10-09 | Owner + Claude Code | N7 done (#81 closed unmerged; security updates off, alerts on). N8 done: Railway service `churchos` deleted (old API URL returns 404); Cloudflare Pages `churchos` (`churchos-bq2.pages.dev`) and `churchos-staging` deleted after clearing ~330 old deployments with a one-off API script (both hostnames no longer resolve); old Supabase project `churchos-libbynaz` paused (resumable until 2027-10-09). All "Do now" items complete |
| 2026-10-09 | Owner + Claude Code | N4 and N5 done (verified via API: auto-delete on, rebase off; Dependabot alerts + security updates, secret scanning, push protection, private vulnerability reporting on). 97 Dependabot alerts in the archived JS stack, 0 secret-scanning alerts; Dependabot PR #81 opened against `main`. Added N7 and N8 |
| 2026-10-18 | Owner + Claude Code | Iteration 1.1 (PR #83): P2–P6 done; default branch changed to `dev`; Dependabot security updates resumed (N7) |
| 2026-10-09 | Owner | N3 done (classic protection removed from `main` and `staging`, verified via API). N0b done: Cloudflare Workers and Pages app uninstalled (`churchos` was its only repo) |
| 2026-10-09 | Owner + Claude Code | N1 + N2 done: rulesets re-imported (new ids `protect-dev` 24813168, `protect-staging` 24813175, `protect-main` 24813187); both CI checks required from GitHub Actions (app 15368), merge methods squash → `dev`, merge commit → `staging`/`main`. Verified via API; PR #80 mergeable and clean. N6 was already read-only. Ruleset JSON exports kept in the owner's `Documents/GitHub/churchos` folder (`*.original.json` = before) |
| 2026-10-09 | Owner | N0 done: Railway source disconnected (project `f4a80fcb-…`); `churchos` removed from the Railway GitHub App's repository access, `devfolio` kept. The old Railway API service is still running (unused) |
