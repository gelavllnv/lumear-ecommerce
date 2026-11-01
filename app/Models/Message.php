<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $guarded = [];

    protected function casts(): array { return ['read_at' => 'datetime']; }

    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function attachments(): HasMany { return $this->hasMany(MessageAttachment::class); }
}
