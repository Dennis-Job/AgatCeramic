# TASK-A097 — Hover кнопок и замена нейтрального ghost

Дата: 2026-10-04.

## Изменения

- `surface` и `primary-ghost`: фон при наведении `primary-50`.
- `danger-ghost`: фон при наведении `error-50`.
- Кнопки копирования: фон при наведении и keyboard focus `gray-100`, серый текст.
- Нейтральный `ghost` удалён из `UiButton`; все его использования в Admin
  переведены на `surface`. Отдельные примеры «Прозрачная» и её disabled-состояния
  удалены из UI-kit.
- Сохранены цвета активных шагов товара и серый hover-текст неактивных вкладок
  импорта после перехода на `surface`.
- Используются существующие токены палитры; новые цвета не добавлены.

## Проверки

- Lint, production build, scoped Prettier и `git diff --check` — пройдены.
- В открытом `/ui-kit` подтверждены hover-фоны: surface, синяя прозрачная и edit
  `rgb(235, 244, 255)`; оба delete — `rgb(254, 243, 242)`; copy —
  `rgb(242, 244, 247)` с текстом `rgb(102, 112, 133)`.
- В исходниках Admin не осталось нейтрального варианта `ghost`.
- Unit/E2E и полный проход маршрутов/адаптивных ширин не запускались.
- Vite сообщает о существующем JS-чанке больше 500 kB.
- UI Design Guard — accepted. Найденный конфликт hover-текста вкладок импорта
  устранён; оставшихся замечаний нет.

## Изменённые файлы

- `frontend/admin/src/components/shared/ConfirmDialog.vue`
- `frontend/admin/src/components/shared/UI_KIT.md`
- `frontend/admin/src/components/shared/UiKitShowcase.vue`
- `frontend/admin/src/components/ui/UiButton.vue`
- `frontend/admin/src/components/ui/UiNotification.vue`
- `frontend/admin/src/features/access-control/components/RoleFormDialog.vue`
- `frontend/admin/src/features/appearance/components/SiteChromeEditor.vue`
- `frontend/admin/src/features/attribute-groups/components/AttributeGroupFormDialog.vue`
- `frontend/admin/src/features/attributes/components/AttributeFormDialog.vue`
- `frontend/admin/src/features/audit-log/components/AuditLogWorkspace.vue`
- `frontend/admin/src/features/banners/components/BannerFormDialog.vue`
- `frontend/admin/src/features/brands/components/BrandFormDialog.vue`
- `frontend/admin/src/features/categories/components/CategoriesList.vue`
- `frontend/admin/src/features/categories/components/CategoryAssignmentsDialog.vue`
- `frontend/admin/src/features/categories/components/CategoryDetailsDialog.vue`
- `frontend/admin/src/features/categories/components/CategoryFormDialog.vue`
- `frontend/admin/src/features/dashboard/components/DashboardWorkspace.vue`
- `frontend/admin/src/features/employees/components/EmployeeFormDialog.vue`
- `frontend/admin/src/features/homepage/components/HomePageBodyEditor.vue`
- `frontend/admin/src/features/media/components/MediaReferenceField.vue`
- `frontend/admin/src/features/pages/components/PageBlocksEditor.vue`
- `frontend/admin/src/features/pages/components/PageFormDialog.vue`
- `frontend/admin/src/features/products/components/ProductEditor.vue`
- `frontend/admin/src/features/products/components/ProductEditorSteps.vue`
- `frontend/admin/src/features/products/components/ProductImportWorkspace.vue`
- `frontend/admin/src/features/products/components/ProductPhotoAction.vue`
- `frontend/admin/src/features/settings/components/SettingsWorkspace.vue`
- `frontend/admin/src/features/sliders/components/SliderFormDialog.vue`
- `frontend/admin/src/features/stores/components/StoreFormDialog.vue`
- `frontend/admin/src/features/stores/components/WorkingHoursDialog.vue`
- `frontend/admin/src/layouts/components/AdminNavigation.vue`
- `frontend/admin/src/layouts/components/AdminUserMenu.vue`
- `tasks/DONE.md`
- Этот отчёт.
