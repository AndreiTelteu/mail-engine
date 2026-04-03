<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

#[Fillable([
    'user_id',
    'message_id',
    'folder',
    'from_address',
    'from_name',
    'to_addresses',
    'cc_addresses',
    'subject',
    'date',
    'body_text',
    'body_html',
    'attachments',
    'indexing_failed_at',
    'indexing_error',
])]
class Email extends Model
{
    use HasFactory, Searchable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'to_addresses' => 'array',
            'cc_addresses' => 'array',
            'attachments' => 'array',
            'indexing_failed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'user_id' => (int) $this->user_id,
            'from_address' => $this->from_address,
            'from_name' => $this->from_name,
            'subject' => $this->subject ?? '',
            'body_text' => $this->body_text ?? '',
            'folder' => $this->folder,
            'date' => $this->date?->timestamp ?? 0,
        ];
    }

    public function searchableAs(): string
    {
        return 'emails';
    }
}
