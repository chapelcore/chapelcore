<?php

/**
 * RedactorFailsClosedTest.php — the Redactor's answer when a pattern can't be applied.
 *
 * Why: DOMAIN-MODEL §5.8 ("When a pattern can't be applied"): PHP's regular-expression
 * engine gives up on some inputs, and the Redactor then returned an empty string, which
 * made the log entry throw and lost the original error. The whole string must become
 * "[unredactable]" instead: never partly redacted, never empty.
 *
 * How it connects: tests src/Diagnostics/Redactor.php and, through it,
 * src/Diagnostics/LogEntryFactory.php. A crafted text over a tiny backtracking limit makes
 * the email pattern give up the same way on every host, with or without PCRE's JIT;
 * every test restores the limit afterwards.
 */

declare(strict_types=1);

namespace ChapelCore\Core\Tests\Diagnostics;

use ChapelCore\Core\Diagnostics\LogEntryFactory;
use ChapelCore\Core\Diagnostics\Redactor;
use ChapelCore\Core\Diagnostics\UuidGenerator;
use ChapelCore\Core\Model\LogLevel;
use ChapelCore\Core\Tests\Doubles\FixedClock;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RedactorFailsClosedTest extends TestCase
{
    private string|false $backtrackLimit = false;

    protected function setUp(): void
    {
        // 100 steps instead of PHP's default 1,000,000: the email pattern gives up on nearlyAnEmail()
        $this->backtrackLimit = ini_set('pcre.backtrack_limit', '100');
    }

    protected function tearDown(): void
    {
        if ($this->backtrackLimit !== false) {
            ini_set('pcre.backtrack_limit', $this->backtrackLimit);
        }
    }

    /** 801 characters that look almost like an email address: `a.a.…@b.b.…`, with no ending. */
    private static function nearlyAnEmail(): string
    {
        return str_repeat('a.', 200) . '@' . str_repeat('b.', 200);
    }

    public function testTextAPatternCannotBeAppliedToBecomesUnredactable(): void
    {
        self::assertSame('[unredactable]', (new Redactor())->text(self::nearlyAnEmail()));
    }

    public function testNothingRedactedBeforeTheFailureIsKept(): void
    {
        // the Bearer step succeeds first; the email step then gives up, and none of it is kept
        self::assertSame('[unredactable]', (new Redactor())->text('Bearer abc ' . self::nearlyAnEmail()));
    }

    public function testOnlyThatContextValueBecomesUnredactable(): void
    {
        $context = ['note' => self::nearlyAnEmail(), 'count' => 3, 'password' => 'hunter2'];

        self::assertSame(
            ['note' => '[unredactable]', 'count' => 3, 'password' => '[redacted]'],
            (new Redactor())->context($context),
        );
    }

    public function testUnredactableStaysTheSameWhenRedactedAgain(): void
    {
        self::assertSame('[unredactable]', (new Redactor())->text('[unredactable]'));
    }

    public function testALogEntryIsStillMadeAndSaysUnredactable(): void
    {
        $factory = new LogEntryFactory(
            new FixedClock(),
            new UuidGenerator(static fn(int $length): string => str_repeat("\0", $length)),
            new Redactor(),
        );

        $entry = $factory->create(LogLevel::Error, 'app', self::nearlyAnEmail(), ['exception' => new RuntimeException(self::nearlyAnEmail())], null);

        self::assertSame(['[unredactable]', '[unredactable]'], [$entry->message, $entry->exception?->message]);
    }
}
