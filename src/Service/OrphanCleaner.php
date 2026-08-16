<?php

namespace Mie\FlarumFiles\Service;

use Carbon\Carbon;
use Mie\FlarumFiles\Model\File;

final class OrphanCleaner
{
    public function __construct(private FileService $files) {}
    public function clean(int $retentionDays = 30): int
    {
        $count = 0; $cutoff = Carbon::now()->subDays($retentionDays);
        File::query()->where('status', 'success')->where('created_at', '<', $cutoff)->chunkById(100, function ($files) use (&$count) {
            foreach ($files as $file) if (!$file->posts()->exists()) { try { $this->files->remove($file); $count++; } catch (\Throwable) {} }
        });
        return $count;
    }
}
