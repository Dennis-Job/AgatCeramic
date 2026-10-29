# OpenAPI migration plan — version history

Date: 2026-09-09.

Breaking compatibility policy: a breaking wire-contract change requires a new major
`info.version`. Its version section in this file must contain a `Breaking change` heading
and describe client migration, rollout and rollback. A patch/minor bump never authorizes
operation removal or another incompatible contract change.

## v12.0 — editable side-menu note

Date: 2026-10-28. HTTP routes remain `/api/v1`.

### Breaking change

Header responses now expose the required `menu_note` string (maximum 1000
characters). Previous closed response schemas reject the additional property;
strict consumers must update to v12.0. Both appearance and legacy home-page
PATCH accept `menu_note` optionally as string/null. Empty or null input stores
an empty string. Omitted input preserves an existing saved value. Missing
historical keys project the existing footer tagline without modifying storage;
an explicitly saved empty string disables the note instead of restoring it.

### Client migration

Admin edits the plain text in global header settings. Client renders the
side-menu note as text with line breaks, never as HTML, and uses the existing
site-settings phone/email and footer social links for the adjacent contact
section. These contact values are not duplicated in header storage. Update
strict home-page, appearance and saved-preview response validators. Saving
changes only the appearance draft; page publication retains appearance isolation.

### Rollout and rollback

Release backend, Admin and Client together. No schema migration, automatic
rewrite or content publication is required. Before rollback, retain a protected
copy of configured menu notes and the current appearance snapshots. Old Client
shows its former tagline; old backend rejects new `menu_note` writes. Roll back
all applications together and omit the property from writes to the old backend.
Do not discard saved JSON simply to satisfy older response validators. Restore
the compatible release and saved configuration to recover the editable note.

## v11.0 — independent footer navigation and social links

Date: 2026-10-28. HTTP routes remain `/api/v1`.

### Breaking change

Footer responses now always expose `navigation_links`, `social_links` and
`bottom_right_url`. Previous closed response schemas reject these new properties,
so strict consumers must update to v11.0. Both appearance and legacy home-page
PATCH accept the fields optionally. Flat link entries retain `{label,to}`;
navigation contains 0–256 entries and social links 0–12. Existing SafeSiteUrl,
255-character labels and 2048-character destinations apply. Empty lists hide
their columns. Empty/null URL input removes the bottom-right text link; output
uses an empty string. Unknown properties and nested groups remain rejected.

### Client migration

Admin manages the two lists independently and supplies a separate destination
for bottom-right text. Client renders safe configured links and hides empty
columns. Missing legacy navigation projects the flattened, deduplicated header
links in order; social links default to `[]` and the bottom-right URL to `''`.
Explicit navigation `[]` is preserved. Omitted optional fields from old writers
preserve already-saved values. Read projections never rewrite storage. Regenerate
strict response validators for all home-page/appearance and saved-preview APIs.
Saving remains draft-only and page publication cannot publish appearance edits.

### Rollout and rollback

Release backend, Admin and Client together before configuring these fields.
No schema migration, automatic content rewrite or publication is required.
Before rollback, retain a protected copy of new footer configuration. Old Client
renders its former header-derived navigation and ignores new display fields;
old backend rejects writes containing them. Roll back the three applications
together and ensure any older writer omits the new properties. Do not discard
stored JSON or snapshots merely to satisfy older response validators. Restore
the compatible release and saved configuration to recover new footer behavior.

## v10.0 — optional one-level navigation submenus

Date: 2026-10-28. HTTP routes remain `/api/v1`.

### Breaking change

Only `header.navigation` gains a union: existing `{label,to}` links remain valid,
and `{label,children:[{label,to}]}` defines a disclosure-only group without a
parent URL. The presence of `children` selects group mode. Previous strict
response schemas require `to` and forbid `children`, so consumers must update
to v10.0 before groups are used. All six homepage/appearance request and response
schemas use the same union; saved content preview uses the appearance schema.
Footer links remain flat `{label,to}` objects.

