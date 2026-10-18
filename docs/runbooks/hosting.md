# Runbook — Hosting (Namecheap shared hosting, cPanel)

> **Maintainer runbook** for the Libby deployment. It is also the raw material for the
> setup help articles in `docs/help/setup/` and the Phase 12 new-church guide.
> **Last verified:** 2026-10-16 (hosting session, Phase 1 iteration 1.2).
> **Review:** at every release, and whenever the host changes something.

Values that identify the account (cPanel username, server IP and server name) are written as
placeholders here because this repository is public. The real values are in the owner's
records and in the GitHub environment secrets.

| Placeholder | Meaning |
|---|---|
| `<cpanel-user>` | cPanel username (also the SSH user) |
| `<server-ip>` | The account's shared IP address |
| `<server-host>` | The server's hostname (`<name>.web-hosting.com`) |

---

## 1. The account

- Namecheap **Stellar Business** shared hosting, cPanel 134, CloudLinux (CageFS, PHP selector)
- Web server: **LiteSpeed** (reads Apache `.htaccess`), with HAProxy in front
- Database: **MariaDB 11.4.13**. Server default charset `latin1`, so ChapelCore always uses `utf8mb4`.
- DNS: Namecheap hosting nameservers (`dns1/dns2.namecheaphosting.com`). New subdomains get
  their DNS records automatically.
- Tools on the server: `git`, `rsync`, `tar`, `curl`. **No Composer**, which is fine: CI uploads
  finished builds including `vendor/`.
- Server time zone: US Eastern.

## 2. SSH

1. cPanel → **Manage Shell** → turn on **Enable SSH access**. The SSH Access page on its own
   is not enough: without this switch the server answers *"Shell access is not enabled on
   your account!"*
2. cPanel → **SSH Access** → **Manage SSH Keys** → **Import Key**. Paste only the *public*
   key, leave the private-key and passphrase boxes empty, then **Manage → Authorize**.
3. Connect: `ssh -p 21098 <cpanel-user>@<server-ip>`

**Server host key fingerprint** (check it on first connect; deploys verify it automatically):

| Type | Fingerprint |
|---|---|
| ED25519 | `SHA256:5W6bO2cDCpyMDfQy9q7LES2Ne+91z4yEKs0eD74fRtg` |
| ECDSA | `SHA256:mSHJJQAC3uiy1zWXvURVILbcLdzdTyNmaZFvLZlhozw` |
| RSA | `SHA256:SfhIaVdWtfD3oUDBXJDMlKUC6ADDsZkzmLEPvCAGtqk` |

**Keys in use:**

| cPanel name | Purpose | Where the private key lives |
|---|---|---|
| `pj-ws1` | Owner's own access (and Claude Code, with the owner's approval per command) | Owner's machine |
| `chapelcore-deploy-test` | GitHub Actions deploys to **test** | GitHub environment `test` (from iteration 1.9) |
| *(to create)* `chapelcore-deploy-prod` | GitHub Actions deploys to **production** | GitHub environment `production` |

Separate keys per environment mean a leaked test key can never reach production. To revoke
a key, delete it in Manage SSH Keys.

**Verified 2026-10-16:** a GitHub-hosted runner checked the pinned ED25519 fingerprint,
logged in with the test deploy key and wrote a file (risk R4).

## 3. PHP

- **Set the version:** cPanel → **PHP Tweaks** → **Account settings** → **8.5**. The version
  radio buttons elsewhere on that page only choose *which version's extensions you're
  editing*; they don't change the active version.
- **Then set the extensions.** Extensions belong to the active version, so set the version
  first. Required (greyed-out ones are built in and always on; the "Can't be enabled… Built in"
  pop-up is harmless):
  `bcmath ctype curl dom fileinfo filter hash intl mbstring openssl pdo pdo_mysql session
  sodium tokenizer xml xmlwriter zip` plus OPcache.
