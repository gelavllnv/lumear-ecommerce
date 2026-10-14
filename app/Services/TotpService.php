<?php

namespace App\Services;

/** RFC 6238 TOTP implementation for administrator authentication. */
class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function verify(string $secret, string $code, int $window = 1): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) return false;
        $counter = intdiv(time(), 30);
        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->codeAt($secret, $counter + $offset), $code)) return true;
        }
        return false;
    }

    private function codeAt(string $secret, int $counter): string
    {
        $message = pack('N2', intdiv($counter, 4_294_967_296), $counter % 4_294_967_296);
        $hash = hash_hmac('sha1', $message, $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $value): string
    {
        $bits = '';
        foreach (str_split($value) as $character) $bits .= str_pad(decbin(ord($character)), 8, '0', STR_PAD_LEFT);
        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) $encoded .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        return $encoded;
    }

    private function base32Decode(string $value): string
    {
        $bits = '';
        foreach (str_split(strtoupper(str_replace([' ', '='], '', $value))) as $character) {
            $position = strpos(self::ALPHABET, $character);
            if ($position === false) throw new \InvalidArgumentException('Invalid TOTP secret.');
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }
        $decoded = '';
        foreach (str_split($bits, 8) as $chunk) if (strlen($chunk) === 8) $decoded .= chr(bindec($chunk));
        return $decoded;
    }
}
