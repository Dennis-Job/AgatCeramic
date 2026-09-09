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
| Interim Audit | TASK-A011 | Laravel API-only and Admin/Client boundaries confirmed; see [`API_ONLY_BOUNDARIES_AUDIT.md`](../docs/API_ONLY_BOUNDARIES_AUDIT.md). |
| Interim Audit | TASK-A012 | Security, audit and PII controls reviewed; no high/critical Phase 0–6 application finding. See [`SECURITY_PII_AUDIT.md`](../docs/SECURITY_PII_AUDIT.md). |
| Interim Audit | TASK-A013 | Queues, imports and durable storage cleanup reviewed; stale processing recovery and terminal cleanup atomicity added. See [`ASYNC_STORAGE_AUDIT.md`](../docs/ASYNC_STORAGE_AUDIT.md). |
| Interim Audit | TASK-A014 | OpenAPI and API guide reconciled with all 111 Phase 0–6 HTTP operations; route coverage and versioned compatibility gates added. See [`OPENAPI_CONTRACT_AUDIT.md`](../docs/OPENAPI_CONTRACT_AUDIT.md). |

## Проверки последней приёмки

`TASK-A002`: backend 226 tests / 1608 assertions, Pint, Composer validation/audit, OpenAPI JSON,
Admin unit tests (25), Admin/Client builds passed. Полный Admin E2E: 47 passed и 4 environment
failures; follow-up остаётся `TASK-A015`.
