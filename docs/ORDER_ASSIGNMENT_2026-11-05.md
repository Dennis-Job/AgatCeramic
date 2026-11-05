# Ответственный менеджер заказа

Заказ можно вручную закрепить за сотрудником, сменить ответственного или снять
назначение. Интерфейс следует обращениям: в карточке заказа поле «Ответственный»
сохраняется при выборе, а список показывает менеджера и фильтр по сотруднику.
Новые и прежние заказы остаются без ответственного до ручного назначения.

## Поведение и доступ

- Кандидаты — активные сотрудники с `orders.manage`. Сервер повторно проверяет
  доступность сотрудника при записи. Роли во frontend не хардкодятся.
- Для назначения, переназначения и снятия требуется `orders.manage`. Назначение
  не выдаёт permissions и не закрывает заказ для остальных сотрудников.
- При `orders.view` сотрудник видит текущее имя ответственного в карточке,
  таблице и мобильной карточке. Каталог кандидатов, как у обращений, доступен
  только с manage; view-only фильтр содержит «Все сотрудники»/«Не назначен».
- Существующий ответственный, который больше не входит в каталог кандидатов,
  остаётся видимым. Снять назначение можно выбором «Не назначен».
- Фильтры по сотруднику/без назначения применяются сервером совместно с поиском,
  группой статусов и оплатой до пагинации. Frontend отправляет `unassigned=1`.
- Назначение не стирает несохранённые статус, оплату и комментарий. Во время
  записи controls и закрытие панели блокируются; запоздалые ответы не возвращают
  закрытую карточку. Ошибка каталога не блокирует таблицу; предусмотрен retry.
- Escape закрывает список кандидатов и возвращает фокус к полю, сохраняя
  карточку открытой. Используется существующий modal portal UiSelect.
  Минимальная ширина таблицы 1200 px сохраняет читаемость фамилий; горизонтальная
  прокрутка локализована в UiTable, страница не выходит за viewport.
- В аудит записываются исполнитель и previous/next IDs в событии
  `order.assignee-changed`. Повтор того же назначения не меняет timestamp и
  не добавляет запись. Ошибка аудита откатывает назначение.

## Данные и контракт

Миграция `2026_11_05_000001_add_assignment_to_orders.php` добавляет nullable
`assignee_id` с FK users/nullOnDelete и индексом, nullable `assigned_at`.
Удаление сотрудника сохраняет заказ и обнуляет FK; явное снятие назначения
обнуляет также assigned_at. Backfill и автоматическое назначение отсутствуют.
Миграция проверена up/down/up на изолированной PostgreSQL и применена к локальной
рабочей БД отдельным `--path`; статус Ran, batch 19. Рабочие заказы в QA не назначались.
Rollback удаляет данные назначения и не выполнялся на рабочей БД.

API v17.0, HTTP-пути остаются `/api/v1`:

- `GET /admin/order-assignees` → `{data:[{id,name}]}`, `orders.manage`.
- `PATCH /admin/orders/{id}/assignee`, `{assignee_id: integer|null}` → 204,
  `orders.manage`; после записи запрашивается актуальная карточка.
- Admin list/detail включают `assignee: {id,name}|null`, `assigned_at: string|null`.
  Добавлены фильтры assignee_id/unassigned. Relations загружаются eagerly.
- Публичный OrderResource и checkout не раскрывают ответственного.

Прежний AdminOrder имел `additionalProperties:false`, поэтому новые обязательные
admin-поля требуют major-версии и обновления строгих внешних валидаторов перед
совместным развёртыванием Admin/backend. Инструкция — в OPENAPI_MIGRATION_PLAN.md;
compatibility checker учитывает объявленный major release, проверки не ослаблялись.

## Файлы

- Backend: OrderAssignmentController, AssignOrderRequest, OrderAssignmentService,
  OrderAssigneeQuery, Order/OrderPolicy, OrderQuery, ListOrdersRequest,
  AdminOrderResource, AuditLabels, routes/api/v1/admin.php, новая миграция и
  OrderAssignmentTest. Предыдущий statuses[] сохранён.
- Admin orders: useOrderAssignees, useOrdersWorkspace, services/orders,
  types/order.types, OrdersWorkspace, OrdersList, OrderDetails.
- Audit UI: features/audit-log/utils/auditActions.ts.
- Tests/fixtures: orderAssignment, adminNotifications, orders, adminBaseline/
  adminBaselineApi, accessSalesContacts, contactNotifications, navigation;
  восемь принятых Windows/Linux route-orders snapshots.
- Документация: API.md, openapi.json, OPENAPI_MIGRATION_PLAN.md, этот отчёт,
  docs/superpowers/plans/2026-11-05-order-assignment.md, tasks/DONE.md.

## Проверки

- Admin build, lint, format — PASS; полный unit 306 tests / 59 files PASS.
  Предупреждение Vite о размере общего bundle сохраняется.
- Исходный профиль Windows — 38/38 PASS (дополнительно соседние контакты,
  уведомления и ограниченные permissions).
- Финальные профили Windows и Linux — по 26/26 PASS: назначение/переназначение/
  снятие, фильтр по сотруднику/без назначения, ошибки каталога/PATCH и retry,
  view-only, сохранение всех черновиков, slow PATCH/busy, deep links,
  Escape/focus, длинный контент, все контрольные ширины и четыре snapshots.
- Full backend: 768 total, 761 passed, 7 штатных skips, 8246 assertions.
  Использован `php -d memory_limit=512M vendor/bin/phpunit`: первоначальный
  стандартный 128 MB composer-прогон исчерпал память на существующем большом
  fixture ProductImageImportTest. Код/ожидания этого теста не менялись.
- Новый assignment suite — 9 tests / 89 assertions PASS; PostgreSQL affected
  suite — 30 tests / 239 assertions PASS, включая старые workflow/оплату.
  Изолированная migration up/down/up, FK/index/nullOnDelete — PASS.
- Composer validate/audit, full PHPStan, architecture без blocking нарушений,
  full Pint 699 files, OpenAPI lint/conventions и 5 tooling tests — PASS.
  Compatibility checker принимает объявленную breaking major v17.0 с инструкцией
  миграции строгих валидаторов; запреты не ослаблялись.
- Независимый UI Design Guard и focused code/security review — PASS. Приняты
  восемь actual snapshots Windows/Linux; обновлены только route-orders PNG.
  Финальные min-width/portal уточнения и новые actual screenshots также приняты.
- Реальный localhost: просмотрен каталог доступных сотрудников, подтверждено
  исправление Escape и возврат фокуса; рабочие назначения не менялись.

Артефакты в `frontend/admin/.tmp/`: `order-assignment-final-windows.log`,
`order-assignment-final-linux.log`, `order-assignment-e2e.log`,
`order-assignment-backend-full-512m.log`, `order-assignment-static.log`,
`order-assignment-final-build.log` и соответствующие каталоги PNG.
Полный Admin E2E не повторялся: shared UI/layout/tokens не менялись.
Новые зависимости и коммиты не создавались.
