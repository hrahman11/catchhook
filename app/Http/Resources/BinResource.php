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
            'expires_at' => $this->expires_at->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
