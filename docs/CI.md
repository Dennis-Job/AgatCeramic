# CI

GitHub Actions запускает workflow [`ci.yml`](../.github/workflows/ci.yml) для каждого pull request, push в `main` и ручного запуска.

Workflow получает только право `contents: read` и не использует production secrets.
Используемые GitHub Actions запускаются на Node 24.

| Job | Проверки |
| --- | --- |
| Repository security | Запрет database dumps/backups во всех Git refs и fully-redacted Gitleaks scan полной истории |
| Backend checks | Composer manifest и audit, Laravel Pint, два последовательных полных прогона PHPUnit/Laravel tests на SQLite, миграции и отдельные integration tests на PostgreSQL 17 |
| Admin checks | `npm ci`, audit production-зависимостей, ESLint без warnings, Prettier format check, Vitest component/unit-тесты, TypeScript/Vite build, Playwright E2E в Chromium и axe accessibility scan |
| Client checks | `npm ci`, audit production-зависимостей, Nuxt typecheck и SSR build |
| Compose configuration | Валидация `compose.yaml` с `.env.example` |

CI не выполняет deploy и не подключается к production-инфраструктуре. Production CI/CD, secrets и deployment настраиваются отдельной задачей TASK-141.

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

PostgreSQL integration suite отдельно проверяет неизменяемость audit log и retention evidence,
rollback retention batch, ограничения legal hold, а также реальные конкурентные транзакции
Catalog и retention. Concurrency-тесты используют независимые PHP-процессы и соединения, подтверждают
ожидание блокировки через `pg_stat_activity` и защищают инварианты единственного primary image,
удаления товара против загрузки изображения, отсутствия reverse relations и ацикличности/допустимости
родителей дерева категорий. Эти тесты намеренно не входят в быстрый SQLite suite.

После инцидента 2026-09-03 тестовые параметры принудительно задаются и через `<env>`, и через
`<server>`: Docker заполняет `$_SERVER`, который Laravel читает раньше `$_ENV`. Одного
`<env force="true">` недостаточно для изоляции рабочей БД. Обычный `php artisan test`, включая
запуск через `docker compose exec -T backend`, использует только SQLite `:memory:`, cache/session
`array` и синхронную очередь. `Tests\\TestCase` до запуска `RefreshDatabase` запрещает небезопасное
подключение, URL подключения и запуск с кэшированной конфигурацией.

PostgreSQL integration-тесты запускаются с `--configuration=phpunit.postgres.xml`; разрешена
только отдельная база `agatceramic_test` при `CI=true`. Не запускать `migrate:fresh`, `db:wipe`
или интеграционные проверки против локальной базы `agatceramic`.

Для локального прогона используется только
[`backend/scripts/run-postgres-integration.ps1`](../backend/scripts/run-postgres-integration.ps1):
он требует явный destructive-флаг, жёстко задаёт `agatceramic_test`, проверяет `pdo_pgsql` и
запускает миграции и PostgreSQL-only suites с `CI=true`. Пароль передаётся параметром процесса и
не сохраняется в репозитории.

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
