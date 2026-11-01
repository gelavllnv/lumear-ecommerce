<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerStoreCategory extends Model
{
    protected $guarded = [];

    protected function casts(): array { return ['is_visible' => 'boolean']; }

    public function seller(): BelongsTo { return $this->belongsTo(Seller::class); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('position'); }
    public function products(): BelongsToMany { return $this->belongsToMany(Product::class, 'product_seller_store_category')->withTimestamps(); }
}
