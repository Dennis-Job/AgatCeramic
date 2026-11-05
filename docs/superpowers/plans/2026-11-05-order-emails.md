# Order emails implementation plan

**Goal:** Notify the configured site mailbox and the customer after checkout; give the customer a branded receipt containing every submitted checkout field, order items and public store contacts.

**Architecture:** Keep OrderCreationService's transaction and immutable item snapshots. OrderConfirmationService records independent admin/customer deliveries in an order-specific outbox, dispatches identifier-only jobs after commit, and handles safe retries. Existing callback transport protection is shared without changing its behavior. The old confirmation job remains a compatibility adapter for queued customer confirmations.

**Constraints:** Backend remains API-only; mail uses inline HTML, no Blade. Customer email is required for new checkout following the owner's subsequent 2026-11-05 instruction; historical/anonymized rows remain nullable. Internal notification_email never appears in customer mail. No PII in jobs/logs/errors, no historical bulk send, no real test mail, no commit. Existing site palette and public contact settings are authoritative.

## Tasks

- [x] Delivery: first add regression tests in OrderMailDeliveryTest, then create order_mail_deliveries migration/model, SendOrderMail and orders:retry-mail. Update OrderConfirmationService and legacy job, share MailTransportSafety with callback. Verify checkout idempotency, rollback, independent failures, current recipient settings, log protection, anonymization and recovery.
- [x] Templates: update OrderConfirmationMail, add OrderNotificationMail and shared OrderMailContent. First test rendering, escaping, all checkout fields, immutable items, exact Russian money formatting and public contacts. Preserve the existing approved mail style and responsive table layout.
- [x] Integration: update OrderConfirmationTest and RedisQueueDeliveryTest to verify two identifier-only jobs after commit, actual worker completion and duplicate suppression. Run SQLite suite, PostgreSQL feature tests, isolated Redis worker, Pint, PHPStan, architecture and Composer checks.
- [x] Review: render synthetic HTML at 320/640/768/1024/1280, review independently, update API side-effect documentation, queue/database docs and tasks. Apply only the additive mail migration to local development, restart queue/scheduler and verify schema without sending a real order.

## Review focus

Missing site notification address must leave admin pending without blocking customer mail. Duplicate jobs must skip sent deliveries. Transport exception details must never be stored. Anonymized orders must never be mailed. SMTP cannot guarantee exactly-once across the remote accept/local commit boundary; document this limit.

