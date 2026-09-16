# CI

GitHub Actions запускает workflow [`ci.yml`](../.github/workflows/ci.yml) для каждого pull request, push в `main` и ручного запуска.

Workflow получает только право `contents: read` и не использует production secrets.
Используемые GitHub Actions запускаются на Node 24.

| Job | Проверки |
| --- | --- |
| Repository security | Запрет database dumps/backups во всех Git refs и fully-redacted Gitleaks scan полной истории |
| Backend checks | Composer manifest и audit, OpenAPI 3.1 semantic/compatibility gates, Laravel Pint, два последовательных полных прогона PHPUnit/Laravel tests на SQLite, миграции и отдельные integration tests на PostgreSQL 17 |
| Backend feature suite (PostgreSQL) | Все Laravel feature tests на отдельной PostgreSQL 17 database в фиксированном случайном порядке |
| Redis queue delivery | Реальные import, storage cleanup и order confirmation jobs через отдельный Redis worker и PostgreSQL 17 |
| Admin checks | `npm ci`, audit production-зависимостей, ESLint без warnings, Prettier format check, Vitest component/unit-тесты, TypeScript/Vite build, Playwright E2E в Chromium и axe accessibility scan |
| Client checks | `npm ci`, audit production-зависимостей, Nuxt typecheck и SSR build |
| Compose bootstrap | Валидация `compose.yaml`; clean-volume и stale-volume smoke для общего Composer volume и отдельных Admin/Client npm volumes |

CI не выполняет deploy и не подключается к production-инфраструктуре. Production CI/CD, secrets и deployment настраиваются отдельной задачей TASK-141.

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
`docs/openapi.json`. Compatibility gate сравнивает спецификацию с base Git revision;
направленно-рекурсивные PHP mutation tests входят в обычный backend suite. Breaking
изменение проходит только при major bump и совпадающем явном разделе migration plan.

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

PostgreSQL integration suite отдельно проверяет неизменяемость audit log и retention evidence,
rollback retention batch, ограничения legal hold, индексы guest carts, а также реальные конкурентные
транзакции Catalog, retention и cart cleanup. Concurrency-тесты используют независимые PHP-процессы и соединения, подтверждают
ожидание блокировки через `pg_stat_activity` и защищают инварианты единственного primary image,
удаления товара против загрузки изображения, отсутствия reverse relations и ацикличности/допустимости
родителей дерева категорий и пропуск cart row, занятой конкурентной write-транзакцией. Эти тесты
намеренно не входят в быстрый SQLite suite.

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
`agatceramic_feature_test` и `agatceramic_queue_test` при `CI=true`. Перед каждым destructive reset CI и
локальный runner вызывают `scripts/assert-safe-postgres-test-environment.php`: он fail-closed
проверяет `APP_ENV=testing`, `CI=true`, драйвер `pgsql`, разрешённый test host, точное allowlisted имя
базы, пустой `DB_URL` и отсутствие cached config. Не запускать `migrate:fresh`, `db:wipe`
или тесты против локальной базы
`agatceramic`.

Для локального прогона используется только
[`backend/scripts/run-postgres-integration.ps1`](../backend/scripts/run-postgres-integration.ps1):
он требует явный destructive-флаг, жёстко задаёт обе test-only базы, проверяет `pdo_pgsql` и
запускает миграции, PostgreSQL-only integration suites и полный feature suite с `CI=true`. Обе базы
должны быть заранее созданы на разрешённом localhost PostgreSQL. Пароль передаётся параметром
процесса и не сохраняется в репозитории.

Для Redis queue suite поддерживаемым локальным способом является Compose profile выше. Внутренний
[`run-redis-queue-integration.sh`](../backend/scripts/run-redis-queue-integration.sh) запускается
только после обоих safety guards и не предназначен для development database/services.

Разбор инцидента и проверка восстановления: [DATABASE_RECOVERY_2026-09-03.md](DATABASE_RECOVERY_2026-09-03.md).

Admin E2E в CI и локальном Compose запускает production-сборку SPA через Vite preview в одном
поддерживаемом `admin-e2e` Docker-окружении; CI явно передаёт безопасный `.env.example`. Это
устраняет различия системных шрифтов и Chromium rasterization между hosted runner и средой, в
которой создаются Linux visual snapshots, без ослабления pixel-diff threshold.
Playwright retries отключены во всех окружениях: каждый упавший тест немедленно делает suite
неуспешным и не может быть скрыт успешной повторной попыткой.
Детерминированные browser-level mock-ответы API удерживают
loading-состояния управляемыми deferred fixtures до явного release, а не таймерами. Проверяются
маршрутизация, восстановление административной сессии, каталоговые представления и интерактивные
компоненты без зависимости от общей тестовой базы данных. Full-page Axe scan без исключения
`color-contrast` блокирует serious/critical accessibility-регрессии на маршрутах, списках и открытых
диалогах. Backend API-контракт отдельно защищён Laravel-тестами и OpenAPI.

Локальный Compose runner запускается отдельной командой `docker compose --profile test run --rm admin-e2e`.
Он выполняет чистый `npm ci`, устанавливает Chromium в отдельный volume и запускает production E2E;
dev-сервис `admin` и его `node_modules` не используются и не изменяются.

Полный локальный Admin workflow после `npm ci` запускается командой `npm run test:ci`: ESLint,
Prettier check, unit-тесты, production build и Playwright выполняются последовательно с единым
ненулевым exit code при любой ошибке. Быстрая проверка без браузера доступна как `npm run check`.

## Статус TASK-A039 (2026-09-14)

Admin source, tests и конфигурация приведены к зафиксированному Prettier baseline. ESLint 10
использует flat config для JavaScript, TypeScript и Vue; warnings считаются ошибками. Обе проверки
выполняются отдельными blocking steps в Admin CI job. Playwright запускается с `retries: 0`
локально и в CI.

Visual-тест открытого `UiDatePicker` фиксирует системное время на 13.09.2026. Это исключает
изменение подсветки «сегодня» при смене календарного дня без изменения или ослабления snapshot
threshold.

Два последовательных локальных запуска `npm run test:ci` прошли одинаково: ESLint и Prettier
без ошибок, 47 unit-тестов, production build и 148/148 Playwright E2E/axe/visual тестов. Финальный
Linux Compose suite после чистого `npm ci` прошёл 148/148.
