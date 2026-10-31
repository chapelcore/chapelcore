<?php

/**
 * ClientErrorReportParserTest.php — tests how a browser error report is read from a
 * request body.
 *
 * Why: POST {apiBase}/diagnostics/client-errors is public, so any bytes can arrive.
 * A body over 8 KB gets PAYLOAD_TOO_LARGE before it is decoded; anything else that
 * isn't a valid report gets VALIDATION_FAILED with a message under each field to fix,
 * all at once and in the model's field order (DOMAIN-MODEL §4 and §5.8). The contract
 * fixtures are the definitive examples: every valid one is read back exactly, and
 * every invalid one is refused under exactly the field the fixture names.
 *
 * How it connects: tests src/Transport/ClientErrorReportParser.php; finds the fixtures
 * next to the contract's schemas folder, so a new fixture is picked up without a code
 * change. Laravel (1.5) sends the ApiError carried by RejectedInput.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Transport;

use ChapelCore\Contract\ContractValidator;
use ChapelCore\Core\Model\ApiError;
use ChapelCore\Core\Model\ErrorCode;
use ChapelCore\Core\Tests\Examples;
use ChapelCore\Core\Transport\ClientErrorReportParser;
use ChapelCore\Core\Transport\RejectedInput;
use ChapelCore\Core\Transport\Serializer;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ClientErrorReportParserTest extends TestCase
{
    private const array FIELDS = ['app', 'appVersion', 'path', 'message', 'stack', 'userAgent', 'occurredAt'];

    /** @return iterable<string, array{string}> */
    public static function validFixtures(): iterable
    {
        foreach (self::fixtureFiles('valid') as $name => $file) {
            yield $name => [(string) file_get_contents($file)];
        }
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function invalidFixtures(): iterable
    {
        foreach (self::fixtureFiles('invalid') as $name => $file) {
            /** @var object{expect: object{path: string}, data: mixed} $fixture */
            $fixture = json_decode((string) file_get_contents($file), flags: JSON_THROW_ON_ERROR);
            yield $name => [$fixture->data, explode('/', $fixture->expect->path)[1]];
        }
    }

    /** @return iterable<string, array{string}> */
    public static function notJsonObjects(): iterable
    {
        yield 'empty body' => [''];
        yield 'plain text' => ['oops'];
        yield 'cut-off JSON' => ['{"app":'];
        yield 'a list' => ['[]'];
        yield 'a string' => ['"admin"'];
        yield 'a number' => ['42'];
        yield 'null' => ['null'];
        yield 'nested too deeply to decode' => [str_repeat('[', 600) . str_repeat(']', 600)];
    }

    /** @return iterable<string, array{string, mixed, list<string>}> */
    public static function fieldProblems(): iterable
    {
        yield 'app missing' => ['app', null, ['Required.']];
        yield 'app unknown' => ['app', 'mobile', ['Must be "admin" or "web".']];
        yield 'app not text' => ['app', 1, ['Must be text.']];
        yield 'appVersion not semver' => ['appVersion', '1.0', ['Must be a version number such as 1.0.0.']];
        yield 'path relative with a query string' => ['path', 'admin/status?tab=1', ['Must start with /.', 'Must not include a query string (?) or fragment (#).']];
        yield 'path with a fragment' => ['path', '/admin/status#top', ['Must not include a query string (?) or fragment (#).']];
        yield 'path too long' => ['path', '/' . str_repeat('a', 2048), ['Must be at most 2048 characters.']];
        yield 'message empty' => ['message', '', ['Must not be empty.']];
        yield 'message too long' => ['message', str_repeat('m', 1001), ['Must be at most 1000 characters.']];
        yield 'message not text' => ['message', ['oops'], ['Must be text.']];
        yield 'stack not a list' => ['stack', 'TypeError', ['Must be a list of lines.']];
        yield 'stack an object' => ['stack', new stdClass(), ['Must be a list of lines.']];
        yield 'stack too many lines' => ['stack', array_fill(0, 51, 's'), ['Must have at most 50 lines.']];
        yield 'stack bad lines, numbered from 1' => [
            'stack',
            ['ok', '', 5, str_repeat('s', 501)],
            ['Line 2: Must not be empty.', 'Line 3: Must be text.', 'Line 4: Must be at most 500 characters.'],
        ];
        yield 'userAgent empty' => ['userAgent', '', ['Must not be empty.']];
        yield 'userAgent too long' => ['userAgent', str_repeat('u', 513), ['Must be at most 512 characters.']];
        yield 'occurredAt with an offset' => ['occurredAt', '2026-10-21T09:04:05-06:00', ['Must be a UTC time such as 2026-10-21T15:04:05.123Z.']];
        yield 'occurredAt impossible date' => ['occurredAt', '2026-02-30T00:00:00Z', ['Must be a UTC time such as 2026-10-21T15:04:05.123Z.']];
    }

    #[DataProvider('validFixtures')]
    public function testReadsEveryValidFixtureBackExactly(string $json): void
    {
        $serializer = new Serializer();
        $report = (new ClientErrorReportParser())->fromTransport(json_decode($json, flags: JSON_THROW_ON_ERROR));
        $expected = json_encode(json_decode($json, flags: JSON_THROW_ON_ERROR), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        self::assertSame($expected, $serializer->json($serializer->toTransport($report)));
    }

    #[DataProvider('invalidFixtures')]
    public function testRefusesEveryInvalidFixtureUnderItsField(mixed $data, string $field): void
    {
        $error = self::rejection(static fn() => (new ClientErrorReportParser())->fromTransport($data));

        self::assertSame([ErrorCode::ValidationFailed, [$field]], [$error->code, array_keys($error->fields)]);
    }

    public function testReadsARequestBody(): void
    {
        self::assertEquals(Examples::clientErrorReport(), (new ClientErrorReportParser())->parse(self::body()));
    }

    public function testAcceptsABodyOfExactly8Kb(): void
    {
        $body = str_pad(self::body(), 8192);

        self::assertEquals(Examples::clientErrorReport(), (new ClientErrorReportParser())->parse($body));
    }

    public function testRefusesABodyOver8KbBeforeReadingIt(): void
    {
        $error = self::rejection(static fn() => (new ClientErrorReportParser())->parse(str_pad(self::body(), 8193)));

        self::assertSame([ErrorCode::PayloadTooLarge, 'The error report is larger than 8 KB.', []], [$error->code, $error->message, $error->fields]);
    }

    public function testCountsBytesNotCharacters(): void
    {
        // "é" is 2 bytes in UTF-8, so this body is 8193 bytes but fewer than 8192 characters.
        $body = str_pad(self::body(['message' => str_repeat('é', 1000)]), 8193);

        self::assertLessThan(8192, mb_strlen($body, 'UTF-8'));
        self::assertSame(ErrorCode::PayloadTooLarge, self::rejection(static fn() => (new ClientErrorReportParser())->parse($body))->code);
    }

    public function testAReportWithinEveryFieldLimitCanStillBeTooLarge(): void
    {
        $file = dirname(ContractValidator::schemasDir()) . '/fixtures/valid/client-error-report/limits-reached.json';
        $body = (string) file_get_contents($file);

        self::assertSame(ErrorCode::PayloadTooLarge, self::rejection(static fn() => (new ClientErrorReportParser())->parse($body))->code);
    }

    #[DataProvider('notJsonObjects')]
    public function testRefusesABodyThatIsNotAJsonObject(string $body): void
    {
        $error = self::rejection(static fn() => (new ClientErrorReportParser())->parse($body));

        self::assertSame([ErrorCode::ValidationFailed, 'The error report must be a JSON object.', []], [$error->code, $error->message, $error->fields]);
    }

    public function testAnEmptyObjectNeedsEveryField(): void
    {
        $error = self::rejection(static fn() => (new ClientErrorReportParser())->parse('{}'));

        self::assertSame(array_fill_keys(self::FIELDS, ['Required.']), $error->fields);
    }

    /** @param list<string> $messages */
    #[DataProvider('fieldProblems')]
    public function testExplainsWhatIsWrongWithAField(string $field, mixed $value, array $messages): void
    {
        $body = self::body([$field => $value]);
        $error = self::rejection(static fn() => (new ClientErrorReportParser())->parse($body));

        self::assertSame([ErrorCode::ValidationFailed, 'The error report was not accepted.', [$field => $messages]], [$error->code, $error->message, $error->fields]);
    }

    public function testRefusesFieldsThatAreNotPartOfAReport(): void
    {
        $body = self::body(['email' => 'someone@example.org', '0' => 'x']);
        $error = self::rejection(static fn() => (new ClientErrorReportParser())->parse($body));

        self::assertSame(['email' => ['Not part of an error report.'], '0' => ['Not part of an error report.']], $error->fields);
    }

    public function testReportsEveryProblemAtOnceInFieldOrder(): void
    {
        $body = '{"extra":true,"occurredAt":"yesterday","message":"","app":"mobile"}';
        $error = self::rejection(static fn() => (new ClientErrorReportParser())->parse($body));

        self::assertSame([...self::FIELDS, 'extra'], array_keys($error->fields));
    }

    public function testTheRefusalCarriesItsMessage(): void
    {
        $exception = null;
        try {
            (new ClientErrorReportParser())->parse('{}');
        } catch (RejectedInput $caught) {
            $exception = $caught;
        }

        self::assertSame('The error report was not accepted.', $exception?->getMessage());
    }

    /** @return array<string, string> fixture name => file */
    private static function fixtureFiles(string $kind): array
    {
        $files = glob(dirname(ContractValidator::schemasDir()) . "/fixtures/{$kind}/client-error-report/*.json");
        $named = [];
        foreach ($files === false ? [] : $files as $file) {
            $named[basename($file, '.json')] = $file;
        }

        return $named;
    }

    /**
     * The valid admin fixture as a request body, with some fields changed; a null value
     * leaves the field out.
     *
     * @param array<array-key, mixed> $changes
     */
    private static function body(array $changes = []): string
    {
        $file = dirname(ContractValidator::schemasDir()) . '/fixtures/valid/client-error-report/admin.json';
        /** @var array<string, mixed> $data */
        $data = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

        return json_encode(
            array_filter([...$data, ...$changes], static fn(mixed $value): bool => $value !== null),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /** The ApiError a refused body carries; fails the test if nothing was refused. */
    private static function rejection(Closure $parse): ApiError
    {
        try {
            $parse();
        } catch (RejectedInput $rejected) {
            return $rejected->error;
        }
        self::fail('The body was accepted');
    }
}
