<?php

namespace App\Services;

use RuntimeException;

class EncryptionService
{
    private string $key;

    private const CIPHER = 'aes-256-gcm';

    private const TAG_LENGTH = 16;

    public function __construct()
    {
        $rawKey = config('app.credential_encryption_key');

        if (! $rawKey) {
            throw new RuntimeException('CREDENTIAL_ENCRYPTION_KEY is not set.');
        }

        $this->key = base64_decode($rawKey);

        if (strlen($this->key) !== 32) {
            throw new RuntimeException('CREDENTIAL_ENCRYPTION_KEY must decode to exactly 32 bytes.');
        }
    }

    public function encrypt(string $plaintext): array
    {
        $iv = random_bytes(12);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return [
            'ciphertext' => base64_encode($ciphertext.$tag),
            'iv' => base64_encode($iv),
        ];
    }

    public function decrypt(string $ciphertext, string $iv): string
    {
        $rawIv = base64_decode($iv);
        $rawData = base64_decode($ciphertext);
        $tag = substr($rawData, -self::TAG_LENGTH);
        $rawCiphertext = substr($rawData, 0, -self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $rawCiphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $rawIv,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException('Decryption failed. Data may be tampered.');
        }

        return $plaintext;
    }
}
