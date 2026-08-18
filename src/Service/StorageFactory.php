<?php

namespace Mie\FlarumFiles\Service;

use Mie\FlarumFiles\Model\StorageConfig;

final class StorageFactory
{
    public function __construct(
        private DogeCloudCredentialProvider $credentials,
        private CredentialCipher $cipher
    ) {}

    public function make(string $name): Storage
    {
        if ($name === 'local') {
            return new LocalStorage(storage_path('mie-files'));
        }

        $config = $this->configuration($name);

        return match ($config->driver) {
            'dogecloud' => $this->dogeCloud($config),
            'aliyun_oss' => $this->aliyunOss($config),
            default => throw new \RuntimeException('The selected storage configuration is unavailable.'),
        };
    }

    public function pathPrefix(string $name): string
    {
        if ($name === 'local') {
            return '';
        }

        $config = $this->configuration($name);

        return match ($config->driver) {
            'dogecloud', 'aliyun_oss' => trim((string) $config->path_prefix, '/'),
            default => throw new \RuntimeException('The selected storage configuration is unavailable.'),
        };
    }

    private function configuration(string $name): StorageConfig
    {
        $config = StorageConfig::query()->where('name', $name)->where('enabled', true)->first();
        if (!$config) {
            throw new \RuntimeException('The selected storage configuration is unavailable.');
        }

        return $config;
    }

    private function dogeCloud(StorageConfig $config): DogeCloudStorage
    {
        if (!$config->access_key_ciphertext || !$config->secret_key_ciphertext) {
            throw new \RuntimeException('The selected storage configuration has no credentials.');
        }

        $credentials = $this->credentials->resolve($config);

        return new DogeCloudStorage(
            $credentials->endpoint,
            $credentials->bucket,
            $credentials->accessKeyId,
            $credentials->secretAccessKey,
            $credentials->sessionToken,
            $config->public_base_url ?: null
        );
    }

    private function aliyunOss(StorageConfig $config): AliyunOssStorage
    {
        if (!$config->access_key_ciphertext || !$config->secret_key_ciphertext) {
            throw new \RuntimeException('The selected storage configuration has no credentials.');
        }

        return new AliyunOssStorage(
            (string) $config->endpoint,
            (string) $config->bucket,
            (string) $config->region,
            $this->cipher->decrypt((string) $config->access_key_ciphertext),
            $this->cipher->decrypt((string) $config->secret_key_ciphertext),
            $config->public_base_url ?: null
        );
    }
}
