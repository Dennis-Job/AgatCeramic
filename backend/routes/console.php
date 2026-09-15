<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('audit:prune')->daily();
Schedule::command('storage-cleanup:retry')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('imports:retry-dispatch')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('checkout-idempotency:prune')->hourly()->withoutOverlapping();
Schedule::command('cart:prune')->hourly()->withoutOverlapping();
Schedule::command('retention:orders')->dailyAt('01:10')->withoutOverlapping();
Schedule::command('retention:contacts')->dailyAt('01:20')->withoutOverlapping();
Schedule::command('retention:technical')->dailyAt('01:30')->withoutOverlapping();

if (config('retention.policy_status') === 'accepted' && config('retention.apply_enabled') === true) {
    Schedule::command('retention:orders --apply')->dailyAt('02:10')->withoutOverlapping();
    Schedule::command('retention:contacts --apply')->dailyAt('02:20')->withoutOverlapping();
    Schedule::command('retention:technical --apply')->dailyAt('02:30')->withoutOverlapping();
}
