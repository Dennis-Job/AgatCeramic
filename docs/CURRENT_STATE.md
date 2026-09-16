# Текущее состояние проекта

Документ отражает состояние после завершения `TASK-A054` 2026-09-16. Исторические результаты
аудитов и их числовые срезы не обновляются задним числом; текущий набор обязательных проверок
описан в [`CI.md`](CI.md), завершённые задачи — в [`tasks/DONE.md`](../tasks/DONE.md).

Admin frontend refactoring принят. Follow-up проверки `TASK-A045`–`TASK-A050` закрыли cart-token,
OpenAPI, dependency bootstrap, PostgreSQL feature-suite, Redis delivery и Admin full-stack gaps.
Security/data-lifecycle задачи `TASK-A042`–`TASK-A044` остаются в работе. Импортный application
layer декомпозирован, production dependencies сделаны явными, а сложные admin read queries
вынесены из Controllers; в roadmap остаётся architecture enforcement `TASK-A055`.

## Реализовано

| Область | Фактическое состояние |
| --- | --- |
| Foundation | Laravel API-only, Vue Admin, Nuxt Client skeleton, Docker Compose, PostgreSQL, Redis, queue, scheduler, versioned `/api/v1`, stable error envelope и OpenAPI. |
| Backend architecture | Нетривиальные admin filters/search/sort, eager loading и audit metadata enrichment изолированы в Query objects; основные equality-filter/sort paths подкреплены PostgreSQL indexes. Простые локальные CRUD reads остаются в Controllers. |
| Access control | Admin authentication, password reset, active-user guard, RBAC, granular permissions, audit log, snapshots, retention и PostgreSQL immutability. |
| Catalog | Categories, brands, attribute groups/typed attributes, standalone sellable products, product groups, generated immutable SKU, images, related products, filters/search и durable storage cleanup. Legacy variants остаются read-only до отдельной verified migration. |
| Import/export | XLSX export/import, category templates, preflight/error reports, resumable queue work, ZIP image import, price/status и product-group workbooks. Workbook I/O, parsing/validation, planning, mutation и report/template generation разделены на небольшие сервисы; orchestration сохраняет прежние transaction, locking и checkpoint contracts. Queue entry points получают обязательные сервисы через container injection; только прямые Laravel `failed()` callbacks используют документированный узкий adapter. |
| Cart and orders | Public guest cart with HMAC-only bearer lookup, configurable TTL and lock-safe cleanup; locked checkout, immutable order snapshots, random order number, status/payment management, history, internal comments и confirmation email. |
| Contacts | Public callback/email/partner forms, assignment, protected list/detail API, statuses, history и internal comments. |
| Admin frontend | Route views и feature-слои выделены, compatibility adapters удалены, source-of-truth UI-kit и обязательные lint, unit, production E2E, accessibility, responsive и visual gates действуют. Orders и contacts имеют рабочие list/detail/workflow экраны. |
| Production-like проверки | Полный Laravel feature suite выполняется на PostgreSQL; отдельные CI profiles проверяют реальную Redis queue delivery, Admin SPA → Sanctum/Laravel API и lock-aware bootstrap dependency volumes. |

## Принятые границы

- Laravel — единственное бизнес-ядро для Admin и будущего Client; Blade и server-rendered
  storefront не используются.
- Товар — самостоятельная продаваемая позиция. Product group служит навигации между товарами и
  не является nested variant.
- Публичные формы, cart и checkout не принимают client-owned prices, totals, statuses или
  workflow fields.
- PII минимизируется и не попадает в обычные application logs или audit metadata.
- OpenAPI — машинный контракт; [`API.md`](API.md) описывает только сценарии и нетривиальные правила.

## Ограничения и обязательные follow-ups

| Приоритет | Ограничение | Владелец |
| --- | --- | --- |
| Critical | Публичные branches/tags очищены от четырёх PostgreSQL dumps, локальные auth/session credentials инвалидированы и prevention gates включены. Старые objects остаются достижимы через скрытые GitHub PR refs до server-side purge по запросу Support `#4756780`; также ожидаются operational confirmations. | `TASK-A042` |
| High | Для business PII orders/contacts подготовлены policy и gated repository-side controls, но отсутствуют подписи ответственного за ПДн/юриста и external provider evidence; production apply выключен. | `TASK-A043`, `TASK-A044` |
| Medium | Правила из `backend/AGENTS.md` пока не подкреплены автоматическим architecture CI gate. | `TASK-A055` |

Функциональная приёмка Phases 0–6 и Admin frontend refactoring сохраняется. До подтверждения
server-side purge и полного закрытия `TASK-A042` публикация несвязанных изменений приостановлена.
Подробный текущий статус активных работ находится в
[`tasks/IN_PROGRESS.md`](../tasks/IN_PROGRESS.md).

## Отложено по roadmap

| Phase | Не реализовано | Зависимость |
| --- | --- | --- |
| 7 — Content | Site settings, pages, banners, stores, working hours, managed Media Library. | Media заменит Catalog placeholders `categories.image_id` и `brands.logo_id`. |
| 8 — SEO | Managed metadata, canonical, sitemap, robots, redirects, structured data и AI drafts. | SEO использует managed media для OG image; slug остаётся Catalog-owned. |
| 9 — Analytics | Orders/paid sales dashboards и reports. | Использует order `paid_at`, не только `created_at`. |
| 10 — Client | Public catalog/category/product pages, cart, checkout, confirmation и SEO implementation. | Использует существующие API contracts без дублирования business logic. |
| 11 — Production | Production Compose, CI/CD delivery, backups, monitoring, security hardening и deployment. | Требует завершённых operational policies. |

## Проверки и историческое evidence

Актуальные обязательные jobs, локальные команды и safety guards перечислены только в
[`CI.md`](CI.md). Количество маршрутов, файлов, тестов и assertions намеренно не копируется сюда:
оно изменяется вместе с кодом и подтверждается самим CI run.

Исторические снимки приёмки сохранены без ретроспективной подмены результатов:

- [`INTERIM_AUDIT_FINAL.md`](INTERIM_AUDIT_FINAL.md) — приёмка Phases 0–6 и последующие findings;
- [`ADMIN_REFACTORING_BASELINE.md`](ADMIN_REFACTORING_BASELINE.md) — baseline и повторная приёмка
  Admin refactoring;
- специализированные `*_AUDIT.md` — evidence конкретного слоя на указанную в них дату.
