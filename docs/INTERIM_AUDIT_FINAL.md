# Final Interim Audit — Phases 0–6 (TASK-A020)

## Повторная оценка 2026-09-14

Историческая функциональная приёмка `TASK-A020` сохраняется, но утверждение об отсутствии
незадокументированных security/data-lifecycle gaps больше не является актуальным. Repository-side
remediation выполнен; до подтверждения GitHub server-side purge по запросу Support `#4756780`
блокирующим остаётся `TASK-A042`. Остальные follow-up задачи добавлены в
[`tasks/TODO.md`](../tasks/TODO.md) и имеют собственные границы.

| Finding | Приоритет | Подтверждённое evidence | Follow-up |
| --- | --- | --- | --- |
| IA-R01 | Critical | Четыре PostgreSQL dumps удалены из текущего дерева и public branches/tags переписанной истории; добавлены ignore/history/secret gates, выполнены локальная auth invalidation и encrypted restore exercise. Fresh mirror всё ещё получает старые objects через `refs/pull/1/head`–`refs/pull/4/head`; server-side purge ожидается по GitHub Support `#4756780`. | `TASK-A042` |
| IA-R02 | High | Для orders, contacts, comments и workflow history отсутствует утверждённая retention/deletion matrix и исполняемый lifecycle. Реализован только срок audit logs и checkout idempotency keys. | `TASK-A043`, `TASK-A044` |
| IA-R03 | Medium | `carts.token` хранит raw bearer token; `GET /cart` создаёт persistent row, а scheduler не очищает пустые, брошенные или оформленные carts. | `TASK-A045` |
| IA-R04 | Medium | Compatibility script пропускает изменение типа request field без version bump и принимает удаление operation при любом изменении `info.version`, включая patch. Стандартного OpenAPI semantic validator нет. | `TASK-A046` |
| IA-R05 | Medium | Compose services считают зависимости актуальными по одному существующему файлу/бинарнику. Сохранённый backend volume стартовал без OpenSpout/PHPStan, из-за чего 42 tests упали после успешного запуска контейнера. | `TASK-A047` |
| IA-R06 | Medium | Большинство backend feature tests выполняется только на SQLite; PostgreSQL CI покрывает migration и две специализированные integration suites. | `TASK-A048` |
| IA-R07 | Medium | Redis CI проверяет connection/configuration, а jobs в feature tests fake-ятся или вызываются через `handle()`; реальная доставка отдельному worker не проверяется. | `TASK-A049` |
| IA-R08 | Medium | Все Admin Playwright suites подменяют API через `page.route()`. Отдельного browser smoke реального Sanctum/API contract нет. | `TASK-A050` |
| IA-R09 | Low | `TODO.md` содержит все завершённые audit/refactoring tasks вопреки итоговой записи `TASK-A005`; специализированные отчёты сохраняют устаревшие route/test counts и смешение языков. | `TASK-A051` |
| IA-R10 | Medium | Import application layer остаётся чрезмерно крупным и совмещает несколько причин изменения: `ProductImportService` — 931 строка, `ProductGroupImportService` — 665, `ProductImportTemplateService` — 465. В них одновременно находятся workbook I/O, parsing, validation, planning, mutation и reporting. | `TASK-A052` |
| IA-R11 | Medium | В import/jobs/bootstrap/validation flow сохраняется скрытый container resolution через `app()` и nullable service dependencies ради прямых legacy test invocations; несколько Controllers используют глобальный `request()` вместо явной типизированной зависимости. | `TASK-A053` |
| IA-R12 | Medium | `AuditLogController`, `OrderController`, `ContactRequestController` и `AdminUserController` содержат многоусловные read queries и metadata/reference enrichment, выходящие за границу тонкого Controller. | `TASK-A054` |
| IA-R13 | Medium | Larastan level 8 и Pint строго проверяются, но архитектурные границы SOLID/Laravel пока не являются автоматическим CI gate. Локальный стандарт добавлен в `backend/AGENTS.md`; enforcement остаётся отдельной работой. | `TASK-A055` |

Повторная проверка не выявила причины отвергать реализованные Catalog, import/export, orders,
contacts или Admin workflows целиком. Findings `IA-R10`–`IA-R13` относятся к сопровождаемости и
будущему контролю архитектуры, а не к доказанному нарушению API behavior. Текущий commit `e4cdbff`
прошёл все GitHub Actions jobs; локально прошли Admin lint, format, 50 unit tests и production
build. Локальный backend failure
классифицирован как evidence `IA-R05`, поскольку clean-install CI того же commit успешен.

Audit date: 2026-09-11. Result: **accepted**; the transition to Phase 7 is authorised.

Status note (updated 2026-09-13): this verdict remains the historical result for the Phase 0–6
interim scope. A later Admin refactoring audit reopened `TASK-A030`; its findings were resolved in
`TASK-A031`–`TASK-A035`, and the repeated acceptance passed. Phase 7 is authorised.

## Scope and evidence

- Clean Compose configuration validates successfully; backend, queue, scheduler, PostgreSQL,
  Redis, Admin and Client services start. PostgreSQL and Redis report `healthy`.
- Backend: Composer manifest and security audit pass; Pint checks 403 files; Larastan has
  0 errors across 335 files. After TASK-A021, two consecutive full SQLite suite runs pass.
- PostgreSQL: `migrate:fresh` ran only on `agatceramic_test`; audit-log immutability and
  catalog concurrency integration suites pass: 9 tests, 43 assertions.
- Redis/queue: cache read/write, Redis queue selection and queue-size health check pass;
  scheduler lists audit retention, storage cleanup, import retry and checkout-key pruning.
- OpenAPI JSON parses successfully; the backend contract route-coverage suite passes in both
  complete backend runs.
- Admin: `npm ci`, production dependency audit, 27 unit tests and production build pass.
  After TASK-A022, the isolated local Compose runner installs Chromium and completes the full
  production Playwright/axe suite: 58 tests pass.
- Client: after restoring the running service following a concurrent clean-install attempt,
  production dependency audit, Nuxt typecheck and production build pass.
- UI Design Guard independently checked unauthenticated route protection, `/login`,
  `/forgot-password`, visible keyboard focus and 320/640/768/1024/1280px login layouts.
  It found no blocking/high visual finding. Authenticated order/contact workspace states
  could not be inspected without a review account; the complete automated Admin E2E/axe suite
  provides the required regression coverage.
- Repository hygiene check found no tracked temporary/test-output artifacts. Local Markdown
  references resolve; no stale internal link was found.

## Findings

| ID | Priority | Observation | Evidence | Owner |
| --- | --- | --- | --- | --- |
| A020-01 | Resolved | `ContactAssignmentTest` now checks the eligible active-user set independently from the name-sort contract. The controller and API behavior are unchanged; two consecutive full backend suites pass. | `backend/tests/Feature/Api/ContactAssignmentTest.php` | TASK-A021 |
| A020-02 | Resolved | The profile-based `admin-e2e` runner has Chromium system libraries, installs the matching browser in an isolated volume and completes the clean production Playwright/axe suite (58 tests). The Admin dev service and its dependency volume remain separate. | `infrastructure/docker/node/Dockerfile`; `compose.yaml` | TASK-A022 |

## Historical verdict

At the time of this audit, Phase 7 was **authorised**. No undocumented gap, high/critical
application security finding, stale internal link or tracked temporary artifact was confirmed in
the Phase 0–6 scope. The current blocking status is recorded in the note above.
