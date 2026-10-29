<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

final class MfaService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        $bytes = random_bytes(20);
        $bits = '';
        foreach (str_split($bytes) as $byte) $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        $secret = '';
        foreach (str_split($bits, 5) as $chunk) $secret .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        return $secret;
    }

    public function provisioningUri(User $user, string $secret): string
    {
        return 'otpauth://totp/'.rawurlencode('Enterprise ERP Cloud:'.$user->email).'?secret='.$secret.'&issuer='.rawurlencode('Enterprise ERP Cloud');
    }

    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $normalized = preg_replace('/\s+/', '', $code);
        if (! is_string($normalized) || ! preg_match('/^\d{6}$/', $normalized)) return false;
        $time = (int) floor(time() / 30);
        for ($offset = -$window; $offset <= $window; $offset++) if (hash_equals($this->totp($secret, $time + $offset), $normalized)) return true;
        return false;
    }

    public function totp(string $secret, int $counter): string
    {
        $bits = ''; foreach (str_split($secret) as $character) $bits .= str_pad(decbin(strpos(self::ALPHABET, strtoupper($character))), 5, '0', STR_PAD_LEFT);
        $binary = ''; foreach (str_split($bits, 8) as $chunk) if (strlen($chunk) === 8) $binary .= chr(bindec($chunk));
        $key = $binary; $time = pack('N*', 0).pack('N*', $counter); $hash = hash_hmac('sha1', $time, $key, true); $offset = ord($hash[19]) & 0xf; $value = ((ord($hash[$offset]) & 0x7f) << 24) | ((ord($hash[$offset + 1]) & 0xff) << 16) | ((ord($hash[$offset + 2]) & 0xff) << 8) | (ord($hash[$offset + 3]) & 0xff); return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function encrypt(string $secret): string { return Crypt::encryptString($secret); }
    public function decrypt(User $user): ?string { return $user->mfa_secret_encrypted ? Crypt::decryptString($user->mfa_secret_encrypted) : null; }
    public function recoveryCodes(): array { return collect(range(1, 8))->map(fn () => strtoupper(bin2hex(random_bytes(5))))->all(); }
    public function consumeRecoveryCode(User $user, string $code): bool { $codes = $user->mfa_recovery_codes ?? []; foreach ($codes as $index => $hash) if (Hash::check(strtoupper($code), $hash)) { unset($codes[$index]); $user->update(['mfa_recovery_codes' => array_values($codes)]); return true; } return false; }
}
