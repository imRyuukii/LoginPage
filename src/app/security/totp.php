<?php
// src/app/security/totp.php
// Minimal TOTP implementation (RFC 6238) with Base32 secrets

if (!function_exists('totp_base32_decode')) {
    function totp_base32_decode(string $b32): string {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper($b32);
        $b32 = preg_replace('/[^A-Z2-7]/', '', $b32);
        $buffer = 0; $bitsLeft = 0; $output = '';
        for ($i = 0; $i < strlen($b32); $i++) {
            $val = strpos($alphabet, $b32[$i]);
            if ($val === false) continue;
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $output;
    }
}

if (!function_exists('totp_base32_random')) {
    function totp_base32_random(int $length = 32): string {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $secret;
    }
}

if (!function_exists('totp_generate')) {
function totp_generate(string $base32Secret, ?int $timestamp = null, int $digits = 6, int $period = 30): string {
        $timestamp = $timestamp ?? time();
        $counter = intdiv($timestamp, $period);
        $key = totp_base32_decode($base32Secret);
        $binCounter = pack('N*', 0) . pack('N*', $counter); // 8-byte big-endian
        $hmac = hash_hmac('sha1', $binCounter, $key, true);
        $offset = ord($hmac[19]) & 0x0F;
        $code = ((ord($hmac[$offset]) & 0x7F) << 24) |
                ((ord($hmac[$offset+1]) & 0xFF) << 16) |
                ((ord($hmac[$offset+2]) & 0xFF) << 8) |
                (ord($hmac[$offset+3]) & 0xFF);
        $code = $code % (10 ** $digits);
        return str_pad((string)$code, $digits, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('totp_verify')) {
    function totp_verify(string $base32Secret, string $code, int $window = 1, int $digits = 6, int $period = 30): bool {
        $code = preg_replace('/\D/', '', $code);
        $now = time();
        for ($i = -$window; $i <= $window; $i++) {
            $t = $now + ($i * $period);
            if (hash_equals(totp_generate($base32Secret, $t, $digits, $period), $code)) {
                return true;
            }
        }
        return false;
    }
}
