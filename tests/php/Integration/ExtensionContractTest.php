<?php

namespace Mie\FlarumFiles\Tests\Integration;

use Flarum\Extend\ExtenderInterface;
use Mie\FlarumFiles\Api\Serializer\FileSerializer;
use Mie\FlarumFiles\Api\Controller\StorageController;
use Mie\FlarumFiles\Model\Category;
use Mie\FlarumFiles\Model\File;
use Mie\FlarumFiles\Model\StorageConfig;
use PHPUnit\Framework\TestCase;

final class ExtensionContractTest extends TestCase
{
    public function testExtensionFileLoadsAgainstFlarumTwo(): void
    {
        $extenders = require dirname(__DIR__, 3).'/extend.php';
        self::assertNotEmpty($extenders);
        foreach ($extenders as $extender) {
            self::assertInstanceOf(ExtenderInterface::class, $extender);
        }
    }

    public function testPublicFilePayloadOmitsStorageObjectKey(): void
    {
        $category = new Category();
        $category->forceFill(['name' => 'Images']);
        $file = new File();
        $file->forceFill([
            'id' => 1,
            'category_id' => 1,
            'original_name' => 'image.png',
            'extension' => 'png',
            'mime_type' => 'image/png',
            'size' => 100,
            'status' => 'success',
            'downloads' => 0,
            'object_key' => 'private/key.png',
            'public_token' => str_repeat('a', 48),
        ]);
        $file->setRelation('category', $category);
        $payload = FileSerializer::attributes($file);
        self::assertArrayNotHasKey('object_key', $payload);
        self::assertArrayNotHasKey('objectKey', $payload);
        self::assertArrayNotHasKey('public_token', $payload);
    }

    public function testStoragePayloadMasksCredentialsAndEndpoint(): void
    {
        $storage = new StorageConfig();
        $storage->forceFill([
            'id' => 1,
            'name' => 'doge',
            'driver' => 'dogecloud',
            'enabled' => true,
            'endpoint' => 'https://secret-endpoint.example',
            'bucket' => 'bucket',
            'region' => 'auto',
            'access_key_ciphertext' => 'encrypted-access',
            'secret_key_ciphertext' => 'encrypted-secret',
            'public_base_url' => null,
            'direct_delivery_confirmed' => false,
        ]);
        $method = new \ReflectionMethod(StorageController::class, 'attributes');
        $payload = $method->invoke(null, $storage);
        foreach (['endpoint', 'accessKey', 'secretKey', 'access_key_ciphertext', 'secret_key_ciphertext'] as $forbidden) {
            self::assertArrayNotHasKey($forbidden, $payload);
        }
        self::assertTrue($payload['hasCredentials']);
        self::assertTrue($payload['endpointConfigured']);
    }
}
