<?php

namespace Mie\FlarumFiles\Service;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Mie\FlarumFiles\Model\File;
use Psr\Http\Message\ServerRequestInterface;

final class ThumbnailService
{
    public function __construct(
        private DeliveryService $delivery,
        private SettingsRepositoryInterface $settings,
        private FileCache $cache
    ) {}

    /** @return array{0:resource,1:array<string,string>,2:?string} */
    public function make(File $file, User $actor, ServerRequestInterface $request): array
    {
        if (!str_starts_with(strtolower((string) $file->mime_type), 'image/')) {
            throw new \RuntimeException('This file is not an image.');
        }
        // Authorization and hotlink protection always precede cache lookup.
        $storage = $this->delivery->prepareThumbnail($file, $actor, $request);
        $width = max(32, min(4096, (int) $this->settings->get('mie-files.thumbnail-width', 480)));
        $quality = max(1, min(100, (int) $this->settings->get('mie-files.image-quality', 85)));
        $convertWebp = (string) $this->settings->get('mie-files.thumbnail-convert-webp', '0') === '1';
        $mime = $this->outputMime($file->mime_type, $convertWebp);
        if ($this->delivery->usesCache($file, $storage)) {
            $cached = $this->cache->openThumbnail(
                $file->storage_name,
                $file->object_key,
                $width,
                $quality,
                $mime,
                function (string $target) use ($file, $storage, $width, $quality, $convertWebp): void {
                    $this->generate($this->delivery->source($file, $storage), $target, $file->mime_type, $width, $quality, $convertWebp);
                }
            );
            if (is_resource($cached)) {
                return [$cached, $this->headers($cached, $mime), null];
            }
        }

        $target = tempnam(sys_get_temp_dir(), 'mie-thumbnail-');
        if ($target === false) {
            throw new \RuntimeException('Cannot create a thumbnail temporary file.');
        }
        try {
            $this->generate($this->delivery->source($file, $storage), $target, $file->mime_type, $width, $quality, $convertWebp);
            $stream = fopen($target, 'rb');
            if ($stream === false) {
                throw new \RuntimeException('Cannot open the generated thumbnail.');
            }

            return [$stream, $this->headers($stream, $mime), $target];
        } catch (\Throwable $exception) {
            @unlink($target);
            throw $exception;
        }
    }

    private function generate(mixed $stream, string $target, string $sourceMime, int $maxWidth, int $quality, bool $convertWebp): void
    {
        $source = $this->copyToTemporaryFile($stream);
        try {
            $bytes = file_get_contents($source);
            $image = $bytes === false ? false : @imagecreatefromstring($bytes);
            if ($image === false) {
                throw new \RuntimeException('The image thumbnail could not be generated.');
            }
            try {
                $sourceWidth = imagesx($image);
                $sourceHeight = imagesy($image);
                $width = min($sourceWidth, $maxWidth);
                $height = max(1, (int) round($sourceHeight * ($width / $sourceWidth)));
                $thumbnail = imagecreatetruecolor($width, $height);
                if ($thumbnail === false) {
                    throw new \RuntimeException('The image thumbnail could not be generated.');
                }
                try {
                    imagealphablending($thumbnail, false);
                    imagesavealpha($thumbnail, true);
                    $transparent = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
                    imagefill($thumbnail, 0, 0, $transparent);
                    imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
                    $this->writeImage($thumbnail, $target, $sourceMime, $quality, $convertWebp);
                } finally {
                    imagedestroy($thumbnail);
                }
            } finally {
                imagedestroy($image);
            }
        } finally {
            @unlink($source);
        }
    }

    /** @param resource $stream */
    private function headers($stream, string $mime): array
    {
        $stat = fstat($stream);

        return ['Content-Type' => $mime, 'Content-Length' => (string) ($stat['size'] ?? 0), 'Cache-Control' => 'private, no-store'];
    }

    private function copyToTemporaryFile(mixed $stream): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mie-source-');
        if ($path === false) {
            throw new \RuntimeException('Cannot create an image temporary file.');
        }
        $target = false;
        try {
            $target = fopen($path, 'wb');
            if ($target === false) {
                throw new \RuntimeException('Cannot write an image temporary file.');
            }
            if (is_resource($stream)) {
                if (stream_copy_to_stream($stream, $target) === false) {
                    throw new \RuntimeException('Cannot read image source.');
                }
            } else {
                while (!$stream->eof()) {
                    fwrite($target, $stream->read(8192));
                }
            }
            return $path;
        } catch (\Throwable $exception) {
            @unlink($path);
            throw $exception;
        } finally {
            if (is_resource($target)) {
                fclose($target);
            }
            if (is_resource($stream)) {
                @fclose($stream);
            } else {
                try {
                    $stream->close();
                } catch (\Throwable) {
                }
            }
        }
    }

    private function outputMime(string $sourceMime, bool $convertWebp = false): string
    {
        if ($convertWebp) {
            return 'image/webp';
        }

        return in_array($sourceMime, ['image/png', 'image/gif', 'image/webp', 'image/avif'], true) ? $sourceMime : 'image/jpeg';
    }

    private function writeImage(\GdImage $image, string $path, string $sourceMime, int $quality, bool $convertWebp = false): void
    {
        $outputMime = $this->outputMime($sourceMime, $convertWebp);
        if ($outputMime === 'image/webp' && !function_exists('imagewebp')) {
            throw new \RuntimeException('WebP thumbnail conversion requires PHP GD WebP support.');
        }

        $written = match ($outputMime) {
            'image/png' => imagepng($image, $path, 6),
            'image/gif' => imagegif($image, $path),
            'image/webp' => imagewebp($image, $path, $quality),
            'image/avif' => function_exists('imageavif') && imageavif($image, $path, $quality),
            default => imagejpeg($image, $path, $quality),
        };
        if (!$written || !is_file($path) || filesize($path) === 0) {
            throw new \RuntimeException('Cannot encode thumbnail.');
        }
    }
}
