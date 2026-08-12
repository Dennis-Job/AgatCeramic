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

Тесты Laravel изолированно работают с SQLite `:memory:`, а CI дополнительно прогоняет миграции на PostgreSQL 17.

Подключение Redis реализуется в TASK-011.
