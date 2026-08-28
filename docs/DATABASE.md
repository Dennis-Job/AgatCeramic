# Database

Это предварительная модель. Не создавать все таблицы автоматически только потому, что они перечислены здесь. Каждая сущность должна появляться в рамках соответствующей задачи.

## Core

### users
Администраторы.

TASK-020 adds these `users` fields:
- `name`;
- `email` (unique);
- `password` (one-way hash);
- `status`: `active` or `blocked`;
- `last_login_at`;
- timestamps.

Roles and permissions are introduced in TASK-022 and TASK-023. Administrative HTTP
endpoints require a valid active administrator session; individual authorization policies
are enforced by Laravel policies added in TASK-024.

### roles
Administrative roles. TASK-022 stores a human-readable unique `name`, a stable unique `slug`,
an optional `description`, and timestamps. Baseline roles are seeded without permissions:
`super-admin`, `administrator`, `catalog-manager`, `order-manager`, `content-manager`,
`seo-manager`, and `analyst`.

### permissions
Granular permissions. TASK-023 stores a human-readable unique `name`, a stable unique
`code` in the `module.action` format, an optional `description`, and timestamps. Permissions
are granted only through roles; users do not receive direct permission assignments.

### role_permissions
Many-to-many assignment of permissions to roles. The composite primary key prevents duplicate
assignments; deleting a role or permission removes its pivot rows.

### user_roles
Many-to-many assignment of staff accounts to roles. The composite primary key prevents duplicate
assignments; deleting a staff account or role removes its pivot rows.

## Catalog

### categories
- id
- parent_id
- name
- slug
- description
- image_id
- is_parent
- is_active
- is_on_sale (boolean, default false; existing products remain outside the sale after migration)
- sort_order
- timestamps

`image_id` is a nullable Catalog-phase placeholder, not an active media foreign key and not a raw
storage path. `TASK-096` (Phase 7) owns the `media` entity, the migration that turns this placeholder
into an enforced managed-media relationship, and its replacement/deletion lifecycle. Until then the
Catalog API does not accept or expose category image management. Category SEO is intentionally absent
from this table; `TASK-100` (Phase 8) owns it through the separate `seo_metadata` layer.

### attribute_groups
Группы характеристик.

### attributes
TASK-033 creates the shared attribute catalogue. An attribute can belong to an optional
attribute group and has a stable unique `name` and `slug`. TASK-041M completes `type` with
`string`, `text`, `integer`, `decimal`, `boolean`, `select`, `multiselect`, and `date`.
The former unrestricted `number` type is migrated to `decimal`; legacy numeric JSON strings are
converted to JSON numbers in the same migration. Attributes also have an optional display `unit`,
flags for filtering and product-page visibility (`is_visible_on_product_page`, true by default),
and `sort_order`. Requiredness belongs to each `category_attributes` assignment in the Phase 3.1
target model, not to the global attribute definition. The old `attributes.is_required` column is
legacy migration state and is no longer writable through the API. Choice types keep values in
`attribute_options`.

Характеристики.

### attribute_options
Each option belongs to one attribute and stores a stable per-attribute `value`, a human-readable
`label`, and `sort_order`. Attribute deletion cascades to its options; deleting an attribute group
keeps its attributes and clears their group reference.

Product JSON values must remain valid for the current attribute type. Legacy variant values remain
covered by the same validation only during migration. Choice values
refer to the stable option `value`, not the option row ID. Type and option replacement uses a
reject-on-conflict policy: compatible conversions, option additions, label/order changes, and
removal of unused option codes are allowed; a change that invalidates any stored product or legacy variant
value returns `422` without mutating the definition or audit trail. Definition writers lock assigned
categories in ID order before products and the attribute row, and value writers repeat semantic
validation inside their transaction. Attributes with stored values cannot be deleted.

Value shapes are strict: `string` is non-empty and at most 255 characters, `text` is non-empty and
at most 10000 characters, `integer` is a
JSON integer, `decimal` a finite JSON number, `boolean` a JSON boolean, `select` an option code,
`multiselect` a non-empty unique array of option codes, and `date` is a valid
calendar date formatted as `YYYY-MM-DD`.

Colors intentionally use the ordinary `select` model instead of a separate attribute type: the
option label is the human-readable color name and its option code may be a HEX value such as
`#A1B2C3`.

Значения select/multiselect.

### category_attributes
Defines which characteristics a category uses, their assignment-specific `is_required` flag, and
their display order. Inactive drafts may omit required values; activation requires every required
assignment to have a valid product value.

### brands
Бренды. TASK-035 хранит уникальные `name` и `slug`, необязательное описание,
двухбуквенный ISO 3166-1 alpha-2 `country_code` страны происхождения, будущую ссылку
`logo_id` и флаг `is_active`.

`logo_id` is a nullable Catalog-phase placeholder with no active foreign key and no raw storage-path
semantics. `TASK-096` (Phase 7) owns the media table, the enforced relationship and lifecycle for the
logo, and a separate attachment relationship for reusable brand/catalog documents. The singular
`logo_id` must not be overloaded as a document association. Brand SEO columns are intentionally
absent; `TASK-100` (Phase 8) owns brand metadata through the separate `seo_metadata` layer.

### products
Phase 3.1 makes `products` the only sellable catalogue entity. In addition to required
`category_id`, optional `brand_id`, name, unique URL-safe slug, optional description, activation
state, and timestamps, each product owns a globally unique SKU, optional globally unique article
number and barcode, required controlled sale unit, current and optional old price, and stock.
The supported units remain `piece`, `square_meter`, `linear_meter`, `package`, `kilogram`, `liter`,
and `set`. Each product owns its own attribute values and images. Categories cannot be deleted while
referenced by products; deleting a brand clears the optional reference. Commercial columns remain
nullable at database level only for unconverted legacy rows; Admin create/update and activation
enforce the standalone-product contract.

