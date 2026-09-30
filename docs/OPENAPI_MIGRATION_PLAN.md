# OpenAPI migration plan — version history

Date: 2026-09-09.

Breaking compatibility policy: a breaking wire-contract change requires a new major
`info.version`. Its version section in this file must contain a `Breaking change` heading
and describe client migration, rollout and rollback. A patch/minor bump never authorizes
operation removal or another incompatible contract change.

## v4.0 — typed page drafts and explicit publication

Date: 2026-09-30. TASK-C002; the OpenAPI version is independent of `/api/v1`.

### Breaking change

Page responses add typed ordered blocks, SEO, publication timestamps and admin draft status.
Saving a page or homepage section no longer publishes it. `is_published:true` on create/update
is rejected; use the new explicit publish endpoint. `is_published:false` still withdraws a page.
Public page responses are independent published snapshots, including the published slug;
strict old response validators must be regenerated. System page slugs cannot be renamed/deleted.
`/home-page` adds blocks while retaining existing section fields; the shared header/footer
also remain unpublished until the saved home draft is explicitly published.

### Client migration

Regenerate validators against v4.0. Read the draft through admin endpoints; save it, then call
`POST /admin/pages/{page}/publish` or `POST /admin/home-page/publish`. Use
`has_unpublished_changes`, `is_published`, and `published_at` for editor states. Render public
blocks in array order and ignore disabled blocks. Hero slides and image URLs are resolved by
the public API. Nuxt, Admin, and Laravel in this repository migrate together.

### Rollout and rollback

Apply `2026_09_30_120000_add_page_content_snapshots` before serving new code. It adds columns
and media FK references, preserves prior page text and homepage content, seeds missing system
pages and appends missing public navigation links without replacing existing entries. A colliding
generic `home` retains its legacy body in the draft; only previously published text is exposed.
Existing public data is initially snapshotted; later draft saves do not alter it.

The migration deliberately refuses `down`: dropping snapshots would destroy the independent
draft/published versions. Application rollback may keep the additive schema. A full schema
downgrade requires restoring a verified pre-migration backup and coordinating old application
versions; do not run destructive rollback on current content. Resources and stored media remain.

## v3.0 — managed homepage and banner presentation

Date: 2026-09-29. The OpenAPI document version remains independent of the `/api/v1` URL prefix.

### Breaking change

Banner responses now include `eyebrow` and `image_alt`. Their `image_url` can be a root-relative
storefront asset path as well as an absolute URL. The v2.0 banner response schemas prohibited
additional properties and required an absolute URI, so strict response validators can reject
these responses. The new homepage endpoints are additive.

### Client migration

Regenerate strict clients and response validators against v3.0 before using the updated banner
endpoints. Accept the two new nullable presentation fields. Resolve root-relative banner image
paths against the public storefront origin; continue to accept existing absolute image URLs.
The Admin editor and Nuxt storefront in this repository have been updated together.

### Rollout and rollback

Apply the homepage migration, then deploy the backend and updated Admin/Client together. Any
external client with a strict v2.0 banner response validator must move to v3.0 before calling
the updated backend. For rollback, restore the previous application versions together and retain
the added columns, homepage table, and seeded content until a separate data-preserving cleanup;
dropping the new schema would discard administrator edits.

## v2.0 — managed media response fields

Date: 2026-09-27. The OpenAPI document version is independent of the `/api/v1` URL prefix.

### Breaking change

Category, brand and banner responses include managed media references and metadata. These
fields also appear in nested category and brand objects in product responses. The previous
response schemas prohibited additional properties, so strict schema validators can reject
the expanded responses even though existing fields retain their meaning.

### Client migration

Update generated clients and strict response validators to the v2.0 OpenAPI document before
deploying the media library. Clients should accept the new media fields and continue reading
existing identifiers and URLs. Banner `image_url` still resolves to the effective image URL;
`legacy_image_url` preserves the external URL separately for editors.

### Rollout and rollback

Deploy the media migration before the backend that reads the new tables, then release clients
using the v2.0 schema. Keep old clients that reject unknown response fields off the new backend
until updated. For rollback, restore the prior backend and client versions together; retain the
media tables and stored files until a separate data-preserving rollback plan is approved.

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
