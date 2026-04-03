<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'hostname',
    'port',
    'username',
    'password',
    'encryption',
    'is_active',
])]
#[Hidden(['password'])]
class ImapSetting extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'port' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function password(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : encrypt($value),
        );
    }

    protected function decryptedPassword(): Attribute
    {
        return Attribute::get(
            fn (): ?string => isset($this->attributes['password'])
                ? decrypt($this->attributes['password'])
                : null,
        );
    }
}
