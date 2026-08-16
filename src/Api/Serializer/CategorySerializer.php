<?php

namespace Mie\FlarumFiles\Api\Serializer;

use Mie\FlarumFiles\Model\Category;
use Mie\FlarumFiles\Service\PermissionService;

final class CategorySerializer
{
    /** @return array<string, mixed> */
    public static function attributes(Category $category): array
    {
        return [
            'id' => (string) $category->id,
            'slug' => $category->slug,
            'name' => $category->name,
            'permissionName' => $category->permission_name,
            'maxSizeMb' => (int) floor($category->max_size / 1024 / 1024),
            'storageName' => $category->storage_name,
            'insertTemplate' => $category->insert_template,
            'rules' => (array) $category->rules,
            'extensions' => (array) $category->extensions,
            'mimes' => (array) $category->mimes,
            'enabled' => (bool) $category->enabled,
            'permissions' => [
                'view' => PermissionService::key($category->permission_name, 'view'),
                'download' => PermissionService::key($category->permission_name, 'download'),
                'upload' => PermissionService::key($category->permission_name, 'upload'),
            ],
        ];
    }
}
