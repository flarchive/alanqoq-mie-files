<?php

namespace Mie\FlarumFiles\Tests;

use Mie\FlarumFiles\Service\DogeCloudApiClient;
use PHPUnit\Framework\TestCase;

final class DogeCloudApiClientTest extends TestCase
{
    public function testPermanentApiKeysAreExchangedForThreePartS3Credentials(): void
    {
        $client = new class([
            'code' => 200,
            'data' => [
                'Credentials' => [
                    'accessKeyId' => 'AKIDtemporary',
                    'secretAccessKey' => 'temporary-secret',
                    'sessionToken' => 'temporary-session-token',
                ],
                'ExpiredAt' => time() + 3600,
                'Buckets' => [[
                    'name' => 'forum-files',
                    's3Bucket' => 's-gz-3965-flarum-1258813047',
                    's3Endpoint' => 'https://cos.ap-guangzhou.myqcloud.com',
                ]],
            ],
        ]) extends DogeCloudApiClient {
            /** @var array<string, mixed> */
            private array $response;
            public string $body = '';
            public string $authorization = '';

            /** @param array<string, mixed> $response */
            public function __construct(array $response)
            {
                $this->response = $response;
            }

            /** @return array{status:int, body:string} */
            protected function post(string $body, string $authorization): array
            {
                $this->body = $body;
                $this->authorization = $authorization;

                return ['status' => 200, 'body' => json_encode($this->response, JSON_THROW_ON_ERROR)];
            }
        };

        $credentials = $client->temporaryCredentials('permanent-access', 'permanent-secret', 's-gz-3965-flarum-1258813047');

        self::assertSame('AKIDtemporary', $credentials->accessKeyId);
        self::assertSame('temporary-session-token', $credentials->sessionToken);
        self::assertSame('https://cos.ap-guangzhou.myqcloud.com', $credentials->endpoint);
        self::assertSame('s-gz-3965-flarum-1258813047', $credentials->bucket);
        self::assertSame(
            'TOKEN permanent-access:'.hash_hmac('sha1', "/auth/tmp_token.json\n".$client->body, 'permanent-secret'),
            $client->authorization
        );
        self::assertSame(['channel' => 'OSS_FULL', 'scopes' => ['*'], 'ttl' => 7200], json_decode($client->body, true, 512, JSON_THROW_ON_ERROR));
    }

    public function testRejectsATemporaryCredentialResponseForAnotherBucket(): void
    {
        $client = new class extends DogeCloudApiClient {
            /** @return array{status:int, body:string} */
            protected function post(string $body, string $authorization): array
            {
                return ['status' => 200, 'body' => json_encode([
                    'code' => 200,
                    'data' => [
                        'Credentials' => [
                            'accessKeyId' => 'AKIDtemporary',
                            'secretAccessKey' => 'temporary-secret',
                            'sessionToken' => 'temporary-session-token',
                        ],
                        'ExpiredAt' => time() + 3600,
                        'Buckets' => [[
                            'name' => 'another-bucket',
                            's3Bucket' => 's-cd-1-another-bucket-1',
                            's3Endpoint' => 'https://cos.ap-chengdu.myqcloud.com',
                        ]],
                    ],
                ], JSON_THROW_ON_ERROR)];
            }
        };

        $this->expectExceptionMessage('DogeCloud did not return an S3 bucket matching the configured bucket.');
        $client->temporaryCredentials('permanent-access', 'permanent-secret', 's-gz-3965-flarum-1258813047');
    }
}
