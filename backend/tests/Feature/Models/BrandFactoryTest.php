<?php

namespace Tests\Feature\Models;

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_can_create_brands_after_faker_uniqueness_is_reset(): void
    {
        $faker = fake();

        try {
            $faker->unique(true);
            $faker->seed(48048);
            $first = Brand::factory()->create();

            // Integration tests keep rows while a new application resets Faker uniqueness.
            $faker->unique(true);
            $faker->seed(48048);
            $second = Brand::factory()->create();

            $this->assertNotSame($first->name, $second->name);
            $this->assertNotSame($first->slug, $second->slug);
            $this->assertDatabaseCount('brands', 2);
        } finally {
            $faker->unique(true);
            $faker->seed();
        }
    }
}
