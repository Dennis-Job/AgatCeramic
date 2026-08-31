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

Phase 3.1 makes each product a standalone sellable item. Create requires `category_id`, `name`, a
unique URL-safe `slug`, a controlled `unit`, and a non-negative `price`. The server generates the
immutable, globally unique eight-digit SKU as `TTNNNNNN`: `TT` is the stable two-digit prefix of the
root category inherited by its descendants, while `NNNNNN` is one global product sequence. Clients
must not send `sku` on create or update. Existing legacy SKUs remain readable and are not rewritten.
Create also requires non-negative `stock_quantity`. Optional commercial fields are globally unique
`article_number`, digit-only `barcode`, and `old_price` (not below current price). `brand_id`, `description`, and
`is_active` and `is_on_sale` are optional boolean flags. Product responses include these commercial fields, category, brand, and
nullable `primary_image`. The same primary-image projection is present in product lists, relation
candidates, and product-group members so selectors can show the correct standalone product image.
The endpoints require `catalog.manage` and record `product.created`, `product.updated`, and
`product.deleted` in the audit log.
Category responses expose the read-only `sku_prefix`. New root categories receive the next permanent
code; a child inherits its parent's code. Moving a category subtree changes the prefix used for
products created there in the future but does not change any existing SKU.
Deleting a product locks the product against concurrent image mutations, collects every current
product-image storage reference, then removes the product and its cascaded records. After the
database transaction commits, it removes every collected file from its configured storage disk.
An upload that loses this race fails without creating a database image record and removes its
newly stored file.

`GET /admin/products` supports case-insensitive substring `search` for product name, slug, SKU,
article number, or barcode. It also supports `category_id`, `brand_id`,
`is_active`, `is_on_sale`, `has_stock`, `price_from`, `price_to`, `sort`, `direction`, and `per_page`.
`sort` accepts `sku`, `name`, `created_at`, or `updated_at`; `direction` accepts `asc` or `desc`.
The default order is `created_at desc`, so newly created products appear first. Price and stock
filters apply directly to products; all filters can be combined.

Changing `category_id` is rejected with `422` when the destination category does not assign every
attribute already used by the product. Missing required destination values are permitted while the
product remains inactive; activation is rejected until all assignment-specific required values are
valid. The update is atomic and never silently removes values.

### Product groups

- `GET /admin/product-groups`
- `POST /admin/product-groups`
- `GET /admin/product-groups/{product_group}`
- `PATCH|PUT /admin/product-groups/{product_group}`
- `DELETE /admin/product-groups/{product_group}`

Groups require `catalog.manage`. Create requires unique `code`, `name`, `axis_attribute_ids`, and
`product_ids`; updates use the same fields as a full desired group definition. A group contains at
least two standalone products. Members must share category and brand, provide a unique tuple for the
selected axes, and have equal values for every non-axis characteristic. Each axis must be assigned
to the common category and be scalar; `text` and `multiselect` are rejected. Group changes are
atomic, lock affected products consistently, and are audited. Groups do not copy product fields or
images and are independent of related/recommended product relations.

The former `/admin/products/{product}/variants` routes are removed from the supported API. Legacy
variant tables are read-only migration inputs and are not exposed through public Admin routes.

Legacy conversion is operated with `php artisan catalog:migrate-standalone-products`: no option is
a read-only dry run, `--apply` performs the idempotent conversion, and `--apply --finalize` removes
only verified converted legacy rows. Finalization is run only after the dry-run/apply reconciliation
report is accepted. Physical table removal is deliberately opt-in and runs only after that step:
`php artisan migrate --path=database/finalization-migrations/2026_08_21_230000_drop_legacy_product_variant_tables.php --force`.
The finalization migration refuses to run while any legacy variant row remains.

For categories, attribute groups, attributes, brands, products, and product groups, Laravel's
resource routes accept both `PUT` and `PATCH` on the documented update URL; both methods use the
same partial-update validation and response. Product-image metadata remains `PATCH` only.

### Product attributes

