# Runbook — Local development

> What you need on your machine to build and test ChapelCore, and how to set it up.
> Written for Linux or WSL (Windows Subsystem for Linux).
> **Last verified:** 2026-10-30 (Phase 1 iteration 1.4).

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
| Composer | 2.10.3 in CI (2.10.x works locally) | `.github/actions/setup-php/action.yml` |
| MariaDB | 11.4 | Matches the production host |

PHP needs the `mbstring` extension (part of most PHP installs). Install steps for PHP,
Composer and MariaDB are added here when an iteration first needs a fresh install
written down (1.5). Check what you have:

```bash
php --version
```

```bash
composer --version
```

## 2. One-time setup

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
