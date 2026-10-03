# TASK-A064 — Секционные карточки по референсу Seller

Дата: 2026-10-03.

## Результат

`UiCard` получил именованный слот `#header`: он выводит заголовок в серой
шапке, а содержимое помещает в отдельное белое скруглённое тело. В строках
сеток белое тело растягивается по общей высоте ряда. Компактный вариант без
`#header` сохранён; `:padded="false"` поддерживает таблицы и полноширинные
области.

Паттерн применён ко всем секциям `/ui-kit`, основным панелям Dashboard, шагам
редактора товара и массовых импортов, сводке/сопутствующим товарам, профилю,
настройкам, общему оформлению, медиатеке, страницам, предпросмотру и деталям
заказов/обращений. Редактор вариантов модели разделён на три блока: параметры
группы, различающиеся характеристики и состав товаров группы. Поля, действия и
состояния форм сохранены.

## Проверки

- `npm run lint` — пройден.
- `npm run build` — пройден; Vite предупредил о JS-чанке около 603 kB при пороге
  500 kB.
- Scoped Prettier check изменённых Vue/CSS и относящихся к задаче Markdown —
  пройден. Форматирование всего `tasks/TODO.md` и `tasks/DONE.md` в рамках этой
  задачи не запускалось.
- `git diff --check` — пройден.
- Визуально проверены `/ui-kit` (1097×1139; после доработки белых тел —
  1375×1139), `/settings` и шаги товара
  «Характеристики», «Варианты модели» и «Проверка» (1600×900), а также
  `/products/import` и `/products/combine` (1600×900). В браузере только
  переключались экраны; изменения товара не сохранялись.
- Независимый UI Design Guard: ACCEPTED по статическому аудиту, включая
  доработку flex-растяжения. Его браузер был недоступен. Контроль ширин
  320/640/768/1024/1280 отдельно визуально не выполнялся.
- Unit/E2E не запускались.

## Затронутые файлы

- `frontend/admin/src/components/ui/UiCard.vue`
- `frontend/admin/src/components/shared/UiKitShowcase.vue`
- `frontend/admin/src/components/shared/UI_KIT.md`
- `frontend/admin/src/features/dashboard/components/DashboardWorkspace.vue`
- `frontend/admin/src/features/appearance/components/AppearanceWorkspace.vue`
- `frontend/admin/src/features/banners/components/BannersWorkspace.vue`
- `frontend/admin/src/features/content-preview/components/DraftPreview.vue`
- `frontend/admin/src/features/contacts/components/ContactDetails.vue`
- `frontend/admin/src/features/media/components/MediaWorkspace.vue`
- `frontend/admin/src/features/orders/components/OrderDetails.vue`
- `frontend/admin/src/features/pages/components/PageBlocksEditor.vue`
- `frontend/admin/src/features/pages/components/PagesWorkspace.vue`
- `frontend/admin/src/features/profile/components/ProfileWorkspace.vue`
- `frontend/admin/src/features/products/components/ProductAttributesSection.vue`
- `frontend/admin/src/features/products/components/ProductGroupImportWorkspace.vue`
- `frontend/admin/src/features/products/components/ProductImportWorkspace.vue`
- `frontend/admin/src/features/products/components/ProductPriceStatusImportWorkspace.vue`
- `frontend/admin/src/features/products/components/ProductReviewSection.vue`
- `frontend/admin/src/features/products/components/ProductRelationsSection.vue`
- `frontend/admin/src/features/products/components/ProductVariantsSection.vue`
- `frontend/admin/src/features/settings/components/SettingsWorkspace.vue`
- `frontend/admin/src/features/sliders/components/SlidersWorkspace.vue`
- `frontend/admin/src/features/stores/components/StoresWorkspace.vue`
- `docs/ADMIN_SELLER_REDESIGN_PLAN.md`
