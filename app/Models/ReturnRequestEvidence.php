<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequestEvidence extends Model
{
    protected $guarded = [];
    public function returnRequest(): BelongsTo { return $this->belongsTo(ReturnRequest::class); }
}
