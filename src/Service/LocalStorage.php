<?php

namespace Mie\FlarumFiles\Service;

final class LocalStorage implements Storage
{
    public function __construct(private string $root) {}
    public function put(string $path, string $source): void
    {
        $target = $this->root.'/'.ltrim($path, '/');
        if (!is_dir(dirname($target))) mkdir(dirname($target), 0770, true);
        if (!copy($source, $target)) throw new \RuntimeException('Unable to store file.');
    }
    public function stream(string $path)
    {
        $target = $this->root.'/'.ltrim($path, '/');
        if (!is_file($target)) throw new \RuntimeException('File not found.');
        $handle = fopen($target, 'rb');
        if ($handle === false) throw new \RuntimeException('Unable to open file.');
        return $handle;
    }
    public function delete(string $path): void { $target = $this->root.'/'.ltrim($path, '/'); if (is_file($target) && !unlink($target)) throw new \RuntimeException('Unable to delete object.'); }
    public function publicUrl(string $path): ?string { return null; }
}
