<?php

namespace Mie\FlarumFiles\Tests;

use Flarum\Settings\SettingsRepositoryInterface;
use Mie\FlarumFiles\Service\FileCache;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class FileCacheTest extends TestCase
{
    private string $root;
    private FileCache $cache;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/mie-files-cache-test-'.bin2hex(random_bytes(6));
        $this->cache = new FileCache($this->settings(), $this->root);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->root)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($this->root);
    }

    public function testHitUsesAtomicEntryWithoutInvokingProducerAgain(): void
    {
        $calls = 0;
        $first = $this->cache->openObject('remote', 'objects/a.txt', 4, function (string $target) use (&$calls): void {
            self::assertSame([], $this->cacheFiles());
            file_put_contents($target, 'body');
            $calls++;
        });
        self::assertIsResource($first);
        self::assertSame('body', stream_get_contents($first));
        fclose($first);

        $second = $this->cache->openObject('remote', 'objects/a.txt', 4, static function (): void {
            self::fail('A cache hit must not invoke the producer.');
        });
        self::assertIsResource($second);
        self::assertSame('body', stream_get_contents($second));
        fclose($second);
        self::assertSame(1, $calls);
        self::assertCount(1, $this->cacheFiles());
    }

    public function testProducerFailureReturnsNullAndLeavesNoPartialEntry(): void
    {
        $stream = $this->cache->openObject('remote', 'objects/failure.txt', 4, static function (string $target): void {
            file_put_contents($target, 'bad');
            throw new \RuntimeException('remote read failed');
        });

        self::assertNull($stream);
        self::assertSame([], $this->cacheFiles());
    }

    public function testCleanRemovesExpiredEntriesByLastAccessTime(): void
    {
        $cache = new FileCache($this->settings(['mie-files.cache-retention-days' => '1']), $this->root);
        $stream = $cache->openObject('remote', 'objects/expired.txt', 4, static fn (string $target) => file_put_contents($target, 'body'));
        self::assertIsResource($stream);
        fclose($stream);
        $file = $this->cacheFiles()[0];
        touch($file, time() - 2 * 86400);

        self::assertSame(['removed' => 1, 'bytes' => 4], $cache->clean());
        self::assertSame([], $this->cacheFiles());
    }

    public function testCleanEvictsLeastRecentlyUsedEntriesOverCapacity(): void
    {
        $cache = new FileCache($this->settings(['mie-files.cache-max-mb' => '1']), $this->root);
        $old = str_repeat('a', 700 * 1024);
        $new = str_repeat('b', 700 * 1024);
        $stream = $cache->openObject('remote', 'old', strlen($old), static fn (string $target) => file_put_contents($target, $old));
        self::assertIsResource($stream);
        fclose($stream);
        touch($this->cacheFiles()[0], time() - 60);

        $stream = $cache->openObject('remote', 'new', strlen($new), static fn (string $target) => file_put_contents($target, $new));
        self::assertIsResource($stream);
        fclose($stream);
        self::assertCount(1, $this->cacheFiles());
        self::assertSame($new, file_get_contents($this->cacheFiles()[0]));
    }

    public function testOversizedObjectBypassesTheCacheBeforeReadingIt(): void
    {
        $calls = 0;
        $stream = $this->cache->openObject('remote', 'objects/large.bin', 2 * 1024 * 1024 * 1024, static function () use (&$calls): void {
            $calls++;
        });

        self::assertNull($stream);
        self::assertSame(0, $calls);
    }

    public function testForgetFileRemovesObjectAndEveryThumbnailVariant(): void
    {
        $object = $this->cache->openObject('remote', 'objects/image.png', 4, static fn (string $target) => file_put_contents($target, 'body'));
        self::assertIsResource($object);
        fclose($object);
        foreach ([[480, 85, 'image/png'], [960, 90, 'image/webp']] as [$width, $quality, $mime]) {
            $thumbnail = $this->cache->openThumbnail(
                'remote',
                'objects/image.png',
                $width,
                $quality,
                $mime,
                static fn (string $target) => file_put_contents($target, 'thumb')
            );
            self::assertIsResource($thumbnail);
            fclose($thumbnail);
        }
        self::assertCount(3, $this->cacheFiles());

        $this->cache->forgetFile('remote', 'objects/image.png');

        self::assertSame([], $this->cacheFiles());
    }

    /** @param array<string,string> $values */
    private function settings(array $values = []): SettingsRepositoryInterface
    {
        return new class($values) implements SettingsRepositoryInterface {
            /** @param array<string,string> $values */
            public function __construct(private array $values) {}
            public function all(): array { return $this->values; }
            public function get(string $key, mixed $default = null): mixed { return $this->values[$key] ?? $default; }
            public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
            public function delete(string $keyLike): void { unset($this->values[$keyLike]); }
        };
    }

    /** @return list<string> */
    private function cacheFiles(): array
    {
        if (!is_dir($this->root)) {
            return [];
        }
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if ($item->isFile() && str_ends_with($item->getFilename(), '.bin')) {
                $files[] = $item->getPathname();
            }
        }
        sort($files);

        return $files;
    }
}
