# Переменные окружения и секреты

## Правила

- Реальные `.env`-файлы не попадают в Git и не передаются в обычные логи, задачи или скриншоты.
- В репозитории хранятся только `.env.example` с безопасными шаблонными значениями.
- Для разработки допустимы отдельные локальные пароли. Их нельзя использовать в test, staging или production.
- Секреты production хранятся только в защищённом secret storage выбранной CI/CD-платформы или хостинга; они не записываются в Docker Compose, исходный код или документацию.
- Значения с префиксами `VITE_` и `NUXT_PUBLIC_` попадают в браузер. В них запрещены пароли, токены, ключи и PII.

## Локальная настройка

```powershell
Copy-Item .env.example .env
Copy-Item backend/.env.example backend/.env
Copy-Item frontend/admin/.env.example frontend/admin/.env
Copy-Item frontend/client/.env.example frontend/client/.env
Set-Location backend
php artisan key:generate
Set-Location ..
```

После этого для Docker development-окружения используйте `docker compose up --build` из корня репозитория.

Изолированный smoke реальной Redis queue запускается отдельно и не использует development data:

```sh
docker compose -p agatceramic-queue-test --env-file .env.example \
  --profile queue-integration run --rm queue-integration
docker compose -p agatceramic-queue-test --env-file .env.example \
  --profile queue-integration down --volumes
```

Profile создаёт эфемерные PostgreSQL `agatceramic_queue_test` и Redis database `14` без внешних
портов. Fail-closed guards проверяют точные database/host/prefix до reset; `down` не удаляет
development volumes. Отдельный project name нельзя убирать: он не позволяет test cleanup
останавливать development-контейнеры.

## Lock-aware bootstrap зависимостей Compose

`backend`, `queue` и `scheduler` используют общий named volume `backend_vendor`; Admin и Client
используют отдельные `admin_node_modules` и `client_node_modules`. Перед запуском application
process каждый сервис сравнивает сохранённый в своём dependency volume fingerprint с текущими
`composer.json`/`composer.lock` либо `package.json`/`package-lock.json`, архитектурой контейнера и
версией package manager/runtime. Fingerprint записывается атомарно только после успешного
`composer install` или `npm ci`.

Lock-файлы также входят в Docker image как rebuild inputs. Поэтому обычная команда
`docker compose up --build` после изменения lock-файла создаёт новый image, пересоздаёт связанные
контейнеры и синхронизирует сохранённые dependency volumes до запуска Laravel, queue worker,
scheduler, Vite или Nuxt. Одновременный bootstrap трёх backend-процессов сериализуется через
file lock. Если установка завершается ошибкой или входные файлы меняются во время установки,
fingerprint не публикуется, application process не запускается, а следующий запуск повторяет
установку.

При повреждении dependency volume без изменения lock-файла выполните принудительное
восстановление. Эти команды не удаляют `postgres_data`, `redis_data` и другие рабочие данные:

```bash
docker compose run --rm --no-deps -e DEPENDENCY_BOOTSTRAP_FORCE=1 backend \
  install-locked-dependencies composer /var/www/backend
docker compose run --rm --no-deps -e DEPENDENCY_BOOTSTRAP_FORCE=1 admin \
  install-locked-dependencies npm /app
docker compose run --rm --no-deps -e DEPENDENCY_BOOTSTRAP_FORCE=1 client \
  install-locked-dependencies npm /app
docker compose up --build --force-recreate
```

Удалять все Compose volumes через `docker compose down --volumes` для такого recovery нельзя:
вместе с dependency caches эта команда удалит локальные PostgreSQL и Redis data volumes.

## Ответственность файлов

| Файл | Назначение |
| --- | --- |
| `/.env` | Порты и локальные PostgreSQL credentials для Docker Compose |
| `/backend/.env` | Настройки Laravel и `APP_KEY` |
| `/frontend/admin/.env` | Публичный URL API для Admin SPA |
| `/frontend/client/.env` | Публичный URL API для Nuxt storefront |

Laravel использует PostgreSQL как базу данных по умолчанию. В Docker Compose параметры `DB_*` формируются из PostgreSQL credentials корневого `.env`, а `DB_HOST` равен `postgres`. При запуске Laravel вне Docker укажите доступ к PostgreSQL в `backend/.env`.

The Docker backend starts Laravel's development server with `--no-reload` so all Compose
environment variables, including the PostgreSQL connection settings, are inherited by the
server process. Restart the backend service after changing its environment values.

## Admin SPA authentication

`SANCTUM_STATEFUL_DOMAINS` is the explicit comma-separated allowlist of first-party SPA
origins, including their ports. `CORS_ALLOWED_ORIGINS` is the matching comma-separated CORS
allowlist. Both must be set to the deployed Admin SPA origin in each environment; wildcard
origins are not compatible with credentialed cookie requests. For HTTPS deployments set
`SESSION_SECURE_COOKIE=true`.

`ADMIN_APP_URL` is the absolute Admin SPA origin used in password-reset emails. It must be the
deployed HTTPS Admin SPA URL in non-local environments; links target `/reset-password` on that
origin and must never point at the Laravel API host.

## Initial Super Admin

The first staff account is created only from the backend CLI, never through a public route:

```sh
php artisan admin:bootstrap --email=admin@example.test
```

The command asks for the password through a hidden prompt, seeds the baseline roles and
permissions, assigns the `super-admin` role, and records `admin.bootstrap` in the audit trail.
It refuses to run when any staff account already exists. In production it additionally requires
the explicit `--force` option. Do not pass a password with `--password` except for an isolated
local development environment, because command-line arguments can be visible to other local
processes.

## Emergency authentication invalidation

После подтверждённой утечки administrative database state оператор из backend runtime выполняет:

```sh
php artisan security:invalidate-compromised-admin-auth --force
```

Команда атомарно заменяет password hashes всех администраторов случайными неизвестными значениями,
очищает database sessions/password reset tokens и remember tokens и пишет только агрегированные
счётчики в audit log. Она намеренно лишает все аккаунты доступа. После containment каждый
администратор задаёт уникальный новый пароль через штатный reset flow с защищённым mail transport;
пароли нельзя передавать через CLI options, task evidence или application logs.

Основные тесты Laravel изолированно работают с SQLite `:memory:` для быстрого feedback. CI
дополнительно прогоняет полный feature suite на отдельной базе `agatceramic_feature_test` и
специализированные integration tests на `agatceramic_test` в PostgreSQL 17, включая реальные
конкурентные транзакции в независимых PHP-процессах. Обе PostgreSQL test database разрешены только
при `APP_ENV=testing`, `CI=true`, пустом `DB_URL` и отсутствии cached config; destructive-команды
предварительно проверяются `backend/scripts/assert-safe-postgres-test-environment.php`.

Production queue использует отдельное Redis-подключение/database. Реальная доставка import,
storage cleanup и order confirmation jobs отдельному worker проверяется изолированным
`queue-integration` profile; короткий retry backoff применяется только в этом test profile.
