<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

class EncryptUsersEmailWithSecretField extends AbstractMigration
{
    private const PREFIX = 'sf1$';

    public function up(): void
    {
        $key = $this->readKeyFromEnv();
        $connection = $this->getAdapter()->getConnection();

        // Elaborazione a lotti: carichiamo solo BATCH_SIZE righe per volta per
        // restare entro il memory_limit (la versione "carica tutto" andava in
        // OOM su tabelle grandi). Le righe cifrate non rientrano più nel filtro
        // NOT LIKE 'sf1$%', quindi ogni SELECT pesca il lotto successivo e la
        // migrazione è ripartibile in sicurezza se interrotta.
        $batchSize = 500;

        do {
            $rows = $this->fetchAll(
                "SELECT id, email FROM users
                 WHERE email IS NOT NULL AND email <> ''
                   AND email NOT LIKE '" . self::PREFIX . "%'
                 LIMIT {$batchSize}"
            );

            foreach ($rows as $row) {
                $id = (string)$row['id'];
                $email = (string)$row['email'];

                if ($this->isEncrypted($email)) {
                    continue;
                }

                $encrypted = $this->encryptDeterministic($email, $key);

                $quotedEmail = $connection->quote($encrypted);
                $quotedId = $connection->quote($id);
                $this->execute("UPDATE users SET email = {$quotedEmail} WHERE id = {$quotedId}");
            }

            $processed = count($rows);
            unset($rows);
        } while ($processed === $batchSize);
    }

    public function down(): void
    {
        $key = $this->readKeyFromEnv();

        $rows = $this->fetchAll(
            "SELECT id, email FROM users WHERE email IS NOT NULL AND email LIKE '" . self::PREFIX . "%'"
        );

        foreach ($rows as $row) {
            $id = (string)$row['id'];
            $email = (string)$row['email'];

            $decrypted = $this->decrypt($email, $key);
            if ($decrypted === null) {
                throw new RuntimeException("Unable to decrypt email for user id {$id}");
            }

            $quotedEmail = $this->getAdapter()->getConnection()->quote($decrypted);
            $quotedId = $this->getAdapter()->getConnection()->quote($id);
            $this->execute("UPDATE users SET email = {$quotedEmail} WHERE id = {$quotedId}");
        }
    }

    private function readKeyFromEnv(): string
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            throw new RuntimeException('The sodium extension is required.');
        }

        $raw = getenv('SECRET_FIELD_KEY');
        if ($raw === false || $raw === '') {
            throw new RuntimeException('SECRET_FIELD_KEY is missing. Set it in environment before running this migration.');
        }

        if (strncmp($raw, 'base64:', 7) === 0) {
            $decoded = base64_decode(substr($raw, 7), true);
            if ($decoded === false) {
                throw new RuntimeException('SECRET_FIELD_KEY base64 format is invalid.');
            }
            $raw = $decoded;
        }

        if (strlen($raw) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('SECRET_FIELD_KEY must resolve to exactly 32 bytes.');
        }

        return $raw;
    }

    private function isEncrypted(string $value): bool
    {
        return strncmp($value, self::PREFIX, strlen(self::PREFIX)) === 0;
    }

    private function encryptDeterministic(string $plaintext, string $key): string
    {
        $nonceKey = sodium_crypto_generichash('nonce', $key, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $nonce = sodium_crypto_generichash($plaintext, $nonceKey, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);

        return self::PREFIX . base64_encode($nonce . $ciphertext);
    }

    private function decrypt(string $storedValue, string $key): ?string
    {
        if (!$this->isEncrypted($storedValue)) {
            return $storedValue;
        }

        $payload = substr($storedValue, strlen(self::PREFIX));
        $decoded = base64_decode($payload, true);
        if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        if ($plaintext === false) {
            return null;
        }

        return $plaintext;
    }
}
