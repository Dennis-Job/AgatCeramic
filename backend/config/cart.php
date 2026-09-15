<?php

return [
    'token_hmac_key' => env('CART_TOKEN_HMAC_KEY') ?: env('APP_KEY'),
    'empty_ttl_hours' => env('CART_EMPTY_TTL_HOURS', 24),
    'abandoned_ttl_days' => env('CART_ABANDONED_TTL_DAYS', 30),
    'checked_out_ttl_hours' => env('CART_CHECKED_OUT_TTL_HOURS', 24),
    'cleanup_batch_size' => env('CART_CLEANUP_BATCH_SIZE', 100),
];
