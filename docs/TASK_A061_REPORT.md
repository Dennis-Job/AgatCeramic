# TASK-A061 — контент, dashboard и простые формы

Дата: 2026-10-01. Ветка: `codex/admin-seller-redesign`.
Статус: завершена; независимый UI Design Guard принял результат.

## Результат

Dashboard и обзор магазинов используют общий `AdminWorkspace mode=overview`
с максимумом 1280 px. Профиль и настройки используют `mode=form`, максимум
960 px. Контентные редакторы используют всю доступную ширину. Dashboard
вынесен в feature-компонент; route view остался тонкой композицией.

Общий недоменный `AdminEditorLayout` композирует список страниц, редактор и
предпросмотр через slots. Container queries учитывают ширину рабочей области:

| Доступная ширина | Страницы | Общее оформление |
| --- | --- | --- |
| До 960 px | Все зоны последовательно | Редактор, затем preview |
| От 960 px | Список 240 px и редактор рядом, preview ниже | Редактор, затем preview |
| От 1120 px | Список и редактор рядом, preview ниже | Редактор и preview рядом |
| От 1360 px | Все три зоны рядом | Редактор и preview рядом |

При gutters 24 px это соответствует viewport 1024/1440 px для страниц и
1280 px для оформления. Минимум preview 420 px и ширина списка 240 px заданы
двумя design tokens, включёнными в каталог UI-kit. Неиспользуемый legacy
`.admin-page` удалён; showcase также использует общий обзорный режим.

`DraftPreview` сохраняет настоящий Nuxt iframe с viewport 1280/375 px.
Телефон центрируется внутри доступной области; широкий iframe прокручивается
в собственной именованной focusable области. Dirty guards, выбор ресурсов,
блоки/SEO, черновики, отдельная публикация и permissions сохранены.

Backend, Client source, services/composables, API/OpenAPI и миграции не менялись.
Проверки миграций и изменения API-контракта к этому diff не применяются.
При сдаче реализации изменения TASK-A060 и TASK-A061 находились в рабочем дереве.
На момент сдачи реализации commit и push не создавались.

## Проверки и evidence

- Admin lint, format, Vue/TypeScript build — пройдены; unit 64/64.
- macOS Chromium: 20 существующих E2E контента/настроек/магазинов — пройдены;
  сохранение, публикация, dirty guards, pending uploads, ошибки, retry и RBAC.
- Новые 7 production responsive/axe E2E — пройдены на 320, 640, 768, 1024,
  1280, 1440, 1920, 2560 px. Проверены ширины и центрирование workspace,
  позиции зон, длинные русские строки и отсутствие page-level overflow.
- Новая интеграция реального Admin + production Nuxt — 3/3: главная, обычная
  страница, общее оформление на всех восьми ширинах; desktop/mobile, локальная
  keyboard-прокрутка, отсутствие console/runtime errors. Только API/session
  используют fixtures; iframe не подменяется тестовым HTML.
- Nuxt production build — пройден. Client production preview E2E — 6/6:
  home/about/contacts/catalog, axe, SSR privacy, error/denied/loading/retry,
  очистка после отзыва доступа и keyboard-menu внутри iframe.
- Client development iframe/DevTools E2E — 2/2.
- Полный итоговый Linux gate: lint/format/unit64/build и 221/221 E2E,
  включая 82 строгие baseline/UI-kit проверки, — пройден без перезаписи снимков.
- UI Design Guard — ACCEPTED; блокирующих и actionable findings нет.
  Независимо просмотрены все 56 responsive и 48 real-Nuxt снимков, ключевые
  оригиналы и 6 пар Linux actual/expected до обновления baseline.
- `git diff --check`, repository hygiene и Markdown links — пройдены.

Первый Linux production-прогон: 215 passed, 6 ожидаемых visual differences.
UI Design Guard просмотрел все 6 actual/expected и одобрил замену:
профиль, настройки, content default/loading/empty/error. Baseline обновлён
только после этого review. Windows/macOS baseline не переснимались.

