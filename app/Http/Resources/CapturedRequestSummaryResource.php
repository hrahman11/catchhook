<?php

namespace App\Http\Resources;

use App\Models\CapturedRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight representation used in the request list.
 *
 * @mixin CapturedRequest
 */
class CapturedRequestSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'method' => $this->method,
            'path' => $this->path,
            'query_string' => $this->query_string,
            'content_type' => $this->content_type,
            'size' => $this->size,
            'ip' => $this->ip,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
