# TASK-A094 — Дополнительное информационное сообщение

Дата: 2026-10-04.

В общем `UiAlert` добавлен `tone="additional"`: фон `blue-light-50`, текст
`blue-light-500`, без рамки. Цвета совпадают с `UiBadge tone="additional"`.
В UI-kit добавлен пример рядом с существующим информационным сообщением,
с ролью `status` и `aria-live="polite"`.

## Проверки

- Lint, production build, scoped Prettier и `git diff --check` — пройдены.
- В `/ui-kit` проверено совпадение computed colors сообщения и бейджа:
  фон `rgb(240, 249, 255)`, текст `rgb(11, 165, 236)`.
- Border `0px`, role `status`, aria-live `polite` подтверждены в браузере.
- UI Design Guard — accepted, без замечаний.
- Unit/E2E и отдельные responsive проверки не запускались. Vite сообщает о
  существующем JS-чанке больше 500 kB.

## Изменённые файлы

- `frontend/admin/src/components/ui/UiAlert.vue`
- `frontend/admin/src/components/shared/UiKitShowcase.vue`
- `frontend/admin/src/components/shared/UI_KIT.md`
- `tasks/DONE.md`
- Этот отчёт.
