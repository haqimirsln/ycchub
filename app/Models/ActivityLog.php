<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'action', 'description', 'subject_type', 'subject_id', 'ip_address', 'created_at'])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    public const GROUPS = [
        'members' => ['Members', ['member.registered', 'member.approved', 'member.rejected', 'member.role', 'member.suspended', 'member.reinstated', 'member.deleted', 'member.team']],
        'events' => ['Events', ['event.created', 'event.updated', 'event.cancelled', 'event.deleted', 'event.attendance']],
        'merit' => ['Merit', ['merit.given', 'merit.removed', 'merit.rules']],
        'system' => ['System', ['system.settings', 'system.database', 'system.export', 'system.email']],
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
