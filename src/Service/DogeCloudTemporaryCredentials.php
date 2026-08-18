<?php

namespace Mie\FlarumFiles\Service;

final readonly class DogeCloudTemporaryCredentials
{
    public function __construct(
        public string $accessKeyId,
        public string $secretAccessKey,
        public string $sessionToken,
        public string $endpoint,
        public string $bucket,
        public int $expiresAt
    ) {}

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'accessKeyId' => $this->accessKeyId,
            'secretAccessKey' => $this->secretAccessKey,
            'sessionToken' => $this->sessionToken,
            'endpoint' => $this->endpoint,
            'bucket' => $this->bucket,
            'expiresAt' => $this->expiresAt,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $strings = [];
        foreach (['accessKeyId', 'secretAccessKey', 'sessionToken', 'endpoint', 'bucket'] as $field) {
            $value = $data[$field] ?? null;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException('DogeCloud returned incomplete temporary S3 credentials.');
            }
            $strings[$field] = $value;
        }
        $expiresAt = filter_var($data['expiresAt'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($expiresAt === false) {
            throw new \InvalidArgumentException('DogeCloud returned an invalid temporary credential expiration.');
        }

        return new self(
            $strings['accessKeyId'],
            $strings['secretAccessKey'],
            $strings['sessionToken'],
            $strings['endpoint'],
            $strings['bucket'],
            $expiresAt
        );
    }

    public function isUsableAt(int $now): bool
    {
        return $this->expiresAt > $now + 60;
    }
}
