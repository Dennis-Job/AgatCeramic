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
- sort_order
- timestamps

### attribute_groups
Группы характеристик.

### attributes
TASK-033 creates the shared attribute catalogue. An attribute can belong to an optional
attribute group and has a stable unique `name` and `slug`, a `type` (`text`, `number`,
`boolean`, `select`, or `multiselect`), an optional display `unit`, flags for filtering
and requiredness, and `sort_order`. Choice types keep their values in `attribute_options`.

Характеристики.

### attribute_options
Each option belongs to one attribute and stores a stable per-attribute `value`, a human-readable
`label`, and `sort_order`. Attribute deletion cascades to its options; deleting an attribute group
keeps its attributes and clears their group reference.

Значения select/multiselect.

### category_attributes
Какие характеристики используются категорией.

### brands
Бренды. TASK-035 хранит уникальные `name` и `slug`, необязательное описание,
двухбуквенный ISO 3166-1 alpha-2 `country_code` страны происхождения, будущую ссылку
`logo_id` и флаг `is_active`.

### products
TASK-036 creates the base product card: a required `category_id`, optional `brand_id`, name,
unique URL-safe slug, optional description, publication flag `is_active`, and timestamps.
Product variants, prices, SKU, attributes, images, and relations are intentionally introduced by
their respective later catalog tasks. Categories cannot be deleted while referenced by products;
deleting a brand clears the optional reference.

### product_variants
TASK-037 creates variants owned by a product. Each has a globally unique SKU, display name,
current price, optional old price (never below the current price), stock quantity, publication
flag, sort order, and timestamps. Deleting a product cascades to its variants.

SKU/варианты.

### product_attribute_values
TASK-038 stores one JSON value per product and category-assigned attribute. The product and
attribute pair is unique; deleting a product removes its values. Attribute deletion is restricted
while a value exists.

Значения характеристик.

### product_variant_attribute_values
Stores only category-assigned characteristics that distinguish one SKU variant from another.
Each row belongs to a variant and an attribute and contains one JSON value; the variant/attribute
pair is unique. Deleting a variant deletes its values, while an attribute cannot be deleted while
variant values refer to it.

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

## SEO

### seo_metadata

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
