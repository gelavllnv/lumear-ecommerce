<?php

namespace App\Support;

/**
 * Placeholder category source for buyer-side views.
 *
 * Swap the body of all() for a real query once the Category model/table
 * exists, e.g. Category::orderBy('name')->get(['slug','name','icon'])->toArray().
 * Every view in resources/views/buyer expects this exact shape:
 * ['slug' => string, 'name' => string, 'icon' => string (emoji placeholder)]
 */
class BuyerCategories
{
    public static function all(): array
    {
        return [
            ['slug' => 'pet-supplies',                  'name' => "Pet Supplies",                    'icon' => '🐾'],
            ['slug' => 'kids-and-baby',                  'name' => "Kids & Baby",                     'icon' => '🍼'],
            ['slug' => 'electronics-and-gadgets',        'name' => "Electronics & Gadgets",           'icon' => '🔌'],
            ['slug' => 'home-and-garden',                'name' => "Home & Garden",                   'icon' => '🌿'],
            ['slug' => 'womens-apparel',                 'name' => "Women's Apparel",                 'icon' => '👗'],
            ['slug' => 'sports-and-outdoors',            'name' => "Sports & Outdoors",                'icon' => '🏸'],
            ['slug' => 'mens-apparel',                   'name' => "Men's Apparel",                   'icon' => '👔'],
            ['slug' => 'health-and-beauty',               'name' => "Health & Beauty",                  'icon' => '💄'],
            ['slug' => 'books-and-media',                'name' => "Books & Media",                    'icon' => '📚'],
            ['slug' => 'food-and-gourmet',                'name' => "Food & Gourmet",                   'icon' => '🍯'],
            ['slug' => 'furniture-and-office-equipment', 'name' => "Furniture & Office Equipment",     'icon' => '🪑'],
            ['slug' => 'jewelry-and-watches',             'name' => "Jewelry & Watches",                 'icon' => '💍'],
        ];
    }
}