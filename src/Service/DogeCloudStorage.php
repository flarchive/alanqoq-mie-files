<?php

namespace Mie\FlarumFiles\Service;

use Aws\S3\S3Client;

final class DogeCloudStorage implements Storage
{
    private S3Client $client;

    public function __construct(
        private string $endpoint,
        private string $bucket,
        string $accessKey,
        string $secretKey,
        private ?string $publicBaseUrl = null,
        private string $region = 'auto'
    ) {
        $this->client = new S3Client([
            'version' => 'latest',
            'region' => $this->region ?: 'auto',
            'endpoint' => $this->endpoint,
            'use_path_style_endpoint' => false,
            'credentials' => ['key' => $accessKey, 'secret' => $secretKey],
        ]);
    }

    public function put(string $path, string $source): void
    {
        $this->client->putObject(['Bucket' => $this->bucket, 'Key' => $path, 'SourceFile' => $source]);
    }

    public function stream(string $path)
    {
        return $this->client->getObject(['Bucket' => $this->bucket, 'Key' => $path])['Body'];
    }

    public function delete(string $path): void
    {
        $this->client->deleteObject(['Bucket' => $this->bucket, 'Key' => $path]);
    }

    public function publicUrl(string $path): ?string
    {
        return $this->publicBaseUrl === null ? null : rtrim($this->publicBaseUrl, '/').'/'.ltrim($path, '/');
    }
}
