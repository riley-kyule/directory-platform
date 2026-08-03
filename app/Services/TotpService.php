<?php

namespace App\Services;

use App\Models\User;

class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    /**
     * Verify a 6-digit code against the ±1 step window and return the matched
     * time-step counter, or false when the code doesn't match or was already
     * consumed. Passing $lastUsedCounter rejects a counter at or before it,
     * which stops a captured code from being replayed for the rest of its
     * ~90-second validity window.
     */
    public function verify(string $secret, string $code, ?int $lastUsedCounter = null, ?int $timestamp = null): int|false
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $counter = intdiv($timestamp ?? time(), 30);
        foreach ([-1, 0, 1] as $window) {
            $candidate = $counter + $window;
            if ($lastUsedCounter !== null && $candidate <= $lastUsedCounter) {
                continue;
            }
            if (hash_equals($this->code($secret, $candidate), $code)) {
                return $candidate;
            }
        }

        return false;
    }

    public function currentCode(string $secret, ?int $timestamp = null): string
    {
        return $this->code($secret, intdiv($timestamp ?? time(), 30));
    }

    public function provisioningUri(User $user, string $secret): string
    {
        $issuer = config('app.name');
        $label = rawurlencode($issuer.':'.$user->email);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return list<string> */
    public function recoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => strtoupper(substr(bin2hex(random_bytes(8)), 0, 8).'-'.substr(bin2hex(random_bytes(8)), 0, 8)))
            ->all();
    }

    private function code(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $binaryCounter = pack('N2', 0, $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }

    private function base32Decode(string $encoded): string
    {
        $bits = '';
        foreach (str_split(strtoupper(preg_replace('/[^A-Z2-7]/', '', $encoded))) as $character) {
            $position = strpos(self::ALPHABET, $character);
            if ($position === false) {
                continue;
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $decoded .= chr(bindec($byte));
            }
        }

        return $decoded;
    }
}
