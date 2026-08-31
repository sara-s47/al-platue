<?php

namespace App\Services\Auth;

interface OtpServiceInterface
{
    public function send(string $phone): void;

    public function verify(string $phone, string $code): bool;
}
