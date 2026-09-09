# OpenAPI migration plan — v1 to v1.1

Date: 2026-09-09.

## Breaking change

`POST /api/v1/orders` now requires a client-generated `Idempotency-Key` HTTP header (16–255
characters). A request missing the header receives the standard `422 validation_failed` response.

## Client migration

Before releasing the backend, every checkout client must generate one cryptographically random key
for an order submission and keep it while retrying that same submission. A newly started checkout
must use a new key. The current repository contains no implemented client checkout caller.

## Rollout and rollback

Deploy the migration and backend together, then release checkout clients that send the header. The
database record stores only HMAC values and an order reference; `checkout-idempotency:prune` deletes
it after 24 hours. If rollback is necessary during the migration window, restore the prior API
release before accepting checkout requests from legacy clients; do not persist raw keys or customer
payloads as a compatibility workaround.
