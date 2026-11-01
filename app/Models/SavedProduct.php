<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedProduct extends Model
{
    protected $guarded = [];

    public function buyer(): BelongsTo { return $this->belongsTo(User::class, 'buyer_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
