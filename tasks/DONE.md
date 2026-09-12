# DONE

Компактный индекс завершённых работ. Детальные implementation logs воспроизводимы из Git;
долгосрочные решения находятся в [`docs/DECISIONS.md`](../docs/DECISIONS.md),
operational/recovery rules — в [`docs/`](../docs/).

| Этап | Завершённые задачи | Проверяемый итог |
| --- | --- | --- |
| Фаза 0 | TASK-001–007 | Монорепозиторий, API Laravel, каркасы админки и магазина, Docker, шаблоны окружения и CI. |
| Фаза 1 | TASK-010–017 | PostgreSQL, Redis, API `/api/v1`, единые ошибки и ресурсы, безопасное логирование, очереди, планировщик и OpenAPI. |
| Фазы 2–2.1 | TASK-020–029C | Вход в админку, сброс пароля, роли и права, политики, аудит и неизменяемость журналов PostgreSQL. |
| Фаза 3 | TASK-030–041U | Каталог: CRUD, типизированные характеристики, изображения, связи, поиск, защита API и очистка данных. |
| Фаза 3.1 | TASK-042A–042Z | Обычные и групповые товары, генерация SKU, миграция устаревших данных и сценарии админки. |
| Фаза 4 | TASK-050–058 | Импорт и экспорт XLSX, предварительная проверка и ошибки, возобновляемые очереди, ZIP-изображения, цены, статусы и групповые файлы. |
| Фаза 5 | TASK-060–070 | Гостевая корзина, защищённое оформление заказа, снимки данных, номер, статусы, оплата, история, комментарии и письмо-подтверждение. |
| Фаза 6 | TASK-080–084 | Публичные обращения и защищённые API назначения и обработки. |
| Промежуточный аудит | TASK-A001–A004 | Исходное состояние и повторная приёмка зафиксированы; документация и карта проекта приведены к единой структуре. |
| Реестр задач | TASK-A005 | В TODO остались только невыполненные задачи, в IN_PROGRESS — текущая работа, здесь — завершённые этапы. |
| Чистота артефактов | TASK-A006 | Удалены устаревший архив аудита, пустой `.tmp/` и результаты Playwright; материалы восстановления сохранены отдельно. |
| Промежуточный аудит | TASK-A007 | Larastan/PHPStan уровня 8 проходит без baseline и подавлений: 333 файла, 0 ошибок. Проверка обязательна в CI. См. [`QUALITY_BASELINE.md`](../docs/QUALITY_BASELINE.md). |
| Промежуточный аудит | TASK-A008 | Контроллеры передают в сервисы типизированные и проверенные данные; PHPStan контроллеров проходит без ошибок, API не изменён. См. [`HTTP_API_AUDIT.md`](../docs/HTTP_API_AUDIT.md). |
| Промежуточный аудит | TASK-A009 | Импорт, повторные попытки и жизненный цикл XLSX/ZIP централизованы; оформление заказа идемпотентно в течение 24 часов по HMAC. См. [`SERVICE_BOUNDARIES_AUDIT.md`](../docs/SERVICE_BOUNDARIES_AUDIT.md) и [`OPENAPI_MIGRATION_PLAN.md`](../docs/OPENAPI_MIGRATION_PLAN.md). |
| Промежуточный аудит | TASK-A010 | Модели и фабрики проходят строгую проверку; запросы прав вынесены в `PermissionChecker`; миграции и проверки PostgreSQL пройдены. См. [`MODEL_DATABASE_AUDIT.md`](../docs/MODEL_DATABASE_AUDIT.md). |
| Промежуточный аудит | TASK-A011 | Подтверждены API-only архитектура Laravel и границы админки и магазина. См. [`API_ONLY_BOUNDARIES_AUDIT.md`](../docs/API_ONLY_BOUNDARIES_AUDIT.md). |
| Промежуточный аудит | TASK-A012 | Проверены безопасность, аудит и защита ПДн; критичных проблем в фазах 0–6 не выявлено. См. [`SECURITY_PII_AUDIT.md`](../docs/SECURITY_PII_AUDIT.md). |
| Промежуточный аудит | TASK-A013 | Проверены очереди, импорты и очистка хранилища; добавлены восстановление зависших операций и атомарная финальная очистка. См. [`ASYNC_STORAGE_AUDIT.md`](../docs/ASYNC_STORAGE_AUDIT.md). |
| Промежуточный аудит | TASK-A014 | OpenAPI и API-руководство сверены со 111 HTTP-операциями фаз 0–6; добавлены проверки маршрутов и совместимости версий. См. [`OPENAPI_CONTRACT_AUDIT.md`](../docs/OPENAPI_CONTRACT_AUDIT.md). |
| Промежуточный аудит | TASK-A015 | CI разделяет проверки SQLite, PostgreSQL, контрактов и админки; E2E запускаются на production preview, тестовая БД ограничена `agatceramic_test`. См. [`CI.md`](../docs/CI.md). |
| Промежуточный аудит | TASK-A016 | Матрица API и экранов админки составлена; для заказов и обращений выполнены задачи `TASK-A018` и `TASK-A019`, фазы 7–9 отложены осознанно. См. [`ADMIN_API_UI_MATRIX.md`](../docs/ADMIN_API_UI_MATRIX.md). |
| Промежуточный аудит | TASK-A017 | Состояния UI и опасные действия унифицированы; проверки клавиатуры, адаптивности и E2E пройдены, UI-ревью не выявило блокирующих замечаний. См. [`ADMIN_UI_KIT_AUDIT.md`](../docs/ADMIN_UI_KIT_AUDIT.md). |
| Промежуточный аудит | TASK-A018 | Реализованы API и рабочее место заказов: защищённые снимки данных, статусы, оплата, история и комментарии. См. [`ADMIN_API_UI_MATRIX.md`](../docs/ADMIN_API_UI_MATRIX.md). |
| Промежуточный аудит | TASK-A019 | Реализованы навигация и рабочее место обращений: фильтры, назначение, статусы, история и комментарии. См. [`ADMIN_API_UI_MATRIX.md`](../docs/ADMIN_API_UI_MATRIX.md). |
| Промежуточный аудит | TASK-A020 | Финальная регрессия фаз 0–6 пройдена: два backend-прогона по 236 тестов и 3 371 проверке, PostgreSQL, Redis, очереди, Compose, OpenAPI, клиент и 58 E2E/axe тестов админки; переход к фазе 7 разрешён. См. [`INTERIM_AUDIT_FINAL.md`](../docs/INTERIM_AUDIT_FINAL.md). |
| Промежуточный аудит | TASK-A021 | Проверки назначаемых сотрудников отдельно подтверждают состав, доступность и сортировку; два полных backend-прогона пройдены. |
| Промежуточный аудит | TASK-A022 | Изолированный профиль `admin-e2e` устанавливает Chromium и выполняет 58 production E2E/axe тестов без изменения dev-сервиса админки. |
| Admin refactoring | TASK-A023 | Зафиксированы 52 Admin visual snapshots, smoke/axe state coverage и responsive baseline; описаны карта миграции и переходный контракт `Base*`. См. [`ADMIN_REFACTORING_BASELINE.md`](../docs/ADMIN_REFACTORING_BASELINE.md). |
| Admin refactoring | TASK-A024 | Введены design tokens, `styles/`, UI-kit и shared-компоненты с совместимыми адаптерами `Base*`; устранено Axe-нарушение date picker и обновлён утверждённый visual baseline. См. [`UI_KIT.md`](../frontend/admin/src/components/shared/UI_KIT.md). |
| Admin refactoring | TASK-A025 | Application shell разделён на `AdminLayout`, `AuthLayout` и layout-компоненты header/sidebar; сохранены guards, keyboard/focus-навигация, аутентификационные страницы используют единый shell. Независимый UI Design Guard подтвердил responsive и accessibility-проверки. |
| Admin refactoring | TASK-A026 | Общие page header/action bar, feedback, badges, table shell, collection states, pagination и destructive flow переведены на source-of-truth shared/UI-компоненты на экранах брендов и сотрудников; `UiTable` получил безопасную передачу класса внутренней таблицы. Build, 29 unit-тестов и responsive Playwright QA на 320/640/768/1024/1280 px пройдены; UI Design Guard не оставил blocking findings. |
| Admin refactoring | TASK-A027 | Feature `products` выделен в route-level composition, domain components, composables, services, types и validation. `ProductEditor` композирует шесть секций, а состояние, загрузка, submit и validation вынесены в feature-level composables/schema. Stepper остаётся доступным без горизонтальной прокрутки на 320 px; API-contract, импорт/экспорт и порядок операций сохранены. Build, 29 unit-тестов и product Playwright baseline (5 тестов, включая 320/640/768/1024/1280 px) пройдены; UI Design Guard не оставил blocking findings. |
| Admin refactoring | TASK-A028 | Features `categories`, `brands`, `attribute-groups` и `attributes` выделены в thin route views, domain components, composables, services, types и validation. List/detail/form/dialog states унифицированы через UI-kit; категории декомпозированы на list/detail/form/assignments, длинный контент остаётся доступным на 320 px. Build, 29 unit-тестов и 51 catalog/import Playwright-тест пройдены; независимый UI Design Guard одобрил результат. |
| Admin refactoring | TASK-A029 | Features `profile`, `employees`, `access-control`, `audit-log`, `orders` и `contacts` выделены в thin route views, domain components, composables, services и types; auth остался в Pinia. Permissions, заказы, ПДн, audit semantics и API-contract сохранены; list/detail экраны используют breakpoint-specific cards/tables без случайного horizontal scroll. Build, 29 unit-тестов, 66 production E2E и 18 responsive baseline-тестов пройдены; независимый UI Design Guard одобрил результат. |
| Admin refactoring | TASK-A030 | Удалены временные compatibility adapters, все consumers переведены на source-of-truth UI/shared/feature слои, а route views оставлены тонкими. Build, 30 unit-тестов и 136 production E2E/visual/axe/responsive тестов пройдены локально и в Linux Compose; visual QA и независимый UI Design Guard не оставили blocking findings. Phase 7 разрешена. См. [`ADMIN_REFACTORING_BASELINE.md`](../docs/ADMIN_REFACTORING_BASELINE.md). |

## Проверки последней приёмки

`TASK-A030`: Admin build, 30 unit-тестов и 136 production E2E/visual/axe/responsive тестов
пройдены локально и в Linux Compose; независимый UI Design Guard не оставил blocking findings.
