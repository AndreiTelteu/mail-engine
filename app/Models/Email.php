<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

#[Fillable([
    'mail_folder_id',
    'user_id',
    'uid_validity',
    'imap_uid',
    'message_id',
    'in_reply_to',
    'references',
    'folder',
    'from_address',
    'from_name',
    'to_addresses',
    'cc_addresses',
    'subject',
    'date',
    'body_text',
    'body_html',
    'body_current',
    'body_quoted',
    'preview',
    'attachments',
    'attachment_count',
    'content_hash',
    'parser_version',
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
            'mail_folder_id' => 'integer',
            'uid_validity' => 'integer',
            'imap_uid' => 'integer',
            'date' => 'datetime',
            'to_addresses' => 'array',
            'cc_addresses' => 'array',
            'attachments' => 'array',
            'attachment_count' => 'integer',
            'parser_version' => 'integer',
            'indexing_failed_at' => 'datetime',
        ];
    }

    public function mailFolder(): BelongsTo
    {
        return $this->belongsTo(MailFolder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'user_id' => (int) $this->user_id,
            'from_address' => $this->from_address,
            'from_name' => $this->from_name,
            'to_addresses' => collect($this->to_addresses)->pluck('address')->filter()->values()->all(),
            'cc_addresses' => collect($this->cc_addresses)->pluck('address')->filter()->values()->all(),
            'subject' => $this->subject ?? '',
            'body_current' => $this->body_current ?? '',
            'body_quoted' => $this->body_quoted ?? '',
            'preview' => $this->preview,
            'attachment_names' => collect($this->attachments)->pluck('filename')->filter()->values()->all(),
            'attachment_count' => $this->attachment_count,
            'folder' => $this->folder,
            'date' => $this->date?->timestamp ?? 0,
        ];
    }

    public function searchableAs(): string
    {
        return 'emails';
    }

    /**
     * @return array<string, int|string>
     */
    public function typesenseSearchParameters(): array
    {
        return config('scout.[REDACTED].model-settings.'.self::class.'.search-parameters', []);
    }
}
