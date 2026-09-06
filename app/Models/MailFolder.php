<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'imap_setting_id',
    'path',
    'uid_validity',
    'last_synced_uid',
    'uid_next',
    'remote_message_count',
    'last_synced_at',
])]
class MailFolder extends Model
{
    protected function casts(): array
    {
        return [
            'uid_validity' => 'integer',
            'last_synced_uid' => 'integer',
            'uid_next' => 'integer',
            'remote_message_count' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function imapSetting(): BelongsTo
    {
        return $this->belongsTo(ImapSetting::class);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }
}
