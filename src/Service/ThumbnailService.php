<?php

namespace Mie\FlarumFiles\Service;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Mie\FlarumFiles\CategoryDefaults;
use Mie\FlarumFiles\Model\File;
use Psr\Http\Message\ServerRequestInterface;

final class ThumbnailService
{
    public function __construct(private DeliveryService $delivery, private SettingsRepositoryInterface $settings) {}

    /** @return array{0:resource,1:array<string,string>,2:string} */
    public function make(File $file, User $actor, ServerRequestInterface $request): array
    {
        if ($file->category->insert_template !== CategoryDefaults::IMAGE_DOWNLOAD) {
            throw new \RuntimeException('This file does not use an image download template.');
        }
        [$stream] = $this->delivery->open($file, $actor, 'preview', $request);
        $source = $this->copyToTemporaryFile($stream);
        try {
            $bytes = file_get_contents($source);
            $image = $bytes === false ? false : @imagecreatefromstring($bytes);
            if ($image === false) {
                throw new \RuntimeException('The image thumbnail could not be generated.');
            }
            $sourceWidth = imagesx($image);
            $sourceHeight = imagesy($image);
            $maxWidth = max(32, min(4096, (int) $this->settings->get('mie-files.thumbnail-width', 480)));
            $width = min($sourceWidth, $maxWidth);
            $height = max(1, (int) round($sourceHeight * ($width / $sourceWidth)));
            $thumbnail = imagecreatetruecolor($width, $height);
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
            imagefill($thumbnail, 0, 0, $transparent);
            imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
            $target = tempnam(sys_get_temp_dir(), 'mie-thumbnail-');
            if ($target === false) {
                throw new \RuntimeException('Cannot create a thumbnail temporary file.');
            }
            try {
                $mime = $this->writeImage($thumbnail, $target, $file->mime_type);
                $handle = fopen($target, 'rb');
                if ($handle === false) {
                    throw new \RuntimeException('Cannot open the generated thumbnail.');
                }
                $size = filesize($target);
                return [$handle, ['Content-Type' => $mime, 'Content-Length' => (string) ($size ?: 0), 'Cache-Control' => 'private, no-store'], $target];
            } catch (\Throwable $exception) {
                @unlink($target);
                throw $exception;
            }
        } finally {
            @unlink($source);
        }
    }

    private function copyToTemporaryFile(mixed $stream): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mie-source-');
        if ($path === false) {
            throw new \RuntimeException('Cannot create an image temporary file.');
        }
        $target = fopen($path, 'wb');
        if ($target === false) {
            throw new \RuntimeException('Cannot write an image temporary file.');
        }
        if (is_resource($stream)) {
            stream_copy_to_stream($stream, $target);
            fclose($stream);
        } else {
            while (!$stream->eof()) {
                fwrite($target, $stream->read(8192));
            }
            $stream->close();
        }
        fclose($target);
        return $path;
    }

    private function writeImage(\GdImage $image, string $path, string $sourceMime): string
    {
        $quality = max(1, min(100, (int) $this->settings->get('mie-files.image-quality', 85)));
        return match ($sourceMime) {
            'image/png' => (imagepng($image, $path, 6) ? 'image/png' : throw new \RuntimeException('Cannot encode PNG thumbnail.')),
            'image/gif' => (imagegif($image, $path) ? 'image/gif' : throw new \RuntimeException('Cannot encode GIF thumbnail.')),
            'image/webp' => (imagewebp($image, $path, $quality) ? 'image/webp' : throw new \RuntimeException('Cannot encode WebP thumbnail.')),
            'image/avif' => (function_exists('imageavif') && imageavif($image, $path, $quality) ? 'image/avif' : throw new \RuntimeException('Cannot encode AVIF thumbnail.')),
            default => (imagejpeg($image, $path, $quality) ? 'image/jpeg' : throw new \RuntimeException('Cannot encode JPEG thumbnail.')),
        };
    }
}
