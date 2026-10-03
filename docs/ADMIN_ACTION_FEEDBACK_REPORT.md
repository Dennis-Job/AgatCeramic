# Кнопки управления: hover и tooltip — 2026-10-03

Завершено в запускаемой копии `/Users/dennis.a.k/Downloads/AgatCeramic`,
ветка `codex/admin-seller-redesign`. Docker Admin использует эту папку;
результат подтверждён на открытой странице `http://localhost:5173/products`.
Первоначальная реализация была в отдельном worktree 8510 на detached HEAD,
поэтому её не было видно в основной папке и работающем сайте. Изменения
адаптированы к актуальному Seller UI, включая компактную шапку и денежные поля.
Commit не создавался.

## Поведение

Общий `UiButton` даёт заметный голубой фон управления, красный для удаления.
Подсказки берут доступное имя кнопки, а для товаров используют краткие тексты:
«Копировать товар — создать похожий», «Редактировать товар», «Удалить товар».
Общий контракт покрывает остальные icon actions, пагинацию, закрытие окон,
очистку/выбор фильтров, календарь и иконки шапки. Сортировка и сброс фильтров
получили явные описания. Четыре удаления справочников используют danger-ghost.

Общая директива показывает tooltip спустя 300 мс наведения либо сразу при
фокусе. Подсказка доступна при наведении на неё, сохраняет aria-describedby,
не обрезается таблицей, учитывает viewport, скрывается после активации,
при disabled/loading, скрытии уведомления или размонтировании. Первый Escape
скрывает подсказку, следующий закрывает родительское окно. Открытые header
popovers подавляют tooltip триггеров; общий openId подавляет tooltip бургера.
Документация UI-kit и интерактивная витрина обновлены.

## Проверки

- Admin lint, Prettier для `src`, `tests`, `e2e`, build: успешно.
- Unit: 89/89, включая очистку tooltip после auto-dismiss уведомления.
- Первоначальный полный macOS Chromium E2E: 281/286. Все функциональные сценарии
  проходят, включая новые 7 tooltip-проверок. Остались пять ранее известных
  products visual baseline mismatch: loading, forbidden, empty, error, default.
  Они ранее зафиксированы в отчётах Seller/денежного формата; их эталоны не
  перезаписывались. Полный suite поэтому не является зелёным.
- Tooltip проверен на 320/640/768/1024/1280/1440/1920/2560 px, мобильное меню
  320/640, четыре справочника, UI-kit, dialog Escape и header popovers; axe
  в полном прогоне проходит. Ручная проверка на localhost:5173 с реальными
  товарами подтвердила фон и подсказку копирования, без сохранения бизнес-данных.
- Linux Chromium: 7 новых функциональных сценариев и 4 baseline — 11/11;
  актуализированы только эталоны четырёх удалений. Полный Linux/Windows suite
  этой задачей не запускался. Повторная проверка Linux эталонов без
  перезаписи: 4/4.
- Независимый UI Design Guard принял исходники, responsive-снимки и восемь
  пар baseline macOS/Linux. Отличия baseline только в цвете иконки удаления,
  bbox 1236,293–1251,310 при 1280×720. Блокирующих замечаний нет.
- Общий `npm run format:check` видит 65 прежних неформатированных `.tmp`
  артефактов. Они не менялись; исходники и тесты проверены отдельно.
- `git diff --check` и repository hygiene: успешно.
- Сборка сохраняет предупреждение о chunk больше 500 kB. API, миграции,
  permissions, бизнес-логика и Client не менялись.

Скриншот работающего сайта: `/tmp/agat-primary-live-copy-tooltip.jpg`.
Снимки нового поведения: `/tmp/agat-primary-action-qa/`.
Логи итоговых проверок: `/tmp/agat-primary-action-final-e2e.log`,
`/tmp/agat-primary-action-linux.log`, `/tmp/agat-primary-action-build.log`.

## Уточнение: белые tooltip и синие действия редактирования

По дополнительному запросу владельца:

- Столбец действий товаров получил ширину 160 px; три кнопки идут без gap,
  вплотную. Внутренние отступы ячейки сохраняют место для hover-фона и keyboard
  focus. Прежние 140 px не вмещали все три кнопки с такими отступами.
  140 px три кнопки шириной 41 px плюс gap и padding не помещались.
- Все tooltip используют белый фон, тёмный текст gray-900, тонкую границу
  через общий border token и мягкую dropdown-тень.
- Добавлен общий `UiButton primary-ghost`. Все 14 Pencil controls переведены
  на этот вариант: товары, категории, бренды, группы, характеристики,
  сотрудники (mobile/desktop), роли, страницы, магазины, баннеры, слайдеры,
  медиа и витрина UI-kit. Default/hover цвет primary-600, фон primary-100,
  disabled остаётся серым. Локальный product text-primary class удалён.

