# Компактная шапка и отступ страницы — 2026-10-02

По замечаниям владельца на `/products` (viewport около 626 px):

- Удалена дублирующая подпись текущего раздела рядом с кнопкой «Меню» в
  `AdminNavigation.vue`. Desktop links и active state внутри меню сохранены.
- В `AdminLayout.vue` локально объявлен базовый
  `--admin-workspace-inline-gutter` через существующий compact token 16 px.
  От 640 px остаётся 24 px. Это восстанавливает общий отступ между шапкой
  и содержимым, а также выравнивание нетабличных блоков с краями шапки.
  Full-bleed таблицы наследуют тот же gutter.

До правки чтение computed styles реальной страницы показало отсутствующий
inline gutter, padding 0 и одинаковый нижний край шапки/верх заголовка.
В source tokens переменная уже есть, но в загруженном dev CSS её нет;
layout теперь непосредственно задаёт свой базовый gutter.

Тесты, сборка и обновление snapshots не выполняются по сохранённому указанию
владельца. Ручной просмотр текущей страницы показал padding/gap 16 px и только «Меню»
в компактной строке. Независимый UI Design Guard принял код и снимок без
blocking findings. Снимок: `.tmp/compact-header-spacing.jpg`. API и миграции не затронуты.

Изменённые файлы: `frontend/admin/src/layouts/AdminLayout.vue`,
`frontend/admin/src/layouts/components/AdminNavigation.vue`,
`frontend/admin/src/components/shared/UI_KIT.md`, этот отчёт,
`tasks/TODO.md`, `tasks/DONE.md`.


## Уточнение отступа фильтров

По следующему запросу владельца в
`frontend/admin/src/features/products/components/ProductEditor.vue`
отступ заголовка изменён с `mb-7` (28 px) на `mb-6` (24 px), как `mt-6`
между фильтрами и таблицей. До правки на текущей странице измерены 28/24 px.
Тесты, сборка и snapshots после правки не запускаются по указанию владельца.

Ручной просмотр после HMR подтвердил одинаковые отступы 24/24 px.
Независимый UI Design Guard принял код и снимок без blocking findings.
Снимок: `.tmp/product-filter-equal-spacing.jpg`. Обновлены `ProductEditor.vue`,
этот отчёт и task ledger; API и миграции не менялись.
