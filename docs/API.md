# Контракт API

## OpenAPI

Машиночитаемая спецификация OpenAPI 3.1: [`openapi.json`](openapi.json).
В ней описываются только реализованные маршруты. При добавлении или изменении
маршрута необходимо одновременно обновить соответствующую OpenAPI-операцию;
запланированные маршруты в этом документе не являются доступными операциями API.

## Базовый путь

`/api/v1`

## Маршрутизация

Все маршруты приложения должны быть объявлены ниже `/api/v1`. `GET /api/v1` —
лёгкая точка входа для определения версии; она возвращает `{"version":"v1"}`.
Публичные и административные маршруты находятся в разных группах. Промежуточное
ПО аутентификации и авторизации для административной группы добавляется в рамках
соответствующих задач аутентификации и политик доступа.

## Публичный API

### Каталог

`GET /categories`

`GET /categories/{slug}`

`GET /products`

`GET /products/{slug}`

`GET /brands`

`GET /brands/{slug}`

### Корзина

`GET /cart`

`POST /cart/items`

`PATCH /cart/items/{item}`

`DELETE /cart/items/{item}`

### Заказы

`POST /orders`

`GET /orders/{order_number}/confirmation`

### Контент

`GET /pages/{slug}`

`GET /site-settings/public`

## Admin authentication

The first-party Admin SPA uses Laravel Sanctum's cookie-based session authentication.
Before any state-changing request, it must request `GET /sanctum/csrf-cookie` with
credentials included, then send the URL-decoded `XSRF-TOKEN` value in the `X-XSRF-TOKEN`
header. Browser requests must include credentials.

- `POST /admin/auth/login` — accepts `email` and `password`; limited to five attempts per
  minute for an email/IP combination. Invalid credentials and blocked accounts both return
  the standard `401 unauthenticated` response.
- `GET /admin/auth/me` — returns the authenticated active administrator.
- `PATCH /admin/auth/me` — allows every authenticated administrator to update only their own
  `name`, `email`, and password. It does not require `admin-users.manage`; it cannot alter roles or
  status. Changing the password ends the current session and revokes all other sessions.
- `POST /admin/auth/logout` — invalidates the current session and CSRF token.
- `POST /admin/auth/forgot-password` — accepts an administrator email and always returns `204` to
  prevent account enumeration. Active accounts receive a single-use password-reset link by email.
- `POST /admin/auth/reset-password` — accepts `email`, `token`, `password`, and
  `password_confirmation`; the password must be at least 12 characters. A successful reset revokes
  all current sessions and rotates the remember token.

Password-reset endpoints are limited to five attempts per minute for an email/IP combination. Links
expire after 60 minutes, are sent only to active accounts, and point to
`{ADMIN_APP_URL}/reset-password`. Set `ADMIN_APP_URL` to the deployed Admin SPA origin.
Only completed password changes are written to the audit trail; reset-link requests are not logged
to avoid exposing account existence to audit-log readers.

Changing an employee password through `PATCH /admin/users/{user}` also revokes that employee's
sessions. If an administrator changes their own password, their current Admin SPA session is ended
and the interface redirects them to the login page.

All other administrative API routes require `auth:sanctum` and an active account. Fine-grained
authorization is enforced through Laravel policies introduced in TASK-024.

Successful administrative login and logout are recorded in the internal audit trail. The read-only
audit-log endpoints enforce the `audit-log.view` permission and return only explicitly mapped,
sanitised fields.

Role storage, granular permissions, and their relations are introduced in TASK-022 and TASK-023.
TASK-024 registers policies for `User`, `Role`, and `Permission`: only permissions such as
`admin-users.manage`, `roles.manage`, and `permissions.manage` authorize mutations. Future
controllers must call the registered policy through `Gate::authorize()` before changing access-control data.

### Сотрудники

- `GET /admin/users` — постраничный список сотрудников; поддерживает `search`, `status` и `per_page`.
- `POST /admin/users` — создаёт сотрудника с минимум одной ролью.
- `GET /admin/users/{user}` — возвращает сотрудника и его роли.
- `PATCH /admin/users/{user}` — изменяет учётные данные, статус, пароль и назначенные роли.
- `DELETE /admin/users/{user}` — удаляет сотрудника.
- `GET /admin/users/roles` — возвращает роли, доступные для назначения в форме сотрудника.

Все операции требуют `admin-users.view` или `admin-users.manage` согласно policy. Изменения
сотрудников записываются в audit trail; нельзя заблокировать или удалить собственную учётную
запись, а также лишить систему последнего активного Super Admin.

### Roles

