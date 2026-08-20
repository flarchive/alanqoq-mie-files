<?php

namespace Mie\FlarumFiles\Service;

use Flarum\Foundation\Config;

final class CredentialCipher
{
    private string $key;

    public function __construct(Config $config)
    {
        $this->key = hash('sha256', (string) ($config['database.password'] ?? '').'|'.(string) $config['url'].'|mie-files', true);
    }
    public function encrypt(string $value): string
    {
        $iv = random_bytes(12); $tag = '';
        $ciphertext = openssl_encrypt($value, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) throw new \RuntimeException('Unable to encrypt credential.');
        return base64_encode($iv.$tag.$ciphertext);
    }
    public function decrypt(string $value): string
    {
        $raw = base64_decode($value, true); if ($raw === false || strlen($raw) < 29) throw new \InvalidArgumentException('Invalid encrypted credential.');
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        if ($plain === false) throw new \RuntimeException('Unable to decrypt credential.'); return $plain;
    }
}
