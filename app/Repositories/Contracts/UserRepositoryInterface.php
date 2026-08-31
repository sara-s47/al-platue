<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function findByPhone(string $phone): ?Model;

    public function findByEmail(string $email): ?Model;
}
