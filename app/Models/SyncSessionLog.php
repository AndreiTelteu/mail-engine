<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sync_session_id',
    'to_address',
    'subject',
    'status',
    'error_message',
])]
class SyncSessionLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public static function booted(): void
    {
        static::creating(function (SyncSessionLog $log): void {
            $log->created_at ??= now();
        });
    }

    public function syncSession(): BelongsTo
    {
        return $this->belongsTo(SyncSession::class);
    }
}
