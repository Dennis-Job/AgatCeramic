<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * @return array{name: string, code: string, description: string}
     */
    #[\Override]
    public function definition(): array
    {
        $module = fake()->unique()->slug(1);
        $action = ['view', 'create', 'update', 'delete', 'manage'][fake()->numberBetween(0, 4)];

        return [
            'name' => ucfirst($module).' '.ucfirst($action),
            'code' => $module.'.'.$action,
            'description' => fake()->sentence(),
        ];
    }
}
