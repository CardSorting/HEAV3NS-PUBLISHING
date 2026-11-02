<?php

namespace App\Console\Commands;

use App\Model\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class PromoteUserToAdmin extends Command
{
    protected $signature = 'make-admin {email}';

    protected $description = 'Promotes a user to admin by setting role_id=1 and assigning Spatie admin role';

    public function handle()
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return;
        }

        // Legacy support: set role_id = 1
        $user->role_id = 1;
        $user->save();

        // Spatie role assignment
        if (!$user->hasRole('admin')) {
            $user->assignRole('admin');
        }

        $this->info("User '{$user->email}' is now an admin.");
    }
}
