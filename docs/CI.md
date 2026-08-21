# CI

GitHub Actions запускает workflow [`ci.yml`](../.github/workflows/ci.yml) для каждого pull request, push в `main` и ручного запуска.

Workflow получает только право `contents: read` и не использует production secrets.
Используемые GitHub Actions запускаются на Node 24.

| Job | Проверки |
| --- | --- |
| Backend checks | Composer manifest и audit, Laravel Pint, быстрые PHPUnit/Laravel tests на SQLite, миграции и отдельные integration tests на PostgreSQL 17 |
| Admin checks | `npm ci`, audit production-зависимостей, Vitest component/unit-тесты, TypeScript/Vite build, Playwright E2E в Chromium и axe accessibility scan |
| Client checks | `npm ci`, audit production-зависимостей, Nuxt typecheck и SSR build |
| Compose configuration | Валидация `compose.yaml` с `.env.example` |

CI не выполняет deploy и не подключается к production-инфраструктуре. Production CI/CD, secrets и deployment настраиваются отдельной задачей TASK-141.

PostgreSQL integration suite отдельно проверяет неизменяемость audit log и реальные конкурентные
транзакции Catalog. Concurrency-тесты используют независимые PHP-процессы и соединения, подтверждают
ожидание блокировки через `pg_stat_activity` и защищают инварианты единственного primary image,
удаления товара против загрузки изображения, отсутствия reverse relations и ацикличности/допустимости
родителей дерева категорий. Эти тесты намеренно не входят в быстрый SQLite suite.

Admin E2E запускает реальную SPA с детерминированными browser-level mock-ответами API. Это проверяет маршрутизацию, восстановление административной сессии, каталоговые представления и интерактивные компоненты без зависимости от общей тестовой базы данных. Axe scan блокирует serious/critical семантические accessibility-регрессии на списках и открытых диалогах; правило `color-contrast` исключено, поскольку палитра контролируется отдельным визуальным регламентом `UI_DESIGN_REVIEW.md`. Backend API-контракт отдельно защищён Laravel-тестами и OpenAPI.
