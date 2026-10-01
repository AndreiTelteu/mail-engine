<?php

namespace App\Models;

use Database\Factories\McpAccessTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'token_hash', 'token_encrypted', 'last_used_at'])]
#[Hidden(['token_hash', 'token_encrypted'])]
class McpAccessToken extends Model
{
    /** @use HasFactory<McpAccessTokenFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'token_encrypted' => 'encrypted',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
