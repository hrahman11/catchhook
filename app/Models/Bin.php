<?php

namespace App\Models;

use Database\Factories\BinFactory;
use Illuminate\Database\Eloquent\Concerns\HasUniqueStringIds;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A bin is a unique endpoint that captures every request sent to it.
 *
 * @property string $id
 * @property string|null $name
 * @property Carbon $expires_at
 */
class Bin extends Model
{
    /** @use HasFactory<BinFactory> */
    use HasFactory, HasUniqueStringIds;

    public const ID_LENGTH = 16;

    protected $fillable = [
        'name',
        'expires_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Bin $bin) {
            $bin->expires_at ??= now()->addDays(config('catchhook.bin_ttl_days'));
        });
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<CapturedRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(CapturedRequest::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Generate a short, lowercase, URL-friendly identifier.
     */
    public function newUniqueId(): string
    {
        return Str::lower(Str::random(self::ID_LENGTH));
    }

    protected function isValidUniqueId($value): bool
    {
        return is_string($value) && preg_match('/^[a-z0-9]{'.self::ID_LENGTH.'}$/', $value) === 1;
    }
}
