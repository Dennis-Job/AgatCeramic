<?php

namespace App\Services\Retention;

use RuntimeException;

final class RetentionApplyBlocked extends RuntimeException
{
    public function __construct(public readonly string $reasonCode)
    {
        parent::__construct('Retention apply is blocked by policy configuration.');
    }
}
