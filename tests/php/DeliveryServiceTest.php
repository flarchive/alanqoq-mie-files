<?php

namespace Mie\FlarumFiles\Tests;

use Flarum\User\User;
use Mie\FlarumFiles\CategoryDefaults;
use Mie\FlarumFiles\Model\Category;
use Mie\FlarumFiles\Model\File;
use Mie\FlarumFiles\Service\DeliveryService;
use PHPUnit\Framework\TestCase;

final class DeliveryServiceTest extends TestCase
{
    public function testFileDownloadImageCannotUsePreviewProxyMode(): void
    {
        $delivery = $this->service();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid file delivery mode.');
        $delivery->url($this->file(CategoryDefaults::FILE_DOWNLOAD), $this->actor(), 'preview');
    }

    public function testThumbnailAuthorizationAllowsImageViewWithoutDownloadPermission(): void
    {
        $delivery = $this->service();
        $method = (new \ReflectionClass(DeliveryService::class))->getMethod('assertThumbnailPermission');
        $method->setAccessible(true);

        self::assertNull($method->invoke($delivery, $this->file(CategoryDefaults::FILE_DOWNLOAD), $this->actor()));
    }

    public function testThumbnailAuthorizationRejectsNonImages(): void
    {
        $delivery = $this->service();
        $file = $this->file(CategoryDefaults::FILE_DOWNLOAD);
        $file->mime_type = 'application/pdf';
        $method = (new \ReflectionClass(DeliveryService::class))->getMethod('assertThumbnailPermission');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This file is not an image.');
        $method->invoke($delivery, $file, $this->actor());
    }

    private function service(): DeliveryService
    {
        return (new \ReflectionClass(DeliveryService::class))->newInstanceWithoutConstructor();
    }

    private function file(string $template): File
    {
        $category = new Category();
        $category->forceFill([
            'insert_template' => $template,
            'permission_name' => 'images',
        ]);

        $file = new File();
        $file->forceFill([
            'status' => 'success',
            'mime_type' => 'image/avif',
        ]);
        $file->setRelation('category', $category);

        return $file;
    }

    private function actor(): User
    {
        return new class extends User {
            public function isAdmin(): bool
            {
                return false;
            }

            public function can(string $ability, mixed $arguments = null): bool
            {
                return $ability === 'mie-files.category.images.view';
            }
        };
    }
}
