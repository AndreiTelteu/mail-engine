<?php

namespace Database\Factories;

use App\Models\McpAccessToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<McpAccessToken>
 */
class McpAccessTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = Str::random(64);

        return [
            'user_id' => User::factory(),
            'token_hash' => hash('sha256', $token),
            'token_encrypted' => $token,
        ];
    }
}
