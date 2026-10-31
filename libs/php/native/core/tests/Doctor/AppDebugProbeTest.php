<?php

/**
 * AppDebugProbeTest.php — tests the doctor's `app.debug` check.
 *
 * Why: debug mode is for developers; left on in production it can expose code and
 * settings, so it is down there (DOMAIN-MODEL §5.8). It is only checked in production:
 * on dev and test, debug may well be on deliberately.
 *
 * How it connects: tests src/Doctor/AppDebugProbe.php; Laravel (1.5) passes its
 * environment and its debug setting.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Doctor;

use ChapelCore\Core\Doctor\AppDebugProbe;
use ChapelCore\Core\Environment;
use ChapelCore\Core\Model\Status;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AppDebugProbeTest extends TestCase
{
    public function testDebugOffInProductionIsOk(): void
    {
        $check = (new AppDebugProbe(Environment::Production, false))->check();

        self::assertSame(['app.debug', Status::Ok, 'Debug is off', null], [$check->name, $check->status, $check->message, $check->fix]);
    }

    public function testDebugOnInProductionIsDownWithTheFix(): void
    {
        $check = (new AppDebugProbe(Environment::Production, true))->check();

        self::assertSame(
            [Status::Down, 'Debug is on in production, so error pages could show code and settings', "Set APP_DEBUG=false in the server's .env."],
            [$check->status, $check->message, $check->fix],
        );
    }

    /** @return iterable<string, array{Environment, string}> */
    public static function otherEnvironments(): iterable
    {
        yield 'dev' => [Environment::Dev, 'Not checked in dev'];
        yield 'test' => [Environment::Test, 'Not checked in test'];
    }

    #[DataProvider('otherEnvironments')]
    public function testIsOnlyCheckedInProduction(Environment $environment, string $message): void
    {
        $check = (new AppDebugProbe($environment, true))->check();

        self::assertSame([Status::Ok, $message, null], [$check->status, $check->message, $check->fix]);
    }
}
