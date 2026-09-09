# TODO

Здесь находится только актуальный roadmap. Текущая задача — в `IN_PROGRESS.md`,
завершённые результаты — в `DONE.md`; подробная история воспроизводима из Git, ADR,
canonical documentation и audit reports.

## Interim Audit Phases 0–6

Переход к Phase 7 запрещён до завершения этого блока. Refactoring не меняет подтверждённый
API behaviour без migration plan и синхронного обновления OpenAPI.

- [ ] TASK-A015 Перестроить тестовую пирамиду и CI quality gates
  - Разделить unit/feature/integration/contract/e2e, закрыть negative/permission/concurrency cases
    и исключить flaky tests.
  - Сделать Admin E2E устойчивым к temporary Vite server и снабдить Compose Admin dev dependencies.
  - Автоматизировать safe local PostgreSQL integration run только против CI-only `agatceramic_test`.

- [ ] TASK-A016 Составить и закрыть матрицу Backend API → Admin UI
  - Для каждого Admin endpoint/permission указать screen, actions и loading/empty/error/forbidden states.
  - Пометить backend-only features, намеренно ожидающие Phase 7–10, и создать адресные follow-ups.

- [ ] TASK-A017 Нормализовать Admin UI-kit перед расширением панели
  - Инвентаризировать shared tokens/components и привести buttons, fields, dialogs, tables,
    pagination, states и destructive confirmations к одному accessible pattern.
  - Проверить keyboard/focus, screen readers, contrast и 320/640/768/1024/1280 px; получить UI Guard review.

- [ ] TASK-A018 Реализовать рабочее место менеджера по заказам
  - Добавить permission-safe order list/show API и заменить `/orders` placeholder на list/detail,
    snapshots, contacts/delivery, transitions/history, payments и comments.

- [ ] TASK-A019 Реализовать рабочее место менеджера по обращениям
  - Добавить contacts navigation и list/detail для callback, email и partner requests с filters,
    assignment, statuses, history и comments under `contacts.view/manage`.

- [ ] TASK-A020 Провести финальную регрессию Interim Audit и разрешить переход к Phase 7
  - Повторить clean-environment backend/Admin/Compose, PostgreSQL concurrency, Redis/queue,
    import/export, OpenAPI и key end-to-end scenarios.
  - Подтвердить отсутствие undocumented gaps, high/critical findings, stale links и temporary files.

## Phase 7 — Content

- [ ] TASK-090 Site settings
- [ ] TASK-091 Pages
- [ ] TASK-092 Banners
- [ ] TASK-093 Sliders
- [ ] TASK-094 Stores
- [ ] TASK-095 Working hours
- [ ] TASK-096 Media library
  - Deliver category images, brand logos and documents through managed media references; reconcile
    every pre-existing non-null placeholder before adding foreign keys.

## Phase 8 — SEO

- [ ] TASK-100 SEO metadata
  - Create a separate managed SEO layer for products, categories and brands; do not duplicate values
    in Catalog tables.
- [ ] TASK-101 Canonical for indexable entities
- [ ] TASK-102 Sitemap generation
- [ ] TASK-103 Robots metadata/directives and robots.txt
- [ ] TASK-104 Redirects, including Catalog slug changes
- [ ] TASK-105 Structured data
- [ ] TASK-106 SEO AI draft generation

## Phase 9 — Analytics

- [ ] TASK-110 Orders dashboard
- [ ] TASK-111 Paid sales dashboard
- [ ] TASK-112 Monthly reports
- [ ] TASK-113 Category/brand/product sales
- [ ] TASK-114 Average order value

## Phase 10 — Client

- [ ] TASK-120 Catalog pages
- [ ] TASK-121 Category pages
- [ ] TASK-122 Product pages
- [ ] TASK-123 Cart
- [ ] TASK-124 Checkout
- [ ] TASK-125 Order confirmation
- [ ] TASK-126 SEO implementation
- [ ] TASK-127 Structured data
- [ ] TASK-128 Performance optimization

## Phase 11 — Production

- [ ] TASK-140 Production Docker
- [ ] TASK-141 CI/CD
- [ ] TASK-142 Backups
- [ ] TASK-143 Monitoring
- [ ] TASK-144 Security hardening
- [ ] TASK-145 Production deployment
