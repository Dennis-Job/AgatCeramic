# Interim Audit Phases 0–6 — актуальный отчёт приёмки

Дата фиксации: 2026-09-09.
Задачи: `TASK-A001`, `TASK-A002`.
Ревизия: `e6ab0b9852e09d0c9048677cff5ed64b7e22cd64` (`Define interim audit roadmap`).

## Границы и метод

Это единый актуальный отчёт, а не подтверждение готовности к Phase 7. Проверены рабочее дерево,
зарегистрированные Laravel-маршруты, миграции, исходники, OpenAPI, маршруты Admin, UI-сценарии и
автоматические проверки. Рабочие данные не изменялись. Одна отметка `[x]` в roadmap не
использовалась как доказательство.

## Среда и состояние

| Область | Зафиксированное состояние |
| --- | --- |
| Рабочее дерево | чистое до создания этого отчёта |
| Backend | PHP `^8.3`, Laravel `^13.0`, Sanctum `^4.3`, OpenSpout `^4.32`; точные resolved versions — `backend/composer.lock` |
| Admin | Vue `^3.5.40`, Vite `^8.2.0`, TypeScript `~6.0.2`, Pinia `^4.0.2`; `frontend/admin/package-lock.json` |
| Client | Nuxt `4.5.2`, Vue `^3.5.41`; `frontend/client/package-lock.json` |
| Compose | `backend`, `admin`, `client`, `postgres`, `redis`, `queue`, `scheduler` запущены; PostgreSQL 17.9 и Redis 7.4 healthy |
| БД | все 41 миграция в `backend/database/migrations/` применены по `artisan migrate:status`; destructive inspection не выполнялась |
| Queue/storage | отдельные запущенные worker и scheduler; durable import/image-cleanup tables присутствуют в миграциях; фактические очереди и содержимое storage будут предметом `TASK-A013` |
| API | фактический список получен через `php artisan route:list --json`; канонический машинный контракт — `docs/openapi.json` |

## Карта реализации

В таблице перечислены все завершённые задачи Phases 0–6. Путь `API` означает маршрут(ы) в
`backend/routes/api/v1.php`; все Admin routes используют Sanctum и `EnsureActiveAdmin`, а
конкретное право проверяется Policy/Request/Service. `Тесты` — минимальный адресный evidence;
полный набор также указан ниже.

| Задачи | Подтверждённая реализация: код / миграции / API / право | UI или будущий потребитель | Тесты и контракт |
| --- | --- | --- | --- |
| 001–007 | структура монорепозитория, API-only Laravel, Vue Admin, Nuxt Client, Docker Compose, `.env`-конфигурация и CI | Admin/Client entrypoints; Client пока каркас | `LoggingConfigurationTest`, `RedisConfigurationTest`, `V1RoutingTest`, `TestEnvironmentTest`; `docs/openapi.json` |
| 010–017 | PostgreSQL/Redis/config, `/api/v1`, стандартный error envelope и Resources, PII sanitising, queues/scheduler, OpenAPI | инфраструктурная основа Admin и будущего Client | `ApiErrorResponseTest`, `ApiResourceTest`, `OpenApiDocumentationTest`, logging tests |
| 020–029C | users, roles, permissions, pivots, audit snapshots/retention/immutability; `AdminUserController`, `RoleController`, `PermissionController`, `AuditLogController`, `AuthController`; migrations 2026-08-12–13 | `/login`, `/forgot-password`, `/reset-password`, `/profile`, `/employees`, `/roles`, `/permissions`, `/audit-log` | `AdminAuthenticationTest`, `AdminPasswordResetTest`, `AdminUserManagementTest`, `RoleManagementTest`, `PermissionCatalogueTest`, audit/PostgreSQL immutability tests; OpenAPI |
| 030–041U | categories, groups, attributes/options, brands, standalone-product transition, product values/images/relations/search; migrations 2026-08-14–21 | `/categories`, `/attribute-groups`, `/attributes`, `/brands`, `/products` | category/attribute/brand/product/image/relation/search API tests, `PostgresCatalogConcurrencyTest`, `StorageCleanupTest`; OpenAPI |
| 042A–042Z | sellable `products`, product groups, safe legacy expansion, generated SKU and shared characteristics; migrations 2026-08-21, 08-28, 09-02 | integrated `/products` workflow and product-group controls | `StandaloneProductManagementTest`, `ProductManagementTest`, `ProductAttributeValueManagementTest`, import/export, concurrency and Admin component/e2e coverage; OpenAPI/API guide |
| 050–058 | streaming XLSX export; category and generic import; preflight/errors; resumable import items; ZIP image import; price/status and group workbook operations; migrations 2026-09-01, 03, 07–08 | Product import/export controls in `/products`; no standalone Admin route | `ProductExportTest`, `ProductImportTest`, `CategoryProductImportTest`, `ProductImageImportTest`, `ProductPriceStatusImportTest`, `ProductGroupImportTest`; OpenAPI/API guide |
| 060–064 | public cart and checkout; cart/order/order-item tables, locked revalidation, random order number and immutable snapshots; migrations 2026-09-08 10:00–12:00 | future Phase 10 Client cart/checkout | `GuestCartTest`, `OrderCreationTest`; OpenAPI |
| 065–070 | DB status catalogue, payment snapshot, status history, comments and after-commit email; migrations 2026-09-08 13:00–16:00 | API is present; `/orders` is only `PlaceholderView` | `OrderStatusManagementTest`, `OrderPaymentManagementTest`, `OrderStatusHistoryTest`, `OrderCommentTest`, `OrderConfirmationTest`; OpenAPI |
| 080–084 | public callback/email/partner endpoints and protected contact assignment/workflow; contact tables and workflow migrations 2026-09-09 10:00–12:00 | no contact-management Admin view/navigation yet | `CallbackRequestTest`, `EmailRequestTest`, `PartnerRequestTest`, `ContactAssignmentTest`, `ContactWorkflowTest`; OpenAPI |

