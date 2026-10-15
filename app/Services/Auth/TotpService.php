<?php

namespace App\Services\Auth;

use Illuminate\Support\Str;

class TotpService
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a new Base32 TOTP secret (160-bit key, 32 chars).
     */
    public function generateSecret(int $length = 32): string
    {
        $secret = '';
        $max = strlen(self::BASE32_CHARS) - 1;
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_CHARS[random_int(0, $max)];
        }
        return $secret;
    }

    /**
     * Generate the standard otpauth URL for QR code apps.
     */
    public function getOtpAuthUrl(string $companyName, string $email, string $secret): string
    {
        $label = rawurlencode($companyName . ':' . $email);
        $issuer = rawurlencode($companyName);
        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Verify a 6-digit TOTP code with time drift window (+/- 1 time step = 30 seconds).
     */
    public function verifyCode(string $secret, string $code, int $discrepancy = 1): bool
    {
        $code = trim($code);
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }

        $currentTimeSlice = (int) floor(time() / 30);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculated = $this->calculateTotp($secret, $currentTimeSlice + $i);
            if (hash_equals($calculated, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate 6-digit TOTP code for a given time slice using RFC 6238 HMAC-SHA1.
     */
    public function calculateTotp(string $secret, int $timeSlice): string
    {
        $secretKey = $this->base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);

        $hmac = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hmac, -1)) & 0x0F;

        $unpacked = unpack('N', substr($hmac, $offset, 4));
        $value = ($unpacked[1] & 0x7FFFFFFF) % 1000000;

        return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a list of backup recovery codes (e.g. 8 codes of format XXXX-XXXX).
     * Returns an array with ['plain' => [...], 'hashed' => [...]].
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $plain = [];
        $hashed = [];

        for ($i = 0; $i < $count; $i++) {
            $code = strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
            $plain[] = $code;
            $hashed[] = password_hash($code, PASSWORD_DEFAULT);
        }

        return [
            'plain' => $plain,
            'hashed' => $hashed,
        ];
    }

    /**
     * Verify and consume a recovery code from the stored hashed list.
     * Returns [bool $isValid, array $remainingHashedCodes].
     */
    public function verifyAndConsumeRecoveryCode(string $code, array $hashedCodes): array
    {
        $code = trim(strtoupper($code));
        foreach ($hashedCodes as $index => $hash) {
            if (password_verify($code, $hash)) {
                unset($hashedCodes[$index]);
                return [true, array_values($hashedCodes)];
            }
        }

        return [false, $hashedCodes];
    }

    /**
     * Decode a Base32 string to binary.
     */
    private function base32Decode(string $secret): string
    {
        $secret = strtoupper($secret);
        $buffer = 0;
        $bufferSize = 0;
        $binary = '';

        for ($i = 0; $i < strlen($secret); $i++) {
            $char = $secret[$i];
            $position = strpos(self::BASE32_CHARS, $char);
            if ($position === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $position;
            $bufferSize += 5;

            if ($bufferSize >= 8) {
                $bufferSize -= 8;
                $binary .= chr(($buffer >> $bufferSize) & 0xFF);
            }
        }

        return $binary;
    }
}
