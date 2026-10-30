<?php

/**
 * SerializerTest.php — tests the one place model objects become transport JSON.
 *
 * Why: DOMAIN-MODEL §8: serialization to camelCase happens once, here. Every field is
 * always present (null, never missing), an empty map is `{}` and never `[]`, and every
 * shape must pass its contract schema. These tests check the output against the
 * schemas through the contract's ContractValidator, the same schemas the TypeScript
 * side and the contract suite use.
 *
 * How it connects: tests src/Transport/Serializer.php with objects from
 * tests/Examples.php; Laravel (1.5) sends what the Serializer returns, and the log
 * (checkpoint 4b) writes its LogEntry lines.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Transport;

use ChapelCore\Contract\ContractValidator;
use ChapelCore\Core\Model\ApiError;
use ChapelCore\Core\Model\DoctorCheck;
use ChapelCore\Core\Model\ErrorCode;
use ChapelCore\Core\Model\ExceptionInfo;
use ChapelCore\Core\Model\HealthCheck;
use ChapelCore\Core\Model\LogEntry;
use ChapelCore\Core\Model\LogLevel;
use ChapelCore\Core\Model\PageInfo;
use ChapelCore\Core\Model\Status;
use ChapelCore\Core\Tests\Examples;
use ChapelCore\Core\Transport\Serializer;
use ChapelCore\Core\Version\ModelVersion;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class SerializerTest extends TestCase
{
    /** @return iterable<string, array{object, string}> */
    public static function objectsAndTheirSchemas(): iterable
    {
        yield 'VersionInfo' => [Examples::version(), 'version-info'];
        yield 'ServedBy' => [Examples::servedBy(), 'served-by'];
        yield 'PageInfo' => [new PageInfo(2, 20, 41), 'page-info'];
        yield 'ResponseMeta without page' => [Examples::meta(), 'response-meta'];
        yield 'ResponseMeta with page' => [Examples::meta(new PageInfo(2, 20, 41)), 'response-meta'];
        yield 'HealthCheck ok' => [new HealthCheck('database', Status::Ok, null), 'health-check'];
        yield 'HealthCheck down' => [new HealthCheck('database', Status::Down, 'Database unavailable'), 'health-check'];
        yield 'HealthStatus ok' => [Examples::healthy(), 'health-status'];
        yield 'HealthStatus down' => [Examples::databaseDown(), 'health-status'];
        yield 'ExceptionInfo' => [Examples::exceptionInfo(), 'exception-info'];
        yield 'ExceptionInfo chained' => [Examples::exceptionInfo(new ExceptionInfo('ErrorException', 'Permission denied', null, null, [], null)), 'exception-info'];
        yield 'LogEntry' => [Examples::logEntry(), 'log-entry'];
        yield 'LogEntry outside a request' => [self::cronEntry([]), 'log-entry'];
        yield 'DoctorCheck ok' => [new DoctorCheck('php.version', Status::Ok, 'PHP 8.5.11', null), 'doctor-check'];
        yield 'DoctorCheck down' => [new DoctorCheck('php.extensions', Status::Down, 'Missing: intl', 'Enable intl in cPanel → PHP Tweaks → Extensions.'), 'doctor-check'];
        yield 'DoctorCheck without message' => [new DoctorCheck('php.argon2', Status::Ok, null, null), 'doctor-check'];
        yield 'ClientErrorReport' => [Examples::clientErrorReport(), 'client-error-report'];
    }

    #[DataProvider('objectsAndTheirSchemas')]
    public function testEachObjectMatchesItsSchema(object $object, string $schema): void
    {
        $serializer = new Serializer();

        self::assertSame([], (new ContractValidator())->errorsInJson($serializer->json($serializer->toTransport($object)), $schema));
    }

    public function testWritesAHealthResponseExactly(): void
    {
        $serializer = new Serializer();
        $json = $serializer->json($serializer->envelope(Examples::databaseDown(), Examples::meta()));

        $expected = '{"data":{"status":"down","version":{"version":"1.0.0","codename":"Kootenai"},'
            . '"checks":[{"name":"database","status":"down","message":"Database unavailable"},'
            . '{"name":"scheduler","status":"ok","message":null},{"name":"storage","status":"ok","message":null}]},'
            . '"meta":{"requestId":"7F3K-92QD","modelVersion":"' . ModelVersion::CURRENT . '",'
            . '"servedBy":{"backend":"laravel","backendVersion":"13.35.0","language":"php","languageVersion":"8.5.11"},'
            . '"page":null}}';
        self::assertSame($expected, $json);
        self::assertSame([], (new ContractValidator())->errorsInJson($json, 'health-response'));
    }

    public function testAnErrorWithoutFieldsHasAnEmptyObject(): void
    {
        $serializer = new Serializer();
        $json = $serializer->json($serializer->errorEnvelope(new ApiError(ErrorCode::NotFound, 'Not found'), Examples::meta()));

        self::assertStringContainsString('"fields":{}', $json);
        self::assertSame([], (new ContractValidator())->errorsInJson($json, 'error-envelope'));
    }

    public function testAnErrorWithFieldsMatchesTheSchema(): void
    {
        $serializer = new Serializer();
        $error = new ApiError(ErrorCode::ValidationFailed, 'Check the highlighted fields', ['message' => ['Required']]);
        $json = $serializer->json($serializer->errorEnvelope($error, Examples::meta()));

        self::assertStringContainsString('"error":{"code":"VALIDATION_FAILED","message":"Check the highlighted fields","fields":{"message":["Required"]}}', $json);
        self::assertSame([], (new ContractValidator())->errorsInJson($json, 'error-envelope'));
    }

    public function testDoesNotEscapeSlashesOrUnicode(): void
    {
        $serializer = new Serializer();

        self::assertSame('{"path":"/admin","name":"Libby café"}', $serializer->json(['path' => '/admin', 'name' => 'Libby café']));
    }

    public function testRejectsAnObjectThatIsNotPartOfTheModel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Serializer())->toTransport(new stdClass());
    }

    /** @return iterable<string, array{object, string}> */
    public static function objectsAndTheirFixtures(): iterable
    {
        yield 'LogEntry' => [Examples::logEntry(), 'log-entry/error-with-exception'];
        yield 'ExceptionInfo' => [Examples::exceptionInfo(), 'exception-info/simple'];
        yield 'ExceptionInfo chained' => [
            Examples::exceptionInfo(new ExceptionInfo('ErrorException', 'Permission denied', null, null, [], null)),
            'exception-info/chained',
        ];
        yield 'DoctorCheck ok' => [new DoctorCheck('php.version', Status::Ok, 'PHP 8.5.11', null), 'doctor-check/ok'];
        yield 'DoctorCheck missing extension' => [
            new DoctorCheck('php.extensions', Status::Down, 'The intl extension is missing.', 'In cPanel, open "Select PHP Version", tick "intl" and click Save.'),
            'doctor-check/missing-extension',
        ];
        yield 'ClientErrorReport' => [Examples::clientErrorReport(), 'client-error-report/admin'];
    }

    #[DataProvider('objectsAndTheirFixtures')]
    public function testWritesExactlyTheContractFixture(object $object, string $fixture): void
    {
        $serializer = new Serializer();
        $file = dirname(ContractValidator::schemasDir()) . "/fixtures/valid/{$fixture}.json";
        $expected = json_encode(
            json_decode((string) file_get_contents($file), flags: JSON_THROW_ON_ERROR),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        self::assertSame($expected, $serializer->json($serializer->toTransport($object)));
    }

    public function testALogEntryTimeAlwaysHasMilliseconds(): void
    {
        $serializer = new Serializer();

        self::assertStringContainsString('"occurredAt":"2026-10-21T15:05:00.000Z"', $serializer->json($serializer->toTransport(self::cronEntry([]))));
    }

    public function testAnEmptyContextIsAnEmptyObject(): void
    {
        $serializer = new Serializer();

        self::assertStringContainsString('"context":{}', $serializer->json($serializer->toTransport(self::cronEntry([]))));
    }

    public function testAListContextIsStillAnObject(): void
    {
        $serializer = new Serializer();
        $json = $serializer->json($serializer->toTransport(self::cronEntry(['first', 'second'])));

        self::assertStringContainsString('"context":{"0":"first","1":"second"}', $json);
        self::assertSame([], (new ContractValidator())->errorsInJson($json, 'log-entry'));
    }

    /** @param array<array-key, mixed> $context */
    private static function cronEntry(array $context): LogEntry
    {
        return new LogEntry(
            '3f2b8c1e-9d4a-4f6b-8e2c-1a7d5b9e0c3f',
            new DateTimeImmutable('2026-10-21T15:05:00Z'),
            LogLevel::Info,
            'scheduler',
            'Heartbeat recorded',
            null,
            $context,
            null,
        );
    }
}