### Реальные API-группы и права

- Public: `/cart`, `/cart/items`, `/orders`, `/callback-requests`, `/email-requests`,
  `/partner-requests`; checkout/contact submissions have named rate limits and server-owned
  commercial/workflow fields.
- Admin auth and RBAC: `/admin/auth/*`, `/admin/users`, `/admin/roles`,
  `/admin/permissions`, `/admin/audit-logs`.
- Catalog/imports: `/admin/categories`, `/attribute-groups`, `/attributes`, `/brands`,
  `/products`, `/product-groups`, their image/relation/attribute/import endpoints. Principal
  permissions: `catalog.manage`, `imports.manage`.
- Orders: status/history/comments under `orders.view`/`orders.manage`; payment under
  `payments.manage`. Missing order list/show is an explicit follow-up below.
- Contacts: list/show under `contacts.view`, assignment/status/comments under `contacts.manage`.

## Подтверждённые проверки

В Docker или локальном development environment на этой ревизии успешно выполнены:

- `php artisan test --compact` — 226 tests, 1608 assertions passed;
- `vendor/bin/pint --test` — passed;
- `composer validate --strict` и `composer audit --locked` — passed, advisories отсутствуют;
- `npm.cmd run test:unit -- --run` в `frontend/admin` — 25 tests passed;
- `npm.cmd run build` в `frontend/admin` — passed;
- `npm.cmd run build` в `frontend/client` — passed;
- JSON parse `docs/openapi.json`, `php artisan route:list --json`, `php artisan migrate:status` и
  Docker health inspection — passed.

Не являются частью этого baseline и остаются обязательными в последующих задачах: clean-environment
end-to-end, PostgreSQL concurrency повторно, Redis/worker recovery, contract route-coverage,
manual screen-reader QA и статический анализ без baseline.

## Повторная приёмка TASK-A002

### Foundation, Auth/RBAC и audit

API остаётся API-only: маршруты живут под `/api/v1`, пользовательских Blade flows не обнаружено.
Public и Admin contracts разделены; Admin защищён `auth:sanctum` и `active_admin`, а проверки
прав подтверждены feature tests. Повторно прошли authentication, password reset, employee/role/
permission management, sanitised audit events и PostgreSQL-specific invariant tests по исходникам.

Локально PostgreSQL integration suite намеренно не запускается против рабочей БД: guard допускает
только `:memory:` либо CI-only `agatceramic_test` при `CI=true`. Локальный Compose не предоставляет
эту изолированную базу, поэтому 9 integration tests завершаются protective error до assertions.
Это корректная защита данных, но не воспроизводимая локальная команда приёмки.

### Catalog и import/export

