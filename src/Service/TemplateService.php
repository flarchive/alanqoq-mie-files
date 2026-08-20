<?php

namespace Mie\FlarumFiles\Service;

use Flarum\User\User;
use Mie\FlarumFiles\CategoryDefaults;
use Mie\FlarumFiles\Model\File;

final class TemplateService
{
    public function __construct(private DeliveryService $delivery) {}

    /** @return array{markup:string,url:string} */
    public function render(File $file, User $actor): array
    {
        $template = $file->category->insert_template;
        $mode = $this->delivery->modeFor($file);
        $url = $this->delivery->url($file, $actor, $mode);
        $thumbnailUrl = $template === CategoryDefaults::IMAGE_DOWNLOAD ? $this->delivery->thumbnailUrl($file, $actor) : $url;
        $name = str_replace(['[', ']', '(', ')', "\r", "\n"], '', $file->original_name);
        $downloadUrl = $template === CategoryDefaults::IMAGE_DOWNLOAD
            ? $this->delivery->url($file, $actor, 'download')
            : $url;

        $markup = match ($template) {
            CategoryDefaults::FILE_DOWNLOAD => '['.$name.' ('.self::humanSize((int) $file->size).')]('.$url.')',
            CategoryDefaults::IMAGE_DOWNLOAD => '[!['.$name.']('.$thumbnailUrl.')]('.$downloadUrl.')',
            CategoryDefaults::IMAGE_INLINE, CategoryDefaults::MARKDOWN_IMAGE => '!['.$name.']('.$url.')',
            CategoryDefaults::BBCODE_IMAGE => '[img]'.$url.'[/img]',
            CategoryDefaults::URL_ONLY => $url,
            default => throw new \RuntimeException('Unknown insertion template.'),
        };

        return ['markup' => $markup, 'url' => $url];
    }

    private static function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }
        return number_format($value, $index === 0 ? 0 : 1).$units[$index];
    }
}
