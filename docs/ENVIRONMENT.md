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

Основные тесты Laravel изолированно работают с SQLite `:memory:`. CI дополнительно прогоняет
миграции и отдельные integration tests на PostgreSQL 17, включая реальные конкурентные транзакции
Catalog в независимых PHP-процессах.

Подключение Redis реализуется в TASK-011.
