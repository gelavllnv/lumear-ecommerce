<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LogisticsHeadAdminRoleSeeder extends Seeder
{
    public function run(): void
    {
        $existingRole = DB::table('roles')
            ->where('name', 'logistics_head_admin')
            ->first();

        if ($existingRole) {
            $this->command?->info(
                'Logistics Head Admin role already exists.'
            );

            return;
        }

        DB::table('roles')->insert([
            'name' => 'logistics_head_admin',
        ]);

        $this->command?->info(
            'Logistics Head Admin role created successfully.'
        );
    }
}
