<?php

namespace Mie\FlarumFiles\Console;

use Illuminate\Console\Command;
use Mie\FlarumFiles\Service\FileCache;

final class CleanCacheCommand extends Command
{
    protected $signature = 'mie-files:clean-cache';
    protected $description = 'Remove expired and least-recently-used Mie Files cache entries.';

    public function handle(FileCache $cache): int
    {
        $result = $cache->clean();
        $this->info("Removed {$result['removed']} cached files ({$result['bytes']} bytes).");

        return self::SUCCESS;
    }
}