- **Check from SSH:** `php -v` (command line) and the deployed `/health` (web) should both
  show 8.5. Argon2id: `php -r 'var_dump(defined("PASSWORD_ARGON2ID"));'`
- The selector's state is in `~/.cl.selector/defaults.cfg` (`[versions] php = 8.5`).
- The account can't change `realpath_cache_ttl` (120 s). See §5.

**Verified 2026-10-16:** command line and web both PHP 8.5.11, all extensions present, Argon2id available.

## 4. Domains and document roots

| Hostname | Document root (relative to home) | Serves |
|---|---|---|
| `libbynaz.org`, `www.libbynaz.org` | `public_html` | The current live site; ChapelCore admin will appear at `/admin` |
| `test.libbynaz.org` | `chapelcore/test/current/web` | ChapelCore test site (and `/admin`) |
| `api.test.libbynaz.org` | `chapelcore/test/current/api/public` | ChapelCore test API |
| `api.libbynaz.org` | `chapelcore/prod/current/api/public` | ChapelCore production API (placeholder "not deployed yet", 503, until 0.1.0) |

- **Create:** cPanel → **Domains** → **Create A New Domain** → **Registered Domain** → enter
  the hostname → **untick "Share document root"** → document root *relative to home* (don't
  type `/home/…`) → **Submit**.
- **Change a document root:** Domains → **Manage** → **New Document Root** → **Update**. It
  takes about a minute to apply.
- The document-root folder must exist *before* creating the domain if it's a path through a
  symlink; otherwise cPanel creates a plain folder in the way.
- cPanel sets document-root folders to owner `<cpanel-user>`, group `nobody`, mode `750`.
- **Removing a domain** never deletes its files.
- The old 2025 test site is still in `~/test.libbynaz.org/`, unused.

## 5. ChapelCore folder layout on the server

```
~/chapelcore/<env>/            <env> = test | prod
├── releases/<commit>/       api/ (Laravel + vendor) · admin/ · web/   (with web/admin → ../admin)
├── shared/.env              created once by hand; never in git
├── shared/storage/          logs, cache
├── shared/.well-known/      certificate-validation files, linked into each release's docroots
└── current → releases/<commit>
```

- **Switching releases** is one atomic rename:
  `ln -sfn releases/<new> current.next && mv -T current.next current`
- **Caching caveat:** after a switch, PHP keeps serving the *previous* release for up to
  **~2 minutes** (`realpath_cache_ttl`). Deploys and rollbacks must therefore poll `/health`
  until the expected version answers, and migrations must work with the previous release.
- Files are deployed with `755` folders and `644` files (the shell's default umask gives `775/664`).
- **Why `shared/.well-known`:** the host's certificate tools write validation files into
  each document root's `.well-known/`. Inside a release they would vanish on the next deploy
  and renewals would fail.

## 6. Databases

| Environment | Database | User | Privileges |
|---|---|---|---|
| test | `<cpanel-user>_chapelcore_test` | `<cpanel-user>_cc_test` | ALL, on its own database only |
| prod | `<cpanel-user>_chapelcore_prod` | `<cpanel-user>_cc_prod` | ALL, on its own database only |

- Create in cPanel → **Database Wizard** / **MySQL Databases**. cPanel adds the `<cpanel-user>_` prefix.
- Passwords: generated in cPanel, kept in the owner's password manager, written only into
  `shared/.env` on the server. Never into git, chat or CI logs.
- Collation: phpMyAdmin → database → **Operations** → **Collation** → `utf8mb4_unicode_ci`
  → **Go**. Check it carefully: the neighbouring entry (`utf8mb4_vietnamese_ci`) is easy to pick by mistake.
- Check users and privileges without passwords: `uapi Mysql list_databases`,
  `uapi Mysql get_privileges_on_database user=… database=…`

## 7. TLS certificates (Let's Encrypt via acme.sh, PLAN D18)

**Why:** Namecheap's free "StandardSSL" is a **one-time, one-year promo per hostname**. It
never renews, and re-creating a domain doesn't trigger a new one (Namecheap support,
2026-10-12). The "Namecheap SSL" cPanel tool is only for purchased certificates. Let's
Encrypt is allowed on the plan, but Namecheap won't troubleshoot it, so this runbook is the support.