Review также выявил два пустых screenshot iframe при переключении ширины.
Capture теперь проверяет заголовок/шапку Nuxt перед каждым снимком и ждёт
готовности шрифтов и двух animation frame внутри iframe. Повторный прогон
3/3 прошёл; оба снимка повторно просмотрены с полноценным renderer.

Локальное evidence (ignored):

- `frontend/admin/.tmp/a061-visual/` — 56 responsive screenshots;
- `frontend/admin/.tmp/a061-nuxt/` — 48 real Admin/Nuxt screenshots;
- `frontend/admin/.tmp/a061-linux-visual/` — 56 Linux responsive screenshots;
- `frontend/admin/.tmp/a061-linux-final.log` — Linux quality gate;
- `.tmp/client-e2e/`, `.tmp/client-dev-e2e/` — Client artifacts.

В Client отсутствовали установленные Playwright/axe dev dependencies;
проверки использовали временные symlinks на уже установленные в Admin
версии из lock-файлов (1.62.1 и 4.13.0). Client dependencies/lock не менялись.
Для чистой среды требуется обычный `npm ci` в обоих приложениях.
Существующее предупреждение Vite о chunk больше 500 kB остаётся.
Production API/session и Windows/macOS snapshots этим прогоном не проверялись.
Финальная приёмка всего Seller-редизайна остаётся TASK-A062.

## Воспроизведение

```sh
cd frontend/client
npm ci
npm run build
npm run test:e2e -- preview.spec.ts
npm run test:e2e:dev
cd ../admin
npm ci
npm run lint
npm run format:check
npm run test:unit
npm run test:e2e -- --workers=2
npm run test:e2e:preview
```

Baseline сравнивать в Linux; обновлять только после просмотра actuals.
Integration config запускает Admin Vite 5176, production Nuxt 3015 и fixture
API 8015; обычная production Admin suite использует 4173 и mock iframe.
Проверки, использующие API 8015, запускать последовательно.

## Изменённые файлы TASK-A061

Все пути ниже относительно репозитория; изменения предыдущей TASK-A060
перечислены отдельно в [TASK_A060_REPORT.md](TASK_A060_REPORT.md).

- `frontend/admin/src/components/shared/AdminEditorLayout.vue`, `UiKitShowcase.vue`, `UI_KIT.md`;
- `frontend/admin/src/styles/tokens.css`, `utilities.css`;
- `frontend/admin/src/views/DashboardView.vue`, `ContentView.vue`;
- `frontend/admin/src/features/dashboard/components/DashboardWorkspace.vue`;
- `frontend/admin/src/features/pages/components/PagesWorkspace.vue`;
- `frontend/admin/src/features/appearance/components/AppearanceWorkspace.vue`;
- `frontend/admin/src/features/homepage/components/HomePageWorkspace.vue`;
- `frontend/admin/src/features/content-preview/components/DraftPreview.vue`;
- `frontend/admin/src/features/profile/components/ProfileWorkspace.vue`;
- `frontend/admin/src/features/settings/components/SettingsWorkspace.vue`;
- `frontend/admin/e2e/contentWorkspaceApi.ts`, `contentWorkspaces.spec.ts`;
- `frontend/admin/e2e-preview/contentPreview.spec.ts`;
- `frontend/admin/playwright.preview.config.ts`, `package.json`;
- `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-{profile,settings,content,content-loading,content-empty,content-error}-chromium-linux.png`;
- `docs/ADMIN_SELLER_REDESIGN_PLAN.md`, `CONTENT_WORKSPACE_UX.md`, `CURRENT_STATE.md`, этот отчёт;
- `tasks/TODO.md`, `IN_PROGRESS.md`, `DONE.md`.

## Последующая доработка 2026-10-02

Уточнение владельца: единый контейнер меню и нетабличных блоков всех страниц,
широкие рабочие таблицы. Итог и актуальные проверки описаны в
[отчёте доработки](ADMIN_CONTAINER_REFINEMENT_REPORT.md).
Результаты выше относятся к первоначальной приёмке и сохранены как история.
