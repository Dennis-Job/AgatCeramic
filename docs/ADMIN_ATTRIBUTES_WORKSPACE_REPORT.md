# Единая рабочая область характеристик — 2026-10-15

## Результат

Владелец выбрал расположение «Группы слева, содержимое справа» и быстрые
тумблеры вместо бейджей. `/attributes` и сохранённый `/attribute-groups`
теперь показывают одну рабочую область; в меню остался один вход
«Характеристики» в колонке «Справочники каталога». Permissions `catalog.manage`
сохранены. По уточнению владельца отдельная колонка «Характеристики товаров»
удалена; проверены 20/20 navigation E2E, сборка и независимый UI Design Guard.
Подтверждение: `.tmp/attributes-navigation-catalog-1280.png`.

Слева доступны поиск, группы со счётчиками, «Все характеристики» и служебный
раздел «Без группы». Справа — описание/код/порядок группы, подробные
характеристики: название, код, тип, единица, порядок, варианты значений.
На экранах уже 1024 px навигация групп заменяется общим поисковым UiSelect.
Поиск характеристик ограничен выбранной группой; выбор группы хранится в URL.

Тумблеры «В фильтрах» и «В карточке товара» отправляют существующий PATCH с
одним изменённым boolean. Пока запрос выполняется, оба переключателя и
действия данной строки недоступны. При ошибке прежнее значение возвращается,
ошибка отображается общим notification. GET/PATCH в любом порядке завершения
согласуются по ID и revision только для изменённых полей, не заменяя свежие
название/тип/привязку группы устаревшим ответом.

Создание/редактирование характеристик и групп оформлено как формы категорий:
единая ширина, парные поля, закреплённые header/footer, внутренняя прокрутка.
Из формы характеристики можно создать группу; draft и фокус сохраняются,
новая группа выбирается автоматически. Создание из выбранной группы
предзаполняет её. Удаление группы сохраняет характеристики без группы;
удаление характеристики остаётся с подтверждением и backend-ограничениями.

## Границы и архитектура

UI-композиция — feature workspace/list; состояние и coordination —
`useAttributesWorkspace`; HTTP — attribute/group services. Новый UiSwitch
не знает о домене/сети; показан в UI-kit в состояниях on/off/disabled/busy.
Утверждённые tokens, типографика и общие controls не заменялись.

Backend, OpenAPI и миграции не изменялись: partial PATCH уже поддерживает
оба флага. Обязательность остаётся свойством назначения категории. Группа
не является категорией, не имеет вложенности, у характеристики максимум
одна группа. «Без группы» — фильтр, у него нет slug/порядка/CRUD.

Для точных счётчиков и поиска используются существующие getAll helpers,
загружающие все API-страницы по 100 записей. Пагинация результата локальная,
по 25 строк. Это подход для справочника; при значительном росте отдельно
нужны серверные group/search filters и counts, без изменения семантики UI.

## Проверки

- `npm run lint`, `npm run format:check` — PASS.
- `npm run test:unit` — 25 файлов, 147 тестов PASS.
- `npm run build` — PASS (существующее предупреждение JS chunk >500 kB).
- Финальная production E2E: 99/99 PASS (workspace, catalog, navigation,
  action tooltips). Сохранённый результат:
  `frontend/admin/.tmp/attributes-validate-081234/.last-run.json`.
- Проверены multi-page загрузка, область поиска, оба field-only PATCH,
  pending/retry/rollback, сохранение и отмена вложенной формы группы,
  Escape/возврат фокуса, 320/640/768/1024/1280/1440 и axe.
- Независимый UI Design Guard принял код, выбранную группу и обе формы;
  открытых P0–P2 замечаний нет. Сохранённые palette contrast exceptions
  продолжают действовать; новая палитра не вводилась.
- Полный прогон 570 E2E: 557 PASS и 13 visual mismatch. Восемь относятся
  к намеренному редизайну характеристик/групп; их Windows snapshots
  приняты независимым Guard и обновлены. Пять относятся к ранее изменённой
  кнопке товаров «Скачать Excel» → меню «Скачать» (default/loading/empty/error/
  forbidden). Production-код и снимки товаров в этой задаче не менялись;
  полный прогон не отмечается зелёным.
- Дополнительный контроль обоих маршрутов: 24 baseline/responsive сценария,
  включая 8 обновлённых снимков и ширины 320–2560 px — 24/24 PASS,
  `frontend/admin/.tmp/attributes-baselines-approved-v2/.last-run.json`.
- На открытой реальной админке проверены выбранная группа, создание
  характеристики, вложенная форма группы и отмена с возвратом фокуса.
  Пользовательские данные не изменялись.

Windows-прогоны используют установленный Chromium через
`PLAYWRIGHT_BROWSERS_PATH=C:/Users/dkargopolov/AppData/Local/ms-playwright`.
Временные runtime/cache файлы размещены вне Admin в `.tmp/runtime-admin-final`,
чтобы ESLint не анализировал скомпилированный Playwright cache. Повторы после
промежуточных EPERM/ERR_NO_BUFFER_SPACE выполнялись с отдельным output и одним
worker для baseline. Preview запускается скрыто в одном PowerShell-процессе
с тестами и останавливается в `finally`.

## Изменённые файлы

- UI primitive и каталог: `src/components/ui/UiSwitch.vue`,
  `src/components/shared/UiKitShowcase.vue`, `src/components/shared/UI_KIT.md`.
- Характеристики: `src/features/attributes/components/AttributesWorkspace.vue`,
  `AttributesList.vue`, `AttributeFormDialog.vue`,
  `src/features/attributes/composables/useAttributesWorkspace.ts`,
  `src/features/attributes/services/attributes.ts`,
  `src/features/attributes/types/attribute.types.ts`.
- Группы: `src/features/attribute-groups/components/AttributeGroupsWorkspace.vue`,
  `AttributeGroupFormDialog.vue`.
- Навигация: `src/layouts/navigation.ts`, `src/router/index.ts`.
- Unit: `tests/attributesWorkspace.test.ts`, `tests/uiKitShowcase.test.ts`.
- E2E: `e2e/attributesWorkspace.spec.ts`, `e2e/attributesWorkspaceApi.ts`,
  `e2e/catalog.spec.ts`, `e2e/adminBaseline.spec.ts`, `e2e/navigation.spec.ts`,
  `e2e/actionTooltips.spec.ts`, `e2e/sellerWorkspaces.spec.ts`.
- Восемь Windows PNG: `e2e/adminBaseline.spec.ts-snapshots/route-attributes*`
  и `route-attribute-groups*` (default/loading/empty/error).
- Документы: этот отчёт, добавление в `design-qa.md`,
  `docs/CURRENT_STATE.md`, `tasks/DONE.md`. Временная запись задачи удалена
  из `tasks/IN_PROGRESS.md` после завершения.

Пути `src/`, `tests/`, `e2e/` в списке относятся к `frontend/admin/`.

Доказательства: `.tmp/attributes-workspace-*.png`,
`.tmp/attributes-design-comparison.png`, `.tmp/attributes-full-regression/`.
Изменения не закоммичены по правилам проекта.
