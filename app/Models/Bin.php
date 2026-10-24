<?php

namespace App\Models;

use Database\Factories\BinFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUniqueStringIds;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A bin is a unique endpoint that captures every request sent to it.
 *
 * @property string $id
 * @property string|null $name
 * @property int|null $response_status
 * @property list<array{name: string, value: string}>|null $response_headers
 * @property string|null $response_body
 * @property int $response_delay_ms
 * @property Carbon $expires_at
 */
class Bin extends Model
{
    /** @use HasFactory<BinFactory> */
    use HasFactory, HasUniqueStringIds, MassPrunable;

    public const ID_LENGTH = 16;

    protected $fillable = [
        'name',
        'expires_at',
        'response_status',
        'response_headers',
        'response_body',
        'response_delay_ms',
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
            'response_status' => 'integer',
            'response_headers' => 'array',
            'response_delay_ms' => 'integer',
        ];
    }

    /**
     * Whether the bin answers with a user-defined response instead of the default JSON acknowledgement.
     */
    public function hasCustomResponse(): bool
    {
        return $this->response_status !== null;
    }

    public function resetResponse(): void
    {
        $this->forceFill([
            'response_status' => null,
            'response_headers' => null,
            'response_body' => null,
            'response_delay_ms' => 0,
        ])->save();
    }

    /**
     * @return HasMany<CapturedRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(CapturedRequest::class);
    }

    /**
     * Keep only the most recent requests, deleting everything older.
     */
    public function discardRequestsBeyond(int $keep): int
    {
        // ULIDs sort by creation time, so everything at or below the first
        // request past the limit is older than the ones being kept.
        $cutoff = $this->requests()->newestFirst()->skip($keep)->value('id');

        return $cutoff === null ? 0 : $this->requests()->where('id', '<=', $cutoff)->delete();
    }

    /**
     * Expired bins are deleted by `model:prune`; their requests cascade at the database level.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::where('expires_at', '<=', now());
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
