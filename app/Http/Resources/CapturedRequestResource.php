<?php

namespace App\Http\Resources;

use App\Models\CapturedRequest;
use Illuminate\Http\Request;

/**
 * Full representation of a captured request, including headers and body.
 *
 * @mixin CapturedRequest
 */
class CapturedRequestResource extends CapturedRequestSummaryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'headers' => $this->headers,
            'is_binary' => $this->isBinary(),
            // Binary bodies stay base64-encoded so they can be safely serialized as JSON.
            'body' => $this->body,
        ];
    }
}
