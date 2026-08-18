<?php

namespace Mie\FlarumFiles\Tests;

use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use Mie\FlarumFiles\Model\StorageConfig;
use Mie\FlarumFiles\Service\AliyunOssStorage;
use Mie\FlarumFiles\Service\CredentialCipher;
use Mie\FlarumFiles\Service\DogeCloudCredentialProvider;
use Mie\FlarumFiles\Service\StorageFactory;
use PHPUnit\Framework\TestCase;

final class StorageFactoryTest extends TestCase
{
    public function testAliyunFactoryBuildsStorageWithSegmentEncodedPublicUrl(): void
    {
        $cipher = $this->cipher();
        $config = new StorageConfig();
        $config->forceFill([
            'endpoint' => 'https://oss-cn-hangzhou.aliyuncs.com',
            'bucket' => 'files',
            'region' => 'cn-hangzhou',
            'access_key_ciphertext' => $cipher->encrypt('access-key'),
            'secret_key_ciphertext' => $cipher->encrypt('secret-key'),
            'public_base_url' => 'https://cdn.example.com/files',
        ]);
        $credentials = (new \ReflectionClass(DogeCloudCredentialProvider::class))->newInstanceWithoutConstructor();
        $factory = new StorageFactory($credentials, $cipher);
        $method = new \ReflectionMethod(StorageFactory::class, 'aliyunOss');

        /** @var AliyunOssStorage $storage */
        $storage = $method->invoke($factory, $config);

        self::assertInstanceOf(AliyunOssStorage::class, $storage);
        self::assertSame('https://cdn.example.com/files/folder/a%20file%23.txt', $storage->publicUrl('/folder/a file#.txt'));

        $mock = new MockHandler();
        $mock->append(new Result([]));
        $client = new S3Client([
            'version' => 'latest',
            'region' => 'cn-hangzhou',
            'endpoint' => 'https://oss-cn-hangzhou.aliyuncs.com',
            'use_path_style_endpoint' => false,
            'credentials' => ['key' => 'access-key', 'secret' => 'secret-key'],
            'handler' => $mock,
        ]);
        (new \ReflectionProperty(AliyunOssStorage::class, 'client'))->setValue($storage, $client);
        $source = tempnam(sys_get_temp_dir(), 'mie-files-test-');
        self::assertNotFalse($source);
        file_put_contents($source, 'test');
        try {
            $storage->put('forum/uploads/a file.txt', $source);
            self::assertSame('forum/uploads/a file.txt', $mock->getLastCommand()->toArray()['Key']);
        } finally {
            unlink($source);
        }
    }

    private function cipher(): CredentialCipher
    {
        $reflection = new \ReflectionClass(CredentialCipher::class);
        $cipher = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('key')->setValue($cipher, random_bytes(32));

        return $cipher;
    }
}