- `GET /admin/products/{product}/attributes`
- `PUT /admin/products/{product}/attributes`

`PUT` fully replaces the requested product's attribute values. For a product in a variation group,
axis values belong only to that product, while every non-axis value is shared by the group: supplied
shared values are copied atomically to every member and omitted shared values are removed from every
member. A shared required value cannot be removed while any member is active. Axis uniqueness and all
group invariants are checked before commit; a conflict rolls back the complete update. The response
contains the requested product's resulting values.

The `PUT` endpoint replaces the full value set with an `attributes` array of
`{attribute_id, value}` entries. An attribute must be assigned to the product category. Inactive
drafts may omit category-assignment required values; activation requires all of them. Values are
validated against the attribute type and allowed options. Changes require `catalog.manage` and are
recorded as `product.attributes-updated`.

### Product images

- `GET /admin/products/{product}/images`
- `POST /admin/products/{product}/images` (multipart, `image` field)
- `PATCH /admin/products/{product}/images/{image}`
- `DELETE /admin/products/{product}/images/{image}`

Uploads require `catalog.manage` and accept JPEG, PNG, or WebP images up to 10 MiB. The backend
assigns each file a deterministic `<SKU>_<ordinal>.<extension>` name and sets Alt text to
`<SKU>_<ordinal>` automatically; an `alt` value supplied during upload is ignored. The API returns
the public URL and metadata. Each product always has one primary image while it has images. Alt text
and sort order may still be edited through the API after upload.

### Product relations

- `GET /admin/products/{product}/relations`
- `GET /admin/products/{product}/relation-candidates?search=&limit=`
- `PUT /admin/products/{product}/relations`

The `PUT` endpoint atomically replaces the `relations` array of `{related_product_id, type,
sort_order}` entries. Supported types are `related` and `recommended`. A product cannot relate to
itself or create a reverse duplicate. Replacements acquire locks for the source and every related
product in a consistent order, then revalidate reverse relations within the transaction; a unique
database index on the unordered product pair is a final guard. Changes require `catalog.manage`
and are audited as `product.relations-updated`. The candidates endpoint returns up to 50 products,
excludes the source and both outgoing and incoming existing relations, and searches name, slug, or SKU.

### Catalog response contract

Every Catalog resource response is wrapped in `{"data": ...}`. Category, attribute group,
attribute, brand, product, and product-group lists are paginated and include `data`, `links`, and
`meta`; image lists are also paginated with a maximum page size of 100. Category attributes,
attribute groups, product attribute values, and product relations return unpaginated `data` arrays.

The OpenAPI contract defines the exact fields returned by every Catalog resource, including nested
category, brand, attribute-option, product-group, and related-product fields. It also
defines all validation request bodies and normal error responses (`401`, `403`, `404`, and `422` as
applicable). Clients should treat the specification as the authoritative machine-readable contract.

Product image upload is `multipart/form-data`: the required `image` is JPEG, PNG, or WebP and no
larger than 10 MiB; `is_primary` and `sort_order` are optional. The stored filename and Alt text are
generated from the current product SKU and the next image ordinal. Product-image mutations and
product deletion are serialised per product, and a database partial unique index guarantees that
concurrent uploads or updates cannot leave more than one primary image.

When an attribute is changed to `select` or `multiselect`, the same update request must include a
non-empty replacement `options` array. This prevents a choice-type attribute from being persisted
without permitted values.

### Категории

Все административные операции Catalog ниже требуют активную Sanctum-сессию и permission
`catalog.manage`. Одиночные ответы имеют конверт `data`; списки категорий, групп, характеристик,
брендов и изображений используют стандартные `data`, `links`, `meta`. Для обновления обычных
ресурсов допустимы `PATCH` и `PUT`; оба метода означают частичное обновление. Точные JSON-схемы,
коды ответов и все поля приведены в [`openapi.json`](openapi.json).

- `GET /admin/categories` — постраничный плоский список (25 элементов на страницу).
- `GET /admin/categories/tree` — непостраничное дерево корневых категорий с `children`; применять
  для построения иерархии, а не для экранов с постраничным списком.
