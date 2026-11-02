<?php

namespace App\Support;

/**
 * Category + subcategory source for buyer-side views.
 *
 * Swap the body of all() for a real query once Category/Subcategory models exist.
 * Shape: ['slug','name','icon','subcategories' => [['slug','name'], ...]]
 */
class BuyerCategories
{
    public static function all(): array
    {
        return [
            [
                'slug' => 'pet-supplies', 'name' => "Pet Supplies", 'icon' => '🐾',
                'subcategories' => [
                    ['slug' => 'dog-food-and-treats', 'name' => "Dog Food & Treats"],
                    ['slug' => 'cat-litter-and-accessories', 'name' => "Cat Litter & Accessories"],
                    ['slug' => 'aquariums-and-fish-supplies', 'name' => "Aquariums & Fish Supplies"],
                    ['slug' => 'bird-feeders-and-food', 'name' => "Bird Feeders & Food"],
                    ['slug' => 'pet-grooming-products', 'name' => "Pet Grooming Products"],
                    ['slug' => 'pet-health-and-wellness', 'name' => "Pet Health & Wellness"],
                ],
            ],
            [
                'slug' => 'kids-and-baby', 'name' => "Kids & Baby", 'icon' => '🍼',
                'subcategories' => [
                    ['slug' => 'baby-clothes-and-accessories', 'name' => "Baby Clothes & Accessories"],
                    ['slug' => 'toys-and-games', 'name' => "Toys & Games"],
                    ['slug' => 'educational-materials', 'name' => "Educational Materials"],
                    ['slug' => 'strollers-and-gear', 'name' => "Strollers & Gear"],
                    ['slug' => 'nursery-furniture', 'name' => "Nursery Furniture"],
                    ['slug' => 'safety-and-health', 'name' => "Safety and Health"],
                ],
            ],
            [
                'slug' => 'electronics-and-gadgets', 'name' => "Electronics & Gadgets", 'icon' => '🔌',
                'subcategories' => [
                    ['slug' => 'mobile-phones-and-accessories', 'name' => "Mobile Phones & Accessories"],
                    ['slug' => 'laptops-desktops-and-monitors', 'name' => "Laptops, Desktops & Monitors"],
                    ['slug' => 'audio-and-video-equipment', 'name' => "Audio & Video Equipment"],
                    ['slug' => 'smart-home-devices', 'name' => "Smart Home Devices"],
                    ['slug' => 'cameras-and-photography', 'name' => "Cameras & Photography"],
                    ['slug' => 'wearable-technology', 'name' => "Wearable Technology"],
                ],
            ],
            [
                'slug' => 'home-and-garden', 'name' => "Home & Garden", 'icon' => '🌿',
                'subcategories' => [
                    ['slug' => 'kitchen-appliances', 'name' => "Kitchen Appliances"],
                    ['slug' => 'furniture-and-decor', 'name' => "Furniture & Decor"],
                    ['slug' => 'gardening-tools', 'name' => "Gardening Tools"],
                    ['slug' => 'outdoor-living', 'name' => "Outdoor Living"],
                    ['slug' => 'home-improvement-tools', 'name' => "Home Improvement Tools"],
                    ['slug' => 'bedding-and-bath', 'name' => "Bedding & Bath"],
                ],
            ],
            [
                'slug' => 'womens-apparel', 'name' => "Women's Apparel", 'icon' => '👗',
                'subcategories' => [
                    ['slug' => 'dresses-and-skirts', 'name' => "Dresses & Skirts"],
                    ['slug' => 'tops-and-blouses', 'name' => "Tops & Blouses"],
                    ['slug' => 'activewear-and-yoga-pants', 'name' => "Activewear & Yoga Pants"],
                    ['slug' => 'lingerie-and-sleepwear', 'name' => "Lingerie & Sleepwear"],
                    ['slug' => 'jackets-and-coats', 'name' => "Jackets & Coats"],
                    ['slug' => 'shoes-and-accessories', 'name' => "Shoes & Accessories"],
                ],
            ],
            [
                'slug' => 'sports-and-outdoors', 'name' => "Sports & Outdoors", 'icon' => '🏸',
                'subcategories' => [
                    ['slug' => 'fitness-equipment', 'name' => "Fitness Equipment"],
                    ['slug' => 'camping-and-hiking-gear', 'name' => "Camping & Hiking Gear"],
                    ['slug' => 'sports-apparel', 'name' => "Sports Apparel"],
                    ['slug' => 'cycling-and-bikes', 'name' => "Cycling & Bikes"],
                    ['slug' => 'water-sports', 'name' => "Water Sports"],
                    ['slug' => 'team-sports-equipment', 'name' => "Team Sports Equipment"],
                ],
            ],
            [
                'slug' => 'mens-apparel', 'name' => "Men's Apparel", 'icon' => '👔',
                'subcategories' => [
                    ['slug' => 'suits-and-blazers', 'name' => "Suits & Blazers"],
                    ['slug' => 'casual-shirts-and-pants', 'name' => "Casual Shirts & Pants"],
                    ['slug' => 'outerwear-and-jackets', 'name' => "Outerwear & Jackets"],
                    ['slug' => 'activewear-and-fitness-gear', 'name' => "Activewear & Fitness Gear"],
                    ['slug' => 'shoes-and-accessories', 'name' => "Shoes & Accessories"],
                    ['slug' => 'grooming-products', 'name' => "Grooming Products"],
                ],
            ],
            [
                'slug' => 'health-and-beauty', 'name' => "Health & Beauty", 'icon' => '💄',
                'subcategories' => [
                    ['slug' => 'skincare-products', 'name' => "Skincare Products"],
                    ['slug' => 'haircare-solutions', 'name' => "Haircare Solutions"],
                    ['slug' => 'makeup-and-cosmetics', 'name' => "Makeup & Cosmetics"],
                    ['slug' => 'personal-care-appliances', 'name' => "Personal Care Appliances"],
                    ['slug' => 'mens-grooming', 'name' => "Men's Grooming"],
                    ['slug' => 'health-supplements', 'name' => "Health Supplements"],
                ],
            ],
            [
                'slug' => 'books-and-media', 'name' => "Books & Media", 'icon' => '📚',
                'subcategories' => [
                    ['slug' => 'fiction-and-non-fiction-books', 'name' => "Fiction & Non-Fiction Books"],
                    ['slug' => 'magazines-and-periodicals', 'name' => "Magazines & Periodicals"],
                    ['slug' => 'music-cds-and-vinyl-records', 'name' => "Music CDs & Vinyl Records"],
                    ['slug' => 'movie-dvds-and-blu-ray', 'name' => "Movie DVDs & Blu-ray"],
                    ['slug' => 'video-games-and-consoles', 'name' => "Video Games & Consoles"],
                    ['slug' => 'educational-dvds', 'name' => "Educational DVDs"],
                ],
            ],
            [
                'slug' => 'food-and-gourmet', 'name' => "Food & Gourmet", 'icon' => '🍯',
                'subcategories' => [
                    ['slug' => 'baking-supplies-and-ingredients', 'name' => "Baking Supplies & Ingredients"],
                    ['slug' => 'coffee-tea-and-beverages', 'name' => "Coffee, Tea & Beverages"],
                    ['slug' => 'snacks-and-candy', 'name' => "Snacks & Candy"],
                    ['slug' => 'specialty-foods-and-international-cuisine', 'name' => "Specialty Foods & International Cuisine"],
                    ['slug' => 'organic-and-health-foods', 'name' => "Organic and Health Foods"],
                    ['slug' => 'meal-kits-and-prepped-foods', 'name' => "Meal Kits & Prepped Foods"],
                ],
            ],
            [
                'slug' => 'furniture-and-office-equipment', 'name' => "Furniture & Office Equipment", 'icon' => '🪑',
                'subcategories' => [
                    ['slug' => 'office-desks-and-chairs', 'name' => "Office Desks & Chairs"],
                    ['slug' => 'storage-cabinets-and-shelving', 'name' => "Storage Cabinets & Shelving"],
                    ['slug' => 'conference-and-meeting-furniture', 'name' => "Conference & Meeting Furniture"],
                    ['slug' => 'computer-tables-and-workstations', 'name' => "Computer Tables & Workstations"],
                    ['slug' => 'ergonomic-accessories', 'name' => "Ergonomic Accessories"],
                    ['slug' => 'office-lighting-and-fixtures', 'name' => "Office Lighting & Fixtures"],
                ],
            ],
            [
                'slug' => 'jewelry-and-watches', 'name' => "Jewelry & Watches", 'icon' => '💍',
                'subcategories' => [
                    ['slug' => 'necklaces-and-pendants', 'name' => "Necklaces & Pendants"],
                    ['slug' => 'rings-and-earrings', 'name' => "Rings & Earrings"],
                    ['slug' => 'bracelets-and-bangles', 'name' => "Bracelets & Bangles"],
                    ['slug' => 'watches-for-men-and-women', 'name' => "Watches for Men & Women"],
                    ['slug' => 'fashion-jewelry', 'name' => "Fashion Jewelry"],
                    ['slug' => 'jewelry-storage-and-care', 'name' => "Jewelry Storage & Care"],
                ],
            ],
        ];
    }

    /** Convenience lookup used by the category page. Returns null if not found. */
    public static function find(string $slug): ?array
    {
        foreach (self::all() as $category) {
            if ($category['slug'] === $slug) return $category;
        }
        return null;
    }
}