- `GET /admin/roles` and `GET /admin/roles/{role}` return roles with assigned permissions.
- `POST /admin/roles`, `PATCH /admin/roles/{role}`, and `DELETE /admin/roles/{role}` manage custom roles.
- `GET /admin/roles/permissions` returns the controlled permission catalogue required by the role editor.

System roles cannot be deleted or renamed. A role editor can assign only permissions it already
holds; every mutation is written to the audit trail.

### Permission catalogue

- `GET /admin/permissions` returns the controlled permission catalogue with its assigned roles.
  The optional `module` query parameter filters codes by their `module.action` prefix.
- `GET /admin/permissions/{permission}` returns one catalogue entry and its assigned roles.

The catalogue is read-only: permissions are introduced through controlled application releases and
seed data, rather than arbitrary administrative CRUD.

### Audit log

- `GET /admin/audit-logs` returns a paginated read-only audit trail. It supports `search`, exact
  `action`, `actor_id`, `date_from`, `date_to`, and `per_page` filters.
- `GET /admin/audit-logs/{audit_log}` returns one audit record.

Both operations require `audit-log.view`. For employee-targeted events the response includes the
target's current name and email to make the event identifiable; all other metadata remains
sanitised, so passwords, tokens, and other sensitive values are never exposed.

## Административный API

Административный API должен быть защищён аутентификацией и авторизацией.

### Товары

- `GET /admin/products`
- `POST /admin/products`
- `GET /admin/products/{product}`
- `PATCH /admin/products/{product}`
- `DELETE /admin/products/{product}`

TASK-036 provides the base product card. Create requires `category_id`, `name`, and a unique
URL-safe `slug`; `brand_id`, `description`, and `is_active` are optional. Product responses
include the assigned category and brand (when present). These endpoints require `catalog.manage`
and record `product.created`, `product.updated`, and `product.deleted` in the audit log.
Deleting a product locks the product against concurrent image mutations, collects every current
product-image storage reference, then removes the product and its cascaded records. After the
database transaction commits, it removes every collected file from its configured storage disk.
An upload that loses this race fails without creating a database image record and removes its
newly stored file.

`GET /admin/products` supports `search` (product name, slug, variant SKU or variant name),
`category_id`, `brand_id`, `is_active`, `has_stock`, `price_from`, `price_to`, and `per_page`.
Price and stock filters apply to product variants; all filters can be combined.

### Product variants

- `GET /admin/products/{product}/variants`
- `POST /admin/products/{product}/variants`
- `GET /admin/products/{product}/variants/{variant}`
- `PATCH /admin/products/{product}/variants/{variant}`
- `DELETE /admin/products/{product}/variants/{variant}`

Variants are managed under their owning product and require `catalog.manage`. Creation requires
`name`, a globally unique SKU, and a non-negative `price`; `old_price` is optional but cannot be
less than the current price. `stock_quantity`, `is_active`, and `sort_order` default to `0`,
`true`, and `0`. The optional `attribute_values` array contains only the category-assigned
characteristics that distinguish the SKU, as `{attribute_id, value}` entries; values are validated
against the attribute type and its allowed options. Changes are audited as `product.variant-created`, `product.variant-updated`, and
`product.variant-deleted`.

For categories, attribute groups, attributes, brands, products, and product variants, Laravel's
resource routes accept both `PUT` and `PATCH` on the documented update URL; both methods use the
same partial-update validation and response. Product-image metadata remains `PATCH` only.

### Product attributes

- `GET /admin/products/{product}/attributes`
- `PUT /admin/products/{product}/attributes`

The `PUT` endpoint replaces the full value set with an `attributes` array of
`{attribute_id, value}` entries. An attribute must be assigned to the product category; required
category attributes must be supplied. Values are validated against the attribute type and allowed
options. Changes require `catalog.manage` and are recorded as `product.attributes-updated`.

### Product images

- `GET /admin/products/{product}/images`
- `POST /admin/products/{product}/images` (multipart, `image` field)
- `PATCH /admin/products/{product}/images/{image}`
- `DELETE /admin/products/{product}/images/{image}`

Uploads require `catalog.manage` and accept JPEG, PNG, or WebP images up to 10 MiB. Files receive
a generated storage name; the API returns their public URL and metadata. Each product always has
one primary image while it has images. Alt text and sort order may be edited after upload.

### Product relations

- `GET /admin/products/{product}/relations`
- `PUT /admin/products/{product}/relations`

The `PUT` endpoint atomically replaces the `relations` array of `{related_product_id, type,
sort_order}` entries. Supported types are `related` and `recommended`. A product cannot relate to
itself or create a reverse duplicate. Replacements acquire locks for the source and every related
product in a consistent order, then revalidate reverse relations within the transaction; a unique
database index on the unordered product pair is a final guard. Changes require `catalog.manage`
and are audited as `product.relations-updated`.

