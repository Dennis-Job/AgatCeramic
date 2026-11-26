# CI

GitHub Actions запускает workflow [`ci.yml`](../.github/workflows/ci.yml) для каждого pull request, push в `main` и ручного запуска.

Workflow получает только право `contents: read`. Для OpenAPI tooling, Admin и
Client в шагах `actions/setup-node` закреплён Node 24.19.0.

| Job | Проверки |
| --- | --- |
| Repository security | Внутренние Markdown-ссылки и tracked artifacts, проверка database dumps/backups в дереве и publication refs, Gitleaks scan полной истории с маскированием находок |
| Backend checks | Composer manifest и audit, OpenAPI 3.1 semantic/compatibility gates, Laravel Pint, architecture guard, Larastan, два прогона тестов на SQLite, миграции и integration tests на PostgreSQL 17, проверка Redis cache |
| Backend feature suite (PostgreSQL) | Все Laravel feature tests на отдельной PostgreSQL 17 database в фиксированном случайном порядке |
| Redis queue delivery | Реальные import, storage cleanup и order confirmation jobs через отдельный Redis worker и PostgreSQL 17 |
| Admin full-stack smoke | Production build Admin, реальный Sanctum session/CSRF flow, Laravel, PostgreSQL 17 и Redis 7.4 без browser API mocks |
| Admin checks | `npm ci`, audit production-зависимостей, ESLint без warnings, Prettier format check, Vitest component/unit-тесты, TypeScript/Vite build, Playwright E2E в Chromium и axe accessibility scan |
| Client checks | `npm ci`, audit production-зависимостей, Prettier format check, Nuxt typecheck, SSR build и production Playwright E2E в Chromium, включая content, preview и axe |
| Compose bootstrap | Валидация `compose.yaml`, проверка зависимостей запуска Admin smoke, clean-volume и stale-volume smoke для общего Composer volume и отдельных Admin/Client npm volumes |

Отдельный workflow [Admin Windows approved UI](../.github/workflows/admin-windows-ui.yml)
запускается при push в `main` и pull request с изменениями в `frontend/admin/`
или в самом workflow, а также вручную. Его job `Approved palette and Windows
baselines` выполняет unit-тесты, сборку и сценарии Chromium из
`approvedPalette.spec.ts` и `adminBaseline.spec.ts`.

