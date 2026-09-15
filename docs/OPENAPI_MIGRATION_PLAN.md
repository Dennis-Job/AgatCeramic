# OpenAPI migration plan — version history

Date: 2026-09-09.

Breaking compatibility policy: a breaking wire-contract change requires a new major
`info.version`. Its version section in this file must contain a `Breaking change` heading
and describe client migration, rollout and rollback. A patch/minor bump never authorizes
operation removal or another incompatible contract change.

## v1.2 — guest cart token storage and expiry

Date: 2026-09-15. The HTTP contract is unchanged: clients continue using the 64-character
`X-Cart-Token`. The specification now documents that only its HMAC is persisted and that unknown or
expired carts return `404`. Empty, abandoned and checked-out TTLs are server configuration. After a
successful checkout the token remains usable for an immediate idempotent replay, but cannot accept
new items; clients start a new cart by calling `GET /cart` without the old header. Deploy the schema
migration before serving the new code. Rollback requires a pre-migration database restore because
the raw legacy tokens are intentionally not recoverable from their HMACs.

## v1.1 — checkout idempotency

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