Navigation is an ordered list of 1–16 parents; groups contain 1–16 flat links.
Labels are nonempty strings up to 255 characters and destinations up to 2048.
Destinations retain SafeSiteUrl validation: internal paths starting with `/` or
HTTPS URLs, without credentials, whitespace, control characters or backslashes.
Mixed parent URLs/children, empty groups, extra nesting and unknown fields are
rejected with 422 by both `/admin/site-appearance` and legacy `/admin/home-page`.
The existing `/admin/pages` API continues to reject direct `site_layout` writes;
the global appearance workflow owns the JSON stored in the home page layout.

### Client migration

Admin edits only the active link/group shape. Client detects groups by `children`,
uses the parent as a disclosure button, and renders children as normal links.
Regenerate strict clients and response validators against v10.0. Old flat menus
continue to work without any data conversion; the older Client cannot render
new groups safely. Existing permissions, audit and explicit publication apply.
Saving updates only the appearance draft, including saved authenticated preview;
page publication does not publish the appearance draft.

### Rollout and rollback

Release compatible backend, Admin and Client together before saving or publishing
groups. No schema/data migration, automatic rewrite or publication is required.
Draft and published snapshots retain their existing content until explicitly
saved and published through the usual workflow. Before rolling back to a Client
that supports only flat links, explicitly restore and publish a compatible flat
menu through the updated editor, retaining a protected copy of the grouped
content. Then roll back all three application versions together. Do not silently
discard nested links or rewrite historical snapshots during rollback.

## v9.0 — independent menu banner destinations and notes

Date: 2026-10-27. HTTP routes remain `/api/v1`.

### Breaking change

Menu banner items (`materials.items` and `materials` page-block items) accept and
expose optional nullable `link_url` (maximum 2048 characters) and `note` (maximum
2000 characters). Previous closed response schemas reject these properties, so
strict clients must update to v9.0. Destinations use the same safe root-relative
path or HTTPS URL rules as grid banners. Missing/null links disable navigation;
empty request strings normalize to null. Item notes are independent, optional,
and never fall back to the section-level `materials.note`. The legacy section
note remains accepted as an optional nullable string, including empty input,
for compatibility; its stored value is retained by the migration. Its response
property is now optional and nullable, so consumers must also accept its absence
or null instead of requiring the former non-null shared note.

### Client migration

Admin edits each menu item's destination and note; the shared note editor is
removed. Client renders each item's note and makes its whole menu item clickable
when a safe destination exists, without a separate navigation badge. Neither
client should edit or render the legacy shared note. Grid data and link behavior
remain independent. Saving continues to change only the draft; publication
remains explicit.

### Rollout and rollback

After a protected backup and restore rehearsal following
[DATABASE_BACKUP_RESTORE.md](DATABASE_BACKUP_RESTORE.md), apply
`2026_10_27_130000_move_banner_menu_notes_to_items.php` after the existing menu-list
split migration and release compatible backend/Admin/Client together. This
additive, transactional, idempotent migration copies each menu section's own
legacy note only into items without a `note` key. Draft `pages.blocks`, published
`pages.published_snapshot.blocks`, and legacy `home_pages.content` are processed
separately. Explicit empty/null/custom item notes, links, all existing fields,
publication metadata and timestamps remain intact. No items or missing global
notes are invented; no content is published and clean/dirty state is preserved.
New defaults own their notes per menu item.

The migration deliberately refuses `down` because deleting independent notes
would discard edits. Application rollback may retain all additive fields, but
requires readers/writers that preserve them; strict old schemas are incompatible.
A data downgrade requires a verified protected pre-migration backup and
coordinated application rollback. Do not strip item notes/links automatically.

## v8.0 — optional destinations for grid banners

Date: 2026-10-27. HTTP routes remain `/api/v1`.

### Breaking change

