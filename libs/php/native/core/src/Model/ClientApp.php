<?php

/**
 * ClientApp.php — which ChapelCore app sent a browser error report (DOMAIN-MODEL §5.8).
 *
 * Why: the admin and the public site are built and released separately, so a report
 * has to say which one failed before its appVersion and path mean anything.
 *
 * How it connects: ClientErrorReport.app; the parser reads it from the transport value.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Model;

enum ClientApp: string
{
    case Admin = 'admin';
    case Web = 'web';
}
