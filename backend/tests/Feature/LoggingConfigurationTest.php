<?php

namespace Tests\Feature;

use App\Logging\MaskSensitiveLogData;
use Tests\TestCase;

class LoggingConfigurationTest extends TestCase
{
    public function test_configured_application_log_channels_use_the_masking_tap(): void
    {
        foreach (['stack', 'single', 'daily', 'monthly', 'slack', 'papertrail', 'stderr', 'syslog', 'errorlog'] as $channel) {
            $this->assertContains(
                MaskSensitiveLogData::class,
                config("logging.channels.{$channel}.tap"),
                "The {$channel} channel must sanitize log records.",
            );
        }
    }
}
