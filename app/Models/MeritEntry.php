<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'points', 'source', 'auto_key', 'program_id', 'reason', 'awarded_by'])]
class MeritEntry extends Model
{
    public const SOURCE_LABELS = [
        'attendance' => 'Attended',
        'break_bonus' => 'Semester break bonus',
        'milestone' => 'Milestone',
        'committee' => 'AJK',
        'no_show' => 'No-show',
        'manual' => 'Given by MT',
    ];

    protected function casts(): array
    {
        return ['points' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class)->withTrashed();
    }

    public function giver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    public function isDemerit(): bool
    {
        return $this->points < 0;
    }

    public function isManual(): bool
    {
        return $this->source === 'manual';
    }
}
