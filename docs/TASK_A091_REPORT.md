# TASK-A091 — Текстовые палитры Admin на оттенке 500

Дата: 2026-10-04.

## Результат

Текст Admin использует исходные значения существующих палитр `500` через
Tailwind classes и CSS variables: `gray`, `primary`, `blue-light`, `success`,
`warning` и `error`. Общий `--admin-color-text` ссылается на
`--admin-color-gray-500`.

Удалены добавленные в первой реализации текстовые tokens, переопределения
utilities и строки этих tokens в UI-kit. Значения исходной палитры не изменены.
Применён явный выбор пользователя без корректировки цветов по контрасту.

## Проверки

- `npm run lint` и `npm run build` — пройдены.
- Scoped Prettier изменённых исходников, UI-kit и отчёта — пройден.
- `git diff --check` — пройден.
- Поиск по исходникам подтверждает отсутствие добавленных текстовых tokens и
  текстовых classes оттенков `600`–`900` для указанных палитр.
- Unit/E2E не запускались: изменение ограничено ссылками на цвета.
- Vite сообщает о существующем JS-чанке больше 500 kB.

## Затронутые области

- `frontend/admin/src/styles/` — прямые ссылки на существующие оттенки `500`.
- `frontend/admin/src/components/ui/`, `src/components/shared/`, `src/features/`,
  `src/layouts/` и auth views — текстовые utilities оттенка `500`.
- `docs/ADMIN_SELLER_REDESIGN_PLAN.md`, документация UI-kit и `tasks/DONE.md`.
