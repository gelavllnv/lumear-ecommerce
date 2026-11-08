/// Shared buyer/seller category hierarchy. Keep display names as supplied.
abstract final class ProductCategories {
  static const hierarchy = <String, List<String>>{
    'Pet Supplies': [
      'Dog Food & Treats',
      'Cat Litter & Accessories',
      'Aquariums & Fish Supplies',
      'Bird Feeders & Food',
      'Pet Grooming Products',
      'Pet Health & Wellness',
    ],
    'Electronics and Gadgets': [
      'Mobile Phones & Accessories',
      'Laptops, Desktops & Monitors',
      'Audio & Video Equipment',
      'Smart Home Devices',
      'Cameras & Photography',
      'Wearable Technology',
    ],
    "Women's Apparel": [
      'Dresses & Skirts',
      'Tops & Blouses',
      'Activewear & Yoga Pants',
      'Lingerie & Sleepwear',
      'Jackets & Coats',
      'Shoes & Accessories',
    ],
    "Men's Apparel": [
      'Suits & Blazers',
      'Casual Shirts & Pants',
      'Outerwear & Jackets',
      'Activewear & Fitness Gear',
      'Shoes & Accessories',
      'Grooming Products',
    ],
    'Kids and Baby': [
      'Baby Clothes & Accessories',
      'Toys & Games',
      'Educational Materials',
      'Strollers & Gear',
      'Nursery Furniture',
      'Safety and Health',
    ],
    'Home and Garden': [
      'Kitchen Appliances',
      'Furniture & Decor',
      'Gardening Tools',
      'Outdoor Living',
      'Home Improvement Tools',
      'Bedding & Bath',
    ],
    'Sports and Outdoors': [
      'Fitness Equipment',
      'Camping & Hiking Gear',
      'Sports Apparel',
      'Cycling & Bikes',
      'Water Sports',
      'Team Sports Equipment',
    ],
    'Health and Beauty': [
      'Skincare Products',
      'Haircare Solutions',
      'Makeup & Cosmetics',
      'Personal Care Appliances',
      "Men's Grooming",
      'Health Supplements',
    ],
  };

  static Map<String, List<String>>? _shared;
  static bool get usesShared => _shared != null;
  static void reset() => _shared = null;
  static void useShared(dynamic rows) {
    if (rows is! List || rows.isEmpty) return;
    final roots = rows.where((r) => r['parent_id'] == null).toList();
    _shared = {
      for (final root in roots)
        root['name'] as String: [
          for (final child in rows)
            if (child['parent_id']?.toString() == root['id'].toString())
              child['name'] as String,
        ],
    };
  }

  static List<String> get names => (_shared ?? hierarchy).keys.toList();
  static List<String> children(String? category) =>
      (_shared ?? hierarchy)[category] ?? const [];

  // Allows older backend records to remain visible under the new main categories.
  static String canonical(String category) => usesShared
      ? category
      : switch (category) {
          'Fashion' || 'Jewelry' || 'Bags' => "Women's Apparel",
          'Electronics' || 'Gaming' => 'Electronics and Gadgets',
          'Baby' || 'Baby & Kids' => 'Kids and Baby',
          'Home' || 'Home & Living' => 'Home and Garden',
          'Beauty' || 'Beauty & Personal Care' => 'Health and Beauty',
          'Sports & Outdoors' => 'Sports and Outdoors',
          _ => category,
        };
}
