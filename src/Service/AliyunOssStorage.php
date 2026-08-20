<?php

namespace Mie\FlarumFiles\Service;

use Aws\S3\S3Client;

final class AliyunOssStorage implements Storage
{
    private S3Client $client;

    public function __construct(
        string $endpoint,
        private string $bucket,
        string $region,
        string $accessKey,
        string $secretKey,
        private ?string $publicBaseUrl = null
    ) {
        $this->client = new S3Client([
            'version' => 'latest',
            'region' => $region,
            'endpoint' => $endpoint,
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
        if ($this->publicBaseUrl === null) {
            return null;
        }

        $encodedPath = implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));

        return rtrim($this->publicBaseUrl, '/').'/'.$encodedPath;
    }
}
