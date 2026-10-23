<?php

/**
 * ModelVersion.php — the DOMAIN-MODEL version this core implements.
 *
 * Why: every response carries it as meta.modelVersion (DOMAIN-MODEL §4), so clients
 * know which model they're talking to. It is a constant because it changes only with
 * the code: a model change updates DOMAIN-MODEL.md, this constant and the contract pin
 * in composer.json together, and the tests fail if any of them disagree.
 *
 * How it connects: ResponseMeta (checkpoint 2) reads it.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Version;

final class ModelVersion
{
    public const string CURRENT = '0.4';

    private function __construct() {}
}
