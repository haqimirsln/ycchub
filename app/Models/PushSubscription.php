<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One phone or browser a member turned notifications on for. */
#[Fillable(['user_id', 'endpoint', 'endpoint_hash', 'public_key', 'auth_token', 'device', 'last_sent_at'])]
class PushSubscription extends Model
{
    protected function casts(): array
    {
        return ['last_sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashOf(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
