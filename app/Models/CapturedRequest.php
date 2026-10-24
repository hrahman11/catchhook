<?php

namespace App\Models;

use Database\Factories\CapturedRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An HTTP request captured by a bin.
 *
 * Bodies are stored as text when they are valid UTF-8 (so they stay readable
 * and searchable), and base64-encoded otherwise, so binary payloads survive
 * the round trip byte-for-byte.
 *
 * @property string $id
 * @property string $bin_id
 * @property string $method
 * @property string $path
 * @property string|null $query_string
 * @property array<string, list<string>> $headers
 * @property string|null $body
 * @property string $body_encoding
 * @property string|null $content_type
 * @property int $size
 * @property string|null $ip
 * @property Carbon $created_at
 */
class CapturedRequest extends Model
{
    /** @use HasFactory<CapturedRequestFactory> */
    use HasFactory, HasUlids;

    public const UPDATED_AT = null;

    public const ENCODING_UTF8 = 'utf8';

    public const ENCODING_BASE64 = 'base64';

    protected $fillable = [
        'method',
        'path',
        'query_string',
        'headers',
        'content_type',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Bin, $this>
     */
    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    /**
     * Order by arrival, newest first. ULIDs break ties within the same second.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function newestFirst(Builder $query): void
    {
        $query->latest('created_at')->latest('id');
    }

    /**
     * Case-insensitive search across the path, query string, headers and text bodies.
     *
     * A substring scan is fine here: it only ever covers one bin, and bins are
     * capped at a few hundred requests, so no full-text index is needed.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes(mb_strtolower($term), '\\%_').'%';

        $query->where(fn (Builder $query) => $query
            ->whereRaw('LOWER(path) LIKE ?', [$like])
            ->orWhereRaw('LOWER(query_string) LIKE ?', [$like])
            ->orWhereRaw('LOWER(CAST(headers AS TEXT)) LIKE ?', [$like])
            // Binary bodies are stored base64-encoded, so only text bodies are searchable.
            ->orWhere(fn (Builder $query) => $query
                ->where('body_encoding', self::ENCODING_UTF8)
                ->whereRaw('LOWER(body) LIKE ?', [$like])));
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withMethod(Builder $query, ?string $method): void
    {
        if ($method) {
            $query->where('method', strtoupper($method));
        }
    }

    /**
     * Store the raw request body, choosing a lossless encoding.
     */
    public function setRawBody(string $body): static
    {
        $isText = mb_check_encoding($body, 'UTF-8') && ! str_contains($body, "\0");

        $this->body = $body === '' ? null : ($isText ? $body : base64_encode($body));
        $this->body_encoding = $isText ? self::ENCODING_UTF8 : self::ENCODING_BASE64;
        $this->size = strlen($body);

        return $this;
    }

    /**
     * Get the original request body exactly as it was received.
     */
    public function rawBody(): string
    {
        if ($this->body === null) {
            return '';
        }

        return $this->isBinary() ? (string) base64_decode($this->body, true) : $this->body;
    }

    public function isBinary(): bool
    {
        return $this->body_encoding === self::ENCODING_BASE64;
    }
}
