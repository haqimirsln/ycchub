<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'export_type', 'filters', 'row_count', 'ip_address', 'created_at'])]
#[WithoutTimestamps]
class ExportLog extends Model
{
    protected static function booted(): void
    {
        static::created(function (ExportLog $log) {
            \App\Support\Activity::log('system.export', "Downloaded the {$log->export_type} CSV ({$log->row_count} rows)", null, $log->user_id);
        });
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
