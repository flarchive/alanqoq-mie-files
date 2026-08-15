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
        private SettingsRepositoryInterface $settings
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
        $this->assertAvailable($file);
        $this->assertPermission($file, $actor, $mode);
        $template = $file->category->insert_template;
        $tracked = $this->isTrackedTemplate($template);
        if ($tracked) {
            $this->assertNotHotlinked($request);
            $file->forceFill(['downloads' => ((int) $file->downloads) + 1])->save();
            $delivery = new FileDelivery();
            $delivery->fill([
                'file_id' => $file->id,
                'actor_id' => $actor->id ?: null,
                'mode' => $mode,
                'referer_host' => parse_url($request->getHeaderLine('Referer'), PHP_URL_HOST) ?: null,
            ])->save();
        }
        $name = str_replace(['"', "\r", "\n"], '', $file->original_name);
        return [
            $this->storages->make($file->storage_name)->stream($file->object_key),
            [
                'Content-Type' => $file->mime_type,
                'Content-Length' => (string) $file->size,
                'Content-Disposition' => ($mode === 'download' ? 'attachment' : 'inline').'; filename="'.$name.'"',
                'Cache-Control' => $tracked ? 'private, no-store' : 'private, max-age=300',
            ],
        ];
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
        $this->assertPermission($file, $actor, 'preview');
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
}
