<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Every default seeder is idempotent: existing records are retained.
        $this->call([RoleSeeder::class, PermissionSeeder::class, CatalogCategorySeeder::class]);
    }
}
