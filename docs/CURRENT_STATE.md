# Текущее состояние проекта

Состояние актуализировано по итогам `TASK-A001`–`TASK-A041`, инженерной редакции `TASK-A043`,
повторной финальной приёмки `TASK-A030` (2026-09-13) и повторной оценки Interim Audit
(2026-09-14). Admin frontend
refactoring принят. Repository-side remediation `TASK-A042` завершён, а server-side очистка
GitHub cached views/PR refs ожидается по открытому запросу Support `#4756780`; остальные
security/data-lifecycle, архитектурные и quality-gate follow-ups `TASK-A043`–`TASK-A055` открыты,
кроме завершённой `TASK-A045`.
Для `TASK-A043` подготовлены ADR, threat model и retention/deletion matrix; `TASK-A044` реализует
gated bounded controls, evidence и restore replay. Юридическое принятие ещё не зафиксировано,
поэтому production-очистка остаётся запрещённой.
Evidence приведены в
[`INTERIM_AUDIT_FINAL.md`](INTERIM_AUDIT_FINAL.md) и
[`ADMIN_REFACTORING_BASELINE.md`](ADMIN_REFACTORING_BASELINE.md).

## Реализовано

| Область | Фактическое состояние |
| --- | --- |
| Foundation | Laravel API-only, Vue Admin, Nuxt Client skeleton, Docker Compose, PostgreSQL, Redis, queue, scheduler, versioned `/api/v1`, stable error envelope, OpenAPI. |
| Access control | Admin authentication, password reset, active-user guard, RBAC, granular permissions, audit log, snapshots, retention и PostgreSQL immutability. |
| Catalog | Categories, brands, attribute groups/typed attributes, standalone sellable products, product groups, generated immutable SKU, images, related products, filters/search и durable storage cleanup. Legacy variants остаются read-only до отдельной verified migration. |
| Import/export | XLSX export/import, category templates, preflight/error reports, resumable queue work, ZIP image import, price/status и product-group workbooks. |
| Cart and orders | Public guest cart with HMAC-only bearer lookup, configurable TTL and lock-safe cleanup; locked checkout, immutable order snapshots, random order number, status/payment management, history, internal comments и confirmation email. |
| Contacts | Public callback/email/partner forms, assignment, protected list/detail API, statuses, history и internal comments. |
| Admin frontend | Route views и feature-слои выделены, временные compatibility adapters удалены. `TASK-A031`–`TASK-A037` закрыли findings повторной приёмки и post-acceptance UI contracts; `TASK-A038` вынесла orchestration импортов в feature composables; `TASK-A039` добавила blocking ESLint/Prettier gates, отключила Playwright retries и стабилизировала date-dependent visual test; `TASK-A040` сделала `/ui-kit` полным живым каталогом компонентов и design tokens; `TASK-A041` восстановила независимую desktop-прокрутку sidebar. Повторная финальная приёмка `TASK-A030` пройдена. |

## Принятые границы

- Laravel — единственное бизнес-ядро для Admin и будущего Client; Blade и server-rendered storefront не используются.
- Товар — самостоятельная продаваемая позиция. Product group служит навигации между товарами и не является nested variant.
- Публичные формы, cart и checkout не принимают client-owned prices, totals, statuses или workflow fields.
- PII минимизируется и не попадает в обычные application logs или audit metadata.
- OpenAPI — машинный контракт; [`API.md`](API.md) описывает только сценарии и нетривиальные правила.

## Ограничения и обязательные follow-ups

