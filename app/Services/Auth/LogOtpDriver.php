<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LogOtpDriver implements OtpServiceInterface
{
    public function send(string $phone): void
    {
        $length = (int) config('otp.length', 6);
        $max = (10 ** $length) - 1;
        $code = str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);

        $ttl = (int) config('otp.expires_in', 300);

        Cache::put($this->cacheKey($phone), $code, $ttl);

        Log::info('OTP sent', [
            'phone' => $phone,
            'code' => $code,
            'expires_in' => $ttl,
        ]);
    }

    public function verify(string $phone, string $code): bool
    {
        $cached = Cache::get($this->cacheKey($phone));

        if ($cached === null || ! hash_equals((string) $cached, $code)) {
            return false;
        }

        Cache::forget($this->cacheKey($phone));

        return true;
    }

    protected function cacheKey(string $phone): string
    {
        return 'otp:'.preg_replace('/\D+/', '', $phone);
    }
}
