<?php

namespace Mie\FlarumFiles\Service;

use Flarum\Foundation\Config;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Mie\FlarumFiles\CategoryDefaults;
use Mie\FlarumFiles\Model\File;
use Mie\FlarumFiles\Model\FileDelivery;
use Psr\Http\Message\ServerRequestInterface;

final class DeliveryService
{
    public function __construct(
        private StorageFactory $storages,
        private Config $config,
        private SettingsRepositoryInterface $settings,
        private FileCache $cache
    ) {}

    public function url(File $file, User $actor, string $mode): string
    {
        $this->assertAvailable($file);
        $this->assertPermission($file, $actor, $mode);
        $storage = $this->storages->make($file->storage_name);
        if ($directUrl = $storage->publicUrl($file->object_key)) {
            if (!$actor->id) {
                throw new \RuntimeException('Authentication is required before generating a direct URL.');
            }
            return $directUrl;
        }
        return rtrim((string) $this->config->url(), '/').'/api/mie/files/'.$file->id.'/proxy?mode='.rawurlencode($mode);
    }

    /** @return array{0:mixed,1:array<string,string>} */
    public function open(File $file, User $actor, string $mode, ServerRequestInterface $request): array
    {
        $storage = $this->prepare($file, $actor, $mode, $request);
        $name = str_replace(['"', "\r", "\n"], '', $file->original_name);
        return [
            $this->source($file, $storage),
            [
                'Content-Type' => $file->mime_type,
                'Content-Length' => (string) $file->size,
                'Content-Disposition' => ($mode === 'download' ? 'attachment' : 'inline').'; filename="'.$name.'"',
                'Cache-Control' => $this->isTrackedTemplate($file->category->insert_template) ? 'private, no-store' : 'private, max-age=300',
            ],
        ];
    }

    public function prepare(File $file, User $actor, string $mode, ServerRequestInterface $request): Storage
    {
        $this->assertAvailable($file);
        $this->assertPermission($file, $actor, $mode);
        $template = $file->category->insert_template;
        if ($this->isTrackedTemplate($template)) {
            $this->assertNotHotlinked($request);
            if ($mode !== 'preview') {
                $file->forceFill(['downloads' => ((int) $file->downloads) + 1])->save();
                $delivery = new FileDelivery();
                $delivery->fill([
                    'file_id' => $file->id,
                    'actor_id' => $actor->id ?: null,
                    'mode' => $mode,
                    'referer_host' => parse_url($request->getHeaderLine('Referer'), PHP_URL_HOST) ?: null,
                ])->save();
            }
        }
        return $this->storages->make($file->storage_name);
    }

    /**
     * Authorize and prepare the source for thumbnail generation without
     * exposing the original object through the proxy endpoint.
     */
    public function prepareThumbnail(File $file, User $actor, ServerRequestInterface $request): Storage
    {
        $this->assertAvailable($file);
        $this->assertThumbnailPermission($file, $actor);

        if ($this->isTrackedTemplate($file->category->insert_template)) {
            $this->assertNotHotlinked($request);
        }

        return $this->storages->make($file->storage_name);
    }

    public function source(File $file, Storage $storage)
    {
        if ($this->usesCache($file, $storage)) {
            $cached = $this->cache->openObject(
                $file->storage_name,
                $file->object_key,
                (int) $file->size,
                function (string $target) use ($storage, $file): void {
                    $this->copyToFile($storage->stream($file->object_key), $target);
                }
            );
            if (is_resource($cached)) {
                return $cached;
            }
        }

        return $storage->stream($file->object_key);
    }

    public function usesCache(File $file, Storage $storage): bool
    {
        return $file->storage_name !== 'local' && $storage->publicUrl($file->object_key) === null;
    }

    public function modeFor(File $file): string
    {
        return match ($file->category->insert_template) {
            CategoryDefaults::FILE_DOWNLOAD => 'download',
            CategoryDefaults::IMAGE_DOWNLOAD => 'preview',
            default => 'inline',
        };
    }

    public function thumbnailUrl(File $file, User $actor): string
    {
        $this->assertAvailable($file);
        $this->assertThumbnailPermission($file, $actor);
        return rtrim((string) $this->config->url(), '/').'/api/mie/files/'.$file->id.'/thumbnail';
    }

    private function assertAvailable(File $file): void
    {
        if ($file->status !== 'success') {
            throw new \RuntimeException('File unavailable.');
        }
    }

    private function assertPermission(File $file, User $actor, string $mode): void
    {
        $category = $file->category;
        $template = $category->insert_template;
        $expected = $this->modeFor($file);
        if (!in_array($mode, [$expected, 'download'], true) || ($mode === 'download' && $template !== CategoryDefaults::IMAGE_DOWNLOAD && $expected !== 'download')) {
            throw new \RuntimeException('Invalid file delivery mode.');
        }
        $action = $mode === 'download' ? 'download' : 'view';
        if (!PermissionService::can($actor, $category->permission_name, $action)) {
            throw new \RuntimeException('File delivery permission denied.');
        }
    }

    private function assertThumbnailPermission(File $file, User $actor): void
    {
        if (!str_starts_with(strtolower((string) $file->mime_type), 'image/')) {
            throw new \RuntimeException('This file is not an image.');
        }
        if (!PermissionService::can($actor, $file->category->permission_name, 'view')) {
            throw new \RuntimeException('File delivery permission denied.');
        }
    }

    private function isTrackedTemplate(string $template): bool
    {
        return in_array($template, [
            CategoryDefaults::FILE_DOWNLOAD,
            CategoryDefaults::IMAGE_DOWNLOAD,
            CategoryDefaults::MARKDOWN_IMAGE,
            CategoryDefaults::BBCODE_IMAGE,
        ], true);
    }

    private function assertNotHotlinked(ServerRequestInterface $request): void
    {
        if (!$this->settings->get('mie-files.hotlink-protection', true)) {
            return;
        }
        $referer = $request->getHeaderLine('Referer');
        if ($referer === '') {
            return;
        }
        $host = parse_url($referer, PHP_URL_HOST);
        $forumHost = parse_url((string) $this->config->url(), PHP_URL_HOST);
        if (!$host || !$forumHost || !hash_equals(strtolower($forumHost), strtolower($host))) {
            throw new \RuntimeException('Hotlink protection rejected this request.');
        }
    }

    private function copyToFile(mixed $source, string $target): void
    {
        $destination = fopen($target, 'wb');
        if ($destination === false) {
            throw new \RuntimeException('Cannot write file cache.');
        }
        try {
            if (is_resource($source)) {
                if (stream_copy_to_stream($source, $destination) === false) {
                    throw new \RuntimeException('Cannot read remote file for cache.');
                }
            } else {
                while (!$source->eof()) {
                    fwrite($destination, $source->read(8192));
                }
            }
        } finally {
            if (is_resource($source)) {
                @fclose($source);
            } else {
                try {
                    $source->close();
                } catch (\Throwable) {
                }
            }
            fclose($destination);
        }
    }
}
