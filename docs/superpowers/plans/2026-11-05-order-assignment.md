# Назначение ответственного за заказ

**Goal:** ручное закрепление менеджера по действующему образцу обращений.
Пользователь попросил эту функцию в чате; автоматическое назначение не требуется.

**Architecture:** nullable assignee_id/assigned_at в orders, FK users с nullOnDelete,
index для фильтрации. Отдельный request/controller/service/query для назначения;
OrderQuery eagerly загружает assignee, AdminOrderResource возвращает {id,name}|null
и assigned_at. Публичный OrderResource не раскрывает сотрудника. Выбор не меняет
permissions и не ограничивает работу других сотрудников.

**Contract:** GET /admin/order-assignees при orders.manage возвращает {data:[{id,name}]}.
PATCH /admin/orders/{order}/assignee при orders.manage принимает present nullable
integer assignee_id, возвращает 204. Цель — active сотрудник с orders.manage,
проверяется на сервере; повторное назначение идемпотентно. Аудит
order.assignee-changed хранит from/to IDs. List filters assignee_id/unassigned
сохраняют поиск, оплату, statuses[] и серверную пагинацию. OpenAPI v17.0:
добавление полей в строгий AdminOrder с additionalProperties:false требует
major-версии и инструкции обновления старых response validators. Маршрут остаётся v1.

## План

- [x] Backend TDD: additive migration/model/resource/query/policy, candidate list и
  PATCH, validation/permissions/inactive targets/idempotency/audit/delete/filter/
  public privacy. PostgreSQL migration up/down/up на изолированной БД; API docs,
  format/static/contract checks. Существующие dirty изменения сохранить.
- [x] Admin: service/types, candidate loading/retry, select ответственного в drawer,
  имя в таблице/мобильной карточке, фильтр «Все сотрудники / Не назначен / имя».
  Read-only видит имя; только manage может менять. Сохранять черновики оплаты,
  статуса и комментария при назначении. Busy/error guard, late-response guard,
  фокус и существующая адаптивность. Нынешнее недоступное назначение не скрывать.
- [x] Unit/E2E: assign/reassign/unassign, deny/view-only, candidate errors/retry,
  mutation errors, drafts сохранены, filter+pagination, mobile/desktop/screenshots.
- [x] Независимый UI Design Guard, релевантные build/lint/format/unit/E2E/backend/
  contract checks, task ledger и отчёт. После тестирования применить только новую
  additive migration к локальной рабочей БД; не откатывать рабочие данные.

**Files:** backend assignment module + existing Order/OrderQuery/AdminOrderResource/
OrderPolicy/audit labels/routes/migration/tests/OpenAPI; frontend orders feature,
audit action label и соответствующие unit/E2E/fixtures; docs/tasks.

**Constraints:** утверждённые tokens/палитра/UI primitives, без новых dependencies,
без commit. В текущем checkout сохраняются предыдущие несвязанные с назначением
уточнения вкладок и оплаты; агент backend не редактирует frontend/report/tasks.

## Результат

Назначение реализовано и принято независимым UI/code/security Guard. Локальная
миграция применена точным --path, batch19; реальные назначения не менялись.
Полные проверки и изменённые файлы: [ORDER_ASSIGNMENT_2026-11-05.md](../../ORDER_ASSIGNMENT_2026-11-05.md).
