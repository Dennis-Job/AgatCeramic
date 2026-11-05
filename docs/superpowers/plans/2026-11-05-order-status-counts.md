# Счётчики активных очередей заказов

**Goal:** по образцу обращений показывать реальные числа у «Новые», «В работе»,
«В доставке», включая ноль; на узком экране те же значения в select.

**Semantics:** вся текущая очередь, независимо от search/payment/assignee/status
list filters, пагинации и месяца статистики. Working включает все активные
нетерминальные этапы API, кроме new/shipped, точно как фильтр рабочей очереди.
Счётчики обновляются после смены статуса. Ошибка не заменяется нулями,
не блокирует очередь и имеет retry. Не добавлять badges All/terminal по образцу contacts.

**Contract:** новый read-only GET /admin/order-status-counts при orders.view,
ответ {data:{new:integer>=0,working:integer>=0,shipped:integer>=0}}, OpenAPI v17.1.
Без миграций, новых dependencies и изменений утверждённой палитры/shared UI.

- [x] Backend TDD: grouped aggregate без загрузки заказов/ПДн, правильные working
  stages включая custom/inactive/terminal, zeros, read permissions, ignore filters.
  Thin controller/route/query, контракт и docs, PostgreSQL и static checks.
- [x] Frontend TDD: service/types/count composable, counts/refresher в workspace,
  UiBadge slot в UiTabs с теми же semantic tones и nowrap; mobile select labels.
  Generation/dispose/invalidation guards, countsError notification/retry.
- [x] Unit/E2E: success/zero/error/retry/late requests/status update/permissions,
  сохранение одной строки tabs на 1024/1102/1280, mobile 320/640/768 и независимый
  UI Guard. Обновить только проверенные order snapshots, task ledger и отчёт.

Коммиты не создавать. Работать в текущем checkout, до задачи Git clean.

Результат: [файлы и проверки](../../ADMIN_ORDER_COUNTS_2026-11-05.md).
