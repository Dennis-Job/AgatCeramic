# Текущее состояние проекта

Состояние зафиксировано по итогам `TASK-A001`–`TASK-A029` и повторного аудита `TASK-A030`
(2026-09-12). Промежуточная приёмка Admin frontend refactoring сохранена, но финальная приёмка
повторно открыта; evidence приведены в
[`INTERIM_AUDIT_FINAL.md`](INTERIM_AUDIT_FINAL.md) и
[`ADMIN_REFACTORING_BASELINE.md`](ADMIN_REFACTORING_BASELINE.md).

## Реализовано

| Область | Фактическое состояние |
| --- | --- |
| Foundation | Laravel API-only, Vue Admin, Nuxt Client skeleton, Docker Compose, PostgreSQL, Redis, queue, scheduler, versioned `/api/v1`, stable error envelope, OpenAPI. |
| Access control | Admin authentication, password reset, active-user guard, RBAC, granular permissions, audit log, snapshots, retention и PostgreSQL immutability. |
| Catalog | Categories, brands, attribute groups/typed attributes, standalone sellable products, product groups, generated immutable SKU, images, related products, filters/search и durable storage cleanup. Legacy variants остаются read-only до отдельной verified migration. |
| Import/export | XLSX export/import, category templates, preflight/error reports, resumable queue work, ZIP image import, price/status и product-group workbooks. |
| Cart and orders | Public guest cart, locked checkout, immutable order snapshots, random order number, status/payment management, history, internal comments и confirmation email. |
| Contacts | Public callback/email/partner forms, assignment, protected list/detail API, statuses, history и internal comments. |
| Admin frontend | Route views и feature-слои в основном выделены, временные compatibility adapters удалены. Повторный аудит выявил обязательные follow-ups по mobile sidebar, контрасту/tokens, products/auth migration и acceptance suite. |

## Принятые границы

- Laravel — единственное бизнес-ядро для Admin и будущего Client; Blade и server-rendered storefront не используются.
- Товар — самостоятельная продаваемая позиция. Product group служит навигации между товарами и не является nested variant.
- Публичные формы, cart и checkout не принимают client-owned prices, totals, statuses или workflow fields.
- PII минимизируется и не попадает в обычные application logs или audit metadata.
- OpenAPI — машинный контракт; [`API.md`](API.md) описывает только сценарии и нетривиальные правила.

## Ограничения и обязательные follow-ups

| Приоритет | Ограничение | Владелец |
| --- | --- | --- |
| Medium | OpenAPI compatibility gate requires a reviewed `info.version` bump and migration plan for breaking contract changes. | Ongoing API governance |
| High | Финальная приёмка Admin frontend refactoring заблокирована до исправления findings повторного аудита. | `TASK-A031`–`TASK-A035`, затем `TASK-A030` |

Interim Audit завершён. Переход к Phase 7 заблокирован до повторной приёмки `TASK-A030`.

## Отложено по roadmap

| Phase | Не реализовано | Зависимость |
| --- | --- | --- |
| 7 — Content | Site settings, pages, banners, stores, working hours, managed Media Library. | Media заменит Catalog placeholders `categories.image_id` и `brands.logo_id`. |
| 8 — SEO | Managed metadata, canonical, sitemap, robots, redirects, structured data и AI drafts. | SEO использует managed media для OG image; slug остаётся Catalog-owned. |
| 9 — Analytics | Orders/paid sales dashboards и reports. | Использует order `paid_at`, не только `created_at`. |
| 10 — Client | Public catalog/category/product pages, cart, checkout, confirmation и SEO implementation. | Использует существующие API contracts без дублирования business logic. |
| 11 — Production | Production Compose, CI/CD delivery, backups, monitoring, security hardening и deployment. | Требует завершённых operational policies. |

## Проверки baseline

На 2026-09-11 прошли Composer validation/audit, Pint, Larastan, PostgreSQL migration/concurrency
(9 tests, 43 assertions), Redis/queue, OpenAPI JSON, Admin unit tests (27), Admin build и Client
typecheck/build. После TASK-A021 два последовательных полных backend suite прошли; после TASK-A022
clean-install local Admin E2E/axe run passed (58 tests). Исторический запуск 2026-09-12 также
показал build, 30 unit-тестов и 136 production E2E/visual/axe/responsive тестов локально и в Linux
Compose, но повторный аудит обнаружил blocking findings и недостаточную устойчивость части
evidence. `TASK-A020` принят; `TASK-A030` повторно открыт. Подробности — в
[`INTERIM_AUDIT_FINAL.md`](INTERIM_AUDIT_FINAL.md) и
[`ADMIN_REFACTORING_BASELINE.md`](ADMIN_REFACTORING_BASELINE.md).
