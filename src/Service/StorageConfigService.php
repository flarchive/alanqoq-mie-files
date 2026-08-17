<?php

namespace Mie\FlarumFiles\Service;

use Mie\FlarumFiles\Model\StorageConfig;

final class StorageConfigService
{
    public function __construct(private CredentialCipher $cipher) {}

    public function save(array $data, ?StorageConfig $storage = null): StorageConfig
    {
        $storage ??= new StorageConfig();
        $name = trim((string) ($data['name'] ?? $storage->name));
        $driver = (string) ($data['driver'] ?? $storage->driver ?? '');
        if ($name === '' || mb_strlen($name) > 64 || preg_match('/[\p{Cc}]/u', $name) !== 0 || $driver !== 'dogecloud') {
            throw new \InvalidArgumentException('Storage name or driver is invalid.');
        }

        $baseUrl = trim((string) ($data['publicBaseUrl'] ?? $storage->public_base_url ?? ''));
        if ($baseUrl !== '') {
            $parts = parse_url($baseUrl);
            if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass']) || empty($parts['host'])) {
                throw new \InvalidArgumentException('The public base URL must be an HTTP(S) URL without credentials.');
            }
            if (empty($data['directDeliveryConfirmed']) && !$storage->direct_delivery_confirmed) {
                throw new \InvalidArgumentException('Direct delivery must be explicitly confirmed.');
            }
        }
        $storageTargetChanged = false;
        foreach (['endpoint', 'bucket'] as $field) {
            $submitted = trim((string) ($data[$field] ?? ''));
            $value = $submitted !== '' ? $submitted : trim((string) ($storage->{$field} ?? ''));
            if ($value === '') {
                throw new \InvalidArgumentException("Storage {$field} is required.");
            }
            $storageTargetChanged = $storageTargetChanged || $value !== (string) ($storage->{$field} ?? '');
            $storage->{$field} = $value;
        }
        $endpointParts = parse_url((string) $storage->endpoint);
        if (!$endpointParts || !in_array($endpointParts['scheme'] ?? '', ['http', 'https'], true) || isset($endpointParts['user']) || isset($endpointParts['pass']) || empty($endpointParts['host'])) {
            throw new \InvalidArgumentException('Endpoint must be an HTTP(S) URL without credentials.');
        }
        $storage->name = $name;
        $storage->driver = $driver;
        $storage->region = trim((string) ($data['region'] ?? $storage->region ?? 'auto')) ?: 'auto';
        $storage->public_base_url = $baseUrl ?: null;
        $storage->direct_delivery_confirmed = $baseUrl !== '' && (bool) ($data['directDeliveryConfirmed'] ?? $storage->direct_delivery_confirmed);
        $storage->enabled = (bool) ($data['enabled'] ?? $storage->enabled ?? true);
        $permanentCredentialsChanged = false;
        foreach (['accessKey' => 'access_key_ciphertext', 'secretKey' => 'secret_key_ciphertext'] as $input => $column) {
            $value = trim((string) ($data[$input] ?? ''));
            if ($value !== '') {
                $storage->{$column} = $this->cipher->encrypt($value);
                $permanentCredentialsChanged = true;
            }
        }
        if (!$storage->access_key_ciphertext || !$storage->secret_key_ciphertext) {
            throw new \InvalidArgumentException('DogeCloud AccessKey and SecretKey are required for a new storage configuration.');
        }
        if ($permanentCredentialsChanged || $storageTargetChanged) {
            $storage->doge_temporary_credentials_ciphertext = null;
            $storage->doge_temporary_credentials_expires_at = null;
        }
        $storage->save();
        return $storage;
    }
}
