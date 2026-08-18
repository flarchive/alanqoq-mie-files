<?php

namespace Mie\FlarumFiles\Service;

use FilesystemIterator;
use Flarum\Settings\SettingsRepositoryInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class FileCache
{
    public const OBJECTS = 'objects';
    public const THUMBNAILS = 'thumbnails';

    public function __construct(private SettingsRepositoryInterface $settings, private ?string $root = null) {}

    /** @return resource|null */
    public function openObject(string $storageName, string $objectKey, ?int $expectedSize, callable $producer)
    {
        return $this->openValue(self::OBJECTS, $this->objectKey($storageName, $objectKey), $expectedSize, $producer);
    }

    /** @return resource|null */
    public function openThumbnail(
        string $storageName,
        string $objectKey,
        int $width,
        int $quality,
        string $mime,
        callable $producer
    ) {
        return $this->openValue(
            self::THUMBNAILS,
            $this->thumbnailKey($storageName, $objectKey, $width, $quality, $mime),
            null,
            $producer
        );
    }

    /** @return resource|null */
    public function open(string $namespace, string $key, ?int $expectedSize, callable $producer)
    {
        $this->assertNamespace($namespace);

        return $this->openValue($namespace, $key, $expectedSize, $producer);
    }

    /** Remove an object and every cached thumbnail variant for that object. */
    public function forgetFile(string $storageName, string $objectKey): void
    {
        $hash = $this->fileHash($storageName, $objectKey);
        $this->removeTree($this->objectDirectory($hash));
        $this->removeTree($this->thumbnailDirectory($hash));
    }

    public function forget(string $namespace, string $key): void
    {
        $this->assertNamespace($namespace);
        $this->removeLocked($this->entryPath($namespace, $key));
    }

    /** @return array{removed:int,bytes:int} */
    public function clean(): array
    {
        $removed = 0;
        $bytes = 0;
        foreach ([self::OBJECTS, self::THUMBNAILS] as $namespace) {
            [$count, $size] = $this->cleanNamespace($namespace);
            $removed += $count;
            $bytes += $size;
        }

        return ['removed' => $removed, 'bytes' => $bytes];
    }

    public function objectKey(string $storageName, string $objectKey): string
    {
        return $storageName."\0".$objectKey;
    }

    public function thumbnailKey(string $storageName, string $objectKey, int $width, int $quality, string $mime): string
    {
        return $this->objectKey($storageName, $objectKey)."\0".$width."\0".$quality."\0".$mime;
    }

    /** @return resource|null */
    private function openValue(string $namespace, string $key, ?int $expectedSize, callable $producer)
    {
        if (!$this->enabledFor($namespace)) {
            return null;
        }
        if ($expectedSize !== null && ($expectedSize > $this->maxFileBytes() || $expectedSize > $this->capacityBytes($namespace))) {
            return null;
        }

        $path = $this->entryPath($namespace, $key);
        if ($stream = $this->openExisting($path, $expectedSize)) {
            return $stream;
        }
        if (!$this->ensureDirectory(dirname($path)) || !$this->ensureDirectory($this->lockDirectory())) {
            return null;
        }

        $lock = @fopen($this->lockPath(pathinfo($path, PATHINFO_FILENAME)), 'c');
        if ($lock === false) {
            return null;
        }
        try {
            if (!@flock($lock, LOCK_EX)) {
                return null;
            }
            if ($stream = $this->openExisting($path, $expectedSize)) {
                return $stream;
            }

            $temporary = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
            try {
                $producer($temporary);
                $size = @filesize($temporary);
                if (!$this->validFile($temporary, $expectedSize) || $size === false || $size > $this->capacityBytes($namespace)) {
                    return null;
                }
                if (!@rename($temporary, $path)) {
                    return null;
                }
                @chmod($path, 0640);

                $stream = $this->openExisting($path, $expectedSize);
                $this->trimNamespace($namespace);

                return $stream;
            } catch (\Throwable) {
                return null;
            } finally {
                @unlink($temporary);
            }
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return resource|null */
    private function openExisting(string $path, ?int $expectedSize)
    {
        if (!$this->validFile($path, $expectedSize)) {
            return null;
        }
        @touch($path);
        $stream = @fopen($path, 'rb');

        return $stream === false ? null : $stream;
    }

    private function validFile(string $path, ?int $expectedSize): bool
    {
        if (!is_file($path) || is_link($path)) {
            return false;
        }
        $size = @filesize($path);

        return $size !== false
            && $size <= $this->maxFileBytes()
            && ($expectedSize === null || $size === $expectedSize);
    }

    /** @return array{int,int} */
    private function cleanNamespace(string $namespace): array
    {
        $directory = $this->namespaceDirectory($namespace);
        if (!is_dir($directory)) {
            return [0, 0];
        }

        $cutoff = time() - ($this->retentionDays() * 86400);
        $removed = 0;
        $bytes = 0;
        foreach ($this->files($directory, true) as $file) {
            if (str_ends_with($file, '.tmp') || (int) @filemtime($file) < $cutoff) {
                $size = max(0, (int) @filesize($file));
                if ($this->removeLocked($file)) {
                    $removed++;
                    $bytes += $size;
                }
            }
        }

        [$count, $size] = $this->trimNamespace($namespace);

        return [$removed + $count, $bytes + $size];
    }

    /** @return array{int,int} */
    private function trimNamespace(string $namespace): array
    {
        $files = $this->files($this->namespaceDirectory($namespace));
        $total = array_sum(array_map(static fn (string $file): int => max(0, (int) @filesize($file)), $files));
        $removed = 0;
        $bytes = 0;
        if ($total <= $this->capacityBytes($namespace)) {
            return [$removed, $bytes];
        }

        usort($files, static fn (string $left, string $right): int => (int) @filemtime($left) <=> (int) @filemtime($right));
        foreach ($files as $file) {
            if ($total <= $this->capacityBytes($namespace)) {
                break;
            }
            $size = max(0, (int) @filesize($file));
            if ($this->removeLocked($file)) {
                $total -= $size;
                $removed++;
                $bytes += $size;
            }
        }

        return [$removed, $bytes];
    }

    private function removeLocked(string $path): bool
    {
        if (!is_file($path) || !$this->ensureDirectory($this->lockDirectory())) {
            return !is_file($path);
        }
        $lock = @fopen($this->lockPath($this->lockHashForPath($path)), 'c');
        if ($lock === false) {
            return false;
        }
        try {
            if (!@flock($lock, LOCK_EX | LOCK_NB)) {
                return false;
            }

            return !is_file($path) || @unlink($path);
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function removeTree(string $directory): void
    {
        foreach ($this->files($directory, true) as $file) {
            $this->removeLocked($file);
        }
        if (is_dir($directory)) {
            @rmdir($directory);
        }
    }

    /** @return list<string> */
    private function files(string $directory, bool $includeTemporary = false): array
    {
        if (!is_dir($directory)) {
            return [];
        }
        $files = [];
        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->isLink()) {
                    continue;
                }
                $name = $file->getFilename();
                if (str_ends_with($name, '.bin') || ($includeTemporary && str_ends_with($name, '.tmp'))) {
                    $files[] = $file->getPathname();
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return $files;
    }

    private function entryPath(string $namespace, string $key): string
    {
        $fileHash = $this->fileHashFromKey($key);
        $hash = hash('sha256', $key);

        return $namespace === self::OBJECTS
            ? $this->objectDirectory($fileHash).'/'.$hash.'.bin'
            : $this->thumbnailDirectory($fileHash).'/'.$hash.'.bin';
    }

    private function fileHash(string $storageName, string $objectKey): string
    {
        return hash('sha256', $this->objectKey($storageName, $objectKey));
    }

    private function fileHashFromKey(string $key): string
    {
        $parts = explode("\0", $key, 3);

        return hash('sha256', ($parts[0] ?? '')."\0".($parts[1] ?? ''));
    }

    private function objectDirectory(string $hash): string
    {
        return $this->namespaceDirectory(self::OBJECTS).'/'.substr($hash, 0, 2).'/'.substr($hash, 2, 2).'/'.$hash;
    }

    private function thumbnailDirectory(string $hash): string
    {
        return $this->namespaceDirectory(self::THUMBNAILS).'/'.substr($hash, 0, 2).'/'.substr($hash, 2, 2).'/'.$hash;
    }

    private function namespaceDirectory(string $namespace): string
    {
        return $this->rootPath().'/'.$namespace;
    }

    private function lockDirectory(): string
    {
        return $this->rootPath().'/locks';
    }

    private function lockPath(string $hash): string
    {
        return $this->lockDirectory().'/'.$hash.'.lock';
    }

    private function lockHashForPath(string $path): string
    {
        $name = basename($path);
        if (str_ends_with($name, '.tmp')) {
            return explode('.', $name, 2)[0];
        }

        return pathinfo($name, PATHINFO_FILENAME);
    }

    private function rootPath(): string
    {
        return $this->root ?: storage_path('mie-files-cache');
    }

    private function ensureDirectory(string $directory): bool
    {
        return is_dir($directory) || @mkdir($directory, 0770, true) || is_dir($directory);
    }

    private function enabledFor(string $namespace): bool
    {
        return $this->settingBool('mie-files.cache-enabled', true)
            && ($namespace !== self::THUMBNAILS || $this->settingBool('mie-files.cache-thumbnails', true));
    }

    private function retentionDays(): int
    {
        return $this->settingInt('mie-files.cache-retention-days', 30, 1, 3650);
    }

    private function capacityBytes(string $namespace): int
    {
        $key = $namespace === self::THUMBNAILS ? 'mie-files.cache-thumbnail-max-mb' : 'mie-files.cache-max-mb';

        return $this->settingInt($key, 2048, 1, 1048576) * 1024 * 1024;
    }

    private function maxFileBytes(): int
    {
        return $this->settingInt('mie-files.cache-max-file-mb', 256, 1, 1048576) * 1024 * 1024;
    }

    private function settingBool(string $key, bool $default): bool
    {
        $value = $this->settings->get($key, $default ? '1' : '0');

        return !in_array($value, [false, null, '', '0', 0], true);
    }

    private function settingInt(string $key, int $default, int $min, int $max): int
    {
        $value = filter_var($this->settings->get($key, $default), FILTER_VALIDATE_INT);

        return $value === false ? $default : max($min, min($max, (int) $value));
    }

    private function assertNamespace(string $namespace): void
    {
        if (!in_array($namespace, [self::OBJECTS, self::THUMBNAILS], true)) {
            throw new \InvalidArgumentException('Unknown file cache namespace.');
        }
    }
}