- `POST /admin/categories`, `GET|PATCH|PUT|DELETE /admin/categories/{category}`.

При создании обязательны `name` и уникальный URL-safe `slug` (`lowercase-kebab-case`). Дополнительно
принимаются `parent_id`, `description`, `is_parent`, `is_active` и `sort_order`. Родителем может быть
только категория с `is_parent: true`; нельзя назначить себя родителем или переместить категорию в
собственного потомка. Категорию с детьми нельзя перевести в `is_parent: false`. Эти проверки выполняются
в транзакции, поэтому клиенту следует обработать `422`, если параллельное изменение сделало выбранного
родителя недопустимым.

`image_id` and SEO fields are intentionally unavailable in category requests and responses. Category
images are owned by `TASK-096` (Phase 7), while category metadata is owned by `TASK-100` and the
related SEO tasks (Phase 8); clients must not send placeholder IDs before those contracts exist.

```json
POST /api/v1/admin/categories
{
  "parent_id": 12,
  "name": "Настенная плитка",
  "slug": "wall-tiles",
  "is_parent": true,
  "is_active": true,
  "sort_order": 20
}
```

Назначения характеристик и групп являются полными заменами, а не добавлением к текущему набору:

- `GET /admin/categories/{category}/attributes` — характеристики категории; при назначении
  возвращаются `category_sort_order` и assignment-specific `is_required`.
- `PUT /admin/categories/{category}/attributes` — тело
  `{"attributes":[{"id":17,"sort_order":0,"is_required":true}]}`; пустой массив очищает все назначения.
- `GET /admin/categories/{category}/attribute-groups` — назначенные группы.
- `PUT /admin/categories/{category}/attribute-groups` — тело
  `{"attribute_groups":[{"id":4,"sort_order":0}]}`; пустой массив очищает группы. При удалении
  группы из категории открепляются и относящиеся к ней характеристики.

Полная замена отклоняется с `422`, если она прямо или через удаление группы открепила бы
характеристику, уже используемую товаром этой категории. Проверка и изменение
назначений выполняются в одной транзакции; существующие значения никогда не удаляются
автоматически. Новую обязательную характеристику можно назначить до заполнения значений; неполные
неактивные товары остаются черновиками, а их активация блокируется до заполнения.

### Группы характеристик

- `GET /admin/attribute-groups` — постраничный список (25 элементов).
- `POST /admin/attribute-groups`, `GET|PATCH|PUT|DELETE /admin/attribute-groups/{attribute_group}`.

Для создания требуются уникальные `name` и `slug`. `description` может быть `null`, а `sort_order`
определяет порядок группы в категории. Удаление группы не удаляет сами характеристики: перед удалением
клиент должен при необходимости переназначить их другой группе или оставить без группы.

```json
POST /api/v1/admin/attribute-groups
{"name":"Размеры","slug":"dimensions","description":"Габариты товара","sort_order":10}
```

### Бренды

- `GET /admin/brands` — постраничный список (25 элементов), упорядоченный по названию.
- `POST /admin/brands`, `GET|PATCH|PUT|DELETE /admin/brands/{brand}`.

Создание требует уникальные `name` и `slug`. Поля `description` и `country_code` необязательны;
`country_code` — ISO 3166-1 alpha-2 в верхнем регистре, например `IT`. `is_active` управляет
доступностью бренда на витрине и по умолчанию включён. Удаление бренда не удаляет товары: связанные
товары сохраняются без бренда.

`logo_id`, document attachments, and SEO fields are intentionally unavailable in brand requests and
responses. Logos and documents are owned by `TASK-096` (Phase 7), while brand metadata is owned by
`TASK-100` and the related SEO tasks (Phase 8); clients must not send placeholder IDs before those
contracts exist.

```json
PATCH /api/v1/admin/brands/8
{"country_code":"IT","is_active":true}
```

### Характеристики

- `GET /admin/attributes` — постраничный список (25 элементов), включающий `options`.
- `POST /admin/attributes`, `GET|PATCH|PUT|DELETE /admin/attributes/{attribute}`.