Grid banner items (`categories.items` and `categories` page block items) accept
and expose an optional nullable `link_url` of at most 2048 characters. Adding a
response property breaks the previous closed item schemas even though existing
requests without the field remain valid. Destinations must be root-relative
internal paths or HTTPS URLs without credentials, whitespace, control characters
or backslashes. Empty request strings normalize to `null`; omitted/null values
leave a banner without navigation. The independent `materials` menu and its item
schema are unchanged and do not accept this field.

### Client migration

Admin must edit/save a destination per grid banner. Client must preserve that
value through its block mapping and navigate to that banner's configured URL.
Existing banners without a destination keep their current content and have no
navigation. Draft saves do not publish links; explicit publication retains each
banner's destination independently of menu items with the same ID.

### Rollout and rollback

Deploy compatible backend/Admin/Client together and refresh open editors. No
database migration, data backfill, default destination or automatic publication
is required. Existing draft and published JSON snapshots remain unchanged until
an editor saves/publishes them. A rollback must preserve stored destination
fields; older strict validators/response consumers cannot be assumed compatible.
Use coordinated application rollback with destination-aware readers/writers,
or restore a verified protected backup following
[DATABASE_BACKUP_RESTORE.md](DATABASE_BACKUP_RESTORE.md). Do not strip destinations
from content or merge grid and menu data automatically.

## v7.0 — independent homepage banner collections

Date: 2026-10-27. Admin labels are «Сетка баннеров» (`categories`) and
«Меню баннеров» (`materials`); HTTP routes remain `/api/v1`.

### Breaking change

The `materials` block/section now requires its own `items` array (0–12 entries)
in requests and responses, with the same item fields as `categories.items`.
An item ID must be unique within its own list; the two lists may reuse an ID.
Menu items, images and ordering no longer derive from the grid. `categories`
still requires at least one item. Existing editable headings are preserved.

### Client migration

Admin must edit/save `materials.items` separately. Client must map its image URLs
and render the menu from this list, including when the grid is absent/disabled.
An empty menu does not render a section. Remove grid-to-menu selection coupling;
menu tabs navigate only their own items. No request-time or render-time fallback
to `categories.items` is supported after migration.

### Rollout and rollback

Deploy compatible backend/Admin/Client together and refresh open editors.
Before the data migration, create and verify a protected backup using
[DATABASE_BACKUP_RESTORE.md](DATABASE_BACKUP_RESTORE.md). The additive migration
copies grid items once into an absent menu list, separately for each draft and
published snapshot; it never overwrites an existing list, including `[]`, and
does not publish draft changes. New menus start empty. When a legacy page has no
grid, its menu starts with an empty list. Media references for both snapshots
must remain protected.

Rollback must retain the additive field to avoid losing later independent menu
edits; old clients/validators cannot be assumed compatible with it. Use coordinated
application rollback or restore the verified backup in an isolated database,
then follow the project's restore procedure. Do not merge independent lists back
automatically or remove menu data in a down migration.

## v6.0 — single Russian phone contract

Date: 2026-10-20. Owner-approved unified telephone input, display and storage.
HTTP route URLs remain `/api/v1`; this version identifies the contract revision.

### Breaking change

Phone inputs now accept only a complete Russian number: canonical `+7` and ten
digits, the same formatted with separators, ten national digits, or legacy `8`
and ten digits. Foreign, incomplete, extension and ambiguous values return `422`.
Response phones and stored values are canonical `+7xxxxxxxxxx`. Site settings
retain the `phones` array key but allow at most one item; `[]` clears the phone.
Clients display `+7 (xxx) xxx-xx-xx` and use canonical values in `tel:` links.

### Client migration

Use the shared telephone primitive with automatic `+7` and ten national digits;
send canonical values. Replace multi-phone settings editors with one field.
If legacy settings contain multiple values, require an explicit choice and never
discard additional entries during an unrelated save. Existing legacy input
formats remain accepted when they identify a complete supported Russian number.

### Rollout and rollback

