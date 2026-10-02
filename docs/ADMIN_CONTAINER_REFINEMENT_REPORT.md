# Доработка TASK-A057–TASK-A062 — единый контейнер Admin

Дата: 2026-10-02. Ветка: `codex/admin-seller-redesign`.
Статус: завершена; независимый UI Design Guard принял macOS/Linux
и настоящий Nuxt-preview, финальные gates пройдены.

## Результат

По уточнённому референсу владельца меню, заголовки, действия, фильтры,
feedback, пагинация, медиазагрузка, обзорные экраны, формы и редакторы имеют
единый центрированный контейнер. Source of truth — semantic token
`--admin-container-max-width: 1280px` и общий utility `.admin-container`.
Шапка учитывает собственный padding в максимуме: внутренние края совпадают
с контейнером страниц. На ширинах до 640 px gutters 16 px, далее 24 px.

Таблицы рабочих списков сохраняют доступную ширину (1872 px на экране1920 px)
и локальную прокрутку; общий максимум на них не распространяется.
Вложенные небольшие таблицы dashboard/редактора остаются в своём блоке.
Мобильные карточки и loading/empty/error используют общий контейнер.

Заказы и обращения используют широкую таблицу с деталями ниже в контейнере.
Явный выбор вызывает общий `AdminListDetail.showDetail(loader)`, после загрузки
переводит фокус и прокрутку к панели; кнопка «Вернуться к списку» возвращает
их к выбранной строке. Инициализация страницы не перехватывает фокус.
Backend loading и бизнес-состояние остаются в feature composable.

Редактор контента ограничен 1280 px; порог трёх зон понижен до 1240 px внутренней
ширины, поэтому navigation/editor/live-preview доступны одновременно на
широком экране. На меньшей ширине preview переходит ниже; iframe сохраняет
настоящий Nuxt renderer и desktop/mobile viewport 1280/375 px с локальным scroll.
Профиль и настройки также используют общий page-level максимум 1280 px;
компактные внутренние редакторы и диалоги сохраняют прежние ограничения.

API, permissions, CRUD/import/export, unsaved guards, публикация, шрифты,
размеры текста и схема БД не менялись. Миграций и обновления OpenAPI не требуется.
Commit/push не создавались.

## Проверки и ревью

- `npm run check`: lint, format, 64 unit, TypeScript и production build пройдены.
- Новый geometry suite: 19 маршрутов/вариантов ×8 ширин
  320/640/768/1024/1280/1440/1920/2560; проверяет совпадение краёв меню,
  заголовков, фильтров и page-level блоков, отсутствие page overflow.
- Existing content/product/Seller suites сохраняют проверки длинных строк,
  sticky/local scroll, крайних действий, геометрии зон и a11y.
  Выбор деталей дополнительно проверяет focus, попадание заголовка во viewport
  и возврат к исходной строке.
- Полный E2E до обновления эталонов: 227 passed / 15 failed. 14 failures —
  намеренные изменения profile/settings/orders/contacts/media; 1 — неполный
  mock нового appearance-теста, исправлен на существующий mockContentWorkspace.
- Независимый UI Design Guard просмотрел 19 широких экранов, responsive
  контент/длинные данные/детали и 14 пар expected/actual. Все blocking findings
  устранены; принят semantic shared contract, доступность и сохранение workflow.
- 14 Darwin baseline приняты reviewer до копирования. Автоматическое
  обновление отключено, strict tolerance 30 pixels и assertions не ослаблялись.
- Реальная интеграция Admin + Nuxt: 3/3 passed, три варианта контента ×8 ширин,
  desktop/mobile renderer, keyboard local scroll и console/runtime guard.
  Первый запуск 2 passed / 1 failed обнаружил существующий resize race: проверка
  overflow выполнялась через 5 ms после viewportresize. Тест теперь ждёт
  фактическое compact menu и 2 animation frames, как основной content suite;
  исходная проверка overflow сохранена.