Проверены CRUD, assignment/typed values, standalone product/group invariants, generated SKU,
relations, images/cleanup, XLSX/ZIP import operations и permissions по исходникам, OpenAPI и
backend suite. Отдельный UI audit текущего запуска подтвердил `/products`: таблицу, фильтры,
массовые действия и редактирование на 320 и 1280 px. На узком экране действия и фильтры
перестраиваются в одну колонку без видимого horizontal overflow.

Полный Admin E2E запуск дал `47 passed, 4 failed`: четыре последних import scenarios одновременно
получили `ERR_CONNECTION_REFUSED` от временного Vite server, без product assertion failure.
Один из них повторён изолированно и passed. Следовательно, это подтверждённый flaky test
environment, а не доказанный defect import functionality.

### Cart, orders и contacts

Public cart/checkout, order snapshots, status/payment/history/comment/email и public contact forms,
assignment/workflow подтверждены маршрутами, OpenAPI и feature tests. Однако визуальная приёмка
показывает, что `/orders` содержит только placeholder. В sidebar нет Contacts route/view, хотя
`contacts.view/manage` API готов. Эти блокирующие Admin gaps не являются регрессией API, но не
позволяют принять manager workflows.

### UI evidence and limits

В текущем запуске в локальном Admin были захвачены и визуально inspected четыре состояния:

1. Dashboard — **ограниченно здоров**: layout доступен, но показатели и последние заказы —
   статические пустые placeholders.
2. Products, 1280 px — **здоров**: filters, actions, table and hierarchy readable.
3. Products, 320 px — **здоров**: action buttons and filters reflow without visible overflow.
4. Orders, 1280 px — **нездоров**: explicit placeholder, no manager action or data.

Скриншоты являются evidence текущего browser run; accessibility tree также подтвердило названия
навигации и controls. Скриншоты не доказывают screen-reader announcements, focus order, contrast
ratio или production API integration; это остаётся обязательной частью TASK-A017/A018/A019.

## Реестр дефектов, долга и отложенных возможностей

| Приоритет | Состояние | Наблюдение | Владелец |
| --- | --- | --- | --- |
| High | подтверждённый functional gap | Backend order operations не имеют Admin list/show; `/orders` — placeholder. | `TASK-A018` |
| High | подтверждённый functional gap | Нет Admin navigation/views для обращения, хотя API workflow готов. | `TASK-A019` |
| Medium | подтверждённый quality gap | Полный Admin E2E suite нестабилен: 4/51 сценария потеряли временный Vite server; изолированный повтор passed. | `TASK-A015` |
| Medium | подтверждённый quality gap | Admin Docker runtime не содержит dev dependencies (`vitest`, `playwright`), поэтому не воспроизводит unit/build/e2e checks внутри Compose. | `TASK-A015` |
| Medium | подтверждённый quality gap | PostgreSQL integration suite безопасно блокируется без provisioned CI-only `agatceramic_test`; локальная documented runbook отсутствует. | `TASK-A015` |
| Medium | quality debt | Larastan/PHPStan и воспроизводимый quality baseline отсутствуют в зафиксированном наборе. | `TASK-A007`, `TASK-A015` |
| Medium | contract debt | Полное автоматическое сопоставление route list с OpenAPI/contract drift gate отсутствует. | `TASK-A014` |
| Medium | operations debt | Общий lifecycle queue/import/storage и recovery/visibility требуют системной проверки. | `TASK-A013` |
| Planned | intentional | Category images, brand logos and documents не реализованы: `image_id`/`logo_id` — неэкспонируемые placeholders. | Phase 7, `TASK-096` |
| Planned | intentional | Managed SEO metadata, canonical, sitemap, robots, redirects and structured data не реализованы. | Phase 8, `TASK-100`–`105` |
| Planned | intentional | Public catalog, cart and checkout UI не реализованы; public API предназначен для Nuxt Client. | Phase 10, `TASK-120`–`128` |
| Planned | intentional | Sales analytics and production backup/monitoring/deployment are not implemented. | Phases 9 and 11 |
| Deferred safely | legacy data | `product_variants` and variant attribute values remain read-only until verified reconciliation and an opt-in deletion migration. | `TASK-A010`, separate migration |

## Вердикт

Backend contracts Phases 0–6 имеют подтверждённую основу, но Interim Audit **не принят** для
перехода к Phase 7. До следующей приёмки необходимо закрыть TASK-A015, TASK-A018 и TASK-A019;
остальные пункты A003–A020 сохраняют свои назначенные границы. High/critical regression в уже
прошедшем backend suite не выявлено.
