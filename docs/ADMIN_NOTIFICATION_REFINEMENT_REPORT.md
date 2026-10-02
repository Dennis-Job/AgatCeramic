# Доработка Seller UI-kit — всплывающие уведомления

Дата: 2026-10-02. Ветка: `codex/admin-seller-redesign`.
Статус: завершено. Независимый UI Design Guard и строгие macOS/Linux прогоны приняты.

## Результат

По замечанию к success экспорта Excel на `/products` операционные сообщения
перенесены в общий стек справа сверху. Они не сдвигают фильтры, таблицу,
редактор или форму. `UiNotification` использует семантический `UiAlert`;
единственный `UiNotificationHost` подключён перед RouterView в App и
выводится через Teleport. Стили и геометрия принадлежат общим tokens/styles.

Ширина 360 px ограничена viewport, отступ 16 px; длинные строки переносятся,
стек имеет локальную вертикальную прокрутку и не создаёт page overflow.
Пустая область и промежутки не перехватывают pointer events.
Success/info закрываются через 8 секунд; hover, keyboard focus и hidden tab
приостанавливают оставшееся время. Ошибки/предупреждения сохраняются до
ручного закрытия. Escape из уведомления закрывает его и сохраняет диалог.
Фокус возвращается к следующему toast или живому control источника.
Host помнит control до его временного disabled, например кнопку экспорта.

Host переносится внутрь активного modal для screen reader/focus trap,
после close/unmount возвращается в body либо предыдущий active dialog.
Suspended dialog не участвует. Unmount источника удаляет toast и таймер;
сброс состояния новым действием или изменение текста снова показывает сообщение.
Error использует alert/assertive, success/info/warning — status/polite.
Retry actions из прежних сообщений сохранены.

Перенесены 78 прежних операционных UiAlert, а также старые raw success/error
сообщения настроек, media upload/picker и диалогов баннеров/слайдеров/страниц,
характеристик/брендов/магазинов/времени работы. Общие ошибки ConfirmDialog и
logout также используют toast. Inline field validation, progress/loading/
empty и пояснения контекста остаются частью соответствующих controls/данных.
Backend, API-контракт, migrations, permissions и CRUD не менялись.
Предыдущая soft-focus доработка сохранена.

## Файлы

- `frontend/admin/src/components/ui/UiNotification.vue`, `UiNotificationHost.vue`:
  общий feedback UI, lifetime и доступность.
- `frontend/admin/src/composables/useNotificationContext.ts`: presentation context
  активных dialogs и control источника; без доменных зависимостей.
- `frontend/admin/src/components/ui/UiPopover.vue`: маркер floating panel для
  возврата фокуса к устойчивому trigger без привязки к домену.
- `frontend/admin/src/components/ui/UiDialog.vue`, `src/App.vue`: host и modal
  ownership/trap (все src пути относительно `frontend/admin/`).
- `frontend/admin/src/styles/notifications.css`, `tokens.css`, `index.css`:
  геометрия, переносы и локальный scroll.
- Workspace/form/import/detail компоненты, auth views, ConfirmDialog и
  AdminUserMenu: используют общий компонент вместо локальных сообщений.
- `frontend/admin/src/components/shared/UiKitShowcase.vue`, `UI_KIT.md`:
  inventory 20 primitives, новые tokens и интерактивные примеры всех tones.
- `frontend/admin/tests/notifications.test.ts`, `uiKitShowcase.test.ts`;
  `e2e/notifications.spec.ts`, `headerPopovers.spec.ts`, `sellerWorkspaces.spec.ts`: lifetime/focus и
  фактические notification места проверяются явно.
- Seller plan, CURRENT_STATE, task statuses и этот отчёт.

## Проверки

Независимый UI Design Guard принял архитектуру и visuals export320–2560,
длинный стек320 и error внутри modal. P2 возврата фокуса после закрытия
logout popup исправлен: запоминается устойчивый trigger через aria-controls,
при исчезновении кнопки «Выйти» закрытие toast возвращает фокус к значку
пользователя. Добавлены unit и браузерный regression; повторное review принято.

До замены независимо просмотрены before/actual/diff всех 28 PNG:
14 Darwin и 14 Linux (products forbidden и 13 error routes). Уведомления
переместились справа вверх и освободили место в layout. Высота content-error
817→759 объясняется удалением inline блока58 px; preview не обрезан.
Скопированы только согласованные actual, tolerance30 и updateSnapshots:none
сохранены. Manifest `/private/tmp/agat-notification-review/{darwin,linux}/manifest.json`.

Первый полный прогон каждой OS: 242/258 passed, 14 ожидаемых screenshot
отличий и два strict-locator падения старого `locator('aside')` после добавления
notification host. Scope уточнён к региону «Детали выбранной записи», проверки
не ослаблены.