Поддерживаемые `type`: `string`, `text`, `integer`, `decimal`, `boolean`, `select`, `multiselect`,
`date`. Цвет задаётся характеристикой типа `select`: `label` хранит название цвета, а `value`
может содержать HEX-код, например `#A1B2C3`. Прежний `number` нормализован миграцией в `decimal`; новые запросы с `number`
отклоняются. Имя и slug уникальны во всём каталоге; `attribute_group_id`, `unit`, `is_filterable`,
`is_visible_on_product_page` и `sort_order` необязательны. `is_required` задаётся только в назначении
характеристики категории. Видимость на странице
товара по умолчанию включена и не влияет на возможность редактировать или фильтровать значение.
Поле `options` разрешено только для `select` и `multiselect`; при их создании оно
обязательно и содержит от одного до 500 вариантов с уникальным `value`. Каждый вариант задаётся как
`value`, `label` и необязательный `sort_order`.

Форма JSON-значения зависит от типа: `string` — непустая строка до 255 символов, `text` — непустая строка до 10000,
`integer` — JSON integer, `decimal` — конечный JSON number, `boolean` — JSON boolean, `select` — код
одной опции (в том числе HEX-код цвета), `multiselect` — непустой массив до 500 уникальных кодов, `date` —
существующая календарная дата `YYYY-MM-DD`. Числовые и логические строки (`"12.5"`, `"true"`) не
считаются типизированными значениями и возвращают `422`.

При переключении существующей характеристики на `select` или `multiselect` в том же запросе должен
быть передан непустой полный набор `options`. Передача `options` для другого типа возвращает `422`.
Если `options` передано для типа выбора, оно целиком заменяет существующие варианты; при переключении
на иной тип варианты удаляются. Изменение типа или полного набора опций атомарно отклоняется с `422`,
если хотя бы одно существующее значение товара или legacy-варианта в окне миграции перестало бы соответствовать новому
определению. Добавлять опции, менять их подписи/порядок и удалять неиспользуемые коды разрешено;
используемый код опции переименовывать или удалять нельзя. Проверка повторяется в транзакции под
блокировками, поэтому параллельная запись значения не оставит каталог в несовместимом состоянии.

Изменение assignment-specific `is_required` использует staged workflow: флаг можно включить до
заполнения всех товаров, но неполный товар нельзя активировать. Удаление характеристики с
существующими product- или legacy-значениями также возвращает `422`; значения автоматически не удаляются.

```json
POST /api/v1/admin/attributes
{
  "attribute_group_id": 4,
  "name": "Поверхность",
  "slug": "surface",
  "type": "select",
  "is_filterable": true,
  "is_visible_on_product_page": true,
  "options": [
    {"value":"matte","label":"Матовая","sort_order":0},
    {"value":"glossy","label":"Глянцевая","sort_order":1}
  ]
}
```

### Изображения товаров

- `GET /admin/products/{product}/images` — постраничный список до 100 элементов на страницу,
  упорядоченный обложкой, затем `sort_order` и идентификатором.
- `POST /admin/products/{product}/images` — `multipart/form-data`; обязательный файл находится в
  поле `image`. Принимаются JPEG, PNG и WebP не больше 10 МиБ. Дополнительно: `alt`, `is_primary`,
  `sort_order`.
- `PATCH /admin/products/{product}/images/{image}` — изменяет только `alt`, `is_primary` и/или
  `sort_order`; заменять исходный файл этим маршрутом нельзя.
- `DELETE /admin/products/{product}/images/{image}` — удаляет запись и файл из storage.

Первое изображение товара автоматически становится обложкой. Передача `is_primary: true` снимает этот
признак с остальных изображений. При удалении или снятии признака с текущей единственной обложки
сервис назначает следующую по `sort_order`/id, поэтому товар с изображениями всегда имеет ровно одну
обложку. Операции изображений и удаление товара сериализованы для одного товара; клиенту не следует
пытаться самостоятельно эмулировать это несколькими параллельными запросами.

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
