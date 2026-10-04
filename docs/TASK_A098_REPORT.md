# TASK-A098 — Прозрачный фон нейтральных icon-кнопок

Дата: 2026-10-04.

## Изменения

`surface` задаёт белую поверхность, поэтому у кнопок с короткими icon-only
действиями после TASK-A097 появился белый прямоугольный фон в обычном состоянии.
Добавлен общий вариант `UiButton neutral-ghost`: прозрачный фон по умолчанию,
`gray-100` при hover и `focus-visible`, приглушённый `gray-400` текст в disabled
состоянии.

Вариант используется у кнопки закрытия уведомления и действия «Создать похожий
товар». В UI-kit добавлены примеры enabled/disabled и описание поведения.

## Проверки

- `npm run lint` — пройден.
- `npm run build` — пройден; Vite сохранил существующее предупреждение о чанке
  больше 500 kB.
- Scoped Prettier для Vue-компонентов, UI-kit и отчёта — пройден. `tasks/DONE.md`
  не форматировался: committed baseline этого ledger также не проходит Prettier.
- `git diff --check` — пройден.
- Независимый UI Design Guard — блокеров и замечаний нет; переиспользование,
  состояние disabled, доступное имя, focus и responsive-геометрия проверены по коду.
- Unit/E2E suites не запускались.
- В authenticated in-app browser маршруты `/products` и `/ui-kit` загрузились;
  accessibility snapshot показывает действие товара и enabled/disabled-примеры
  нового варианта. Отдельный headless-сеанс был перенаправлен на `/login`, поэтому
  screenshot-проверка в нём недоступна.

## Изменённые файлы

- `frontend/admin/src/components/ui/UiButton.vue`
- `frontend/admin/src/components/ui/UiNotification.vue`
- `frontend/admin/src/features/products/components/ProductEditor.vue`
- `frontend/admin/src/components/shared/UiKitShowcase.vue`
- `frontend/admin/src/components/shared/UI_KIT.md`
- `tasks/DONE.md`
- Этот отчёт.