| Проверка | Результат |
| --- | --- |
| macOS `npm run check` | lint/format, 69 unit, TypeScript/build — PASS |
| Linux isolated Docker `npm run check` | lint/format, 69 unit, TypeScript/build — PASS |
| macOS финальный `npm run test:e2e -- --workers=2` | 258/258, strict screenshots и axe — PASS |
| Linux финальный `npm run test:e2e -- --workers=2` | 258/258, strict screenshots и axe — PASS |
| Targeted notification scenarios | 3/3 — PASS, также входят в полный набор |
| Logout closed-popup keyboard regression | 1/1 — PASS, также входит в полный набор |
| Настоящий Admin/Nuxt `npm run test:e2e:preview` | 3/3 — PASS |
| Финальный `npm run format:check` | PASS |
| `git diff --check`, repository hygiene/local links | PASS |

Проверены все Admin маршруты из UI_DESIGN_REVIEW, `/ui-kit` и ширины
320/602/640/768/1024/1280/1440/1920/2560 px. 602×910 воспроизводит viewport
замечания пользователя. Unit покрывает auto-dismiss, error manual-dismiss,
remaining hover timer, focused content, Escape и disconnected popup source.
Windows не проверен; имеющееся предупреждение Vite о размере bundle сохранено.


Логи `/private/tmp/agat-notification-*`; screenshots
`frontend/admin/.tmp/notifications/`.
Коммит и отправка этой доработки не выполнялись.

## Мигрированные feature/view файлы

- `frontend/admin/src/features/access-control/components/PermissionsWorkspace.vue`
- `frontend/admin/src/features/access-control/components/RoleFormDialog.vue`
- `frontend/admin/src/features/access-control/components/RolesWorkspace.vue`
- `frontend/admin/src/features/appearance/components/AppearanceWorkspace.vue`
- `frontend/admin/src/features/attribute-groups/components/AttributeGroupFormDialog.vue`
- `frontend/admin/src/features/attribute-groups/components/AttributeGroupsWorkspace.vue`
- `frontend/admin/src/features/attributes/components/AttributeFormDialog.vue`
- `frontend/admin/src/features/attributes/components/AttributesWorkspace.vue`
- `frontend/admin/src/features/audit-log/components/AuditLogWorkspace.vue`
- `frontend/admin/src/features/banners/components/BannerFormDialog.vue`
- `frontend/admin/src/features/banners/components/BannersWorkspace.vue`
- `frontend/admin/src/features/brands/components/BrandFormDialog.vue`
- `frontend/admin/src/features/brands/components/BrandsWorkspace.vue`
- `frontend/admin/src/features/categories/components/CategoriesWorkspace.vue`
- `frontend/admin/src/features/categories/components/CategoryAssignmentsDialog.vue`
- `frontend/admin/src/features/categories/components/CategoryFormDialog.vue`
- `frontend/admin/src/features/contacts/components/ContactDetails.vue`
- `frontend/admin/src/features/contacts/components/ContactsWorkspace.vue`
- `frontend/admin/src/features/content-preview/components/DraftPreview.vue`
- `frontend/admin/src/features/employees/components/EmployeeFormDialog.vue`
- `frontend/admin/src/features/employees/components/EmployeesWorkspace.vue`
- `frontend/admin/src/features/homepage/components/HomePageHeroEditor.vue`
- `frontend/admin/src/features/homepage/components/HomePageWorkspace.vue`
- `frontend/admin/src/features/media/components/MediaReferenceField.vue`
- `frontend/admin/src/features/media/components/MediaWorkspace.vue`
- `frontend/admin/src/features/orders/components/OrderDetails.vue`
- `frontend/admin/src/features/orders/components/OrdersWorkspace.vue`
- `frontend/admin/src/features/pages/components/PageBlocksEditor.vue`
- `frontend/admin/src/features/pages/components/PageFormDialog.vue`
- `frontend/admin/src/features/pages/components/PagesWorkspace.vue`
- `frontend/admin/src/features/pages/components/SliderBlockEditor.vue`
- `frontend/admin/src/features/products/components/ProductEditor.vue`
- `frontend/admin/src/features/products/components/ProductGroupImportDialog.vue`
- `frontend/admin/src/features/products/components/ProductImportDialog.vue`
- `frontend/admin/src/features/products/components/ProductPriceStatusImportDialog.vue`
- `frontend/admin/src/features/profile/components/ProfileWorkspace.vue`
- `frontend/admin/src/features/settings/components/SettingsWorkspace.vue`
- `frontend/admin/src/features/sliders/components/SliderFormDialog.vue`
- `frontend/admin/src/features/sliders/components/SlidersWorkspace.vue`
- `frontend/admin/src/features/stores/components/StoreFormDialog.vue`
- `frontend/admin/src/features/stores/components/StoresWorkspace.vue`
- `frontend/admin/src/features/stores/components/WorkingHoursDialog.vue`
- `frontend/admin/src/layouts/components/AdminUserMenu.vue`
- `frontend/admin/src/views/ForgotPasswordView.vue`
- `frontend/admin/src/views/LoginView.vue`
- `frontend/admin/src/views/ResetPasswordView.vue`
