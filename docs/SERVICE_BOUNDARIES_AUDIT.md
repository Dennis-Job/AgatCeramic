# Interim Audit — services and module boundaries (TASK-A009)

Audit date: 2026-09-09.

## Confirmed strengths

- Catalog mutations, cart changes, order creation, order/contact workflow and access-control
  mutations have application-service boundaries and use `DB::transaction()` with appropriate
  `lockForUpdate()` reads for contested records.
- Checkout locks cart, cart items and products, creates immutable snapshots, clears the cart in
  the same transaction and queues order confirmation with `afterCommit()`.
- `AuditLogService` centralizes action-name validation, entity snapshots and PII sanitisation;
  audited services call it from their business transaction.
- `StorageCleanupService` implements a durable cleanup outbox. Catalog deletion writes cleanup
  intent before dispatching a retryable `DeleteStoredFile` job after commit.
- Product workbook imports retain checkpoints/row items under import-row locks; their documented
  resume semantics are not to be weakened by a controller-level refactor.

## Confirmed findings

### A009-1 — import submission and orchestration are duplicated

The four import controllers directly repeat private-file storage, status-row persistence, queue
dispatch and cleanup-on-error. `ProcessProductImport` then branches across generic, category,
group and price/status workflows; each branch repeats terminal completion and source-file cleanup.
It also resolves operation services via `app()` rather than declared dependencies. This leaves no
single application boundary that owns:

1. submission ownership and source-file metadata;
2. post-commit dispatch;
3. terminal state transition;
4. cleanup outbox scheduling; and
5. retry/resume eligibility.

The equivalent ZIP image import has a separate job with nearly identical status/permission/source
checks. It currently saves terminal image-import state and then schedules cleanup outside one
explicit transaction, unlike the XLSX terminal branches.

### A009-2 — async lifecycle needs a shared policy before deduplication

Redis uses `after_commit: true`, and workbook jobs have three attempts, 80-second timeout and
30/120-second backoff. However, `self::dispatch()` continuation and `failed()` terminal handling
are repeated across import jobs. A shared lifecycle service must preserve chunk checkpoints,
permission revalidation, bounded source-file cleanup and failure reporting. That implementation
belongs jointly to A009 and TASK-A013; simply moving methods around would make an already sensitive
operation less reliable.

### A009-3 — public checkout lacks an explicit idempotency contract

`OrderCreationService` retries database exceptions and protects concurrent cart consumption with
locks, but the public `POST /orders` contract has no idempotency key or persisted request identity.
Browser/network retry after a successful response loss can therefore create a separate order.
Adding a key requires an API schema/OpenAPI change and an explicit retention/privacy decision, so
it is not made during this audit.

## Implemented import lifecycle

The four submission controllers now delegate source-file storage, metadata persistence and
post-commit scheduling to `ImportSubmissionService`. `ImportDispatchService` persists one durable
dispatch task per import before queue dispatch; `imports:retry-dispatch` is scheduled every five
minutes and retries pending or stale dispatched tasks. `ImportLifecycleService` owns permission
revalidation, terminal transition, source cleanup outbox scheduling and resumable continuation for
both XLSX and ZIP imports. The two queue adapters retain their existing three-attempt, 80-second
and 30/120-second retry policy.

This preserves source ownership, row-level checkpoints and transaction boundaries. Targeted import
tests cover the submission outbox and the existing completion/resume scenarios.

## Checkout idempotency decision and implementation

The approved retention period is 24 hours. `POST /orders` now requires `Idempotency-Key`; the
database stores only HMACs of the key and normalized request plus the order reference, never a raw
key or duplicate customer payload. The same key and request replay the original order, while a key
reused with different request data returns `409`. The hourly prune command deletes expired records.
The breaking public-contract change is versioned as OpenAPI v1.1 with its rollout plan in
[`OPENAPI_MIGRATION_PLAN.md`](OPENAPI_MIGRATION_PLAN.md).
