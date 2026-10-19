# TASK-A063 — Единый стиль обособленных панелей Admin

Дата: 2026-10-03.

## Результат

Созданы общие tokens поверхностей и радиусов карточек, а также shared-классы
`admin-panel`, `admin-panel--white`, `admin-panel--inset`,
`admin-panel--state` и `admin-panel--elevated`. `UiCard` теперь по умолчанию оформляет самостоятельный
блок на серо-голубой поверхности с тонкой границей; белая поверхность доступна
как явный вариант. Вложенные значения используют белую поверхность внутри
внешней карточки. Цвета success/warning/error у панелей состояния сохранены.

Новый стиль подключён к карточкам UI-kit и dashboard, редакторам характеристик,
сводке и сопутствующим товарам, экранам импорта, деталям заказов/обращений,
формам управления категориями, контентом, атрибутами и часами работы, а также
общей карточке аутентификации.

Бизнес-логика, API, permissions, таблицы и действия не менялись.

## Проверки

- `npm run lint` — пройден.
- `npm run build` — пройден; Vite вывел предупреждение о чанке приложения размером около 600 kB при пороге 500 kB.
- Scoped Prettier check изменённых исходников и Markdown — пройден.
- `git diff --check` — пройден.
- Ручной просмотр `/products` (шаги «Характеристики» и «Проверка»), `/ui-kit`, `/` и `/settings` в Codex In-app Browser при viewport 1097×1139; продуктовые данные не сохранялись.
- Unit/E2E проверки не запускались.
- Независимый UI Design Guard: ACCEPTED, блокирующих замечаний нет. Проверены
  экраны товаров, dashboard, UI-kit, настроек и контента; остальные маршруты
  проверены по состоянию страницы. Авторизационные маршруты перенаправили на
  главную из-за активной сессии. Визуальная проверка контрольных ширин
  320/640/768/1024/1280 px не выполнялась.

## Изменённые файлы

- `docs/ADMIN_SELLER_REDESIGN_PLAN.md`
- `frontend/admin/src/styles/{cards.css,index.css,tokens.css}`
- `frontend/admin/src/components/ui/UiCard.vue`
- `frontend/admin/src/components/shared/{AuthCard.vue,UI_KIT.md,UiKitShowcase.vue}`
- `frontend/admin/src/features/appearance/components/SiteChromeEditor.vue`
- `frontend/admin/src/features/attributes/components/AttributeFormDialog.vue`
- `frontend/admin/src/features/categories/components/{CategoryAssignmentsDialog.vue,CategoryDetailsDialog.vue}`
- `frontend/admin/src/features/contacts/components/ContactDetails.vue`
- `frontend/admin/src/features/dashboard/components/DashboardWorkspace.vue`
- `frontend/admin/src/features/homepage/components/HomePageBodyEditor.vue`
- `frontend/admin/src/features/orders/components/OrderDetails.vue`
- `frontend/admin/src/features/pages/components/SliderBlockEditor.vue`
- `frontend/admin/src/features/products/components/{ProductAttributesSection.vue,ProductGroupImportWorkspace.vue,ProductImportWorkspace.vue,ProductPriceStatusImportWorkspace.vue,ProductRelationsSection.vue,ProductReviewSection.vue,ProductVariantsSection.vue}`
- `frontend/admin/src/features/stores/components/WorkingHoursDialog.vue`
- `tasks/{TODO.md,DONE.md}`
- `docs/TASK_A063_REPORT.md`
