<?php

/**
 * AppUrlProbeTest.php — tests the doctor's `app.url` check.
 *
 * Why: on test and production the site must be served over https:// (session cookies
 * are Secure, CLAUDE.md), and links and redirects are built from APP_URL. On a
 * developer's machine plain http://localhost is normal, so the check isn't run in dev
 * and says so (DOMAIN-MODEL §5.8).
 *
 * How it connects: tests src/Doctor/AppUrlProbe.php; Laravel (1.5) passes its
 * environment and its configured app URL.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Doctor;

use ChapelCore\Core\Doctor\AppUrlProbe;
use ChapelCore\Core\Environment;
use ChapelCore\Core\Model\Status;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AppUrlProbeTest extends TestCase
{
    public function testAnHttpsUrlIsOkAndShown(): void
    {
        $check = (new AppUrlProbe(Environment::Production, 'https://libbynaz.org'))->check();

        self::assertSame(['app.url', Status::Ok, 'https://libbynaz.org', null], [$check->name, $check->status, $check->message, $check->fix]);
    }

    /** @return iterable<string, array{Environment, string, string}> */
    public static function wrongUrls(): iterable
    {
        yield 'http on test' => [Environment::Test, 'http://test.libbynaz.org', 'APP_URL is http://test.libbynaz.org, not an https:// address'];
        yield 'http on production' => [Environment::Production, 'http://libbynaz.org', 'APP_URL is http://libbynaz.org, not an https:// address'];
        yield 'not set' => [Environment::Production, '', 'APP_URL is not set'];
    }

    #[DataProvider('wrongUrls')]
    public function testAnythingElseIsDownWithTheFix(Environment $environment, string $url, string $message): void
    {
        $check = (new AppUrlProbe($environment, $url))->check();

        self::assertSame(
            [Status::Down, $message, "Set APP_URL to the site's https:// address."],
            [$check->status, $check->message, $check->fix],
        );
    }

    public function testIsNotCheckedInDev(): void
    {
        $check = (new AppUrlProbe(Environment::Dev, 'http://localhost:3000'))->check();

        self::assertSame([Status::Ok, 'Not checked in dev', null], [$check->status, $check->message, $check->fix]);
    }
}
