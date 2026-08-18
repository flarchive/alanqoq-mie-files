<?php

namespace Mie\FlarumFiles\Service;

class DogeCloudApiClient
{
    private const TEMPORARY_TOKEN_PATH = '/auth/tmp_token.json';

    public function temporaryCredentials(string $accessKey, string $secretKey, string $configuredBucket): DogeCloudTemporaryCredentials
    {
        $body = json_encode([
            'channel' => 'OSS_FULL',
            'scopes' => ['*'],
            'ttl' => 7200,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $authorization = 'TOKEN '.$accessKey.':'.hash_hmac('sha1', self::TEMPORARY_TOKEN_PATH."\n".$body, $secretKey);
        $response = $this->post($body, $authorization);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new \RuntimeException('DogeCloud temporary credential request failed.');
        }

        try {
            $payload = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('DogeCloud returned an invalid temporary credential response.', 0, $exception);
        }
        if (!is_array($payload) || (int) ($payload['code'] ?? 0) !== 200 || !is_array($payload['data'] ?? null)) {
            throw new \RuntimeException('DogeCloud could not issue temporary S3 credentials.');
        }

        $data = $payload['data'];
        $bucket = $this->matchingBucket((array) ($data['Buckets'] ?? []), $configuredBucket);
        if ($bucket === null) {
            throw new \RuntimeException('DogeCloud did not return an S3 bucket matching the configured bucket.');
        }

        return DogeCloudTemporaryCredentials::fromArray([
            'accessKeyId' => $data['Credentials']['accessKeyId'] ?? null,
            'secretAccessKey' => $data['Credentials']['secretAccessKey'] ?? null,
            'sessionToken' => $data['Credentials']['sessionToken'] ?? null,
            'endpoint' => $bucket['s3Endpoint'] ?? null,
            'bucket' => $bucket['s3Bucket'] ?? null,
            'expiresAt' => $data['ExpiredAt'] ?? null,
        ]);
    }

    /** @param list<mixed> $buckets
     *  @return array<string, mixed>|null
     */
    private function matchingBucket(array $buckets, string $configuredBucket): ?array
    {
        foreach ($buckets as $bucket) {
            if (!is_array($bucket)) {
                continue;
            }
            if (($bucket['s3Bucket'] ?? null) === $configuredBucket || ($bucket['name'] ?? null) === $configuredBucket) {
                return $bucket;
            }
        }

        return null;
    }

    /** @return array{status:int, body:string} */
    protected function post(string $body, string $authorization): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('The PHP cURL extension is required for DogeCloud storage.');
        }
        $handle = curl_init('https://api.dogecloud.com'.self::TEMPORARY_TOKEN_PATH);
        if ($handle === false) {
            throw new \RuntimeException('Unable to initialize the DogeCloud API request.');
        }

        try {
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: '.$authorization,
                ],
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 20,
            ]);
            $response = curl_exec($handle);
            if (!is_string($response)) {
                throw new \RuntimeException('DogeCloud temporary credential request failed.');
            }

            return ['status' => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'body' => $response];
        } finally {
            curl_close($handle);
        }
    }
}
