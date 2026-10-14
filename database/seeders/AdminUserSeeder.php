<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL');
        $password = (string) env('ADMIN_PASSWORD');
        if ($email === '' || strlen($password) < 12) {
            $this->command?->warn('Set ADMIN_EMAIL and an ADMIN_PASSWORD of at least 12 characters before running AdminUserSeeder.');
            return;
        }

        $admin = User::firstOrCreate(['email' => $email], ['name' => env('ADMIN_NAME', 'Lumear Administrator'), 'password' => $password, 'email_verified_at' => now(), 'phone' => null]);
        $admin->roles()->syncWithoutDetaching([Role::firstOrCreate(['name' => 'admin'])->id]);
    }
}
