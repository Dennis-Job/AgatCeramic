# CI

GitHub Actions запускает workflow [`ci.yml`](../.github/workflows/ci.yml) для каждого pull request, push в `main` и ручного запуска.

Workflow получает только право `contents: read` и не использует production secrets.
Используемые GitHub Actions запускаются на Node 24.

| Job | Проверки |
| --- | --- |
| Backend checks | Composer manifest и audit, Laravel Pint, PHPUnit/Laravel tests |
| Admin checks | `npm ci`, audit production-зависимостей, unit-тесты, TypeScript/Vite build |
| Client checks | `npm ci`, audit production-зависимостей, Nuxt typecheck и SSR build |
| Compose configuration | Валидация `compose.yaml` с `.env.example` |

CI не выполняет deploy и не подключается к production-инфраструктуре. Production CI/CD, secrets и deployment настраиваются отдельной задачей TASK-141.
