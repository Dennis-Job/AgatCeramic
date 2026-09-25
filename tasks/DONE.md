# DONE

Компактный индекс завершённых работ. Детальные implementation logs воспроизводятся из Git;
долгосрочные решения находятся в [`docs/DECISIONS.md`](../docs/DECISIONS.md), актуальное состояние —
в [`docs/CURRENT_STATE.md`](../docs/CURRENT_STATE.md), operational/recovery rules — в
[`docs/`](../docs/). Исторические audit reports сохраняют evidence на дату проверки и не являются
источником текущих количественных метрик.

| Этап | Завершённые задачи | Проверяемый итог |
| --- | --- | --- |
| Foundation | TASK-001–017 | Монорепозиторий, Laravel API-only, Vue Admin, Nuxt Client, Docker Compose, PostgreSQL, Redis, queue, scheduler, versioned API и OpenAPI. |
| Access control | TASK-020–029C | Admin authentication, password reset, RBAC, policies, audit trail и неизменяемость журнала PostgreSQL. |
| Catalog | TASK-030–042Z | CRUD каталога, типизированные характеристики, standalone/grouped products, SKU, изображения, связи, поиск и защита API. |
| Import/export | TASK-050–058 | XLSX/ZIP import/export, preflight, error reports, resumable queue processing, цены, статусы и групповые файлы. |
| Cart, orders, contacts | TASK-060–084 | Гостевая корзина, защищённый checkout, заказы, email-подтверждение и публичные/административные обращения. |
| Документация и исходный аудит | TASK-A001–A006 | Зафиксированы состояние и границы Phases 0–6, канонические документы и task ledger; удалены воспроизводимые audit-артефакты. |
| Архитектура и приёмка Phases 0–6 | TASK-A007–A022 | Усилены backend/API/data/security/queue/OpenAPI/CI границы, реализованы Admin orders/contacts и пройдена итоговая регрессия. Историческое evidence: [`INTERIM_AUDIT_FINAL.md`](../docs/INTERIM_AUDIT_FINAL.md). |
| Admin refactoring | TASK-A023–A041 | Admin переведён на feature-слои и source-of-truth UI-kit, усилены accessibility/responsive/visual/lint gates и подтверждена повторная приёмка. Исторический baseline: [`ADMIN_REFACTORING_BASELINE.md`](../docs/ADMIN_REFACTORING_BASELINE.md). |
| Repository security incident | TASK-A042 | Database dumps удалены из текущего дерева, public branches и tags; credentials/sessions локальной test-базы инвалидированы, history/secret gates и encrypted restore runbook закреплены. Остаточная доступность исключительно тестовых объектов через четыре GitHub-managed PR refs принята владельцем без удаления PR. Evidence: [`SECURITY_INCIDENT_2026-09-14.md`](../docs/SECURITY_INCIDENT_2026-09-14.md). |
| Personal-data lifecycle policy | TASK-A043 | ADR-014, threat model и retention/deletion matrix приняты для реализации; персоналии, юридическая проверка и provider evidence сохранены как blocking pre-production gate `TASK-090`/`TASK-145`, а safe defaults не разрешают production apply. |
| Personal-data lifecycle controls | TASK-A044 | Реализованы bounded/idempotent retention commands для orders, contacts и technical storage, legal holds/exceptions, PII-safe evidence, versioned-HMAC tombstones, scheduler и synthetic restore replay; SQLite/PostgreSQL проверки подтверждают boundary, locking, rollback и immutability. Production apply остаётся закрыт gate `TASK-090`/`TASK-145`. |
| Production-like quality gates | TASK-A045–A050 | Защищены cart tokens и TTL; усилены OpenAPI compatibility, lock-aware dependency bootstrap, PostgreSQL feature suite, реальная Redis delivery и Admin full-stack smoke. Актуальные gates: [`CI.md`](../docs/CI.md). |
| Нормализация project ledger | TASK-A051 | `TODO` содержит только будущие работы, исторические audits явно отделены от текущего состояния, а CI проверяет внутренние Markdown-ссылки и запрещённые tracked artifacts. |
| Backend import architecture | TASK-A052 | Workbook I/O, parsing/validation, planning, mutation, template/error reports и cleanup разделены по ответственностям без изменения API, checkpoint/resume, transaction и locking contracts. |
| Явные backend dependencies | TASK-A053 | Jobs, bootstrap, validation, console и Controllers переведены на constructor/method injection; тесты Jobs используют container invocation, а неизбежный `failed()` adapter изолирован и протестирован. |
| Backend read layer | TASK-A054 | Многоусловные admin filters/search/sort и audit metadata enrichment вынесены в Query objects; eager loading, PostgreSQL indexes и feature-покрытие фильтров закреплены без изменения API-контрактов. |
| Backend architecture guard | TASK-A055 | PHP AST gate блокирует обратные зависимости слоёв, запрещённые DB/Eloquent/service-locator/helper операции в Controllers и stale allowlist; size/complexity findings публикуются для review. |
| Client homepage | TASK-119 | Первая SSR-главная на Nuxt: адаптивные editorial-секции по client UI-kit, локальные оптимизированные изображения, доступные меню/слайдер/tabs, навигация по разделам, canonical/OG/WebSite metadata. Товары и цены ожидают публичного catalog API. |
