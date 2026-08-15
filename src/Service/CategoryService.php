<?php

namespace Mie\FlarumFiles\Service;

use Illuminate\Database\ConnectionInterface;
use Mie\FlarumFiles\CategoryDefaults;
use Mie\FlarumFiles\Model\Category;
use Mie\FlarumFiles\Model\StorageConfig;
use Mie\FlarumFiles\Validator\CategoryValidator;

final class CategoryService
{
    public function __construct(private ConnectionInterface $db) {}

    public function create(array $data): Category
    {
        $attributes = CategoryValidator::validate($data);
        $this->assertStorageExists($attributes['storage_name']);
        $category = new Category();
        $category->fill($attributes)->save();
        return $category;
    }

    public function update(Category $category, array $data): Category
    {
        $attributes = CategoryValidator::validate($data, (int) $category->id);
        $this->assertStorageExists($attributes['storage_name']);
        $category->fill($attributes)->save();
        return $category;
    }

    /** @return list<Category> */
    public function replaceAll(array $items): array
    {
        if ($items === []) {
            throw new \InvalidArgumentException('At least one category is required.');
        }

        $validated = [];
        $slugs = [];
        $permissions = [];
        foreach ($items as $item) {
            $attributes = CategoryValidator::validate((array) $item, null, []);
            CategoryValidator::assertNoConflicts($attributes['rules'], $validated);
            if (isset($slugs[$attributes['slug']]) || isset($permissions[$attributes['permission_name']])) {
                throw new \InvalidArgumentException('Category slug and permission name must be unique.');
            }
            $this->assertStorageExists($attributes['storage_name']);
            $slugs[$attributes['slug']] = true;
            $permissions[$attributes['permission_name']] = true;
            $validated[] = $attributes + ['id' => isset($item['id']) ? (int) $item['id'] : null];
        }

        return $this->db->transaction(function () use ($validated) {
            $kept = [];
            $result = [];
            foreach ($validated as $attributes) {
                $id = $attributes['id'];
                unset($attributes['id']);
                $category = $id
                    ? Category::query()->findOrFail($id)
                    : (Category::query()->where('slug', $attributes['slug'])->first() ?? new Category());
                $category->fill($attributes)->save();
                $kept[] = (int) $category->id;
                $result[] = $category;
            }
            /** @var \Illuminate\Database\Eloquent\Collection<int, Category> $removed */
            $removed = Category::query()->whereNotIn('id', $kept)->get();
            foreach ($removed as $category) {
                if ($category->files()->exists()) {
                    throw new \InvalidArgumentException('A category containing files cannot be removed.');
                }
                $category->delete();
            }
            return $result;
        });
    }

    public function seedTemplates(): void
    {
        foreach (CategoryDefaults::TEMPLATES as $template) {
            if (!Category::query()->where('slug', $template['slug'])->exists()) {
                $this->create($template);
            }
        }
    }

    private function assertStorageExists(string $name): void
    {
        if ($name !== 'local' && !StorageConfig::query()->where('name', $name)->where('enabled', true)->exists()) {
            throw new \InvalidArgumentException('The selected storage configuration does not exist.');
        }
    }
}
