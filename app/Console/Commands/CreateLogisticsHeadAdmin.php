<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateLogisticsHeadAdmin extends Command
{
    protected $signature = 'logistics:create-head-admin';

    protected $description = 'Create a LUMEAR Logistics Head Admin account';

    public function handle(): int
    {
        $this->info('LUMEAR Logistics Head Admin Setup');

        $name = trim((string) $this->ask('Full name'));
        $email = Str::lower(
            trim((string) $this->ask('Email address'))
        );
        $password = $this->secret('Password');
        $confirmation = $this->secret('Confirm password');

        if ($name === '' ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            !$password ||
            strlen($password) < 12 ||
            $password !== $confirmation) {

            $this->error(
                'Invalid details. Use a valid email and a matching password of at least 12 characters.'
            );

            return self::FAILURE;
        }

        if (DB::table('users')->where('email', $email)->exists()) {
            $this->error('This email is already registered.');

            return self::FAILURE;
        }

        $roleId = DB::table('roles')
            ->where('name', 'logistics_head_admin')
            ->value('id');

        if (!$roleId) {
            $this->error(
                'Logistics Head Admin role is missing. Run the role seeder first.'
            );

            return self::FAILURE;
        }

        DB::transaction(function () use (
            $name,
            $email,
            $password,
            $roleId
        ) {
            $userId = DB::table('users')->insertGetId([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'is_suspended' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('role_user')->insert([
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
        });

        $this->info(
            'Logistics Head Admin account created successfully!'
        );

        return self::SUCCESS;
    }
}
