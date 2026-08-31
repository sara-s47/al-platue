<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OTP Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "log", "sms"
    |
    */

    'driver' => env('OTP_DRIVER', 'log'),

    'length' => (int) env('OTP_LENGTH', 6),

    'expires_in' => (int) env('OTP_EXPIRES_IN', 300),

    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 3),

];
