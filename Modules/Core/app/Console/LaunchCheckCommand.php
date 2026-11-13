<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Modules\Core\Support\Ops\LaunchChecklist;

/**
 * Go-live preflight (plan M21 S7). Exits non-zero when a blocking check fails, so the deploy
 * script can stop on it.
 */
class LaunchCheckCommand extends Command
{
    protected $signature = 'core:launch-check {--strict : Treat warnings as failures}';

    protected $description = 'Check production configuration before going live (debug, keys, drivers, caches, security headers)';

    public function handle(LaunchChecklist $checklist): int
    {
        $checks = $checklist->run();

        $this->table(['', 'Check', 'Detail'], array_map(fn (array $check): array => [
            match ($check['status']) {
                LaunchChecklist::PASS => '<info>PASS</info>',
                LaunchChecklist::WARN => '<comment>WARN</comment>',
                default => '<error>FAIL</error>',
            },
            $check['check'],
            $check['status'] === LaunchChecklist::PASS ? '' : $check['detail'],
        ], $checks));

        $statuses = array_count_values(array_column($checks, 'status'));
        $this->line(sprintf('%d passed, %d warnings, %d failed.', $statuses[LaunchChecklist::PASS] ?? 0, $statuses[LaunchChecklist::WARN] ?? 0, $statuses[LaunchChecklist::FAIL] ?? 0));

        $blocked = ! LaunchChecklist::passes($checks) || ($this->option('strict') && isset($statuses[LaunchChecklist::WARN]));

        return $blocked ? self::FAILURE : self::SUCCESS;
    }
}