| Приоритет | Ограничение | Владелец |
| --- | --- | --- |
| Critical | Публичные branches/tags очищены от четырёх PostgreSQL dumps, локальные auth/session credentials инвалидированы и prevention gates включены. Старые objects остаются достижимы через четыре скрытых GitHub PR refs до выполнения server-side purge по запросу Support `#4756780`; также ожидаются оставшиеся operational confirmations. | `TASK-A042` |
| High | Для business PII orders/contacts подготовлена policy и gated repository-side controls, но отсутствуют подписи ответственного за ПДн/юриста и external provider evidence; production apply выключен. | `TASK-A043`, `TASK-A044` |
| Resolved | Guest cart tokens хранятся только как HMAC; configurable TTL и lock-safe bounded cleanup реализованы. | `TASK-A045` |
| Medium | OpenAPI compatibility, PostgreSQL/Redis и real Admin→API boundaries покрыты слабее, чем следует из исторической формулировки приёмки. | `TASK-A046`, `TASK-A048`–`TASK-A050` |
| Medium | Persisted Compose dependency volumes могут не соответствовать lock-файлам. | `TASK-A047` |
| Medium | Import services совмещают workbook I/O, parsing, validation, mutation и reporting; скрытые container dependencies и нетривиальные queries в Controllers ухудшают SOLID/читаемость. | `TASK-A052`–`TASK-A054` |
| Medium | Правила из `backend/AGENTS.md` пока не подкреплены автоматическим architecture CI gate. | `TASK-A055` |
| Low | Task ledger и исторические audit reports требуют нормализации. | `TASK-A051` |

Функциональная приёмка Phases 0–6 и Admin frontend refactoring сохраняется. До подтверждения
server-side purge и полного закрытия `TASK-A042` публикация несвязанных изменений приостановлена;
остальные follow-ups выполняются до соответствующих зависимых фаз и не означают переделку всех
принятых модулей.

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
evidence. Findings устранены в `TASK-A031`–`TASK-A035`; повторная приёмка `TASK-A030` подтвердила
35 unit и 145 E2E/axe/visual тестов локально и 145/145 в Linux Compose. Независимый UI Design Guard
не оставил blocking findings. `TASK-A020` и `TASK-A030` приняты. Подробности — в
[`INTERIM_AUDIT_FINAL.md`](INTERIM_AUDIT_FINAL.md) и
[`ADMIN_REFACTORING_BASELINE.md`](ADMIN_REFACTORING_BASELINE.md).

Post-acceptance follow-up `TASK-A036` синхронизировал фактическую архитектуру с итогом `TASK-A030`:
админка использует backend-названия ролей и больше не содержит employee-service compatibility alias.
Production build, 37 unit-тестов и 146 E2E/axe/visual тестов прошли; UI Design Guard одобрил
поведение на контрольных ширинах 320/640/768/1024/1280 px без замечаний.

`TASK-A037` синхронизировала native disabled-состояние `UiInput`, `UiSelect` и `UiDatePicker` с их
вспомогательными clear/menu actions, сохранила Escape/focus return и закрепила адаптивный calendar
popup. Production build, 41 unit-тест и 147 E2E/axe/visual тестов прошли локально и в Linux Compose;
disabled и открытый calendar визуально проверены на 320/640/768/1024/1280 px в Darwin/Linux;
независимый UI Design Guard не оставил blocking или non-blocking findings.

`TASK-A038` перенесла orchestration трёх товарных import dialogs в feature composables и validation.
`TASK-A039` зафиксировала ESLint/Prettier baseline, добавила blocking lint/format checks в CI,
отключила Playwright retries и устранила зависимость calendar snapshot от текущего дня. Два
последовательных локальных Admin suite прошли по 47 unit и 148 E2E/axe/visual тестов; Linux Compose
suite прошёл 148/148.

`TASK-A040` дополнила `/ui-kit` до полного живого каталога 16 `Ui*` и трёх shared-компонентов,
их значимых состояний и всех CSS design tokens. Полнота закреплена unit/E2E, axe, responsive и
visual-проверками: 50 unit и 149 E2E/axe/visual тестов прошли локально и в Linux Compose;
независимый UI Design Guard не оставил замечаний.

`TASK-A041` восстановила desktop-прокрутку sidebar на экранах с небольшой высотой и исключила
scroll chaining в основную страницу. Адресный сценарий проверяет viewport 1280×480, hover + wheel,
достижимость нижней навигации и переход; 50 unit и 150 E2E/axe/visual тестов прошли локально и в
Linux Compose. Независимый UI Design Guard одобрил результат.
