<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BundleDeal extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'archived_at' => 'datetime']; }
    public function seller(): BelongsTo { return $this->belongsTo(Seller::class); }
    public function qualifierProducts(): BelongsToMany { return $this->belongsToMany(Product::class, 'bundle_deal_products')->wherePivot('role', 'qualifier'); }
    public function rewardProducts(): BelongsToMany { return $this->belongsToMany(Product::class, 'bundle_deal_products')->wherePivot('role', 'reward'); }
}
