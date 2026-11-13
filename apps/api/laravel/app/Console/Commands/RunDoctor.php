<?php

/**
 * RunDoctor.php — `php artisan chapelcore:doctor`: checks this server and explains how to
 * fix any problem.
 *
 * Why: someone on SSH needs one command that says what is wrong in plain words, and the
 * deploy runs it before migrating, stopping when it exits 1 (DOMAIN-MODEL §5.8, phase-1
 * §6.3). Plain text, one line per check, with the fix underneath (E5).
 *
 * How it connects: Laravel finds it in app/Console/Commands. The checks are the core's
 * Doctor, built in AppServiceProvider; this command only prints its report and returns
 * its exit code.
 */

declare(strict_types=1);

namespace App\Console\Commands;

use ChapelCore\Core\Doctor\Doctor;
use ChapelCore\Core\Environment;
use ChapelCore\Core\Model\DoctorCheck;
use ChapelCore\Core\Model\Status;
use Illuminate\Console\Command;

final class RunDoctor extends Command
{
    /** "degraded" is the longest status; two spaces after it */
    private const int STATUS_WIDTH = 10;

    /** @var string */
    protected $signature = 'chapelcore:doctor';

    /** @var string */
    protected $description = 'Checks this server and explains how to fix any problem.';

    public function handle(Doctor $doctor, Environment $environment): int
    {
        $report = $doctor->run();

        // the names line up in one column, as wide as the longest name plus two spaces
        $nameWidth = max([0, ...array_map(static fn (DoctorCheck $check): int => strlen($check->name), $report->checks)]) + 2;
        $fixIndent = str_repeat(' ', self::STATUS_WIDTH + $nameWidth);

        $this->line("ChapelCore doctor ({$environment->value})");
        $this->newLine();
        foreach ($report->checks as $check) {
            $this->line(str_pad($check->status->value, self::STATUS_WIDTH).str_pad($check->name, $nameWidth).($check->message ?? ''));
            if ($check->fix !== null) {
                $this->line("{$fixIndent}Fix: {$check->fix}");
            }
        }
        $this->newLine();
        $this->line("Result: {$report->status->value} (".self::summary($report->checks).')');

        // 1 when any check is down, so the deploy stops before migrating
        return $report->exitCode();
    }

    /**
     * "all 9 checks passed", or how many are down and degraded: "1 down, 2 degraded".
     *
     * @param  list<DoctorCheck>  $checks
     */
    private static function summary(array $checks): string
    {
        $counts = [];
        foreach ([Status::Down, Status::Degraded] as $status) {
            $count = count(array_filter($checks, static fn (DoctorCheck $check): bool => $check->status === $status));
            if ($count > 0) {
                $counts[] = "{$count} {$status->value}";
            }
        }

        return $counts === [] ? 'all '.count($checks).' checks passed' : implode(', ', $counts);
    }
}
