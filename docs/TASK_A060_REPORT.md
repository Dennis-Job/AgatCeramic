# TASK-A060 — остальные списки и list/detail рабочие области

Дата: 2026-10-01. Ветка: `codex/admin-seller-redesign`.
Статус: завершена; независимый UI Design Guard принял результат.

## Результат

На `AdminWorkspace mode=list` перенесены категории, бренды, группы
характеристик, характеристики, сотрудники, роли, права, аудит и медиатека.
Короткий PageHeader использует ограниченный slot `intro`, данные используют
всю доступную ширину. Заказы и обращения используют `mode=editor`.

Все рабочие таблицы переиспользуют `UiTable` и общий `seller-table`: фиксированная
раскладка, перенос длинных значений, отступы через tokens, разделители и hover.
Внешние карточки у списков удалены. Заголовки закреплены внутри ограниченной
по высоте локальной прокрутки; крайние колонки с названием и действиями
закреплены от 1280 px у управляемых списков. Права, аудит, заказы и обращения
закрепляют только заголовки. Числовые колонки выровнены вправо.

Общий `AdminListDetail` сохраняет две зоны для заказов и обращений от 1280 px:
список использует оставшуюся ширину, детали получают
`clamp(380px, 35vw, 720px)`. Ниже зоны расположены вертикально. Таблица теперь
доступна и в двухколоночном режиме; карточки заказов и обращений показываются
до 768 px, сотрудников и аудита — до 1280 px. Остальные списки используют
именованный focusable table region с локальной горизонтальной прокруткой.
Форма загрузки/изменения медиа ограничена 960 px; библиотека остаётся широкой.
Изображения сохраняют contain, alt и fallback; документы — отдельную иконку.

Действующие filters/search/pagination, CRUD, category details/assignments,
системные роли, protected workflow, backend permissions и состояния сохранены.
Сервисы, composables, маршруты, Backend, Client, API/OpenAPI и миграции не
менялись. Миграционные и API-contract проверки к этому UI-diff не применяются.
На момент сдачи реализации commit и push не создавались.

## Проверки

- `npm run lint`, `npm run format:check`, `npm run build` — пройдены.
- `npm run test:unit -- --maxWorkers=1` — 64/64.
- macOS Chromium: 65 функциональных E2E и 11 новых Seller workspace E2E — пройдены.
- Production responsive/axe: 320, 640, 768, 1024, 1280, 1440, 1920, 2560 px;
  проверены длинные русские строки и идентификаторы, большие суммы, отсутствие
  page/cell overflow, ширина данных, sticky headers/edges, keyboard scroll,
  выбор строки клавиатурой и обе selected detail зоны.
- Итоговый полный Linux `npm run test:e2e -- --workers=2` — 214/214,
  включая 82 baseline/UI-kit/responsive проверки. Прогон выполнен со строгим
  сравнением без перезаписи screenshots.
- Независимый UI Design Guard — ACCEPTED; blocking и оставшихся actionable
  findings нет. Все 41 изменённый Linux actual независимо просмотрены до
  обновления baseline: 37 существующих снимков заменены, 4 состояния медиатеки
  добавлены. Непосредственные selected detail и mobile action crops тоже приняты.
- `git diff --check`, repository hygiene и Markdown links — пройдены.

В начальном responsive-прогоне найден и исправлен перенос неизвестного действия
аудита в badge и длинного значения details в мобильной карточке. Проверки после
resize ждут два animation frame, чтобы измерять новую раскладку. По замечаниям
UI Design Guard расширены колонки типа роли и суммы заказа.

Первый полный Linux-прогон дал 171 passed и 43 failed: 41 ожидаемое visual
изменение и два сбоя синхронизации существующих тестов контента. Исходники HEAD
в той же тестовой среде также воспроизводили axe-сбой во время CSS-перехода
secondary → primary; публикационный тест проходил непостоянно, поскольку
проверял уже видимый published badge и disabled busy button до ответа запроса.
В тестах добавлены ожидания завершения transition и применения публикации.
Все сценарные и axe assertions сохранены; контентный UI не изменён. Отдельный
повтор прошёл 3/3, затем полный прогон — 214/214.

Высокие detail-only снимки скрывают внешнюю закреплённую шапку только на время screenshot, чтобы
она не перекрывала середину component crop. Production UI от этого не меняется.

Существующее предупреждение сборки о JS chunk >500 kB сохраняется. Linux
проверяется в отдельной временной копии с тестовыми dependency/browser caches;
работающие сервисы проекта не изменяются. Windows runner в текущей сессии
отсутствует; platform baseline не заменяются снимками другой OS.
Evidence: `frontend/admin/.tmp/a060-*` (локальные игнорируемые artifacts):
production screenshots macOS/Linux, полные логи и индекс просмотренных actuals.
Первоначальные failure artifacts сохранены в
`/private/tmp/agat-a060-linux-initial-20261001`, итоговые результаты — в
`/private/tmp/agat-a060-linux-final-20261001`. Временный контейнер удалён после
сохранения evidence. macOS проверен функционально и по новым responsive E2E;
Darwin/Windows baseline в этой задаче не обновлялись.

## Изменённые файлы

Пути относительно `frontend/admin/`, если не указан другой корень:

- `src/components/shared/AdminListDetail.vue`, `UI_KIT.md`; `src/components/ui/UiTable.vue`.
- `src/features/categories/components/CategoriesWorkspace.vue`, `CategoriesList.vue`.
- `src/features/brands/components/BrandsWorkspace.vue`.
- `src/features/attribute-groups/components/AttributeGroupsWorkspace.vue`.
- `src/features/attributes/components/AttributesWorkspace.vue`, `AttributesList.vue`.
- `src/features/employees/components/EmployeesWorkspace.vue`, `EmployeesList.vue`.
- `src/features/access-control/components/RolesWorkspace.vue`, `PermissionsWorkspace.vue`.
- `src/features/audit-log/components/AuditLogWorkspace.vue`.
- `src/features/orders/components/OrdersWorkspace.vue`, `OrdersList.vue`.
- `src/features/contacts/components/ContactsWorkspace.vue`, `ContactsList.vue`.
- `src/features/media/components/MediaWorkspace.vue`.
- `e2e/adminBaselineApi.ts`, `adminBaseline.spec.ts`, `sellerWorkspaces.spec.ts` и
  `e2e/homepage.spec.ts`, `pages.spec.ts` (синхронизация проверок) и
  просмотренные Linux screenshots в `e2e/adminBaseline.spec.ts-snapshots/`.
- `docs/ADMIN_SELLER_REDESIGN_PLAN.md`, `docs/CURRENT_STATE.md`, этот отчёт и task ledger.

## Последующая доработка 2026-10-02

Уточнение владельца: единый контейнер меню и нетабличных блоков всех страниц,
широкие рабочие таблицы. Итог и актуальные проверки описаны в
[отчёте доработки](ADMIN_CONTAINER_REFINEMENT_REPORT.md).
Результаты выше относятся к первоначальной приёмке и сохранены как история.
