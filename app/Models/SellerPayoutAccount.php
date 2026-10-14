<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerPayoutAccount extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['account_number' => 'encrypted', 'verified_at' => 'datetime', 'is_default' => 'boolean'];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}
