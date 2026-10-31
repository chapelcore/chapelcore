<?php

/**
 * ResponseMetaTest.php — tests the meta block of every response.
 *
 * Why: meta.modelVersion must always be the model version this code implements, so
 * callers can't pass a different one; page is null unless the response is a list.
 *
 * How it connects: tests src/Model/ResponseMeta.php; the Serializer writes it into
 * every success and error envelope.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Model;

use ChapelCore\Core\Model\PageInfo;
use ChapelCore\Core\Tests\Examples;
use ChapelCore\Core\Version\ModelVersion;
use PHPUnit\Framework\TestCase;

final class ResponseMetaTest extends TestCase
{
    public function testCarriesTheModelVersionThisCodeImplements(): void
    {
        self::assertSame(ModelVersion::CURRENT, Examples::meta()->modelVersion);
    }

    public function testHasNoPageUnlessGivenOne(): void
    {
        self::assertNull(Examples::meta()->page);
        self::assertSame(3, Examples::meta(new PageInfo(3, 20, 75))->page?->page);
    }

    public function testKeepsTheReferenceCodeAndBackend(): void
    {
        $meta = Examples::meta();

        self::assertSame(['7F3K-92QD', 'laravel'], [$meta->requestId->value, $meta->servedBy->backend]);
    }
}
