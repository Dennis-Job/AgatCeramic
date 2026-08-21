<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * @return array{name: string, slug: string, description: string}
     */
    public function definition(): array
    {
        $identifier = (string) Str::uuid();

        return [
            'name' => "Test role {$identifier}",
            'slug' => "test-role-{$identifier}",
            'description' => fake()->sentence(),
        ];
    }
}
