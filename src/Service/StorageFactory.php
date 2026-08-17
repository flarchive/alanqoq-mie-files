<?php

namespace Mie\FlarumFiles\Service;

use Mie\FlarumFiles\Model\StorageConfig;

final class StorageFactory
{
    public function __construct(private DogeCloudCredentialProvider $credentials) {}

    public function make(string $name): Storage
    {
        if ($name === 'local') {
            return new LocalStorage(storage_path('mie-files'));
        }

        $config = StorageConfig::query()->where('name', $name)->where('enabled', true)->first();
        if (!$config || $config->driver !== 'dogecloud') {
            throw new \RuntimeException('The selected storage configuration is unavailable.');
        }
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
            $config->public_base_url ?: null,
            $config->region ?: 'auto'
        );
    }
}