### Catalog response contract

Every Catalog resource response is wrapped in `{"data": ...}`. Category, attribute group,
attribute, brand, and product lists are paginated and include `data`, `links`, and `meta`; product
variant and image lists are also paginated with a maximum page size of 100. Category attributes,
attribute groups, product attribute values, and product relations return unpaginated `data` arrays.

The OpenAPI contract defines the exact fields returned by every Catalog resource, including nested
category, brand, attribute-option, variant-attribute-value, and related-product fields. It also
defines all validation request bodies and normal error responses (`401`, `403`, `404`, and `422` as
applicable). Clients should treat the specification as the authoritative machine-readable contract.

Product image upload is `multipart/form-data`: the required `image` is JPEG, PNG, or WebP and no
larger than 10 MiB; `alt`, `is_primary`, and `sort_order` are optional. Product-image mutations and
product deletion are serialised per product, and a database partial unique index guarantees that
concurrent uploads or updates cannot leave more than one primary image.

When an attribute is changed to `select` or `multiselect`, the same update request must include a
non-empty replacement `options` array. This prevents a choice-type attribute from being persisted
without permitted values.

### Категории

- `GET /admin/categories`
- `POST /admin/categories`
- `GET /admin/categories/{category}`
- `PATCH /admin/categories/{category}`
- `DELETE /admin/categories/{category}`
- `GET /admin/categories/{category}/attributes` — assigned category attributes.
- `PUT /admin/categories/{category}/attributes` — replaces assignments with an `attributes` array of `{id, sort_order}` objects; an empty array clears assignments.
- `GET /admin/categories/{category}/attribute-groups` — assigned attribute groups.
- `PUT /admin/categories/{category}/attribute-groups` — replaces groups with an `attribute_groups` array of `{id, sort_order}` objects.

### Бренды

Операции создания, просмотра, изменения и удаления.

### Характеристики

Операции создания, просмотра, изменения и удаления.

### Заказы

- список;
- просмотр;
- изменение статуса;
- изменение статуса оплаты;
- добавление комментария;
- фиксация оплаты.

### Обращения

Операции создания, просмотра, изменения и удаления, а также процесс обработки
статусов.

### Контент

Операции создания, просмотра, изменения и удаления.

### SEO

Операции создания, просмотра, изменения и удаления.

### Аналитика

Маршруты только для чтения отчётов.

## Правила API

- единообразные HTTP-коды состояния;
- ошибки валидации в стабильном формате;
- пагинация;
- фильтрация;
- сортировка;
- отсутствие скрытых побочных эффектов;
- авторизация для каждой защищённой операции;
- обновление документации API при изменениях.

## Формат ошибок

Каждый ошибочный ответ `/api/v1` использует единый JSON-конверт:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "Переданные данные не прошли проверку.",
    "details": {
      "name": ["Поле «Название» обязательно для заполнения."]
    }
  }
}
```

`code` стабилен и предназначен для программной обработки. `message` безопасен
для отображения; непредвиденные исключения никогда не раскрывают внутреннее
сообщение. `details` всегда является объектом и содержит сообщения валидации по
полям только при `validation_failed`; для остальных типов ошибок он пуст.

| HTTP-код | Код ошибки |
| --- | --- |
| 400 | `bad_request` |
| 401 | `unauthenticated` |
| 403 | `forbidden` |
| 404 | `not_found` |
| 405 | `method_not_allowed` |
| 409 | `conflict` |
| 422 | `validation_failed` / `unprocessable_entity` |
| 429 | `too_many_requests` |
| 5xx | `internal_server_error` / `http_error` |

## Соглашение о ресурсах API

Каждый доменный ответ API должен использовать ресурс Laravel API. Ресурсы
наследуются от `App\\Http\\Resources\\ApiResource`, именуются
`{Entity}Resource` и располагаются в пространстве имён соответствующего домена внутри
`app/Http/Resources` (например,
`App\\Http\\Resources\\Catalog\\ProductResource`). Контроллеры не должны
возвращать Eloquent-модели напрямую.

Ответ с одиночным ресурсом использует стабильный конверт:

```json
{
  "data": {
    "id": 17,
    "name": "Керамическая плитка"
  }
}
```

Списки используют `{Entity}Resource::collection($items)` и возвращают `data`
в виде массива. Если входные данные являются пагинатором, сохраняются стандартные
объекты Laravel `links` и `meta`. Поля ресурса задаются явным списком разрешённых
полей: нельзя
раскрывать атрибуты модели прямой сериализацией модели или возвратом
`$this->resource`.
