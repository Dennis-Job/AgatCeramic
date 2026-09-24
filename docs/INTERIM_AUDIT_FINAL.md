# Исторический итоговый аудит Phases 0–6 (TASK-A020)

> **Статус:** исторический evidence-снимок с status-аннотациями по 2026-09-16. Указанные ниже
> количества маршрутов, файлов и тестов относятся только к датам соответствующих прогонов и не
> являются текущими метриками. Актуальное состояние описано в
> [`CURRENT_STATE.md`](CURRENT_STATE.md), действующие проверки — в [`CI.md`](CI.md).

## Повторная оценка 2026-09-14

Историческая функциональная приёмка `TASK-A020` сохраняется, но утверждение об отсутствии
незадокументированных security/data-lifecycle gaps больше не является актуальным. Repository-side
remediation выполнен. 2026-09-24 владелец подтвердил test-only характер дампов и принял остаточную
доступность четырёх GitHub-managed PR refs без удаления PR; `TASK-A042` закрыта. Активные follow-up задачи находятся в
[`tasks/IN_PROGRESS.md`](../tasks/IN_PROGRESS.md), будущие — в
[`tasks/TODO.md`](../tasks/TODO.md).

| Finding | Приоритет | Подтверждённое evidence | Follow-up |
| --- | --- | --- | --- |
| IA-R01 | Resolved — risk accepted | Четыре PostgreSQL dumps удалены из текущего дерева и public branches/tags переписанной истории; добавлены ignore/history/secret gates, выполнены локальная test auth invalidation и encrypted restore exercise. Владелец подтвердил test-only характер данных и принял остаточную доступность objects через `refs/pull/1/head`–`refs/pull/4/head`, отказавшись удалять PR №1–4. | `TASK-A042` |
| IA-R02 | High | `TASK-A043` приняла policy, threat model и retention/deletion matrix для реализации, а `TASK-A044` подготовила gated repository-side controls и synthetic restore evidence. До production activation остаются обязательные approvals и external provider evidence; apply выключен. | `TASK-A044`, `TASK-090`, `TASK-145` |
| IA-R03 | Resolved | `TASK-A045`: `carts.token_hash` хранит HMAC, а configurable empty/abandoned/checked-out TTL обслуживает bounded lock-safe scheduled cleanup. | `TASK-A045` |
| IA-R04 | Resolved | `TASK-A046`: закреплённый Redocly валидирует OpenAPI 3.1 semantics, а рекурсивный direction-aware checker с mutation fixtures отклоняет несовместимые request/response changes; breaking разрешён только major bump с явным migration plan. | `TASK-A046` |
| IA-R05 | Resolved | `TASK-A047`: dependency volumes сверяются с атомарным manifest/lock/runtime fingerprint; backend bootstrap сериализован, lock-файлы инициируют image rebuild, а clean/stale volume recovery проверяется в CI до запуска application processes. | `TASK-A047` |
| IA-R06 | Resolved | `TASK-A048`: все 229 backend feature tests запускаются в blocking CI job на отдельной PostgreSQL 17 database; SQLite сохранён как быстрый feedback. | `TASK-A048` |
| IA-R07 | Resolved | `TASK-A049`: отдельный blocking Compose/CI profile доставляет representative import, storage cleanup и order confirmation jobs через настоящую Redis queue отдельным workers; проверены after-commit, identifier-only payloads, retry/failure/recovery и идемпотентность. | `TASK-A049` |
| IA-R08 | Resolved | `TASK-A050`: отдельный blocking Compose/Playwright smoke проверяет production Admin, реальный Sanctum CSRF/login/logout, Laravel API, PostgreSQL/Redis, representative Catalog/orders/contacts mutations, permissions и стандартные error envelopes без `page.route()` mocks. | `TASK-A050` |
| IA-R09 | Resolved | `TASK-A051`: `TODO.md` содержит только будущие задачи, `DONE.md` сжат до индекса, исторические reports отделены от актуального состояния; CI проверяет Markdown links и запрещённые tracked artifacts. | `TASK-A051` |
| IA-R10 | Medium | Import application layer остаётся чрезмерно крупным и совмещает несколько причин изменения: `ProductImportService` — 931 строка, `ProductGroupImportService` — 665, `ProductImportTemplateService` — 465. В них одновременно находятся workbook I/O, parsing, validation, planning, mutation и reporting. | `TASK-A052` |
| IA-R11 | Medium | В import/jobs/bootstrap/validation flow сохраняется скрытый container resolution через `app()` и nullable service dependencies ради прямых legacy test invocations; несколько Controllers используют глобальный `request()` вместо явной типизированной зависимости. | `TASK-A053` |
| IA-R12 | Resolved | `TASK-A054`: многоусловные admin read queries и пакетный metadata/reference enrichment вынесены в Query objects; eager loading, PostgreSQL indexes и отдельное feature-покрытие фильтров добавлены без изменения API-контрактов. | `TASK-A054` |
| IA-R13 | Resolved | `TASK-A055`: PHP AST guard блокирует обратные зависимости слоёв, DB/Eloquent mutations и service locator/global request/auth helpers в Controllers; task-bound allowlist отклоняет некорректные и устаревшие записи, а size/complexity report включён в blocking backend CI step. | `TASK-A055` |

Повторная проверка не выявила причины отвергать реализованные Catalog, import/export, orders,
contacts или Admin workflows целиком. Findings `IA-R10`–`IA-R13` относятся к сопровождаемости и
будущему контролю архитектуры, а не к доказанному нарушению API behavior. Проверенный commit `e4cdbff`
прошёл все GitHub Actions jobs; локально прошли Admin lint, format, 50 unit tests и production
build. Зафиксированный тогда локальный backend failure был evidence `IA-R05`, поскольку
clean-install CI того же commit был успешен; lock-aware remediation выполнена в `TASK-A047`.

Дата аудита: 2026-09-11. Результат: **принято**; переход к Phase 7 разрешён.

Статусная аннотация от 2026-09-13: вердикт остаётся историческим результатом interim-проверки
Phases 0–6. Последующий аудит Admin refactoring повторно открыл `TASK-A030`; findings устранены в
`TASK-A031`–`TASK-A035`, повторная приёмка пройдена. Phase 7 разрешена.

## Исторические границы и evidence

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

## Результаты

| ID | Priority | Observation | Evidence | Owner |
| --- | --- | --- | --- | --- |
| A020-01 | Resolved | `ContactAssignmentTest` now checks the eligible active-user set independently from the name-sort contract. The controller and API behavior are unchanged; two consecutive full backend suites pass. | `backend/tests/Feature/Api/ContactAssignmentTest.php` | TASK-A021 |
| A020-02 | Resolved | The profile-based `admin-e2e` runner has Chromium system libraries, installs the matching browser in an isolated volume and completes the clean production Playwright/axe suite (58 tests). The Admin dev service and its dependency volume remain separate. | `infrastructure/docker/node/Dockerfile`; `compose.yaml` | TASK-A022 |

## Исторический вердикт

На момент аудита Phase 7 была **разрешена**. В границах Phases 0–6 не были подтверждены
незадокументированные gaps, high/critical finding уровня application security, устаревшая
внутренняя ссылка или tracked temporary artifact. Текущие ограничения перечислены только в
[`CURRENT_STATE.md`](CURRENT_STATE.md).
