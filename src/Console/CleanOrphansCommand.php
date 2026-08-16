<?php

namespace Mie\FlarumFiles\Console;

use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Console\Command;
use Mie\FlarumFiles\Service\OrphanCleaner;

final class CleanOrphansCommand extends Command
{
    protected $signature = 'mie-files:clean-orphans {--days= : Override the configured retention period}';
    protected $description = 'Delete unreferenced Mie Files objects beyond the retention period.';

    public function handle(OrphanCleaner $cleaner, SettingsRepositoryInterface $settings): int
    {
        $days = $this->option('days');
        $retentionDays = $days === null
            ? (int) $settings->get('mie-files.orphan-retention-days', 30)
            : (int) $days;
        if ($retentionDays < 1) {
            $this->error('The retention period must be at least one day.');
            return self::FAILURE;
        }
        $count = $cleaner->clean($retentionDays);
        $this->info("Removed {$count} orphaned files.");
        return self::SUCCESS;
    }
}
