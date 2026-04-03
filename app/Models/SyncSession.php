<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'status',
    'folder_stats',
    'total_remote_count',
    'total_to_sync',
    'synced_count',
    'failed_count',
    'started_at',
    'completed_at',
])]
class SyncSession extends Model
{
    protected function casts(): array
    {
        return [
            'folder_stats' => 'array',
            'total_remote_count' => 'integer',
            'total_to_sync' => 'integer',
            'synced_count' => 'integer',
            'failed_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SyncSessionLog::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'counting', 'syncing']);
    }

    public function markCompleteIfDone(): void
    {
        if ($this->status !== 'syncing') {
            return;
        }

        if (($this->synced_count + $this->failed_count) >= $this->total_to_sync) {
            $this->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }
    }
}
