<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReplayRequest extends FormRequest
{
    public const METHODS = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target_url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'method' => ['nullable', 'string', 'in:'.implode(',', self::METHODS)],
            'headers' => ['nullable', 'array', 'max:100'],
            // RFC 9110 token characters only, so header names can't smuggle CR/LF.
            'headers.*.name' => ['required', 'string', 'max:256', 'regex:/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/'],
            'headers.*.value' => ['present', 'nullable', 'string', 'max:8192', 'not_regex:/[\r\n]/'],
            'body' => ['nullable', 'string', 'max:1048576'],
        ];
    }

    public function targetUrl(): string
    {
        return $this->string('target_url')->toString();
    }

    public function replayMethod(): ?string
    {
        return $this->filled('method') ? $this->string('method')->toString() : null;
    }

    /**
     * The edited headers, or null to re-send the original ones.
     *
     * @return list<array{name: string, value: string}>|null
     */
    public function replayHeaders(): ?array
    {
        if (! $this->has('headers')) {
            return null;
        }

        return array_map(fn (int|string $index) => [
            'name' => $this->string("headers.{$index}.name")->toString(),
            'value' => $this->string("headers.{$index}.value")->toString(),
        ], array_keys($this->array('headers')));
    }

    /**
     * The edited body, or null to re-send the original bytes.
     */
    public function replayBody(): ?string
    {
        return $this->has('body') ? $this->string('body')->toString() : null;
    }
}
