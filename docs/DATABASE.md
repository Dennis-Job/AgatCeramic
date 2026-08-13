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
- is_active
- sort_order
- timestamps

### attribute_groups
Группы характеристик.

### attributes
Характеристики.

### attribute_options
Значения select/multiselect.

### category_attributes
Какие характеристики используются категорией.

### brands
Бренды.

### products
Основная карточка товара.

### product_variants
SKU/варианты.

### product_attribute_values
Значения характеристик.

### product_images
Изображения.

### product_relations
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

Содержит:
- actor;
- action;
- entity;
- entity_id;
- timestamp;
- sanitized metadata.
