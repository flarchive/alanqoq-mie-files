<?php

namespace Mie\FlarumFiles\Service;

use Mie\FlarumFiles\Model\StorageConfig;

final class DogeCloudCredentialProvider
{
    public function __construct(
        private CredentialCipher $cipher,
        private DogeCloudApiClient $api
    ) {}

    public function resolve(StorageConfig $storage): DogeCloudTemporaryCredentials
    {
        $cached = $this->cachedCredentials($storage);
        if ($cached !== null) {
            return $cached;
        }

        $credentials = $this->api->temporaryCredentials(
            $this->cipher->decrypt((string) $storage->access_key_ciphertext),
            $this->cipher->decrypt((string) $storage->secret_key_ciphertext),
            (string) $storage->bucket
        );
        $storage->forceFill([
            'doge_temporary_credentials_ciphertext' => $this->cipher->encrypt(json_encode($credentials->toArray(), JSON_THROW_ON_ERROR)),
            'doge_temporary_credentials_expires_at' => $credentials->expiresAt,
        ])->save();

        return $credentials;
    }

    private function cachedCredentials(StorageConfig $storage): ?DogeCloudTemporaryCredentials
    {
        if (!$storage->doge_temporary_credentials_ciphertext || !$storage->doge_temporary_credentials_expires_at) {
            return null;
        }

        try {
            $payload = json_decode(
                $this->cipher->decrypt((string) $storage->doge_temporary_credentials_ciphertext),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            $credentials = DogeCloudTemporaryCredentials::fromArray($payload);

            return $credentials->isUsableAt(time()) ? $credentials : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
