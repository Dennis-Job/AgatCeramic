# Доработка Seller — таблицы без боковых отступов

Дата: 2026-10-02. Ветка: `codex/admin-seller-redesign`.
Статус: завершено; независимый UI Design Guard принял результат.

## Результат

Рабочая таблица `/products` уже была широкой, но находилась внутри общего
AdminLayout padding16/24 px, поэтому scroll shell занимал viewport−32/48 px.
Общий opt-in `UiTable fullBleed` теперь компенсирует именно этот gutter:
ширина calc100%+2gutter, margin-inline−gutter, max-width:none. `100vw` не
используется, page overflow не создаётся при вертикальном scrollbar.
Source of truth — `--admin-workspace-inline-gutter`, его активное значение
наследуется от AdminLayout с учётом breakpoint640.

Свойство включено для 12 рабочих таблиц: товары, категории, бренды, группы
характеристик, характеристики, сотрудники, роли, права, аудит, медиатека,
заказы и обращения. Header, кнопки/фильтры, пагинация, loading/empty, mobile
cards и выбранные детали сохраняют контейнер1280. Вложенная таблица dashboard,
UI-kit, редакторы и диалоги не включают opt-in. Padding ячеек сохранён;
sticky headers/edges, именованный локальный scroll и inset focus не меняются.
Предыдущие soft-focus/notification доработки сохранены. Backend, API,
permissions, migrations и бизнес-flow не менялись.

## Изменённые файлы

- `frontend/admin/src/components/ui/UiTable.vue`: опциональный fullBleed и его CSS.
- `frontend/admin/src/layouts/AdminLayout.vue`, `src/styles/tokens.css`:
  inherited текущий gutter (src пути здесь относительно frontend/admin).
- Табличные feature-компоненты, перечисленные ниже: включён opt-in.
- `frontend/admin/src/components/shared/UiKitShowcase.vue`, `UI_KIT.md`:
  inventory token и спецификация геометрии.
- `frontend/admin/e2e/containerLayout.spec.ts`, `productWorkspace.spec.ts`,
  `sellerWorkspaces.spec.ts`: table x0/width clientWidth; прежние container,
  scroll/sticky/actions/keyboard/axe assertions сохранены.
- Seller plan, CURRENT_STATE и task statuses.

## Проверки и ревью

UI Design Guard принял реализацию, реальные снимки рабочих областей и все
24 кандидата эталонов (12 Darwin + 12 Linux) **до их замены**. Манифесты
before/actual/diff сохранены в `/private/tmp/agat-table-bleed-review/`.
Порог `maxDiffPixels: 30` и `updateSnapshots: none` сохранены.

Первый параллельный прогон показал ожидаемые различия 12 табличных эталонов
на каждой платформе и таймауты многошаговых проверок. Итоговые прогоны
выполняются последовательно. После начала параллельной работы другого чата
над инструментами импорта товаров проверка перенесена в зафиксированные
копии `/private/tmp/agat-table-bleed-{macos,linux}`. Они содержат именно
согласованную версию этой доработки. macOS preview использует отдельный
порт 4174; Linux — изолированный контейнер и порт 4173.

Логи: `/private/tmp/agat-table-bleed-*`. Геометрия проверяется на ширинах
320, 602, 640, 768, 1024, 1280, 1440, 1920 и 2560 px. Дополнительные снимки
таблицы и действий на 602 px сохранены вместе с существующими 320 px
в `.tmp/a059-visual/` соответствующей проверяемой копии.
macOS: `npm run check` пройден (lint, format, 69 unit, typecheck и build);
полный строгий E2E — 258/258 за 3,9 минуты. Linux: `npm run check` пройден
(lint, format, 69 unit, typecheck и build); полный строгий E2E — 258/258
за 4,0 минуты. Проверка реального Nuxt iframe — 3/3. Таймауты не повторились
в итоговых прогонах. UI Design Guard дополнительно принял оба снимка на 602 px.
`git diff --check`, repository hygiene и внутренние Markdown-ссылки проверены.
Windows не проверен.
Коммит и отправка в репозиторий не выполнялись.

## Feature-файлы

- `frontend/admin/src/features/access-control/components/PermissionsWorkspace.vue`
- `frontend/admin/src/features/access-control/components/RolesWorkspace.vue`
- `frontend/admin/src/features/attribute-groups/components/AttributeGroupsWorkspace.vue`
- `frontend/admin/src/features/attributes/components/AttributesList.vue`
- `frontend/admin/src/features/audit-log/components/AuditLogWorkspace.vue`
- `frontend/admin/src/features/brands/components/BrandsWorkspace.vue`
- `frontend/admin/src/features/categories/components/CategoriesList.vue`
- `frontend/admin/src/features/contacts/components/ContactsList.vue`
- `frontend/admin/src/features/employees/components/EmployeesList.vue`
- `frontend/admin/src/features/media/components/MediaWorkspace.vue`
- `frontend/admin/src/features/orders/components/OrdersList.vue`
- `frontend/admin/src/features/products/components/ProductEditor.vue`
