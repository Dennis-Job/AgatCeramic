# TASK-A092 — Цвета кнопок и поверхности Admin без рамок

Дата: 2026-10-04.

## Результат

- Опасные кнопки используют существующий фон `error-500`; hover — `error-600`.
- Сплошные серые disabled/loading кнопки используют существующий `gray-400`.
- `UiAlert` и построенные на нём `UiNotification` отображаются без рамок.
- Общие внешние, белые, внутренние и state панели отображаются без рамок.

Изменены общие компоненты и стили, поэтому правки применяются во всём Admin.
Новые цветовые tokens не добавлены.

## Проверки

- Lint, production build, scoped Prettier и `git diff --check` — пройдены.
- `tests/notifications.test.ts` — 6/6 passed.
- В открытом `/ui-kit` проверены computed styles: danger `rgb(240, 68, 56)`
  (`error-500`), серые кнопки `rgb(152, 162, 179)` (`gray-400`).
- Все 31 внешняя и внутренняя панель имеют border width `0px`; сообщения и
  всплывающее уведомление также имеют `0px`. Role `alert` у уведомления сохранён;
  ручное закрытие проверено в браузере.
- UI Design Guard — accepted, без замечаний; проверка учитывает явно выбранные
  пользователем оттенки без корректировки цветов.
- Полный unit/E2E suite и отдельные responsive ширины не запускались.
- Vite сообщает о существующем JS-чанке больше 500 kB.

## Изменённые файлы

- `frontend/admin/src/components/ui/UiButton.vue`
- `frontend/admin/src/components/ui/UiAlert.vue`
- `frontend/admin/src/styles/cards.css`
- `frontend/admin/src/components/shared/UI_KIT.md`
- `docs/ADMIN_SELLER_REDESIGN_PLAN.md`
- `tasks/DONE.md`
- Этот отчёт.
