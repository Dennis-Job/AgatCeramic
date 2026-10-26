# Страница уведомлений Admin

Запрос владельца от 2026-10-26: убрать всплывающее окно колокольчика,
сделать страницу с разделами «Заказы» и «Обращения» слева и новыми записями справа.

## Поведение

- Колокольчик ведёт на `/notifications`; hover не открывает окно.
- По умолчанию выбран раздел «Заказы», при отсутствии права — первый доступный.
  Выбор хранится в `?section=orders|contacts`; back/forward поддерживается Router.
- От 1024 px разделы слева, на меньших ширинах — над списком.
- Уведомления — записи со статусом `new`; просмотр не меняет статус.
  Счётчики используют API `meta.total`, список имеет пагинацию по 5 записей.
- Шапка и страница используют один экземпляр состояния, принадлежащий AdminLayout.
  Обновление: каждые 30 секунд, вход на страницу и успешное сохранение статуса.
- Ошибка раздела не скрывает второй. Сохранены loading/error/retry/empty,
  доступный фокус и очистка данных при смене сотрудника/permissions.
- Ссылки открывают конкретные записи `/orders?order=<id>` и
  `/contacts?contact=<id>`. Детали заказа защищены от устаревших ответов.
- Палитра и UI-kit сохранены. API-контракт, backend и миграции не изменены.

## Изменённые файлы

Пути ниже относительно `frontend/admin/`:

- `src/views/NotificationsView.vue`, `src/router/index.ts` — маршрут и тонкий view.
- `src/features/notifications/components/NotificationsWorkspace.vue` — страница.
- `src/features/notifications/composables/{useAdminNotifications,notificationContext}.ts`
  — пагинация, отдельные ошибки, общее состояние.
- `src/layouts/AdminLayout.vue`, `src/layouts/components/{AdminHeader,AdminNotifications}.vue`
  — владение состоянием и колокольчик-ссылка.
- `src/features/orders/components/OrdersWorkspace.vue`,
  `src/features/orders/composables/useOrdersWorkspace.ts` — переход к заказу и защита запросов.
- `tests/{headerNotifications,adminNotifications,orderNotificationSelection}.test.ts`.
- `e2e/{contactNotifications,notificationCounters,headerPopovers,contactsRedesign,scrollHeader,actionTooltips}.spec.ts`.
- `src/components/shared/UI_KIT.md` — актуальный контракт.

Также обновлены `tasks/DONE.md`, этот отчёт и
`docs/superpowers/plans/2026-10-26-notifications-page.md`.

## Проверки

- Lint, format, TypeScript/production build: PASS.
- Полный unit: 49 файлов, 247 тестов PASS.
- Профильный production E2E: 50/50 PASS. Проверены 320/640/768/1024/1280 px,
  axe, клавиатура, ссылки, пагинация, права, ошибка одного раздела и retry/focus.
- Независимый UI Design Guard: accepted, blocking findings отсутствуют.
  Проверены код и 9 production screenshots: пять ширин, заказы, loading/error/empty.
- Живой Admin: переход из товаров с колокольчика на новую страницу проверен.
  Контейнер `admin` перезапущен для сброса старого Vite module cache.
- Полный `npm run test:e2e -- --workers=4`: **672 PASS / 8 FAIL**, 6,5 минуты.
  Все failures — screenshot mismatches Windows baseline: `/media`
  (loading/empty/error/default), `/products`, `/roles`, `/permissions`, `/settings`.
  Этот же список зафиксирован до задачи в
  [отчёте закрепления действий](CONTENT_STICKY_ACTIONS_2026-10-26.md).
  В проверенных diff изображениях расхождения относятся к содержимому страниц,
  шапка не выделена. Эталоны не обновлялись; функциональные E2E прошли.

Локальные screenshots: `frontend/admin/.tmp/notifications-page-review/`.
Commit не создавался.
