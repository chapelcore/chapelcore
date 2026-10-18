# Runbook — Local development

> What you need on your machine to build and test ChapelCore, and how to set it up.
> Written for Linux or WSL (Windows Subsystem for Linux).
> **Last verified:** 2026-10-18 (Phase 1 iteration 1.1).

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
| Composer | 2.10.3 | CI (`setup-php` tools); not used until iteration 1.4 |
| MariaDB | 11.4 | Matches the production host |

Install and update steps for PHP, Composer and MariaDB are added here when the PHP
iterations (1.4, 1.5) first need them.

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

Then run any of the checks in [CLAUDE.md → Commands](../../CLAUDE.md#commands).
