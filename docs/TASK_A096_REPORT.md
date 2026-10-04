# TASK-A096 — Основной стиль примера UI-kit

Дата: 2026-10-04.

Для примера «Информационное сообщение.» в UI-kit добавлен и явно выбран
`UiAlert tone="primary"` с существующими `primary-50` и `primary-500`.
Остальные `info/additional` блоки используют дополнительную палитру.

## Проверки

- Lint, production build, scoped Prettier и `git diff --check` — пройдены.
- В браузере подтверждены цвета примера: фон `rgb(235, 244, 255)`, текст
  `rgb(0, 91, 255)`, role `status`. Дополнительный пример использует
  фон `rgb(240, 249, 255)` и текст `rgb(11, 165, 236)`.
- UI Design Guard — accepted; primary tone применён только к выбранному примеру.
- Unit/E2E и отдельные responsive проверки не запускались. Vite сообщает о
  существующем JS-чанке больше 500 kB.

## Изменённые файлы

- `frontend/admin/src/components/ui/UiAlert.vue`
- `frontend/admin/src/components/shared/UiKitShowcase.vue`
- `frontend/admin/src/components/shared/UI_KIT.md`
- `tasks/DONE.md`
- Этот отчёт.
