<?php

namespace Mie\FlarumFiles\Tests;

use Mie\FlarumFiles\Model\StorageConfig;
use Mie\FlarumFiles\Service\CredentialCipher;
use Mie\FlarumFiles\Service\StorageConfigService;
use PHPUnit\Framework\TestCase;

final class StorageConfigServiceTest extends TestCase
{
    public function testUnicodeStorageNameIsSavedUnchanged(): void
    {
        $storage = new class extends StorageConfig {
            public function save(array $options = []): bool
            {
                return true;
            }
        };
        $storage->access_key_ciphertext = 'access';
        $storage->secret_key_ciphertext = 'secret';

        $saved = $this->service()->save([
            'name' => '多吉云',
            'driver' => 'dogecloud',
            'endpoint' => 'https://s3.example.com',
            'bucket' => 'files',
        ], $storage);

        self::assertSame('多吉云', $saved->name);
    }

    public function testInvalidStorageNameOrDriverIsRejected(): void
    {
        foreach ([['', 'dogecloud'], ["multi\nline", 'dogecloud'], [str_repeat('a', 65), 'dogecloud'], ['多吉云', 's3']] as [$name, $driver]) {
            try {
                $this->service()->save(['name' => $name, 'driver' => $driver]);
                self::fail('Expected invalid storage configuration to be rejected.');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('Storage name or driver is invalid.', $exception->getMessage());
            }
        }
    }

    public function testReplacingPermanentKeysInvalidatesCachedDogeCloudCredentials(): void
    {
        $storage = new class extends StorageConfig {
            public function save(array $options = []): bool
            {
                return true;
            }
        };
        $storage->forceFill([
            'name' => '多吉云',
            'driver' => 'dogecloud',
            'endpoint' => 'https://cos.ap-guangzhou.myqcloud.com',
            'bucket' => 's-gz-3965-flarum-1258813047',
            'access_key_ciphertext' => 'old-access',
            'secret_key_ciphertext' => 'old-secret',
            'doge_temporary_credentials_ciphertext' => 'cached-credentials',
            'doge_temporary_credentials_expires_at' => time() + 3600,
        ]);

        $saved = $this->service()->save([
            'accessKey' => 'new-permanent-access',
            'secretKey' => 'new-permanent-secret',
        ], $storage);

        self::assertNull($saved->doge_temporary_credentials_ciphertext);
        self::assertNull($saved->doge_temporary_credentials_expires_at);
    }

    private function service(): StorageConfigService
    {
        $reflection = new \ReflectionClass(CredentialCipher::class);
        $cipher = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('key')->setValue($cipher, random_bytes(32));

        return new StorageConfigService($cipher);
    }
}
