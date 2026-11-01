<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlashSale extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'archived_at' => 'datetime', 'is_paused' => 'boolean']; }
    public function seller(): BelongsTo { return $this->belongsTo(Seller::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function remainingQuantity(): int { return max(0, $this->quantity_limit - $this->quantity_sold); }
    public function isActive(): bool { return ! $this->is_paused && ! $this->archived_at && in_array($this->status, ['upcoming', 'running'], true) && now()->between($this->starts_at, $this->ends_at->copy()->subSecond()) && $this->remainingQuantity() > 0; }
}
