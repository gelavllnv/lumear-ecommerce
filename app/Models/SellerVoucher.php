<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerVoucher extends Model
{
    protected $guarded = [];

    protected function casts(): array { return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'archived_at' => 'datetime', 'is_claimable' => 'boolean', 'is_paused' => 'boolean']; }

    public function seller(): BelongsTo { return $this->belongsTo(Seller::class); }
    public function products(): BelongsToMany { return $this->belongsToMany(Product::class, 'seller_voucher_products'); }
    public function claims(): HasMany { return $this->hasMany(BuyerSellerVoucher::class); }

    public function isActive(): bool { return ! $this->is_paused && ! $this->archived_at && in_array($this->status, ['upcoming', 'ongoing'], true) && now()->between($this->starts_at, $this->ends_at->copy()->subSecond()) && $this->used_count < $this->total_usage_limit; }
}
