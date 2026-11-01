<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnRequest extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['respond_by' => 'datetime', 'responded_at' => 'datetime', 'escalated_at' => 'datetime', 'refunded_at' => 'datetime']; }
    public function sellerOrder(): BelongsTo { return $this->belongsTo(SellerOrder::class); }
    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
    public function buyer(): BelongsTo { return $this->belongsTo(User::class, 'buyer_id'); }
    public function evidence(): HasMany { return $this->hasMany(ReturnRequestEvidence::class); }
}
