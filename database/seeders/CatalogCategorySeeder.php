<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Fashion', 'Beauty & Wellness', 'Home & Living', 'Electronics', 'Pet Supplies', 'Sports & Outdoor', 'Books & Stationery', 'Baby & Kids', 'Food & Drinks', 'Bags & Accessories', 'Jewelry', 'Local Picks'] as $position => $name) {
            // Keep an instructor-provided category unchanged when it already exists.
            Category::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'position' => $position, 'is_active' => true]);
        }
    }
}
