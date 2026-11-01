<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReviewReply extends Model
{
    protected $guarded = [];
    public function review(): BelongsTo { return $this->belongsTo(ProductReview::class, 'product_review_id'); }
    public function seller(): BelongsTo { return $this->belongsTo(Seller::class); }
}
