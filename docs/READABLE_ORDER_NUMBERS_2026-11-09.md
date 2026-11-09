# Короткие номера заказов — 2026-11-09

Утверждённый формат новых заказов: `124-3` — клиент 124, третий заказ новой
системы нумерации. Один нормализованный телефон означает одного клиента, даже
при других ФИО/email. Контакты остаются собственным снимком каждого заказа.
Счётчик не сбрасывается по годам и выделяется атомарно с созданием заказа.
Повтор checkout возвращает прежний номер; ошибка откатывает счётчик.

Миграция применена к локальному PostgreSQL. Все 3 существующих заказа сохранили
номера и ID: проверено сравнением до/после без вывода контактных данных.
Исторические номера не перенумерованы, поиск по ним сохранён. Новые номера
автоматически показываются в существующих интерфейсах и письмах.

При обезличивании удаляются связь с клиентом и суффикс; телефон технической
записи клиента очищается после последнего связанного заказа. ID и счётчик
сохраняются без контактов, чтобы не использовать номера повторно. После
очистки телефона повторная покупка получает новый ID. Сопоставление по телефону
не предоставляет публичного доступа к истории заказов.

## Проверки

| Проверка | Результат |
| --- | --- |
| Исходный полный backend-прогон | 769 PASS, 7 SKIP |
| Полный прогон до замечания ревью | 784 PASS, 7 SKIP |
| Заключительный полный прогон | 785 PASS, 7 SKIP, 8389 assertions |
| SQLite: создание, новые номера, retention | 34 PASS, 2 SKIP |
| PostgreSQL: одновременные checkout и гонки retention в обе стороны | 6 PASS, 111 assertions |
| PostgreSQL: API, retention, migration down/up и защита выданных номеров | 16 PASS, 87 assertions |
| PHPStan level 8 | PASS, no errors |
| Pint, 12 затронутых PHP-файлов | PASS |
| Architecture guard | Нет блокирующих нарушений; существующие review advisories сохранены |
| OpenAPI tooling tests | 5 PASS |
| Redocly и project conventions | PASS |
| OpenAPI compatibility | Breaking pattern/nullability changes оформлены как v19.0 с migration plan |
| git diff --check | PASS |

Независимый reviewer проверил выделение номеров, rollback, блокировки,
retention и контракт. Единственное замечание: имя/адрес обезличенного заказа
должны допускать null в AdminOrder. Добавлен тест реального ресурса: сначала
RED на строковом типе, затем GREEN после корректировки схемы.
Отложенных замечаний нет.

Frontend-компоненты не изменены: оба приложения выводят `order_number` как
серверную строку. По регламенту UI_DESIGN_REVIEW.md для backend-only изменения
независимый UI Design Guard не требуется. Изменения GitHub CI добавлены,
удалённый прогон CI не запускался. Коммит не создавался.

## Изменённые файлы

Backend:

- `backend/database/migrations/2026_11_09_000001_add_customer_order_numbers.php`
- `backend/app/Models/Customer.php`
- `backend/app/Models/Order.php`
- `backend/app/Services/OrderNumberService.php`
- `backend/app/Services/OrderCreationService.php`
- `backend/app/Services/Retention/OrderDataDestructionService.php`
- `backend/app/Http/Requests/Api/V1/StoreOrderRequest.php`

Тесты и CI:

- `backend/tests/Feature/Api/OrderCreationTest.php`
- `backend/tests/Feature/Api/ReadableOrderNumberTest.php`
- `backend/tests/Feature/Retention/OrderCustomerRetentionTest.php`
- `backend/tests/Integration/PostgresReadableOrderNumberTest.php`
- `backend/tests/Support/readable_order_number_worker.php`
- `.github/workflows/ci.yml`

Документация и задачи:

- `docs/openapi.json`
- `docs/API.md`
- `docs/DATABASE.md`
- `docs/DECISIONS.md`
- `docs/CURRENT_STATE.md`
- `docs/PERSONAL_DATA_LIFECYCLE.md`
- `docs/OPENAPI_MIGRATION_PLAN.md`
- `docs/superpowers/specs/2026-11-09-readable-order-numbers-design.md`
- `docs/superpowers/plans/2026-11-09-readable-order-numbers.md`
- `docs/READABLE_ORDER_NUMBERS_2026-11-09.md`
- `tasks/DONE.md`

## Развёртывание и откат

Перед новым checkout-кодом требуется миграция
`2026_11_09_000001_add_customer_order_numbers` и принятие формата строгими
потребителями API. Схема расширяет допустимый номер до 64 символов и сохраняет
старые номера. В локальном окружении этот шаг выполнен.
После первого выданного номера schema rollback намеренно запрещён: при откате
кода сохранять таблицу, ID и счётчики. До выделения первого номера down/up
разрешён и проверен на SQLite/PostgreSQL. При откате останавливать checkout
writers и использовать код, читающий оба формата. Инструкции — в разделе v19.0
`OPENAPI_MIGRATION_PLAN.md`.
