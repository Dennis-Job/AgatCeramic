# Заказы в стиле обращений — 2026-11-05

Последующее расширение: [ответственный менеджер](ORDER_ASSIGNMENT_2026-11-05.md)
реализует ручное назначение по обращению владельца. Описание отсутствия назначения
ниже фиксирует состояние до этого расширения.

По запросу владельца заказы используют тот же рабочий паттерн, что обращения:
месячная статистика, статусы над списком, компактные фильтры и правая панель
вместо карточки под таблицей. Утверждённая палитра и типографика сохранены.
Обе отмеченные поясняющие строки из статистики обращений удалены.

## Уточнение цветов — 2026-11-09

По прямому указанию владельца бейджи «В обработке», «Ожидает уточнения»,
«Комплектация» и счётчик вкладки «В работе» используют жёлтый `warning`.
«Подтверждён», «Передан в доставку» и счётчик «В доставке» используют голубой
`additional`. Цвета заданы в общем feature-mapper
`frontend/admin/src/features/orders/utils/orderStatusTone.ts`, который уже
используется таблицей, мобильными карточками, деталями и счётчиками.
Токены UiBadge и другие статусы не меняются; API и миграции не затронуты.

Проверки: build/lint/format, 309 unit, 16 профильных production E2E PASS.
Независимый UI Design Guard принял mapping и screenshots на 320–1280 px.
Для live-проверки перезапущен только dev-контейнер Admin: Vite сохранял старый
transform после изменения файла через Windows bind mount.

## Бейджи оплаты — 2026-11-09

По указанию владельца статусы оплаты отображаются через существующий `UiBadge`:
«Возврат» — красный `danger`, «Оплачено» — зелёный `success`,
«Частично оплачено» — голубой `additional`, «Ожидает оплаты» и «Не оплачено» —
жёлтый `warning`. Общий mapper
`frontend/admin/src/features/orders/utils/paymentStatusTone.ts` используется
в `OrdersList.vue` (desktop/mobile) и `OrderDetails.vue` (сводка заказа).
Неизвестные коды получают нейтральный тон. Подписи, формы, токены UI-kit,
API и БД не менялись; миграции не нужны.

Build/lint/format, 309 unit и 16 профильных production E2E PASS.
Визуально проверена реальная карточка с зелёным бейджем оплаты; адаптивные
снимки 320/640/768/1024/1280 приняты независимым UI Design Guard без замечаний.
Изменены mapper, два компонента, этот документ и статус в `tasks/DONE.md`.

Следующее уточнение владельца 2026-11-09: текст бейджей оплаты не переносится
внутри бейджа (`whitespace-nowrap`), как у статуса заказа. Правило применяется
в обоих представлениях `OrdersList.vue` и в сводке `OrderDetails.vue`.
Целые бейджи могут переноситься flex-контейнером; desktop-таблица сохраняет
локальную горизонтальную прокрутку. Build, ESLint, Prettier и 16 профильных
production E2E PASS. Реальный «Ожидает оплаты» проверен в одну строку.

## Уточнение: расположение сохранения оплаты

По следующему замечанию владельца в `OrderDetails.vue` select оплаты и кнопка
«Сохранить» от 640 px стоят в одной строке с теми же колонками и gap, что у
статуса заказа. Accessible name кнопки остаётся «Сохранить оплату». На узком
экране оба блока одинаково складываются вертикально. Дополнительные поля
суммы/способа/документа остаются ниже; loading, disabled, permissions и payload
не менялись. Новых API, миграций и логики назначения менеджера нет.

По вопросу владельца проверены Order, AdminOrderResource, status/payment/comment
services: постоянного ответственного у заказа сейчас нет. Сотрудник определяется
как исполнитель конкретного действия и записывается в историю/аудит/авторство;
это не закрепление заказа. Добавление назначения требует отдельного решения.

Проверки этого уточнения: build/lint/format PASS, 301 unit PASS, существующие
11 order E2E PASS, независимый UI Design Guard принял код и свежие screenshots
320/640/768/1024/1280. Новый тест для локальной стилизации не добавлялся.
Артефакты: `.tmp/orders-payment-aligned-build.log`,
`.tmp/orders-payment-aligned-e2e.log`, `.tmp/orders-payment-aligned/`
в `frontend/admin/`. Изменены OrderDetails.vue, этот отчёт и tasks/DONE.md.

## Уточнение: шесть рабочих очередей

После замечания владельца о количестве вкладок очередь показывает «Все»,
«Новые», «В работе», «В доставке», «Завершённые», «Отменённые». На узких
экранах тот же набор используется в select. «В работе» объединяет активные
нетерминальные этапы из API, кроме новых и переданных в доставку: обработку,
уточнение, подтверждение, ожидание оплаты, оплату и комплектацию. Будущие
промежуточные нетерминальные этапы также попадают в группу.

Оплата фильтруется отдельно, а в панели заказа остаётся полный справочник
статусов. Для правильной выборки до пагинации добавлен совместимый фильтр
`statuses[]` в API v16.2. Данные и workflow заказов не мигрируются.

Изменены `ListOrdersRequest.php`, `OrderQuery.php`, `AdminOrderWorkspaceTest.php`,
OrdersWorkspace/useOrdersWorkspace, orders service/types, unit/E2E заказов,
API.md/OpenAPI/migration plan, план, этот отчёт и tasks/DONE.md.

Проверки уточнения: build/lint/format PASS, 301 unit PASS, 23 профильных
Windows E2E PASS (в том числе четыре прежних order snapshots), PostgreSQL
5 tests / 44 assertions PASS, API/OpenAPI 9 tests / 2763 assertions PASS.
Pint, PHPStan, architecture (без blocking нарушений), OpenAPI tooling/lint/
conventions и compatibility с HEAD PASS. Независимый UI Design Guard принял
код и screenshots 1024/1102/1280, mobile drawers 320/640/768; шесть вкладок
в одну строку подтверждены E2E. Общие UI/tokens не менялись, полный E2E и
полный backend suite для этого локального уточнения повторно не запускались.

Артефакты уточнения: `frontend/admin/.tmp/orders-compact-tabs-windows.log`,
`.tmp/orders-compact-tabs-windows/`, `.tmp/orders-compact-tabs-backend.log`.

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
