<?php

/**
 * ModelVersionTest.php — checks ModelVersion::CURRENT matches DOMAIN-MODEL.md.
 *
 * Why: every response carries meta.modelVersion (DOMAIN-MODEL §4). The constant
 * must be the version in the model document's header, so a model change without
 * a code change fails here.
 *
 * How it connects: tests src/Version/ModelVersion.php; reads
 * docs/design/DOMAIN-MODEL.md from the repository root.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Version;

use ChapelCore\Core\Version\ModelVersion;
use PHPUnit\Framework\TestCase;

final class ModelVersionTest extends TestCase
{
    public function testHasTheModelVersionFormat(): void
    {
        self::assertMatchesRegularExpression('/^\d+\.\d+$/', ModelVersion::CURRENT);
    }

    public function testMatchesTheVersionInDomainModel(): void
    {
        $document = (string) file_get_contents(dirname(__DIR__, 6) . '/docs/design/DOMAIN-MODEL.md');

        $documented = preg_match('/Model version: \*\*(\d+\.\d+)\*\*/', $document, $match) === 1 ? $match[1] : null;

        self::assertSame($documented, ModelVersion::CURRENT);
    }
}
