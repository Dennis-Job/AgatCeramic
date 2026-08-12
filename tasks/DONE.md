# DONE

| ID | Название | Дата | Результат | Проверки |
| --- | --- | --- | --- | --- |
| TASK-001 | Initialize Git repository and project structure | 2026-08-11 | Создан Git-репозиторий с основной веткой `main`; зафиксирована базовая структура и добавлены общие правила игнорирования для секретов, зависимостей и артефактов сборки. | `git status --short --branch`; проверка структуры файлов |
| TASK-002 | Initialize Laravel API-only backend | 2026-08-11 | Инициализирован Laravel 13.24.0 в `backend/`; удалены web/Blade/Vite-заготовки, зарегистрирован пустой API route file. Версионирование API и прикладные endpoints остаются в TASK-012. | `composer validate --strict`; `php artisan route:list`; `php artisan test`; `vendor/bin/pint --test` |
| TASK-003 | Initialize Vue Admin | 2026-08-11 | Инициализирована административная SPA-панель на Vue 3, TypeScript и Vite; подключены Vue Router, Pinia и Tailwind CSS, создан базовый каркас интерфейса в стиле TailAdmin. | `npm.cmd run build --prefix frontend/admin` |
| TASK-004 | Initialize Nuxt Client | 2026-08-12 | Инициализирован клиентский интернет-магазин на Nuxt 4.5.2 и TypeScript с включённым SSR; созданы базовый каркас приложения, макет и нейтральная главная страница. | `npm.cmd audit --omit=dev --audit-level=high` — уязвимости не обнаружены; `npm.cmd exec nuxi typecheck`; `npm.cmd run build --prefix frontend/client` |
| TASK-005 | Configure Docker development environment | 2026-08-12 | Настроено локальное Docker Compose-окружение для Laravel API, Vue Admin, Nuxt Client, PostgreSQL 17 и Redis 7; для зависимостей и данных используются именованные volumes. | `docker compose config --quiet`; проверки готовности PostgreSQL и Redis; HTTP 200 для API `/up`, Admin и Client |
| TASK-006 | Configure environment variables and secrets | 2026-08-12 | Добавлены безопасные шаблоны `.env.example` для Docker Compose, Admin и Client; секреты и реальные `.env` исключены из Git. Пароль PostgreSQL удалён из `compose.yaml` и обязателен в локальном корневом `.env`. | `docker compose --env-file .env.example config --quiet`; `git check-ignore` для реальных `.env`; `php artisan about`; `npm.cmd exec nuxi typecheck`; сборки Admin и Client |

После завершения каждой задачи добавлять:
- ID;
- название;
- дату;
- краткий результат;
- тесты/checks;
- важные решения.
