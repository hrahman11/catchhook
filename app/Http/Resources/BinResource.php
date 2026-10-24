<?php

namespace App\Http\Resources;

use App\Models\Bin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Bin
 */
class BinResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => route('hooks.capture', $this->resource),
            'expired' => $this->isExpired(),
            'max_requests' => (int) config('catchhook.limits.max_requests_per_bin'),
            'response' => $this->hasCustomResponse() ? [
                'status' => $this->response_status,
                'headers' => $this->response_headers ?? [],
                'body' => $this->response_body,
                'delay_ms' => $this->response_delay_ms,
            ] : null,
            'expires_at' => $this->expires_at->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