### product_variants
Legacy-only storage during Phase 3.1 migration. Existing rows are expanded into standalone products
by an idempotent command with explicit dry-run and apply modes. The command reports identifier,
attribute, image, and grouping conflicts before writes. Nested-variant API writes are removed; only
the migration command may update conversion markers until reconciliation is accepted and cleanup is
authorised. `catalog:migrate-standalone-products` defaults to dry run, `--apply` converts rows,
and verified converted rows are removed with `--apply --finalize`. After the accepted reconciliation,
the explicitly invoked migration in `database/finalization-migrations` refuses non-empty legacy storage
and then drops both legacy variant tables; it is intentionally excluded from the ordinary migration path.

### product_attribute_values
TASK-038 stores one JSON value per product and category-assigned attribute. The product and
attribute pair is unique; deleting a product removes its values. Attribute deletion is restricted
while a value exists.

Значения характеристик.

### product_variant_attribute_values
Legacy-only values consumed by the Phase 3.1 migration. Their values are merged with the former
parent product values when creating each standalone product; a conflict is reported and never
silently resolved. This table is removed together with `product_variants` by the opt-in finalization migration.

Category reassignment and category-attribute replacement use a reject-on-conflict policy. A product
cannot move while it uses an attribute absent from the destination. Requiredness gates activation,
not inactive draft persistence. An assigned attribute cannot be detached while category products
use it. Writers repeat assignment validation inside the transaction.

### product_images
TASK-039 stores product-owned image metadata: public-disk path, MIME type, byte size, optional
alt text, primary-image flag, and sort order. Image files use generated names under
`product-images/{product_id}`; deleting a product cascades to its image records.

Изображения.

### product_relations
TASK-040 stores directed `related` or `recommended` relationships between products with sort
order. The source/product/type combination is unique; deleting either product cascades to its
relations.

Связанные/рекомендуемые товары.

### product_groups
Variation-family metadata with a unique stable `code` and display `name`. A group has at least two
standalone products through `product_group_members` and one or more ordered axis attributes through
`product_group_axes`. Each product may belong to at most one group. Members must share category and brand, have equal non-axis values,
and have a unique tuple of axis values. Axis attributes must be assigned to the category and must be
scalar; `text` and `multiselect` are prohibited. Membership/group writes validate and lock all
affected products atomically. Product groups are independent of `product_relations`.

## Orders

### carts
Анонимная корзина.

### cart_items

### orders
- order_number
- customer_name
- customer_phone
- customer_email
- delivery_address
- customer_comment
- status
- payment_status
- total_amount
- created_at
- paid_at
- completed_at

### order_items
Должны хранить snapshot:
- product/variant reference;
- product name snapshot;
- SKU snapshot;
- price snapshot;
- quantity;
- line total.

### order_status_history

### order_comments

## Contacts

### contact_requests
Обратные звонки и email requests.

### contact_request_comments

## Content

### pages

### banners

### sliders

### stores

### store_working_hours

### site_settings

### media

Owned by `TASK-096` (Phase 7). Besides content assets, this entity supplies managed category images,
brand logos, and reusable brand/catalog documents. The task must reconcile the existing nullable
`categories.image_id` and `brands.logo_id` placeholders by adding valid relationships and explicit
replacement/deletion behavior. Before adding foreign keys, its migration must define how every
pre-existing non-null value is mapped, backfilled, nulled, or rejected and must define the on-delete
semantics. Documents require their own association, cardinality, role/order, and lifecycle instead
of overloading `brands.logo_id`. The task must not preserve unconstrained IDs or file paths.

## SEO

### seo_metadata

Owned by `TASK-100` (Phase 8) as the single managed metadata layer for products, categories, and
brands. Category/brand SEO title and meta description are not Catalog-table columns. `TASK-100` must
define the entity association, constraints, permissions, validation, API/Admin behavior, and any
legacy-field migration before exposing the metadata.

### redirects

## Analytics

Не хранить избыточные PII ради аналитики.

Для финансовой аналитики основными датами являются:
- order.created_at;
- order.paid_at;
- order.completed_at.

## Audit

### audit_logs

TASK-025 creates the append-only audit trail for material administrative actions.

- `actor_id` — nullable historical staff-account identifier. It deliberately has no database foreign
  key: deleting an account must not alter an immutable audit record;
- `action` — stable dot-notated code, for example `auth.login` or `admin-users.update`;
- `entity_type` and `entity_id` — optional target model class and identifier;
- `metadata` — optional minimal JSON context, sanitised before persistence;
- `actor_snapshot` — the actor name at the event time, retained if the account is later deleted or renamed;
- `entity_snapshot` — an allowlisted identity snapshot for the event target: employee name/email,
  role name/slug, or permission name/code;
- `occurred_at` — event time.

The audit payload must never contain passwords, tokens, contact details, names, addresses, or
other unnecessary PII. Models do not write audit records themselves: application services record
explicit action codes and an allowlisted minimal context through `AuditLogService`.

Snapshots are an explicit exception for administrative-account identification: they preserve only
the fields listed above so that material actions remain understandable after a target is deleted.
They are retained for five years and then deleted with the audit record.

PostgreSQL permits only INSERT operations on `audit_logs`. Database triggers reject every UPDATE
and direct DELETE; the retention command is the sole exception, using a transaction-local database
setting that applies only while it removes records older than the approved retention period.