Production CI/CD и deployment относятся к
[Phase 11](../tasks/TODO.md#phase-11--production).

`Admin checks` на Linux и отдельный Windows workflow сохраняют browser
`test-results/` при падении на семь дней. [История исправлений PR #5](CI_PR5_REPAIR_2026-10-19.md).

`Client checks` после сборки запускает production SSR (`127.0.0.1:3015`) и
изолированный mock API (`127.0.0.1:8015`) через существующую конфигурацию Playwright.
Для запуска достаточно сборки и тестовых фикстур. Один worker последовательно
меняет сценарии mock API. При падении браузерного сценария Playwright сохраняет
trace и скриншот. CI публикует `.tmp/client-e2e/` из корня репозитория как
`client-linux-browser-evidence` на семь дней.

Для воспроизведения шагов Client на чистом Linux checkout:

```bash
cd frontend/client
npm ci
npm audit --omit=dev --audit-level=high
npm run format:check
npm exec nuxi typecheck
npm run build
npx playwright install --with-deps chromium
CI=true npm run test:e2e
```

Текстовые файлы Frontend используют LF по правилам `.gitattributes`. Prettier
запускается со стандартной настройкой конца строки.

В `frontend/admin` команда `npm run format:check` запускает `prettier . --check`.
Она охватывает поддерживаемые Prettier файлы в каталоге Admin, включая `src/`,
тесты и корневые конфигурации вроде `tsconfig.json`. Правило `.tmp/` в
[`.prettierignore`](../frontend/admin/.prettierignore) исключает вложенные
JSON- и Markdown-отчёты. Тот же каталог исключён из Git по корневому
[`.gitignore`](../.gitignore).

С 2026-10-13 текущая macOS-палитра утверждена владельцем также для Windows.
Проверка axe включает `color-contrast`. Helper сверяет цветовой контракт,
фильтрует только подтверждённые владельцем совпадения и прикладывает исходные
находки для таких случаев. Контракт, ограничения и порядок приёмки снимков
описаны в [ADMIN_APPROVED_PALETTE_TESTS.md](ADMIN_APPROVED_PALETTE_TESTS.md).

Client audit использует актуальную базу advisories, поэтому может перестать проходить даже без
изменения зависимостей в последнем коммите. Исправление выполняется совместимым обновлением
lock-файла, после которого обязательны чистый install, повторный audit, typecheck и SSR build:

```bash
cd frontend/client
npm audit fix --package-lock-only --ignore-scripts
npm ci
npm audit --omit=dev --audit-level=high
npm exec nuxi typecheck
npm run build
```

Для ветки `archiver@8.0.0` действует согласованное исключение: npm закрепляет
`readdir-glob` 4.0.0 до выпуска совместимого диапазона upstream. Обоснование,
проверки и условия снятия приведены в
[отчёте о зависимостях Client](CLIENT_DEPENDENCIES_2026-11-24.md).

Состояние audit для конкретной ревизии фиксируется в её CI run или датированном
отчёте. [Запуск TASK-A056](https://github.com/Dennis-Job/AgatCeramic/actions/runs/36686153839),
[запуск TASK-C004](https://github.com/Dennis-Job/AgatCeramic/actions/runs/36827398873),
[ноябрьский CI follow-up](CI_FOLLOWUP_2026-11-09.md).

Backend architecture guard локально запускается после установки Composer dependencies:

```bash
cd backend
composer architecture
```

Блокирующие правила, review-сигналы и политика временного allowlist описаны в
[`ARCHITECTURE.md`](ARCHITECTURE.md#автоматические-границы).

Repository hygiene локально проверяется без установки зависимостей:

```bash
python3 scripts/check-repository-hygiene.py
```

Скрипт обходит tracked Markdown-файлы, проверяет относительные цели и anchors ссылок и отклоняет
tracked dependency/build/test/editor/runtime artifacts. Проверка database exports во всех Git refs
остаётся в отдельном
[`assert-no-database-artifacts.sh`](../scripts/assert-no-database-artifacts.sh).

Compose smoke запускает [`scripts/test-compose-dependency-bootstrap.sh`](../scripts/test-compose-dependency-bootstrap.sh)
с отдельным project name и только временными named volumes. Он собирает реальные backend/node
images, подтверждает установку из чистых volumes, повторное использование общего `backend_vendor`
сервисами backend/queue/scheduler и обязательную переустановку после stale fingerprint для
Composer, Admin и Client. Cleanup удаляет только volumes временного smoke project и не обращается
к development database. Ошибка установки блокирует запуск соответствующего application process,
поскольку runtime-команда связана с bootstrap через `&&`.

OpenAPI tooling из `backend/openapi-tooling` устанавливается строго через `npm ci` по
lock-файлу. `npm test` запускает mutation tests проектных правил форматов/media types,
а `npm run lint` — закреплённый Redocly OpenAPI 3.1 ruleset и эти правила на
`docs/openapi.json`. Compatibility gate сравнивает спецификацию с PR base или
предыдущей ревизией push, когда та содержит OpenAPI. При ручном запуске
выполняется semantic gate. PHP mutation tests совместимости входят в обычный
backend suite. Breaking изменение требует major bump и явного раздела migration plan.

Локально проверки запускаются так:

```bash
cd backend/openapi-tooling
npm ci --ignore-scripts
npm test
npm run lint

cd ..
vendor/bin/phpunit tests/Unit/OpenApiCompatibilityCheckerTest.php
php scripts/assert-openapi-compatible.php <base.json> ../docs/openapi.json \
  --migration-plan ../docs/OPENAPI_MIGRATION_PLAN.md
```

Repository security checkout использует полную историю. Скрипт
[`assert-no-database-artifacts.sh`](../scripts/assert-no-database-artifacts.sh) отклоняет SQL exports,
PostgreSQL dumps и backup archives как в текущем дереве, так и во всех publication refs
(local/remote branches and tags). Внутренние tool refs, которые не публикуются GitHub, не входят в
gate; публичные hidden PR refs обрабатываются GitHub Support после sensitive-data rewrite.
Gitleaks 8.30.1 загружается только с официального release, проверяется закреплённым SHA-256 и
сканирует `--all`; вывод находок всегда полностью редактируется. Версия и checksum обновляются
одновременно после проверки официального release manifest.

Два последовательных запуска полного backend test suite защищают общие factory и другое
состояние тестовой инфраструктуры от недетерминированных коллизий между прогонами.

Отдельный job `Backend feature suite (PostgreSQL)` поднимает собственный PostgreSQL service с
базой `agatceramic_feature_test`, выполняет guarded `migrate:fresh` и все `tests/Feature` через
`phpunit.postgres-feature.xml`. Порядок тестов псевдослучайный с закреплённым seed `48048`, поэтому
скрытые зависимости от порядка выявляются воспроизводимо. `RefreshDatabase` создаёт схему один раз
и изолирует DB-dependent test cases транзакциями; перед suite база всегда пересоздаётся.

PostgreSQL-прогон является блокирующим и не заменяет быстрые SQLite-прогоны. Он фиксирует различия,
которые SQLite не моделирует: реальный signed-диапазон `smallint`, сравнение и casts JSON-колонок,
точность `decimal`, foreign/unique/check constraints, вложенные application-транзакции и
`lockForUpdate()`. Межпроцессные ожидания блокировок и `SKIP LOCKED` остаются в специализированных
integration suites, поскольку feature suite намеренно однопроцессный.

PostgreSQL integration-тесты отдельно проверяют неизменяемость журнала аудита,
инварианты каталога, retention, конкурентную очистку гостевых корзин и
выделение читаемых номеров заказов при параллельном checkout. Конкурентные
сценарии используют независимые PHP-процессы и соединения. `Backend checks`
также проверяет запись и чтение через Redis cache store и подключение очереди Redis.
Перечень вызовов находится в [workflow](../.github/workflows/ci.yml).

Отдельный blocking job `Redis queue delivery` запускает Compose profile `queue-integration` с
эфемерными PostgreSQL 17 и Redis 7.4 без опубликованных портов и persistent data volumes. Тест
отправляет `ProcessProductImport`, `DeleteStoredFile` и `SendOrderConfirmation` в настоящую Redis
queue и обрабатывает их отдельными `queue:work` процессами. Проверяются after-commit visibility,
identifier-only payloads без PII и file paths, завершение import и cleanup, order confirmation,
реальный delayed retry/backoff, terminal failure и `failed_jobs`, stale cleanup redispatch и
идемпотентность duplicate delivery. Каждый worker имеет `--max-time` и внешний process timeout;
диагностика ограничена техническими ID, статусами и именами job classes.

Production backoff cleanup-job остаётся `60,300` секунд. Только изолированная конфигурация
`phpunit.redis-queue.xml` задаёт `1,1`, чтобы проверить оба delayed retry в bounded CI run. Redis
gate разрешает только database `14`, prefix `agatceramic-queue-test:` и localhost либо внутренний
Compose host `queue-test-redis`; PostgreSQL gate аналогично разрешает отдельную базу
`agatceramic_queue_test`. Перед тестом оба fail-closed guard выполняются до `migrate:fresh` и
очистки изолированной Redis database.

Локальный воспроизводимый запуск не использует development PostgreSQL/Redis:

```bash
docker compose -p agatceramic-queue-test --env-file .env.example \
  --profile queue-integration run --rm queue-integration
docker compose -p agatceramic-queue-test --env-file .env.example \
  --profile queue-integration down --volumes
```

Отдельный project name обязателен: он изолирует test containers, network и dependency volume от
уже запущенного development Compose project, поэтому `down --volumes` удаляет только test volume.

После инцидента 2026-09-03 тестовые параметры принудительно задаются и через `<env>`, и через
`<server>`: Docker заполняет `$_SERVER`, который Laravel читает раньше `$_ENV`. Одного
`<env force="true">` недостаточно для изоляции рабочей БД. Обычный `php artisan test`, включая
запуск через `docker compose exec -T backend`, использует только SQLite `:memory:`, cache/session
`array` и синхронную очередь. `Tests\\TestCase` до запуска `RefreshDatabase` запрещает небезопасное
подключение, URL подключения и запуск с кэшированной конфигурацией.

PostgreSQL integration-тесты запускаются с `--configuration=phpunit.postgres.xml`, feature suite —
с `--configuration=phpunit.postgres-feature.xml`, а Redis queue suite — с
`--configuration=phpunit.redis-queue.xml`. Разрешены только отдельные базы `agatceramic_test`,
`agatceramic_feature_test`, `agatceramic_queue_test` и `agatceramic_admin_smoke_test` при `CI=true`.
Перед каждым destructive reset CI и
локальный runner вызывают `scripts/assert-safe-postgres-test-environment.php`: он fail-closed
проверяет `APP_ENV=testing`, `CI=true`, драйвер `pgsql`, разрешённый test host, точное allowlisted имя
базы, пустой `DB_URL` и отсутствие cached config. Не запускать `migrate:fresh`, `db:wipe`
или тесты против локальной базы
`agatceramic`.

Локальный runner
[`run-postgres-integration.ps1`](../backend/scripts/run-postgres-integration.ps1)
требует явный флаг сброса тестовых БД, задаёт обе test-only базы, проверяет
`pdo_pgsql` и запускает миграции, сценарии аудита, каталога, retention и гостевой
корзины, а также полный feature suite с `CI=true`. Обе базы должны быть заранее
созданы на разрешённом localhost PostgreSQL. Пароль передаётся параметром
процесса и не сохраняется в репозитории. Проверки Redis cache и конкурентной
выдачи номеров заказов входят в шаги `Backend checks` в CI.

Для Redis queue suite поддерживаемым локальным способом является Compose profile выше. Внутренний
[`run-redis-queue-integration.sh`](../backend/scripts/run-redis-queue-integration.sh) запускается
только после обоих safety guards и не предназначен для development database/services.

Разбор инцидента и проверка восстановления: [DATABASE_RECOVERY_2026-09-03.md](DATABASE_RECOVERY_2026-09-03.md).

Admin E2E в CI и локальном Compose запускает production-сборку SPA через Vite preview в одном
поддерживаемом `admin-e2e` Docker-окружении; CI явно передаёт безопасный `.env.example`. Это
устраняет различия системных шрифтов и Chromium rasterization между hosted runner и средой, в
которой создаются Linux visual snapshots, без ослабления pixel-diff threshold.
С TASK-A062 обычный Playwright-прогон использует `updateSnapshots: 'none'`:
отсутствующий или несовпадающий baseline завершает тест ошибкой. Новые actuals
сначала проходят независимое UI Design Guard-ревью, затем копируются в эталоны
и проверяются повторным строгим прогоном. `expect.soft` для набора UI-kit crops
собирает все различия за один прогон, сохраняя failed-статус теста при любом diff.
Responsive/axe матрица проверяет маршруты на ширинах
320/640/768/1024/1280/1440/1920/2560 px и требует пустой список нарушений после
применения утверждённых исключений контраста. Маршруты перечислены в
[`adminBaseline.spec.ts`](../frontend/admin/e2e/adminBaseline.spec.ts).

Playwright retries отключены во всех окружениях: каждый упавший тест немедленно делает suite
неуспешным и не может быть скрыт успешной повторной попыткой.
Visual-тест открытого `UiDatePicker` фиксирует системное время, чтобы подсветка текущего дня не
меняла snapshot без изменения интерфейса.
Детерминированные browser-level mock-ответы API удерживают
loading-состояния управляемыми deferred fixtures до явного release, а не таймерами. Проверяются
маршрутизация, восстановление административной сессии, каталоговые представления и интерактивные
компоненты без зависимости от общей тестовой базы данных. Профильные проверки
axe блокируют serious/critical нарушения на маршрутах, списках и в открытых
диалогах с учётом утверждённых исключений контраста. Backend API-контракт
отдельно защищён Laravel-тестами и OpenAPI.

Локальный Compose runner запускается отдельной командой `docker compose --profile test run --rm admin-e2e`.
Он выполняет чистый `npm ci`, устанавливает Chromium в отдельный volume и запускает production E2E;
dev-сервис `admin` и его `node_modules` не используются и не изменяются.

Отдельный blocking job `Admin full-stack smoke` не дублирует visual suite. Compose profile
`admin-smoke` собирает Admin с `VITE_API_BASE_URL=/api/v1`, обслуживает production bundle через
Vite preview с same-origin proxy к Laravel и поднимает эфемерные PostgreSQL/Redis без опубликованных
портов. Playwright без `page.route()` проходит настоящий Sanctum CSRF/login/logout flow, читает и
изменяет representative Catalog, order и contact записи, а затем проверяет route permission,
backend `403` и стандартные `forbidden`/`unauthenticated` error envelopes.

Обе учётные записи, пароли и синтетические contact/order данные генерируются при запуске. Только
идентификаторы учётных записей и пароли записываются с mode `0600` во временный Compose volume;
синтетические PII остаются в PostgreSQL tmpfs. Trace, screenshots и video отключены, credentials не
печатаются; `if: always()` удаляет containers, network и volumes даже после failure. Локальный
воспроизводимый запуск:

```bash
docker compose -p agatceramic-admin-smoke-test --env-file .env.example \
  --profile admin-smoke run --rm admin-smoke-e2e
docker compose -p agatceramic-admin-smoke-test --env-file .env.example \
  --profile admin-smoke down --volumes
```

Отдельный project name обязателен, чтобы cleanup не затронул development Compose.

Cold preparation вынесена в one-shot `admin-smoke-backend-bootstrap`
(Composer + safety guard + migrations + fixtures, bounded 300s) и
`admin-smoke-bootstrap` (npm + build, bounded 360s). Runtime ждёт
`service_completed_successfully`; прежний HTTP health budget начинается после
подготовки. Build artifacts изолированы в `admin_smoke_dist`, shared только
между двумя Admin test services. TERM kill grace — 10 секунд.

Compose job проверяет конфигурационный regression gate. Полная локальная
проверка independent bootstrap failures, clean volumes, readiness и настоящего
browser smoke доступна отдельно:

```sh
node scripts/test-admin-smoke-bootstrap.mjs --config-only
node scripts/test-admin-smoke-bootstrap.mjs --browser-smoke
```

Harness отказывается переиспользовать уже существующие project resources и
в `finally` удаляет только свой отдельный project. Optional `--composer-audit`
выполняет отдельный audit и передаёт nonzero exit; network failure не считается
успешной security-проверкой.

Локальная команда `npm run test:ci` после `npm ci` объединяет ESLint, Prettier
check, unit-тесты, production build и Playwright в текущей ОС. CI отдельно
выполняет dependency audit и запускает Linux E2E в описанном выше Compose
профиле. Быстрая проверка без браузера доступна как `npm run check`.
