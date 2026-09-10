# DONE

Компактный индекс завершённых работ. Детальные implementation logs воспроизводимы из Git;
долгосрочные решения находятся в [`docs/DECISIONS.md`](../docs/DECISIONS.md),
operational/recovery rules — в [`docs/`](../docs/).

| Scope | Завершённые задачи | Проверяемый итог |
| --- | --- | --- |
| Phase 0 | TASK-001–007 | Монорепозиторий, Laravel API-only, Vue Admin, Nuxt Client skeleton, Docker, environment templates и CI. |
| Phase 1 | TASK-010–017 | PostgreSQL/Redis, `/api/v1`, errors/resources, PII-safe logging, queue/scheduler и OpenAPI. |
| Phase 2–2.1 | TASK-020–029C | Admin auth, password reset, RBAC, policies, audit snapshots/retention и PostgreSQL immutability. |
| Phase 3 | TASK-030–041U | Catalog CRUD, typed attributes, images/relations/search, API/Admin hardening, durable cleanup и concurrency coverage. |
| Phase 3.1 | TASK-042A–042Z | Standalone products, product groups, generated SKU, legacy migration lifecycle и Admin product workflow. |
| Phase 4 | TASK-050–058 | XLSX export/import, preflight/errors, resumable queue, ZIP images, price/status и group workbooks. |
| Phase 5 | TASK-060–070 | Guest cart, locked checkout, order snapshots/number/status/payment/history/comments и email confirmation. |
| Phase 6 | TASK-080–084 | Public contact requests и protected assignment/workflow API. |
| Interim Audit | TASK-A001–A004 | Baseline, repeat acceptance, documentation architecture и current-state map. |
| Registry | TASK-A005 | Active registry normalized: TODO contains only unfinished roadmap, IN_PROGRESS only current work, and this index retains completed scopes. |
| Artifact hygiene | TASK-A006 | Removed obsolete audit archive, empty `.tmp/` and generated Playwright output; recovery materials retained separately. |
| Interim Audit | TASK-A007 | Larastan/PHPStan level 8 passes without baseline or suppressions (333 files, 0 errors) and is a blocking backend CI step. See [`QUALITY_BASELINE.md`](../docs/QUALITY_BASELINE.md). |
| Interim Audit | TASK-A008 | HTTP controller boundaries now supply typed authenticated administrators, uploaded files and validated scalar/structured inputs to application services. Controller PHPStan passes with zero errors; no API contract changed. See [`HTTP_API_AUDIT.md`](../docs/HTTP_API_AUDIT.md). |
| Interim Audit | TASK-A009 | Import submission, durable dispatch/retry and XLSX/ZIP lifecycle are centralized; checkout has a 24-hour HMAC-only idempotency contract. See [`SERVICE_BOUNDARIES_AUDIT.md`](../docs/SERVICE_BOUNDARIES_AUDIT.md) and [`OPENAPI_MIGRATION_PLAN.md`](../docs/OPENAPI_MIGRATION_PLAN.md). |
| Interim Audit | TASK-A010 | Model/factory declarations and relation generics are strict-clean; authorization queries live in `PermissionChecker`; PostgreSQL migrations and integrity/concurrency suites passed on `agatceramic_test`. See [`MODEL_DATABASE_AUDIT.md`](../docs/MODEL_DATABASE_AUDIT.md). |
| Interim Audit | TASK-A011 | Laravel API-only and Admin/Client boundaries confirmed; see [`API_ONLY_BOUNDARIES_AUDIT.md`](../docs/API_ONLY_BOUNDARIES_AUDIT.md). |
| Interim Audit | TASK-A012 | Security, audit and PII controls reviewed; no high/critical Phase 0–6 application finding. See [`SECURITY_PII_AUDIT.md`](../docs/SECURITY_PII_AUDIT.md). |
| Interim Audit | TASK-A013 | Queues, imports and durable storage cleanup reviewed; stale processing recovery and terminal cleanup atomicity added. See [`ASYNC_STORAGE_AUDIT.md`](../docs/ASYNC_STORAGE_AUDIT.md). |
| Interim Audit | TASK-A014 | OpenAPI and API guide reconciled with all 111 Phase 0–6 HTTP operations; route coverage and versioned compatibility gates added. See [`OPENAPI_CONTRACT_AUDIT.md`](../docs/OPENAPI_CONTRACT_AUDIT.md). |
| Interim Audit | TASK-A015 | CI separates SQLite, PostgreSQL integration, contract and Admin unit/E2E checks; Admin E2E uses production preview and the local PostgreSQL runner is restricted to `agatceramic_test`. See [`CI.md`](../docs/CI.md). |
| Interim Audit | TASK-A016 | Backend API → Admin UI matrix is complete. Catalog, access-control, authentication and audit operations are mapped; orders and contacts have explicit high-priority follow-ups (`TASK-A018`, `TASK-A019`), while Phase 7–9 domains remain intentionally deferred. See [`ADMIN_API_UI_MATRIX.md`](../docs/ADMIN_API_UI_MATRIX.md). |
| Interim Audit | TASK-A017 | Admin UI-kit states and destructive confirmations are normalized with accessible shared feedback components; keyboard, responsive and E2E checks passed, and the final independent UI Guard review found no blocking issue. See [`ADMIN_UI_KIT_AUDIT.md`](../docs/ADMIN_UI_KIT_AUDIT.md). |

## Проверки последней приёмки

`TASK-A002`: backend 226 tests / 1608 assertions, Pint, Composer validation/audit, OpenAPI JSON,
Admin unit tests (25), Admin/Client builds passed. Полный Admin E2E: 47 passed и 4 environment
failures; follow-up остаётся `TASK-A015`.
