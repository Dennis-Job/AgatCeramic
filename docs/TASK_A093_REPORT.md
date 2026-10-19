# TASK-A093 — Иконки Admin в оттенке gray-400

Дата: 2026-10-04.

Иконки уведомлений и меню пользователя используют существующий `gray-400`
в обычном, hover и открытом состояниях. Общий стиль также применяется к кнопке
компактного меню. Круг добавления фотографии в `ProductPhotoAction` использует
существующий `gray-400`; символ плюса остаётся белым.

## Проверки

- Lint, production build и scoped Prettier — пройдены.
- `git diff --check` — пройден. UI Design Guard — accepted.
- В `/products` computed styles обоих header controls и круга добавления фото
  равны `rgb(152, 162, 179)`, то есть исходному `gray-400`.
- Unit/E2E и отдельные responsive проверки не запускались: изменены только
  три ссылки на цвета. Vite сообщает о существующем JS-чанке больше 500 kB.

## Изменённые файлы

- `frontend/admin/src/styles/navigation.css`
- `frontend/admin/src/features/products/components/ProductPhotoAction.vue`
- `frontend/admin/src/components/shared/UI_KIT.md`
- `tasks/DONE.md`
- Этот отчёт.
