<?php

namespace Database\Factories;

use App\Models\Bin;
use App\Models\CapturedRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CapturedRequest>
 */
class CapturedRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bin_id' => Bin::factory(),
            'method' => 'POST',
            'path' => '/',
            'query_string' => null,
            'headers' => [
                'content-type' => ['application/json'],
                'user-agent' => [fake()->userAgent()],
            ],
            'content_type' => 'application/json',
            'ip' => fake()->ipv4(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (CapturedRequest $request) {
            if ($request->body === null) {
                $request->setRawBody((string) json_encode(['event' => fake()->word(), 'id' => fake()->uuid()]));
            }
        });
    }
}
