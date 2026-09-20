<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class AssignMissingUserRolesCommand extends Command
{
    protected $signature = 'users:assign-missing-roles';

    protected $description = 'Assign the customer role to users that have no role';

    public function handle(): int
    {
        $role = Role::findOrCreate('customer', 'web');

        $users = User::query()->doesntHave('roles')->orderBy('id')->get();

        if ($users->isEmpty()) {
            $this->info('All users already have a role.');

            return self::SUCCESS;
        }

        $this->info("Assigning customer role to {$users->count()} user(s).");

        foreach ($users as $user) {
            $user->assignRole($role);
            $this->line("  #{$user->id} {$user->name}");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
