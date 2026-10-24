<?php

namespace App\Http\Requests;

use App\Http\Middleware\SecureHookResponse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBinResponseRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'integer', 'between:200,599'],
            'headers' => ['present', 'array', 'max:20'],
            'headers.*.name' => [
                'required', 'string', 'max:256',
                'regex:/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/',
                Rule::notIn(SecureHookResponse::RESERVED_HEADERS),
            ],
            'headers.*.value' => ['present', 'nullable', 'string', 'max:4096', 'not_regex:/[\r\n]/'],
            'body' => ['nullable', 'string', 'max:'.config('catchhook.response.max_body_bytes')],
            'delay_ms' => ['required', 'integer', 'min:0', 'max:'.config('catchhook.response.max_delay_ms')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'headers.*.name.not_in' => 'The :input header is managed by Catchhook and cannot be overridden.',
            'headers.*.name.regex' => 'Header names may only contain letters, digits and !#$%&\'*+-.^_`|~.',
            'headers.*.value.not_regex' => 'Header values cannot contain line breaks.',
        ];
    }

    /**
     * Header names are compared case-insensitively against the reserved list.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'headers' => array_map(fn ($header) => is_array($header) ? [
                ...$header,
                'name' => is_string($header['name'] ?? null) ? strtolower(trim($header['name'])) : ($header['name'] ?? null),
            ] : $header, (array) $this->input('headers', [])),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'response_status' => (int) $this->validated('status'),
            'response_headers' => array_map(fn (array $header) => [
                'name' => $header['name'],
                'value' => (string) ($header['value'] ?? ''),
            ], array_values($this->validated('headers'))),
            'response_body' => $this->validated('body'),
            'response_delay_ms' => (int) $this->validated('delay_ms'),
        ];
    }
}
