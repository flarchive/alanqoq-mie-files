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

    private function service(): StorageConfigService
    {
        $cipher = (new \ReflectionClass(CredentialCipher::class))->newInstanceWithoutConstructor();

        return new StorageConfigService($cipher);
    }
}
