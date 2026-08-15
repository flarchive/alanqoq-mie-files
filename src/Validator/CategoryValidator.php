<?php

namespace Mie\FlarumFiles\Validator;

use Mie\FlarumFiles\CategoryDefaults;
use Mie\FlarumFiles\Model\Category;

final class CategoryValidator
{
    /** @return array<string, mixed> */
    public static function validate(array $data, ?int $ignoreId = null, ?array $otherCategories = null): array
    {
        foreach (['slug', 'name', 'permissionName', 'rules', 'storageName', 'maxSizeMb', 'insertTemplate'] as $field) {
            if (!array_key_exists($field, $data)) {
                throw new \InvalidArgumentException("Missing {$field}.");
            }
        }

        $slug = self::slug((string) $data['slug'], 'slug');
        $permissionName = self::slug((string) $data['permissionName'], 'permission name');
        $name = trim((string) $data['name']);
        $storageName = self::slug((string) $data['storageName'], 'storage name');
        $template = (string) $data['insertTemplate'];
        $maxSizeMb = filter_var($data['maxSizeMb'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10240]]);

        if ($name === '' || mb_strlen($name) > 128 || $maxSizeMb === false) {
            throw new \InvalidArgumentException('Category name or maximum size is invalid.');
        }
        if (!in_array($template, CategoryDefaults::INSERT_TEMPLATES, true)) {
            throw new \InvalidArgumentException('Unknown insertion template.');
        }

        $rules = self::normalizeRules((array) $data['rules']);
        if (in_array($template, [CategoryDefaults::IMAGE_DOWNLOAD, CategoryDefaults::IMAGE_INLINE, CategoryDefaults::MARKDOWN_IMAGE, CategoryDefaults::BBCODE_IMAGE], true)
            && array_filter($rules, fn (array $rule) => !str_starts_with($rule['mime'], 'image/'))) {
            throw new \InvalidArgumentException('Image insertion templates require image MIME rules.');
        }
        $others = $otherCategories ?? Category::query()->when($ignoreId, fn ($q) => $q->where('id', '<>', $ignoreId))->get()->all();
        self::assertNoConflicts($rules, $others);

        return [
            'slug' => $slug,
            'name' => $name,
            'permission_name' => $permissionName,
            'rules' => $rules,
            'extensions' => array_values(array_unique(array_column($rules, 'extension'))),
            'mimes' => array_values(array_unique(array_column($rules, 'mime'))),
            'storage_name' => $storageName,
            'max_size' => $maxSizeMb * 1024 * 1024,
            'insert_template' => $template,
            'enabled' => (bool) ($data['enabled'] ?? true),
        ];
    }

    /** @return list<array{extension:string,mime:string}> */
    public static function normalizeRules(array $rules): array
    {
        if ($rules === []) {
            throw new \InvalidArgumentException('At least one file type rule is required.');
        }

        $normal = [];
        foreach ($rules as $rule) {
            $extension = strtolower(ltrim(trim((string) ($rule['extension'] ?? '')), '.'));
            $mime = strtolower(trim((string) ($rule['mime'] ?? '')));
            if (!preg_match('/^[a-z0-9]{1,16}$/', $extension) || !preg_match('#^[a-z0-9][a-z0-9.+-]*/[a-z0-9][a-z0-9.+-]*$#', $mime)) {
                throw new \InvalidArgumentException('A file type rule is invalid.');
            }
            $normal[$extension.'|'.$mime] = ['extension' => $extension, 'mime' => $mime];
        }

        return array_values($normal);
    }

    /** @param iterable<Category|array<string,mixed>> $categories */
    public static function assertNoConflicts(array $rules, iterable $categories): void
    {
        foreach ($categories as $category) {
            $existing = $category instanceof Category ? (array) $category->rules : (array) ($category['rules'] ?? []);
            foreach (self::normalizeRules($existing) as $old) {
                foreach ($rules as $new) {
                    if ($new['extension'] !== $old['extension']) {
                        continue;
                    }
                    if (!in_array($new['extension'], ['ogg', 'webm'], true) || $new['mime'] === $old['mime']) {
                        throw new \InvalidArgumentException('The extension/MIME rule is already assigned to another category.');
                    }
                }
            }
        }
    }

    private static function slug(string $value, string $label): string
    {
        $value = strtolower(trim($value));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $value)) {
            throw new \InvalidArgumentException("Invalid {$label}.");
        }
        return $value;
    }
}
