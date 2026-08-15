<?php

namespace Mie\FlarumFiles\Api\Serializer;

use Mie\FlarumFiles\Model\File;

final class FileSerializer
{
    /** @return array<string, mixed> */
    public static function attributes(File $file): array
    {
        return [
            'id' => (string) $file->id,
            'categoryId' => (string) $file->category_id,
            'categoryName' => $file->category->name,
            'originalName' => $file->original_name,
            'extension' => $file->extension,
            'mimeType' => $file->mime_type,
            'size' => (int) $file->size,
            'status' => $file->status,
            'downloads' => (int) $file->downloads,
            'createdAt' => $file->created_at?->toIso8601String(),
        ];
    }
}
