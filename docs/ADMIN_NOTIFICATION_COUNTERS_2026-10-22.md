# Счётчики уведомлений Admin — 2026-10-22

Выполнено по запросу владельца от 2026-10-21 и уточнению об обновлении после
сохранения статуса. Считаются все обращения (callback/email/partner) и заказы
со статусом `new`. Колокольчик показывает сумму доступных сотруднику разделов.

## Результат

- Общий `UiNotificationCount`: коралловый красный фон `#ff6b52`, белый текст
  12 px / 700, высота 16 px, радиус 4 px. Ноль скрыт; группировка тысяч ru-RU.
  Bell ограничен «99+», пункты меню — «9 999+»; полное число доступно в title
  и accessible description ссылки/кнопки. Другие badges не менялись.
- Счётчики находятся у колокольчика, «Обращений» и «Заказов» в обычной,
  свёрнутой и компактной навигации. Один state Header исключает дублирование
  polling-запросов между потребителями.
- Данные берутся из `meta.total` существующих защищённых списков, независимо
  от длины preview. Запросы только при `contacts.view`/`orders.view`.
  Обновление при открытии, каждые 30 секунд и сразу после успешного сохранения
  статуса обращения/заказа. Ошибочный PATCH не инвалидирует данные.
- Локальный сигнал не содержит ПДн. При сохранении инвалидируется даже уже
  выполняющийся фоновый запрос: его поздний ответ не восстанавливает старое
  число. При unmount подписка и таймер удаляются; при смене сотрудника или
  permissions очищаются данные и игнорируются старые ответы.
- Сбой одного списка не удаляет успешные данные другого; панель сообщает об
  ошибке и предлагает retry. В панели есть обращения всех типов и ссылка на
  новые заказы; открытие не меняет статус записи.

## Изменённые файлы

- `frontend/admin/src/components/ui/UiNotificationCount.vue` — новый primitive.
- `frontend/admin/src/styles/{tokens,navigation}.css` — оформление и отступы.
- `frontend/admin/src/layouts/components/{AdminHeader,AdminNotifications,AdminNavigation,AdminNavigationLinks}.vue`.
- `frontend/admin/src/features/notifications/composables/useAdminNotifications.ts`
  заменяет callback-only `useContactNotifications.ts`.
- `frontend/admin/src/features/notifications/services/notificationChanges.ts` —
  локальный сигнал обновления после успешной смены статуса.
- `frontend/admin/src/features/{contacts,orders}/composables/use*Workspace.ts` —
  отправка сигнала после сохранения.
- `frontend/admin/src/components/shared/{UiKitShowcase.vue,UI_KIT.md}` — каталог,
  пример и актуальные правила нового primitive.
- Unit: `adminNotifications.test.ts`, `headerNotifications.test.ts`,
  `notificationCount.test.ts`, `uiKitShowcase.test.ts`. Старые data/UI тесты
  `contactNotifications.test.ts` разделены с сохранением прежних UI regressions.
- E2E: новый `notificationCounters.spec.ts`; адаптированы
  `contactNotifications.spec.ts`, `headerPopovers.spec.ts`, `navigation.spec.ts`,
  `adminBaselineApi.ts`, `contracts/{approvedPalette,approvedContrast}.ts`.
- `docs/API.md`, этот отчёт, `tasks/DONE.md`.

## Проверки и границы

- Production build, lint, format:check — PASS; все 214 unit-тестов — PASS.
- Полный Windows E2E/visual baseline: 644 сценария, первоначально 627 PASS и
  17 FAIL. В 13 navigation-сценариях mock не учитывал новый polling заказов;
  mock исправлен, вся навигация повторно PASS. Один tooltip-сценарий при
  повторном запуске PASS без изменений продукта/теста. Остались три известные
  desktop/hosted Windows pixel-разницы в SKU/permission glyphs на
  `/products`, `/roles`, `/permissions`; это те же области, которые описаны в
  [CI repair report](CI_PR5_REPAIR_2026-10-19.md#hosted-windows-follow-up).
  Baselines и tolerance не изменялись; полный прогон не объявляется зелёным.
- После добавления немедленного обновления выполнен итоговый targeted E2E:
  13/13 PASS — counters, contacts notifications, Seller workspaces, включая
  `/orders` и `/contacts`. Новая проверка реальной формы подтверждает:
  failed PATCH сохраняет число, successful PATCH сразу скрывает нулевые
  counters, нового HTTP document request нет. Фоновая гонка проверена unit-тестом.
- Повтор navigation/action tooltip/new counters: 36 PASS; первоначальная ошибка
  дополнительного теста считала Vue Router history за перезагрузку, исправлена
  проверкой именно HTTP document requests и прошла в итоговых 13/13.
- Независимый UI Design Guard: accepted, P1/P2 нет. Просмотрены PNG
  320/640/768/1024/1280 px, панель уведомлений и condensed 1024/1280 px.
- Известное ограничение утверждённого референса: white/#ff6b52 имеет контраст
  около 2.81:1 при 12 px bold, ниже WCAG AA 4.5:1. Палитра сохранена;
  точечное исключение оформлено по существующей практике проекта, raw axe
  evidence сохраняется. Остальные пары контраста продолжают проверяться.
- API-контракт и БД не менялись, миграций нет. E2E использует изолированные
  mock API. Письма не отправлялись, commit не создавался.
