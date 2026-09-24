<?php

return [
    'policy_version' => env('PII_RETENTION_POLICY_VERSION', '2026-09-24'),
    'policy_status' => env('PII_RETENTION_POLICY_STATUS', 'proposed'),
    'apply_enabled' => env('PII_RETENTION_APPLY_ENABLED', false),
    'service_identity' => env('PII_RETENTION_SERVICE_IDENTITY', 'scheduler'),
    'tombstone_key_id' => env('PII_RETENTION_TOMBSTONE_KEY_ID', 'v1'),
    'tombstone_key' => env('PII_RETENTION_TOMBSTONE_KEY'),
    'previous_tombstone_keys' => env('PII_RETENTION_PREVIOUS_TOMBSTONE_KEYS', '{}'),
    'batch_size' => env('PII_RETENTION_BATCH_SIZE', 100),

    'orders' => [
        'pii_years' => env('PII_RETENTION_ORDER_YEARS', 3),
        'processing_grace_days' => env('PII_RETENTION_ORDER_GRACE_DAYS', 30),
        'active_review_days' => env('PII_RETENTION_ORDER_REVIEW_DAYS', 90),
        // pending | retain_commercial | delete_all. Production apply is blocked while pending.
        'commercial_disposition' => env('PII_RETENTION_ORDER_DISPOSITION', 'pending'),
        'commercial_years' => env('PII_RETENTION_COMMERCIAL_YEARS', 5),
    ],

    'contacts' => [
        'completed_days' => env('PII_RETENTION_CONTACT_COMPLETED_DAYS', 180),
        'rejected_days' => env('PII_RETENTION_CONTACT_REJECTED_DAYS', 30),
        'processing_grace_days' => env('PII_RETENTION_CONTACT_GRACE_DAYS', 30),
        'active_review_days' => env('PII_RETENTION_CONTACT_REVIEW_DAYS', 90),
        'active_max_days' => env('PII_RETENTION_CONTACT_MAX_DAYS', 365),
    ],

    'technical' => [
        'failed_jobs_days' => env('PII_RETENTION_FAILED_JOBS_DAYS', 30),
        'session_expiry_grace_hours' => env('PII_RETENTION_SESSION_GRACE_HOURS', 24),
        'password_reset_expiry_grace_hours' => env('PII_RETENTION_PASSWORD_RESET_GRACE_HOURS', 24),
    ],
];
