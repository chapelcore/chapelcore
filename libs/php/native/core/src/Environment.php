<?php

/**
 * Environment.php — where ChapelCore is running: dev, test or production.
 *
 * Why: some doctor checks only make sense on a real server (https:// addresses,
 * certificates) or only in production (debug mode off). A check that isn't run reports
 * "Not checked in {env}" (DOMAIN-MODEL §5.8), using these names, which match the
 * environments in CLAUDE.md and the GitHub deploy environments.
 *
 * How it connects: the app.url, app.debug and tls.certificate probes take one; Laravel
 * (1.5) maps its APP_ENV to it.
 */

declare(strict_types=1);

namespace ChapelCore\Core;

enum Environment: string
{
    case Dev = 'dev';
    case Test = 'test';
    case Production = 'production';
}
