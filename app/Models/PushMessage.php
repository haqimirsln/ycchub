<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A notification waiting to go out to a member's phones (sent by the scheduler every minute). */
#[Fillable(['user_id', 'title', 'body', 'url', 'tag', 'created_at', 'sent_at'])]
class PushMessage extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