Итоговые проверки этой итерации: lint, source/test/E2E Prettier, build,
89 unit — успешно. Новый профильный E2E — 8/8: белая поверхность/граница/текст,
containment всех трёх actions на 8 ширинах, синий цвет edit в 8 workspaces,
popover/mobile/dialog и доступность. Полный macOS Chromium — 282/287;
остаются те же пять products visual mismatches. Default products теперь
также намеренно отличается шириной столбца действий; прежние несвязанные
изменения Seller в этом эталоне не принимались автоматически.

Linux — 15/15 (8 action + 7 baseline). Семь эталонов macOS и Linux
актуализированы после независимого UI Design Guard: отличия ограничены
цветом Pencil внутри 17×17 px, остальные пиксели совпадают. Повторная
проверка без перезаписи — по 7/7 на macOS/Linux. Guard принял white tooltip,
синий edit и отсутствие пересечения столбца; blocking findings нет.
Полный Linux/Windows не запускались. Общий format:check по-прежнему имеет
прежние .tmp артефакты; source-only проходит. API и миграции не менялись.

Live screenshot: `/tmp/agat-white-tooltip-live.jpg`.
Новые QA screenshots: `/tmp/agat-white-actions-qa/`.
Логи: `/tmp/agat-white-actions-full.log`, `/tmp/agat-white-actions-targeted.log`,
`/tmp/agat-white-actions-linux.log`, `/tmp/agat-white-actions-build.log`.
Исправление в запускаемой папке Downloads на `codex/admin-seller-redesign`;
commit не создавался.

## Изменённые файлы

- `docs/ADMIN_ACTION_FEEDBACK_REPORT.md`
- `frontend/admin/e2e/accessSalesContacts.spec.ts`
- `frontend/admin/e2e/actionTooltips.spec.ts`
- `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-attribute-groups-chromium-darwin.png`
- `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-attribute-groups-chromium-linux.png`
- `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-attributes-chromium-darwin.png`
- `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-attributes-chromium-linux.png`
- `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-brands-chromium-darwin.png`
- `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-brands-chromium-linux.png`
- `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-categories-chromium-darwin.png`
- `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-categories-chromium-linux.png`
- `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-employees-chromium-darwin.png`
- `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-employees-chromium-linux.png`
- `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-media-chromium-darwin.png`
- `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-media-chromium-linux.png`
- `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-roles-chromium-darwin.png`
- `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-roles-chromium-linux.png`
- `frontend/admin/e2e/catalog.spec.ts`
- `frontend/admin/e2e/headerPopovers.spec.ts`
- `frontend/admin/e2e/notifications.spec.ts`
- `frontend/admin/e2e/productFilterCounts.spec.ts`
- `frontend/admin/e2e/scrollHeader.spec.ts`
- `frontend/admin/src/components/shared/UI_KIT.md`
- `frontend/admin/src/components/shared/UiKitShowcase.vue`
- `frontend/admin/src/components/ui/UiButton.vue`
- `frontend/admin/src/components/ui/UiDatePicker.vue`
- `frontend/admin/src/components/ui/UiInput.vue`
- `frontend/admin/src/components/ui/UiNotification.vue`
- `frontend/admin/src/components/ui/UiSelect.vue`
- `frontend/admin/src/components/ui/tooltip.ts`
- `frontend/admin/src/features/access-control/components/RolesWorkspace.vue`
- `frontend/admin/src/features/attribute-groups/components/AttributeGroupsWorkspace.vue`
- `frontend/admin/src/features/attributes/components/AttributesList.vue`
- `frontend/admin/src/features/banners/components/BannersWorkspace.vue`
- `frontend/admin/src/features/brands/components/BrandsWorkspace.vue`
- `frontend/admin/src/features/categories/components/CategoriesList.vue`
- `frontend/admin/src/features/employees/components/EmployeesList.vue`
- `frontend/admin/src/features/media/components/MediaWorkspace.vue`
- `frontend/admin/src/features/pages/components/PagesWorkspace.vue`
- `frontend/admin/src/features/products/components/ProductEditor.vue`
- `frontend/admin/src/features/sliders/components/SlidersWorkspace.vue`
- `frontend/admin/src/features/stores/components/StoresWorkspace.vue`
- `frontend/admin/src/layouts/components/AdminNavigation.vue`
- `frontend/admin/src/layouts/components/AdminNotifications.vue`
- `frontend/admin/src/layouts/components/AdminUserMenu.vue`
- `frontend/admin/src/styles/navigation.css`
- `frontend/admin/src/styles/reset.css`
- `frontend/admin/src/styles/utilities.css`
- `frontend/admin/tests/notifications.test.ts`
- `frontend/admin/tests/tooltip.test.ts`
- `tasks/DONE.md`
