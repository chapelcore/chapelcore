# Runbook — Local development

> What you need on your machine to build and test ChapelCore, and how to set it up.
> Written for Linux or WSL (Windows Subsystem for Linux).
> **Last verified:** 2026-11-13 (Phase 1 iteration 1.5).

Only commands that have been run successfully are listed here. The everyday commands
(tests, formatting, checks) are in [CLAUDE.md → Commands](../../CLAUDE.md#commands).

**Your ChapelCore folder** means the folder you cloned the repository into, for example
`~/projects/chapelcore`. Commands below assume you start in it.

## 1. Versions

| Tool | Version | Pinned in |
|---|---|---|
| Node | 24 LTS (24.22.0 verified) | `.nvmrc` |
| pnpm | 12.14.0 | `package.json` → `packageManager` |
| PHP | 8.5 (8.4 is the oldest supported) | CI matrix, `.github/workflows/ci.yml` |
| Composer | 2.10.3 | `.github/actions/setup-php/action.yml` (CI); §2.1 installs the same |
| MariaDB | 11.4 (11.4.13 verified) | Matches the production host; CI's `mariadb:11.4.13` service |

The PHP core needs only `mbstring`. The Laravel API needs the extensions listed in
[DOMAIN-MODEL §5.8](../design/DOMAIN-MODEL.md), with `pdo_mysql` for MariaDB;
`php artisan chapelcore:doctor` names any that are missing (§6.6). To install them, see
§2.1. Check what you have:

```bash
php --version
```

```bash
composer --version
```

## 2. One-time setup

### 2.1 PHP, MariaDB and Composer

Verified 2026-11-13 on Ubuntu 24.04 (arm64). Each command can be run again safely; apt
reports "already the newest version" for what is installed.

**PHP 8.5**, from the ondrej PPA (the usual source of current PHP for Ubuntu):

```bash
sudo apt update
```

```bash
sudo apt install -y software-properties-common
```

```bash
sudo add-apt-repository -y ppa:ondrej/php
```

```bash
sudo apt install -y php8.5-cli php8.5-bcmath php8.5-curl php8.5-intl php8.5-mbstring php8.5-mysql php8.5-xml php8.5-zip
```

```bash
sudo update-alternatives --set php /usr/bin/php8.5
```

These packages give every extension the doctor checks; OPcache is built into PHP 8.5.
`php --version` should show `PHP 8.5.x`. To test on 8.4 as CI does (optional), install the
same packages with `php8.4-` in place of `php8.5-`.

The PPA says it is moving to `packages.sury.org`; Ubuntu 24.04 can keep using it for
now. These steps change when its author publishes how to move.

**MariaDB 11.4**, from MariaDB's own repository (Ubuntu 24.04 ships an older version).
Download MariaDB's setup script and check it before running it:

```bash
cd ~ && curl -fsSLO https://r.mariadb.com/downloads/mariadb_repo_setup
```

See which version it is (this reads the file without running it):

```bash
grep -m1 '^version=' mariadb_repo_setup
```

Find that version's checksum in the "Versions" table at the bottom of
[MariaDB Package Repository Setup and Usage](https://mariadb.com/docs/server/server-management/install-and-upgrade-mariadb/installing-mariadb/binary-packages/mariadb-package-repository-setup-and-usage),
put it in place of `<checksum>`, and check the file. It must print
`mariadb_repo_setup: OK`; if it doesn't, stop:

```bash
echo "<checksum>  mariadb_repo_setup" | sha256sum -c -
```

Then add the repository (kept on the 11.4 line the host runs, without MaxScale, a proxy
we don't use), install, start and clean up:

```bash
sudo bash mariadb_repo_setup --mariadb-server-version=mariadb-11.4 --skip-maxscale
```

```bash
sudo apt install -y mariadb-server mariadb-client
```

```bash
sudo service mariadb start
```

```bash
rm mariadb_repo_setup
```

`mariadb --version` should show `11.4.x`. If a repository file was already there, the
script keeps it as `mariadb.sources.old_1.disabled`, which apt ignores.

**Composer 2.10.3** (the version CI uses), through Composer's installer with its
signature checked. The second command must print `Installer verified`:

```bash
cd ~ && php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
```

```bash
php -r "if (hash_file('sha384', 'composer-setup.php') === trim(file_get_contents('https://composer.github.io/installer.sig'))) { echo 'Installer verified'.PHP_EOL; } else { echo 'Installer corrupt'.PHP_EOL; unlink('composer-setup.php'); exit(1); }"
```

```bash
php composer-setup.php --version=2.10.3 && rm composer-setup.php
```

```bash
sudo mv composer.phar /usr/local/bin/composer
```

`composer --version` should show `Composer version 2.10.3`.

### 2.2 Node and pnpm

**Node**, through [fnm](https://github.com/Schniz/fnm). From your ChapelCore folder:

```bash
fnm install
```

This reads `.nvmrc` and installs Node 24 if it isn't already installed. If fnm's
automatic switching is off (the default), switch by hand in each new terminal:

```bash
fnm use
```

**pnpm**, through Corepack (included with Node 24). This adds a `pnpm` launcher to the
active Node version only; other Node versions keep their own pnpm:

```bash
corepack enable pnpm
```

Check it. Corepack downloads the pinned version on first use, so expect `12.14.0`:

```bash
pnpm --version
```

## 3. Each session

From your ChapelCore folder:

```bash
fnm use
```

```bash
pnpm install
```

```bash
composer install --working-dir=libs/shared/contract
```

```bash
composer install --working-dir=libs/php/native/core
```

```bash
composer install --working-dir=apps/api/laravel
```

MariaDB must be running for the Laravel app and its tests. On WSL without systemd, start
it after each restart of WSL (or add it to `/etc/wsl.conf`, see §6.1):

```bash
sudo service mariadb start
```

Then run any of the checks in [CLAUDE.md → Commands](../../CLAUDE.md#commands).

## 4. The contract (`libs/shared/contract`)

The contract turns [DOMAIN-MODEL.md](../design/DOMAIN-MODEL.md) into files a computer can
check: JSON Schemas, example data (fixtures) and `openapi.json`. The same tests run in
TypeScript and in PHP, so both languages must accept and reject exactly the same data.
How to add a schema or fixture: [libs/shared/contract/README.md](../../libs/shared/contract/README.md).

Run both test suites from your ChapelCore folder:

```bash
pnpm --filter @chapelcore/contract test
```

```bash
composer --working-dir=libs/shared/contract test
```

The TypeScript suite should end with 250 passing tests and the PHP suite with 258 (the
PHP suite also tests `ContractValidator`). The counts grow as the model does.

## 5. The PHP core (`libs/php/native/core`)

Plain PHP 8.4+ with no framework. It holds the business rules (model objects, the one
serializer, health and doctor rules, reference codes, redaction, the JSON logger and the
client error report parser); Laravel only wires it in (iteration 1.5). Its tests check
everything it serializes against the contract schemas.

From your ChapelCore folder:

```bash
composer --working-dir=libs/php/native/core test
```

It should end with 414 passing tests. PHPStan must report no errors:

```bash
composer --working-dir=libs/php/native/core analyse
```

## 6. The Laravel API (`apps/api/laravel`)

A thin adapter over the PHP core: routes, HTTP, the database, storage and configuration.
It serves `GET /health` and `POST /diagnostics/client-errors`, and has two commands,
`chapelcore:heartbeat` and `chapelcore:doctor`. Its tests run against a real MariaDB.

### 6.1 MariaDB service

Check that MariaDB is running:

```bash
sudo service mariadb status
```

To start it automatically when WSL starts, `/etc/wsl.conf` can contain:

```ini
[boot]
command = "service mariadb start"
```

It takes effect after `wsl --shutdown`, run from Windows PowerShell (inside Ubuntu,
`wsl` is an unrelated tool).

### 6.2 The two local databases (one time)

The app uses `chapelcore_dev` while you work with it and `chapelcore_test` for its tests.
The tests empty their database, so the two must stay separate. Both use `utf8mb4`
(CLAUDE.md security rules; MariaDB's default on many hosts is `latin1`).

Open MariaDB as its administrator (on Ubuntu, `root` signs in through `sudo`):

```bash
sudo mariadb
```

Then run these statements, choosing your own password for `chapelcore_dev`. The test
user's password must be `chapelcore_test`, because `phpunit.xml` and CI use that value
(it is a throwaway local value, not a secret):

```sql
CREATE DATABASE chapelcore_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'chapelcore_dev'@'127.0.0.1' IDENTIFIED BY 'your-dev-password';
GRANT ALL PRIVILEGES ON chapelcore_dev.* TO 'chapelcore_dev'@'127.0.0.1';

CREATE DATABASE chapelcore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'chapelcore_test'@'127.0.0.1' IDENTIFIED BY 'chapelcore_test';
GRANT ALL PRIVILEGES ON chapelcore_test.* TO 'chapelcore_test'@'127.0.0.1';
```

Type `exit` to leave. The users are tied to `127.0.0.1`, the address Laravel connects
to, so when you sign in as one of them by hand, give the host:

```bash
mariadb -h 127.0.0.1 -u chapelcore_test -p chapelcore_test
```

`-p` asks for the password; the last word is the database to open.

### 6.3 `.env` (one time)

From your ChapelCore folder, copy the example settings and create the app's encryption
key:

```bash
cp apps/api/laravel/.env.example apps/api/laravel/.env
```

```bash
php apps/api/laravel/artisan key:generate
```

Then open `apps/api/laravel/.env` and set `DB_PASSWORD` to the password you chose in
§6.2. Keep `APP_ENV=local` and `APP_DEBUG=true` on your machine; even with debug on, the
API never sends a stack trace (errors are always our JSON envelope with a reference
code). `CHAPELCORE_ENV=dev` tells the doctor this is a development machine, and an empty
`CHAPELCORE_API_PREFIX` serves the API at the root (`/health`), as on its own host name
at Libby; set it to `api` to try the same-domain layout (`/api/health`). `.env` is never
committed; each setting is explained in `.env.example`.

### 6.4 First migration (one time, and after each new migration)

This creates the tables, so far only `cache` (the rate limiter's counts live there):

```bash
php apps/api/laravel/artisan migrate
```

`php apps/api/laravel/artisan migrate:status` shows which migrations have run.

### 6.5 Run the API

```bash
php apps/api/laravel/artisan serve
```

It listens on `http://localhost:8000` until you press Ctrl+C. In a second terminal:

```bash
curl -i http://localhost:8000/health
```

The answer is `ok`, or `degraded` when the heartbeat is old (cron doesn't run on your
machine). Record one by hand, then ask again:

```bash
php apps/api/laravel/artisan chapelcore:heartbeat
```

Every response carries an `X-Request-Id` reference code. When a request writes to the
log (an error, or an accepted client-error report), its lines in
`apps/api/laravel/storage/logs/` carry the same code.

### 6.6 The doctor

The doctor checks this server and prints a fix for each problem:

```bash
php apps/api/laravel/artisan chapelcore:doctor
```

On your machine expect `degraded` (the heartbeat) and exit code 0. It exits 1 when any
check is `down`; the deploy relies on that. To see how it judges another environment
without editing `.env`, put settings in front of the command. Settings given this way
win over `.env` for that one run:

```bash
CHAPELCORE_ENV=test APP_URL=http://localhost:8000 php apps/api/laravel/artisan chapelcore:doctor
```

That run reports `app.url` (an `http://` address) and `tls.certificate` (no hosts in
`CHAPELCORE_TLS_HOSTS`) as `down`, each with its fix, and exits 1.

### 6.7 Tests and checks

From your ChapelCore folder, with MariaDB running:

```bash
composer --working-dir=apps/api/laravel test
```

It should end with 109 passing tests. PHPStan (with Larastan) must report no errors:

```bash
composer --working-dir=apps/api/laravel analyse
```

Formatting (Pint, Laravel preset) and the audit are in
[CLAUDE.md → Commands](../../CLAUDE.md#commands).
