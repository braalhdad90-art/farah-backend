<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminAccount extends Command
{
    protected $signature = 'admin:create {--name= : Administrator name} {--email= : Email address} {--role=system.admin : Admin role: ops.moderator|finance.reviewer|system.admin} {--password= : Secure password value (if omitted, prompt securely)} {--if-missing : Do nothing when an account with this email already exists}';

    protected $description = 'Create a protected admin account and assign the requested role';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Administrator name');
        $email = $this->option('email') ?: $this->ask('Administrator email');
        $role = $this->option('role') ?: 'system.admin';
        $password = $this->option('password');

        if (! in_array($role, ['ops.moderator', 'finance.reviewer', 'system.admin'], true)) {
            $this->error('Invalid admin role. Choose one of: ops.moderator, finance.reviewer, system.admin.');

            return self::FAILURE;
        }

        if ($this->option('if-missing') && $email && User::where('email', strtolower(trim($email)))->exists()) {
            $this->info('Admin account already exists: '.strtolower(trim($email)));

            return self::SUCCESS;
        }

        if (! $password) {
            $password = $this->secret('Administrator password');
        }

        if (! $name || ! $email || ! $password) {
            $this->error('Name, email, and password are required.');

            return self::FAILURE;
        }

        $user = User::firstOrCreate(
            ['email' => strtolower(trim($email))],
            [
                'name' => trim($name),
                'password' => Hash::make($password),
                'user_type' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        $user->syncRoles([]);
        if (method_exists($user, 'assignRole')) {
            $user->assignRole($role);
        }

        $this->info('Admin account created successfully for: '.$user->email);
        $this->info('Assigned role: '.$role);

        return self::SUCCESS;
    }
}
