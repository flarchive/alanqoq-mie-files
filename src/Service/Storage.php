<?php

namespace Mie\FlarumFiles\Service;

interface Storage
{
    public function put(string $path, string $source): void;
    public function stream(string $path);
    public function delete(string $path): void;
    public function publicUrl(string $path): ?string;
}