Deploy the backend and compatible Admin/Client together. No schema migration is
required. Run the default dry-run `phones:normalize` first; unambiguous historical
conversions require backup, verification on a copy and explicit `--apply`.
Invalid/multiple data stays unchanged for owner review. Details are in
[PHONE_NUMBER_NORMALIZATION.md](PHONE_NUMBER_NORMALIZATION.md).

Checkout keys created before normalization may have a different payload HMAC
for up to 24 hours; verify an existing order before retrying, and do not
automatically generate another key after `409`. On backup restore, replay old
retention tombstones before normalizing phones. Never rewrite tombstone HMACs.
Rollback backend and clients together; canonical numbers remain valid in the
previous contract. Restore a protected preconversion backup only if original
formatting is required; do not automatically expand a single-phone setting.

## v5.0 — page indexing controls

Date: 2026-10-20. Managed content pages support a separate `is_indexable` boolean.
New pages default to false; existing rows and old published snapshots retain true.
Saving the flag changes only the draft. Page publication activates the choice;
appearance publication does not change indexing.

### Breaking change

Page, published page, homepage and admin homepage responses add `is_indexable`.
Their previous closed schemas rejected unknown properties, so strict consumers
must update their response DTO/schema before rollout. Request payloads accept the
optional boolean on page creation/update; omission preserves the existing draft
choice on update. The `preview` slug is reserved for the protected Nuxt service
route and is rejected on creation/rename. `/api/v1` URLs and all existing fields remain available.

Deploy the additive database migration and backend before the Admin and Nuxt
clients. Existing clients may ignore the extra field. New clients interpret a
missing flag as true for old snapshots. Nuxt renders `noindex,follow` for excluded
pages and lists only published indexable content pages in `/sitemap.xml`.
Rollback clients first; retain the column and snapshots until the saved choices
are no longer needed. Dropping the column discards draft indexing choices and
requires a backup. Old code retains the content but may show unpublished changes
because its snapshot comparison does not recognize the added indexing property.

## v4.2 — authenticated saved-draft preview

Date: 2026-10-01. TASK-C005. Additive `GET /admin/content-preview/{slug}` returns
saved page content and global appearance drafts for the actual Nuxt renderer. The
response is `{data: {page: {title, slug, body, blocks, seo}, appearance: {header, footer}}}`.
Every request requires a current active Sanctum session and `content.manage`; no
reusable public link or token is issued. Enabled blocks, media and published shared
slider/banner dependencies use the same resolver as public page rendering.

Success and error responses are private/no-store/no-cache, noindex/nofollow, with
`Referrer-Policy: no-referrer`. Preview callers must send credentials and an allowed
Origin or origin-only Referer for Sanctum to initialize the session. Add the Nuxt
origin to `SANCTUM_STATEFUL_DOMAINS` and `CORS_ALLOWED_ORIGINS` when overriding them;
local defaults and `.env.example` include localhost/127.0.0.1 port 3000. Production
Admin, Nuxt and API must share the configured cookie site/domain.

No schema/data migration or public endpoint change is required. Deploy backend before
the preview UI. Rolling back the UI and endpoint leaves saved and published content intact.

## v4.1 — independent global appearance publication

Date: 2026-09-30. TASK-C004. Additive `/site-appearance` and `/admin/site-appearance`
GET/PATCH plus `/admin/site-appearance/publish` expose the global header/footer draft
and its separate publication. Existing homepage payloads retain their fields.

Homepage publication now retains the previously published global header/footer.
Legacy homepage header/footer updates still save the same appearance draft; callers
must publish it using the dedicated endpoint. Page `has_unpublished_changes` describes
page content and SEO only; the appearance endpoint has its own flag. Nuxt shell callers
should fetch `/site-appearance`, which remains available after home unpublication.

No migration is required: existing home `site_layout` and snapshot fields are retained.
Deploy backend/Admin/Client together to expose the independent publish action. Application
rollback may retain all schema/content, but restores the previous coupled publication
behavior; review outstanding appearance/page drafts before using that older editor.

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
