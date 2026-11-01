<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerSellerVoucher extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['claimed_at' => 'datetime', 'removed_at' => 'datetime']; }
    public function buyer(): BelongsTo { return $this->belongsTo(User::class, 'buyer_id'); }
    public function voucher(): BelongsTo { return $this->belongsTo(SellerVoucher::class, 'seller_voucher_id'); }
}
