# Восстановление базы и изоляция тестов — 2026-09-03

При диагностике PostgreSQL `agatceramic` содержал 0 пользователей, товаров, категорий и брендов.
Все 37 миграций были в batch 1. API и Admin SPA отвечали; вход был невозможен из-за отсутствия пользователя.

Причина: PHPUnit переопределял `DB_CONNECTION` и `DB_DATABASE` через `<env>`, но Docker сохранял
`pgsql` / `agatceramic` в `$_SERVER`. Laravel отдаёт этому источнику приоритет. Безопасная проверка
без обращения к БД воспроизвела расхождение: `$_ENV` содержал SQLite `:memory:`, а Laravel выбирал
рабочий PostgreSQL. В истории задачи импорта подтверждён запуск тестов через `docker compose exec`.
Тесты с `RefreshDatabase` вызывают `migrate:fresh`, пересоздавая таблицы; тестовые записи затем
откатываются. Миграция нового импорта сама по себе данные не удаляет.

С разрешения пользователя был восстановлен локальный PostgreSQL custom-format archive
от 02.09.2026 17:46 МСК; перед restore его SHA-256 совпал с тогдашним recovery manifest.
Архив сначала восстановлен в отдельную базу с `--single-transaction --exit-on-error`, затем применена
миграция `2026_09_03_120000_extend_product_imports_for_category_templates`. При остановленных
backend/queue/scheduler имена баз переключены одной транзакцией. Исходная пустая база сохранена
как `agatceramic_empty_20260903`, также создан отдельный страховочный archive.

После восстановления: 1 активный пользователь, 11 товаров, 11 категорий, 6 брендов, 37 миграций.
Пароль пользователя восстановлен из бэкапа без сброса. Более поздние изменения в бэкап не входят.
Все сервисы приложения запущены. Admin HTTP 200; CSRF HTTP 204. Вход с личным паролем пользователя
в браузере не проверялся.

Изменённые файлы защиты:

- `backend/phpunit.xml`: принудительные настройки `$_SERVER` и окружения для SQLite, cache, session и queue.
- `backend/phpunit.postgres.xml`: отдельная конфигурация для CI-базы PostgreSQL.
- `backend/tests/TestCase.php`: блокировка опасного подключения до `RefreshDatabase`.
- `backend/tests/Unit/TestEnvironmentTest.php`: регрессия фактической изоляции внутри Docker.
- `.github/workflows/ci.yml`: явный выбор PostgreSQL-конфигурации для integration suites.
- `docs/CI.md` и `tasks/DONE.md`: правила запуска и результат исправления.

Проверки: 21 тест / 203 assertions (`TestEnvironmentTest`, `AdminAuthenticationTest`,
`CategoryProductImportTest`) прошли в Docker; после них PostgreSQL сохранил указанные количества
записей. Запуск без PHPUnit-конфигурации был намеренно проверен и остановился с `Unsafe test database`
до выполнения теста (0 assertions). Pint — 2 файла без замечаний. Независимое ревью защиты — без
существенных замечаний. API-контракт и схема приложения этим исправлением не менялись.

## Security correction 2026-09-14

Исторические archives и recovery manifest позднее были ошибочно опубликованы в публичном GitHub
repository. Они удалены из current tree, public branches и tags в рамках `TASK-A042`; остаточная
доступность test-only objects через четыре GitHub-managed PR refs принята владельцем без удаления
PR. Эти archives нельзя использовать как recovery source. Incident evidence и статус находятся в
[`SECURITY_INCIDENT_2026-09-14.md`](SECURITY_INCIDENT_2026-09-14.md), актуальная процедура — в
[`DATABASE_BACKUP_RESTORE.md`](DATABASE_BACKUP_RESTORE.md).
