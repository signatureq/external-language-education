<?php

namespace App\Services;

class ApplicationToken
{
    public function issue(string $origin): string
    {
        $value = now()->timestamp.'.'.bin2hex(random_bytes(16));

        return $value.'.'.hash_hmac('sha256', $origin.'|'.$value, config('app.key'));
    }

    public function valid(string $token, string $origin): bool
    {
        if (! preg_match('/^(\d{10})\.([a-f0-9]{32})\.([a-f0-9]{64})$/', $token, $parts)) {
            return false;
        }
        $age = now()->timestamp - (int) $parts[1];

        return $age >= 0 && $age <= 7200 && hash_equals(hash_hmac('sha256', $origin.'|'.$parts[1].'.'.$parts[2], config('app.key')), $parts[3]);
    }
}