**Installed:** `acme.sh` **3.1.6** in `~/.acme.sh`, from the official release tarball:
```bash
curl -fsSL -o acme-3.1.6.tar.gz https://github.com/acmesh-official/acme.sh/archive/refs/tags/3.1.6.tar.gz
tar xzf acme-3.1.6.tar.gz && cd acme.sh-3.1.6 && ./acme.sh --install --home ~/.acme.sh
cd ~ && rm -rf acme.sh-3.1.6 acme-3.1.6.tar.gz
~/.acme.sh/acme.sh --set-default-ca --server letsencrypt   # default would be ZeroSSL
```
No account email is registered. Let's Encrypt no longer sends expiry emails; the doctor
check warns 30 days ahead instead.

**Issue and install a certificate** (validation file goes in the document root's `.well-known/`):
```bash
~/.acme.sh/acme.sh --issue -d <host> -w ~/<document-root>
~/.acme.sh/acme.sh --deploy -d <host> --deploy-hook cpanel_uapi
```
For `libbynaz.org`, one certificate covers both names:
`--issue -d libbynaz.org -d www.libbynaz.org -w ~/public_html`, then `--deploy -d libbynaz.org …`.

**Expect noise:** the deploy step prints a long Perl/Go stack trace from **Namecheap's own
post-install hooks** (`NcCustomHooks::SSL::NotifyChanges`, `hc_agent.token: no such file`).
It's harmless. The lines that matter are *"Successfully deployed certificate to 1 of 1 sites"*
and *"Success"*.

**Renewal:** automatic. The installer added one cron line (an exception to the single
ChapelCore cron entry until the Phase 3 scheduler exists):
```
9 4,10,16,22 * * * "/home/<cpanel-user>/.acme.sh"/acme.sh --cron --home "/home/<cpanel-user>/.acme.sh" > /dev/null
```
It renews about 30 days before expiry, choosing the date from Let's Encrypt's suggested window.

**Check:** `~/.acme.sh/acme.sh --list`, and from anywhere:
`echo | openssl s_client -connect <host>:443 -servername <host> | openssl x509 -noout -issuer -enddate`

| Hostname | Issued | Expires | Renews |
|---|---|---|---|
| `test.libbynaz.org` | 2026-10-16 | 2027-01-14 | ~2026-12-16 |
| `api.test.libbynaz.org` | 2026-10-16 | 2027-01-14 | ~2026-12-15 |
| `api.libbynaz.org` | 2026-10-16 | 2027-01-14 | ~2026-12-16 |
| `libbynaz.org` + `www` | 2026-10-16 | 2027-01-14 | ~2026-12-16 |

## 8. Cron

| Entry | Purpose | Status |
|---|---|---|
| acme.sh (above) | Certificate renewal | ✅ installed 2026-10-16 |
| `* * * * * php ~/chapelcore/<env>/current/api/artisan chapelcore:heartbeat` | ChapelCore heartbeat (later the scheduler), one per environment | ⏳ iteration 1.5/1.9 |

Managed in cPanel → **Cron Jobs** (the acme.sh line shows there too).

## 9. Still to do (Phase 1)

- [ ] Production deploy key `chapelcore-deploy-prod` (iteration 1.9)
- [ ] `shared/` folders, `.env` files and the first real releases (iteration 1.9)
- [ ] ChapelCore cron entries (iteration 1.5/1.9)
- [ ] Remove the spike releases (`releases/spike-1`, `spike-2`, `placeholder`) once real releases exist
- [ ] Turn on cPanel **Two-Factor Authentication** for the owner's login (recommended)
