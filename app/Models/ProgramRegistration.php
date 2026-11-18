<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'program_id', 'user_id', 'status', 'registered_at', 'cancelled_at',
    'checked_in_at', 'checked_in_by', 'checkin_method',
])]
class ProgramRegistration extends Model
{
    public const STATUS_LABELS = [
        'registered' => 'Registered',
        'cancelled' => 'Cancelled',
        'attended' => 'Attended',
        'absent' => 'Absent',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'reminded_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'checked_in_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }
}
