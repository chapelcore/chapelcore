<?php

/**
 * ContractDependencyTest.php — checks the core can use the contract, at the right version.
 *
 * Why: the core's tests validate everything it serializes against the contract
 * schemas, through the contract's ContractValidator (a local Composer path package).
 * These tests fail loudly if that link breaks or drifts: the contract must be the
 * DOMAIN-MODEL version the core implements, and opis must be the version the
 * contract itself pins (a Dependabot bump in one lockfile but not the other).
 *
 * How it connects: composer.json requires chapelcore/contract from
 * ../../../shared/contract; runs with `composer test`.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests;

use ChapelCore\Contract\ContractValidator;
use ChapelCore\Core\Version\ModelVersion;
use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;

final class ContractDependencyTest extends TestCase
{
    public function testTheContractValidatorIsAvailable(): void
    {
        $errors = (new ContractValidator())->errorsInJson('{"version":"1.0.0","codename":"Kootenai"}', 'version-info');

        self::assertSame([], $errors);
    }

    public function testTheContractVersionIsTheModelVersion(): void
    {
        self::assertSame(ModelVersion::CURRENT . '.0', InstalledVersions::getPrettyVersion('chapelcore/contract'));
    }

    public function testOpisIsTheVersionTheContractPins(): void
    {
        $composer = json_decode(
            (string) file_get_contents(dirname(ContractValidator::schemasDir()) . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($composer);
        self::assertIsArray($composer['require'] ?? null);

        self::assertSame(
            $composer['require']['opis/json-schema'] ?? null,
            InstalledVersions::getPrettyVersion('opis/json-schema'),
        );
    }
}
