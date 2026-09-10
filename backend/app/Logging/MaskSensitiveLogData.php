<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Logger as MonologLogger;

final class MaskSensitiveLogData
{
    public function __invoke(Logger $logger): void
    {
        $logger->getLogger() instanceof MonologLogger && $logger->getLogger()->pushProcessor(new SanitizesLogRecord);
    }
}
