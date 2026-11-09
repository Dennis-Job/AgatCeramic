# Разделение этапов заказа и оплаты

Дата: 2026-11-09. Выполнено по замечанию владельца к карточке заказа.

«Ожидает оплаты» и «Оплачен» удалены из выбора нового статуса заказа.
Отдельный статус оплаты сохраняет все варианты. Сервер отклоняет переход в
`awaiting_payment`/`paid` с 422, включая повтор текущего legacy-статуса.
Старые заказы, их русские подписи, история и включение в очередь «В работе»
сохранены. Из старого статуса разрешён переход в обычный этап без изменения оплаты.

План выполнен: проверить независимость этапа и оплаты; добавить серверное правило
доступности; отфильтровать варианты общего UiSelect; проверить старые заказы,
документировать контракт и провести независимое UI-ревью. Схема/данные БД,
палитра и общий UI-kit не менялись. Миграций и commit нет.

## Изменённые файлы

- `backend/app/Support/OrderStatusSelection.php` — единое правило доступности.
- `backend/app/Http/Resources/OrderStatusResource.php` — серверный `is_selectable`.
- `backend/app/Services/OrderStatusManagementService.php` — защита переходов.
- `backend/tests/Feature/Api/OrderStatusManagementTest.php` — API-регрессии.
- `frontend/admin/src/features/orders/types/order.types.ts` — совместимое поле.
- `frontend/admin/src/features/orders/components/OrderDetails.vue` — варианты,
  legacy-подпись и disabled save до выбора разрешённого этапа.
- `frontend/admin/e2e/orders.spec.ts` — обычные/legacy-заказы и отдельная оплата.
- `docs/API.md`, `docs/DATABASE.md`, `docs/openapi.json`,
  `docs/OPENAPI_MIGRATION_PLAN.md` — контракт v18.0 и сохранение старых данных.
- Этот отчёт и `tasks/DONE.md` — результат и статус задачи.

## Проверки

- До реализации новые backend-assertions воспроизвели отсутствие поля и
  ошибочно разрешённый переход в legacy-статус.
- Backend: PHPUnit 776 тестов, 8302 assertions; 769 PASS, 7 штатных skips.
- Профильные API/OpenAPI: 67 тестов, 3132 assertions; 66 PASS, 1 штатный skip.
- PHPStan, Pint, architecture guard — PASS (старые complexity notices остаются).
- Composer validate --strict и audit --locked — PASS, уязвимостей не обнаружено.
- Admin build, lint, format:check, 309 unit tests — PASS.
- Production Chromium orders E2E — 16/16 PASS; ширины 320/640/768/1024/1280,
  клавиатура, доступность, права, ошибки/повтор, drafts и независимость оплаты.
- OpenAPI lint/conventions и 5 tooling tests — PASS. Строгая проверка контракта
  выявила, что добавление поля нарушает previous additionalProperties:false;
  поэтому обновлена major-редакция v18.0 и добавлен явный migration plan.
- Независимый UI Design Guard: важных и блокирующих замечаний нет.

Визуальные артефакты находятся в `frontend/admin/test-results/`, включая
`legacy-order-status.png`, `order-status-options.png` и
`order-status-separated-from-payment.png`. Сохранены также 5 размеров drawer.

Ограничение окружения: профильный PostgreSQL-прогон не выполнен успешно:
25 тестов завершились до assertions с `could not find driver`. PHP 8.3.1
не загружает pdo_pgsql, в том числе при явном пути к установленной DLL.
Рабочая БД не затрагивалась. Полный SQLite-прогон не заменяет PostgreSQL-проверку.
Build сохраняет существующее предупреждение о bundle >500 kB.
