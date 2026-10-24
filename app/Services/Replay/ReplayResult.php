<?php

namespace App\Services\Replay;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class ReplayResult implements Arrayable
{
    /**
     * @param  array<string, list<string>>  $headers
     */
    public function __construct(
        public bool $ok,
        public int $durationMs,
        public ?int $status = null,
        public ?string $reason = null,
        public array $headers = [],
        public ?string $body = null,
        public bool $isBinary = false,
        public int $size = 0,
        public bool $truncated = false,
        public ?string $error = null,
    ) {}

    /**
     * @param  array<string, list<string>>  $headers
     */
    public static function received(int $status, string $reason, array $headers, string $body, int $durationMs, int $maxBytes): self
    {
        $size = strlen($body);
        $truncated = $size > $maxBytes;
        $body = $truncated ? substr($body, 0, $maxBytes) : $body;

        // Truncation can split a multibyte character, so check after cutting.
        $isText = mb_check_encoding($body, 'UTF-8') && ! str_contains($body, "\0");

        return new self(
            ok: true,
            durationMs: $durationMs,
            status: $status,
            reason: $reason,
            headers: $headers,
            body: $isText ? $body : base64_encode($body),
            isBinary: ! $isText,
            size: $size,
            truncated: $truncated,
        );
    }

    public static function failed(string $error, int $durationMs): self
    {
        return new self(ok: false, durationMs: $durationMs, error: $error);
    }

    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'status' => $this->status,
            'reason' => $this->reason,
            'headers' => $this->headers,
            'body' => $this->body,
            'is_binary' => $this->isBinary,
            'size' => $this->size,
            'truncated' => $this->truncated,
            'duration_ms' => $this->durationMs,
            'error' => $this->error,
        ];
    }
}
