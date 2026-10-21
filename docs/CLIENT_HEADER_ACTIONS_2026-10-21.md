# Значки действий в хедере Client — 2026-10-21

В хедер добавлены действия в порядке референса `exampleSite.html`: поиск,
корзина, боковое меню. Поиск открывает существующую панель поиска по разделам.
После закрытия фокус возвращается к кнопке, открывшей панель.

По прямому указанию владельца корзина пока неактивна; её функциональность
будет подключена отдельно. Не показываются вымышленные количество товаров
или ссылка на отсутствующий экран.

Общий `UiIconButton` закрепляет область нажатия 44×44 px, круглую форму,
бежевый hover и disabled-состояние. На узком экране отступы сокращаются,
чтобы текстовый бренд и три кнопки помещались без горизонтальной прокрутки.
Правило UI-009 внесено в каталог UI-kit и Client AGENTS.

## Изменённые файлы

- `frontend/client/app/components/ui/UiIconButton.vue`
- `frontend/client/app/components/shared/SiteHeader.vue`
- `frontend/client/e2e/ui-kit.spec.ts`
- `frontend/client/AGENTS.md`
- `docs/CLIENT_UI_KIT.md`
- `docs/CLIENT_HEADER_ACTIONS_2026-10-21.md`
- `tasks/DONE.md`

## Проверка

- Client build, typecheck, format:check — PASS.
- Полный production E2E — 39/39 PASS.
- Проверены ширины 320, 640, 768, 1024 и 1280 px: порядок, размеры кнопок,
  отсутствие пересечений и overflow, hover, поиск и возврат фокуса.
- Пройдены проверки с настоящим классическим scrollbar, анимаций и callback.
- Независимый UI Design Guard: accepted, замечаний нет.
- `git diff --check` — PASS. API и БД не изменялись.
