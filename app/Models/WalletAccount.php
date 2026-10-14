<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WalletAccount extends Model
{
    protected $guarded = [];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function journals(): HasMany
    {
        return $this->hasMany(WalletJournal::class);
    }
}
