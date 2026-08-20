<?php

namespace Mie\FlarumFiles\Service;

use Mie\FlarumFiles\Model\StorageConfig;

final class StorageConfigService
{
    private const SUPPORTED_DRIVERS = ['dogecloud', 'aliyun_oss'];

    public function __construct(private CredentialCipher $cipher) {}

    public function save(array $data, ?StorageConfig $storage = null): StorageConfig
    {
        $storage ??= new StorageConfig();
        $name = trim((string) ($data['name'] ?? $storage->name));
        $driver = (string) ($data['driver'] ?? $storage->driver ?? '');
        if ($name === '' || mb_strlen($name) > 64 || preg_match('/[\p{Cc}]/u', $name) !== 0 || !in_array($driver, self::SUPPORTED_DRIVERS, true)) {
            throw new \InvalidArgumentException('Storage name or driver is invalid.');
        }
        if ($storage->exists && $storage->driver !== $driver) {
            throw new \InvalidArgumentException('Changing an existing storage driver is not supported.');
        }

        return match ($driver) {
            'dogecloud' => $this->saveDogeCloud($data, $storage, $name),
            'aliyun_oss' => $this->saveAliyunOss($data, $storage, $name),
            default => throw new \InvalidArgumentException('Storage name or driver is invalid.'),
        };
    }

    private function saveDogeCloud(array $data, StorageConfig $storage, string $name): StorageConfig
    {
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
        $pathPrefix = $this->normalizePathPrefix((string) ($data['pathPrefix'] ?? $storage->path_prefix ?? ''));
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
        $storage->driver = 'dogecloud';
        $storage->path_prefix = $pathPrefix ?: null;
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

    private function saveAliyunOss(array $data, StorageConfig $storage, string $name): StorageConfig
    {
        $baseUrl = $this->publicBaseUrl($data, $storage);
        $pathPrefix = $this->normalizePathPrefix((string) ($data['pathPrefix'] ?? $storage->path_prefix ?? ''));
        foreach (['endpoint', 'bucket', 'region'] as $field) {
            $submitted = trim((string) ($data[$field] ?? ''));
            $value = $submitted !== '' ? $submitted : trim((string) ($storage->{$field} ?? ''));
            if ($value === '') {
                throw new \InvalidArgumentException("Storage {$field} is required.");
            }
            $storage->{$field} = $value;
        }
        $this->validateHttpUrl((string) $storage->endpoint, 'Endpoint');
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)+$/', (string) $storage->region) !== 1) {
            throw new \InvalidArgumentException('Storage region is invalid.');
        }
        $storage->name = $name;
        $storage->driver = 'aliyun_oss';
        $storage->path_prefix = $pathPrefix ?: null;
        $storage->public_base_url = $baseUrl ?: null;
        $storage->direct_delivery_confirmed = $baseUrl !== '' && (bool) ($data['directDeliveryConfirmed'] ?? $storage->direct_delivery_confirmed);
        $storage->enabled = (bool) ($data['enabled'] ?? $storage->enabled ?? true);
        foreach (['accessKey' => 'access_key_ciphertext', 'secretKey' => 'secret_key_ciphertext'] as $input => $column) {
            $value = trim((string) ($data[$input] ?? ''));
            if ($value !== '') {
                $storage->{$column} = $this->cipher->encrypt($value);
            }
        }
        if (!$storage->access_key_ciphertext || !$storage->secret_key_ciphertext) {
            throw new \InvalidArgumentException('Aliyun OSS AccessKey and SecretKey are required for a new storage configuration.');
        }
        $storage->save();

        return $storage;
    }

    private function publicBaseUrl(array $data, StorageConfig $storage): string
    {
        $baseUrl = trim((string) ($data['publicBaseUrl'] ?? $storage->public_base_url ?? ''));
        if ($baseUrl !== '') {
            $this->validateHttpUrl($baseUrl, 'The public base URL');
            if (empty($data['directDeliveryConfirmed']) && !$storage->direct_delivery_confirmed) {
                throw new \InvalidArgumentException('Direct delivery must be explicitly confirmed.');
            }
        }

        return $baseUrl;
    }

    private function validateHttpUrl(string $value, string $label): void
    {
        $parts = parse_url($value);
        if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass']) || empty($parts['host'])) {
            throw new \InvalidArgumentException("{$label} must be an HTTP(S) URL without credentials.");
        }
    }

    private function normalizePathPrefix(string $value): string
    {
        $prefix = trim(str_replace('\\', '/', $value));
        $prefix = trim(preg_replace('~/+~', '/', $prefix) ?? $prefix, '/');
        if (mb_strlen($prefix) > 255 || preg_match('/[\p{Cc}]/u', $prefix) !== 0 || preg_match('~(?:^|/)\.\.?($|/)~', $prefix) === 1) {
            throw new \InvalidArgumentException('Storage path prefix is invalid.');
        }

        return $prefix;
    }
}
