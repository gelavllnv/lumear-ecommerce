<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSale extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['starts_at' => 'datetime', 'ends_at' => 'datetime']; }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function isActive(): bool { return in_array($this->status, ['upcoming', 'running'], true) && now()->between($this->starts_at, $this->ends_at->copy()->subSecond()); }
}
