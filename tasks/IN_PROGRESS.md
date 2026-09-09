# IN PROGRESS

## Interim Audit Phases 0–6

- [ ] TASK-A007 Ввести измеримый baseline качества Laravel backend
  - Выполнены проверка структуры, namespaces, синтаксиса, Composer dependencies и Laravel conventions.
  - Larastan/PHPStan level 8 настроен без baseline и подавлений; initial run выявил более 1,000
    существующих type errors. Подробности и условия включения blocking CI gate — в
    [`docs/QUALITY_BASELINE.md`](../docs/QUALITY_BASELINE.md).
  - Для закрытия необходимы addressable исправления в A008–A010; task остаётся active,
    чтобы не маскировать незакрытый quality debt.

- [ ] TASK-A008 Нормализовать HTTP/API-слой Laravel
  - Audit completed: [`docs/HTTP_API_AUDIT.md`](../docs/HTTP_API_AUDIT.md) documents route,
    Form Request, Resource, policy/error-envelope and OpenAPI checks.
  - The strict scan reports 546 HTTP-layer errors. Typed authenticated-user and validated-input
    boundaries are required before this task can close without ignores or a contract regression.
  - Duplicated import controller lifecycle is deliberately assigned to A009/A013, where its
    transaction, retry and storage guarantees can be changed as one operation.

- [ ] TASK-A009 Нормализовать business services и module boundaries
  - Audit completed: [`docs/SERVICE_BOUNDARIES_AUDIT.md`](../docs/SERVICE_BOUNDARIES_AUDIT.md).
  - Catalog/order/contact/auth/audit transactions are preserved; import submission and async
    orchestration remain duplicated and require one explicit lifecycle/outbox application service.
  - Checkout idempotency has no public contract yet and requires a documented API/OpenAPI decision;
    task remains active until both findings are resolved with A013 safeguards.

- [ ] TASK-A010 Проверить модели, БД и целостность данных
  - Audit completed: [`docs/MODEL_DATABASE_AUDIT.md`](../docs/MODEL_DATABASE_AUDIT.md).
  - Model/factory static declarations need correction; `User::hasPermission()` needs an explicit
    model-versus-authorization-service boundary decision with A009/A012.
  - PostgreSQL checks remain unverified locally because `pdo_pgsql` is unavailable; run them only
    against CI-only `agatceramic_test` under TASK-A015. Legacy variants remain conversion-only.