- Финальный строгий macOS E2E: 242/242 passed, включая 19 новых
  geometry scenarios и существующую полную responsive/axe матрицу.
- Linux CI-проверка на изолированной копии Admin в существующем
  `agatceramic-admin-e2e` образе с Node 24.19, чистым `npm ci` и Chromium:
  lint/format/64 unit/build пройдены; initial full — 228 passed / 14 visual
  failures. Все 14 Linux actual/expected пар отдельно приняты UI Design Guard
  и скопированы в baseline. Финальный строгий Linux E2E: 242/242 passed.
  Допуски screenshot/axe и retries не ослаблялись.
- Настоящий Nuxt дополнительно просмотрен независимым UI Design Guard:
  главная, «О компании», оформление, desktop/mobile на 1920/320 px одобрены.
- `git diff --check`, repository hygiene и локальные ссылки изменённых
  Markdown-файлов: пройдены; diff Backend/Client/API/миграций пустой.

Windows runner в этой среде недоступен: Windows baseline не проверены
и не обновлены; нельзя считать их актуальной успешной Windows-проверкой.
Результаты прошлой приёмки A057–A062 относятся к 2026-10-01 и сохранены как
историческое evidence. Существующее предупреждение Vite о chunk >500 kB остаётся.

## Evidence и воспроизведение

Локальные ignored artifacts:

- `frontend/admin/.tmp/container-layout/` — 19 wide geometry screenshots;
- `frontend/admin/.tmp/a059-visual/`, `.tmp/a060-visual/`, `.tmp/a061-visual/` —
  свежие продукт/списки/content и selected/mobile/action captures;
- `frontend/admin/.tmp/a062-visual/` — полная responsive/axe матрица;
- `frontend/admin/.tmp/a061-nuxt/` — 48 реальных desktop/mobile iframe captures;
- `/private/tmp/agat-container-baseline-candidates/manifest.json` — 14 принятых
  Darwin actual/expected пар;
- `/private/tmp/agat-container-linux-baseline-candidates/manifest.json` —
  14 отдельно принятых Linux пар;
- `/private/tmp/agat-container-{check-final,full-final,nuxt,linux-initial,linux-final}.log` — gates.

```sh
cd frontend/admin
npm run check
npm run test:e2e -- --workers=2
npm run test:e2e:preview
```

Последняя команда использует собранный `frontend/client/.output`;
при отсутствии сборки сначала выполнить Client `npm ci && npm run build`.
Оба E2E-прогона запускаются последовательно; real-preview поднимает API 8015,
Nuxt 3015 и Admin 5176. API/session являются test fixtures; iframe renderer реальный.

## Изменённые файлы

- Styles: `src/styles/tokens.css`, `utilities.css`, `navigation.css`.
- Shared: `AdminWorkspace.vue`, `AdminListDetail.vue`, `AdminEditorLayout.vue`,
  `UiKitShowcase.vue`, `UI_KIT.md`.
- Feature workspace: products, categories, brands, attribute-groups, attributes,
  employees, roles, permissions, audit-log, orders, contacts, media.
- Feature lists: `CategoriesList.vue`, `AttributesList.vue`, `EmployeesList.vue`,
  `OrdersList.vue`, `ContactsList.vue` — container для состояний/mobile.
- QA: `e2e/containerLayout.spec.ts`, `contentWorkspaces.spec.ts`,
  `sellerWorkspaces.spec.ts`, `e2e-preview/contentPreview.spec.ts`;
  28 принятых `e2e/adminBaseline.spec.ts-snapshots/*-chromium-{darwin,linux}.png`.
- Docs: план редизайна, current state, этот отчёт, ссылки из A057–A062 reports;
  task ledger TODO/IN_PROGRESS/DONE.

Пути `src/` и `e2e/` в этом списке относятся к `frontend/admin/`.
