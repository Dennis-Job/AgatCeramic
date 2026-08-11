# DONE

| ID | Название | Дата | Результат | Проверки |
| --- | --- | --- | --- | --- |
| TASK-001 | Initialize Git repository and project structure | 2026-08-11 | Создан Git-репозиторий с основной веткой `main`; зафиксирована базовая структура и добавлены общие правила игнорирования для секретов, зависимостей и артефактов сборки. | `git status --short --branch`; проверка структуры файлов |
| TASK-002 | Initialize Laravel API-only backend | 2026-08-11 | Инициализирован Laravel 13.24.0 в `backend/`; удалены web/Blade/Vite-заготовки, зарегистрирован пустой API route file. Версионирование API и прикладные endpoints остаются в TASK-012. | `composer validate --strict`; `php artisan route:list`; `php artisan test`; `vendor/bin/pint --test` |

После завершения каждой задачи добавлять:
- ID;
- название;
- дату;
- краткий результат;
- тесты/checks;
- важные решения.
