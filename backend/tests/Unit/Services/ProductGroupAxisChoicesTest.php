<?php

namespace Tests\Unit\Services;

use App\Models\Attribute;
use App\Services\ProductGroupAxisChoices;
use PHPUnit\Framework\TestCase;

class ProductGroupAxisChoicesTest extends TestCase
{
    public function test_reserved_archival_collection_is_never_an_axis_choice(): void
    {
        $legacy = (new Attribute)->forceFill(['id' => 1, 'name' => 'Collection', 'slug' => 'kollektsiya']);
        $color = (new Attribute)->forceFill(['id' => 2, 'name' => 'Color', 'slug' => 'color']);

        $this->assertSame([2 => 'Color'], (new ProductGroupAxisChoices)->forAttributes([$legacy, $color]));
    }
}
