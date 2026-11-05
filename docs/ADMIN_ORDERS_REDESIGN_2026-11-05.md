# Заказы в стиле обращений — 2026-11-05

По запросу владельца заказы используют тот же рабочий паттерн, что обращения:
месячная статистика, статусы над списком, компактные фильтры и правая панель
вместо карточки под таблицей. Утверждённая палитра и типографика сохранены.
Обе отмеченные поясняющие строки из статистики обращений удалены.

## Поведение

- Показатели согласованы в чате: всего заказов, новые, завершённые, отменённые.
  Выбор месяца и сравнение с предыдущим календарным месяцем работают независимо
  от фильтров очереди. Считается дата поступления в Europe/Moscow, статусы текущие.
  Ненулевое значение при нулевой базе — «Нет базы»; оба нуля — 0%.
- Справочник статусов приходит из API: вкладки от 1024 px, select ниже;
  поиск по номеру/клиенту/телефону/email и фильтр оплаты сохранены.
- Desktop-таблица в контейнере 1280: заказ, клиент, статус, оплата, сумма,
  получено. Клик по строке и кнопка с клавиатуры открывают панель.
  Page-sticky шапка и локальная горизонтальная прокрутка; ниже 768 px — карточки.
- Правая панель до 544 px; состав, доставка, телефон/email, статус, оплата,
  история и внутренние комментарии. Deep links `?order=ID` открывают заказ вне
  загруженной страницы; закрытие удаляет query и возвращает фокус без прокрутки.
- Несохранённые статус, оплата и комментарий защищены подтверждением при
  закрытии/смене заказа/переходе. Сохранение статуса не стирает черновик оплаты
  и наоборот. Во время сохранения закрытие и смена заказа блокируются.
- Запоздалые ответы списка и деталей не заменяют актуальный выбор; закрытие
  инвалидирует detail request. Ошибки статистики, списка и деталей имеют retry;
  ошибка статистики не блокирует работу с заказами.

## Архитектура и API

`AdminSidePanel` и `AdminMonthlyStatistics` переиспользуются заказами и обращениями.
API и бизнес-состояние принадлежат feature services/composables; permissions
orders.view/orders.manage/payments.manage сохранены. Новые зависимости и
миграции не нужны, рабочие данные не изменялись.

Добавлен read-only `GET /admin/order-statistics?month=YYYY-MM` при orders.view.
Контракт, диапазон 1900–9998, структура метрик и ошибки описаны в API.md/OpenAPI
v16.1; совместимость существующих endpoints сохранена.

## Изменённые файлы

- Backend: OrderStatisticsController, OrderStatisticsRequest, OrderStatisticsQuery,
  OrderStatisticsTest и routes/api/v1/admin.php.
- Admin shared: AdminSidePanel.vue, AdminMonthlyStatistics.vue, UI_KIT.md.
- Orders: OrdersWorkspace.vue, OrdersList.vue, OrderDetails.vue,
  OrderStatistics.vue, useOrdersWorkspace.ts, useOrderStatistics.ts,
  services/orders.ts, types/order.types.ts, utils/orderStatusTone.ts.
- Contacts: ContactsWorkspace.vue, ContactStatistics.vue.
- Unit/E2E: orderNotificationSelection, orderStatistics, orders,
  orderStatisticsFixtures, contactsMonthlyStatistics, sellerWorkspaces,
  accessSalesContacts, adminBaseline/adminBaselineApi, navigation,
  contactNotifications и принятые визуальные эталоны изменённых страниц.
- Документация: этот отчёт, API.md, openapi.json, OPENAPI_MIGRATION_PLAN.md,
  docs/superpowers/plans/2026-11-05-orders-redesign.md, tasks/DONE.md.

## Проверки

- Admin: build, ESLint, Prettier — PASS; полный unit — 300 tests / 58 files PASS.
  Сохраняется существующее предупреждение Vite о размере общего bundle.
- Backend: полный composer test — 750 passed, 7 штатных PostgreSQL-only skips
  в SQLite, 8117 assertions. Новый endpoint отдельно прошёл PostgreSQL:
  14 tests / 41 assertions в изолированной тестовой БД.
- Финальная перепроверка нового endpoint и OpenAPI документации/маршрутов:
  18 tests / 2760 assertions PASS. Pint, PHPStan, architecture, Composer
  validate/audit, OpenAPI lint/conventions/tooling и compatibility — PASS.
- Финальный Windows-профиль — 57/57 PASS: заказы, обращения, месяцы,
  права, оплата, комментарии, несохранённые формы, stalled deep link,
  адаптивность, длинные данные, доступность и восемь принятых snapshots.
- Linux-профиль — 33/33 PASS, включая обновлённые сценарии shared-panel,
  все ширины, длинные данные, keyboard focus и motion/reduced motion.
  После принятия Linux-эталонов отдельный прогон восьми snapshots и
  адаптивности orders/contacts на 320–2560 px — 24/24 PASS.
- Полный Windows E2E до принятия новых снимков: 701/719 PASS, 18 failures.
  Восемь ожидаемых изменений orders/contacts независимо просмотрены и приняты;
  последующий Windows-профиль подтвердил их. Два сбоя product tooltip и
  page indexing повторно прошли в том же финальном прогоне без изменений кода
  этих функций. Остались восемь screenshot differences на незатронутых страницах:
  media (normal/loading/empty/error), products, roles, permissions, settings.
  Их эталоны, palette и допуски не менялись. Полный suite повторно не запускался.
- Независимый UI Design Guard принял код, desktop, drawers на
  320/640/768/1024/1280 px, длинные данные на 320/1280, контакты и восемь
  Windows baseline actual. Замечания по фокусу изначально открытой панели
  и потере соседнего черновика устранены и проверены regression tests.
  Отдельный независимый Guard принял восемь Linux baseline actual после
  просмотра; обновлены только orders/contacts Linux/Windows PNG.
- Реальный localhost проверен через браузер: удалённые тексты отсутствуют,
  статистика получает реальные агрегаты, детали заказа открываются и
  закрываются с возвратом фокуса. Рабочие заказы не редактировались.

Логи и screenshots: `frontend/admin/.tmp/orders-redesign-full-windows.log`,
`orders-redesign-accepted-windows.log`, `orders-redesign-linux-final.log`,
`orders-redesign-baselines-linux.log`, `orders-redesign-baselines-accepted-linux.log`;
PNG находятся в соответствующих каталогах `.tmp/`, длинные данные —
`.tmp/a060-visual/`. macOS runner недоступен; Darwin snapshots не обновлялись.
Коммиты не создавались.
