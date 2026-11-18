<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One club email from the MT, and how far its sending has got. */
#[Fillable(['subject', 'body', 'button_label', 'button_url', 'program_id', 'audience', 'audience_label', 'recipient_count', 'created_by'])]
class Announcement extends Model
{
    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'pending_ids' => 'array',
            'finished_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class)->withTrashed();
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function remaining(): int
    {
        return count($this->pending_ids ?? []);
    }

    public function isSending(): bool
    {
        return $this->remaining() > 0 && ! $this->cancelled_at;
    }

    public function progress(): int
    {
        return $this->recipient_count ? (int) round(($this->recipient_count - $this->remaining()) / $this->recipient_count * 100) : 100;
    }
}
