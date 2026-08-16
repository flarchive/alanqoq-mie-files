<?php

namespace Mie\FlarumFiles\Service;

use Mie\FlarumFiles\Model\StorageConfig;

final class StorageFactory
{
    public function __construct(private CredentialCipher $cipher) {}

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

        return new DogeCloudStorage(
            (string) $config->endpoint,
            (string) $config->bucket,
            $this->cipher->decrypt((string) $config->access_key_ciphertext),
            $this->cipher->decrypt((string) $config->secret_key_ciphertext),
            $config->public_base_url ?: null,
            $config->region ?: 'auto'
        );
    }
}
