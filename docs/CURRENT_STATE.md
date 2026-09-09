# Текущее состояние проекта

Состояние зафиксировано по итогам `TASK-A001` и `TASK-A002` (2026-09-09).
Краткий результат последней приёмки приведён в [`../tasks/DONE.md`](../tasks/DONE.md).

## Реализовано

| Область | Фактическое состояние |
| --- | --- |
| Foundation | Laravel API-only, Vue Admin, Nuxt Client skeleton, Docker Compose, PostgreSQL, Redis, queue, scheduler, versioned `/api/v1`, stable error envelope, OpenAPI. |
| Access control | Admin authentication, password reset, active-user guard, RBAC, granular permissions, audit log, snapshots, retention и PostgreSQL immutability. |
| Catalog | Categories, brands, attribute groups/typed attributes, standalone sellable products, product groups, generated immutable SKU, images, related products, filters/search и durable storage cleanup. Legacy variants остаются read-only до отдельной verified migration. |
| Import/export | XLSX export/import, category templates, preflight/error reports, resumable queue work, ZIP image import, price/status и product-group workbooks. |
| Cart and orders | Public guest cart, locked checkout, immutable order snapshots, random order number, status/payment management, history, internal comments и confirmation email. |
| Contacts | Public callback/email/partner forms, assignment, protected list/detail API, statuses, history и internal comments. |

## Принятые границы

- Laravel — единственное бизнес-ядро для Admin и будущего Client; Blade и server-rendered storefront не используются.
- Товар — самостоятельная продаваемая позиция. Product group служит навигации между товарами и не является nested variant.
- Публичные формы, cart и checkout не принимают client-owned prices, totals, statuses или workflow fields.
- PII минимизируется и не попадает в обычные application logs или audit metadata.
- OpenAPI — машинный контракт; [`API.md`](API.md) описывает только сценарии и нетривиальные правила.

## Ограничения и обязательные follow-ups

| Приоритет | Ограничение | Владелец |
| --- | --- | --- |
| High | Admin не имеет order list/detail: `/orders` остаётся placeholder. | `TASK-A018` |
| High | Admin не имеет contacts navigation и manager workspace. | `TASK-A019` |
| Medium | Admin E2E нестабилен из-за временного Vite server; Compose Admin не содержит dev dependencies для unit/build/e2e. | `TASK-A015` |
| Medium | Нет документированного безопасного локального запуска PostgreSQL integration suite против CI-only test DB. | `TASK-A015` |
| Medium | OpenAPI compatibility gate requires a reviewed `info.version` bump and migration plan for breaking contract changes. | Ongoing API governance |
| Medium | Larastan/PHPStan strict gate обнаружил более 1,000 type errors и пока не может быть включён как blocking CI check без baseline/ignore rules. | `TASK-A007` with A008–A010 |
| Medium | HTTP API audit confirms typed authenticated-user/request boundaries remain incomplete; four import controllers also duplicate async file lifecycle. | `TASK-A008`, `TASK-A009` |
| Medium | Import submission/job orchestration has no single lifecycle boundary; checkout has concurrency safety but no explicit idempotency key contract. | `TASK-A009` |
| Medium | Model/factory strict declarations need completion; local PHP lacks `pdo_pgsql`, so PostgreSQL migration/integrity verification remains pending CI-only test DB execution. | `TASK-A010`, `TASK-A015` |

Interim Audit не разрешает переход к Phase 7 до закрытия этих high-priority functional gaps и
quality gates.

## Отложено по roadmap

| Phase | Не реализовано | Зависимость |
| --- | --- | --- |
| 7 — Content | Site settings, pages, banners, stores, working hours, managed Media Library. | Media заменит Catalog placeholders `categories.image_id` и `brands.logo_id`. |
| 8 — SEO | Managed metadata, canonical, sitemap, robots, redirects, structured data и AI drafts. | SEO использует managed media для OG image; slug остаётся Catalog-owned. |
| 9 — Analytics | Orders/paid sales dashboards и reports. | Использует order `paid_at`, не только `created_at`. |
| 10 — Client | Public catalog/category/product pages, cart, checkout, confirmation и SEO implementation. | Использует существующие API contracts без дублирования business logic. |
| 11 — Production | Production Compose, CI/CD delivery, backups, monitoring, security hardening и deployment. | Требует завершённых operational policies. |

## Проверки baseline

`php artisan test --compact`: 226 tests, 1608 assertions; Pint, Composer validation/audit,
OpenAPI JSON, Admin unit tests (25), Admin/Client builds прошли. Полный Admin E2E запуск
зафиксировал 47 passed и 4 environment failures; это не считается принятым quality gate.
