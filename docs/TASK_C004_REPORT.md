# TASK-C004 — общее оформление и магазины в «Контенте»

Завершено 2026-10-01. Это evidence конкретных запусков; обязательные CI gates
описаны в [CI.md](CI.md), UX-контракт — в [CONTENT_WORKSPACE_UX.md](CONTENT_WORKSPACE_UX.md).

## Реализовано

- В Admin «Контент» объединяет вкладки «Страницы», «Общее оформление», «Магазины».
  Общие шапка, навигация и подвал вынесены из редактора главной. Панель восстанавливается
  по URL `/content?section=appearance&panel=header|footer|filters`; Back/Forward работает.
  Дублирующий пункт «Главная сайта» убран из sidebar; `/home-page` перенаправляет на
  `/content?section=pages&page=home` с сохранением остальных query-параметров.
- Оформление сохраняется в черновик по разделам и публикуется отдельным действием.
  Сохранение шапки сохраняет локальные правки подвала. Ошибки допускают повтор без потери
  формы; несохранённые правки блокируют публикацию. Уход и закрытие страницы защищены
  dirty guards; выбранный файл или активная загрузка блокируют смену панели, включая Back.
- Логотип выбирается и загружается на месте с `media.manage`. Сотрудник только с
  `content.manage` видит выбранное изображение без controls управления медиатекой.
  API проверяет доступ независимо от видимости controls.
- Магазины используют прежние CRUD/расписание/permissions внутри той же рабочей области;
  добавлены проверки несохранённой формы при закрытии, Escape, смене раздела и маршрута.
  Сохранение/загрузка блокируют уход; отказ от подтверждения сохраняет данные формы.
- Nuxt получает опубликованное оформление отдельным SSR-запросом, независимо от наличия
  и публикации главной. Сбой страницы не убирает шапку/подвал других страниц; ошибка
  оформления использует существующие локальные fallback. Hydration переиспользует
  SSR payload без повторного первоначального запроса.
- Длинные подписи и строки шапки/подвала переносятся без horizontal overflow. До четырёх
  ссылок доступны в desktop navigation; более четырёх (до API-предела 16) — в существующем
  меню. Drawer сохраняет клавиатурную навигацию, Escape, focus return и локальную прокрутку.

## API и сохранность данных

Добавлены публичный `GET /api/v1/site-appearance`, административные
`GET/PATCH /api/v1/admin/site-appearance` и
`POST /api/v1/admin/site-appearance/publish` с `content.manage`.
PATCH принимает только `header`/`footer`; `logo_url` остаётся вычисляемым read-only полем.
Admin DTO включает `has_unpublished_changes`. OpenAPI и [API.md](API.md) обновлены;
переход закреплён в [OPENAPI_MIGRATION_PLAN.md](OPENAPI_MIGRATION_PLAN.md).

Хранение использует существующие `Page(home).site_layout` и
`published_snapshot.site_layout`. Миграций нет. Публикация оформления меняет только
layout snapshot, сохраняет блоки, SEO, статус и время публикации страницы. Публикация
главной сохраняет ранее опубликованное оформление; page dirty-status сравнивает
страничные данные отдельно от оформления. Старый PATCH главной совместим с тем же
черновиком. Managed media остаются защищены для черновика и опубликованного snapshot.
Транзакции и lock order сохранены; аудит `site-appearance.updated`/
`site-appearance.published` атомарен с изменением данных, rollback проверен тестом.

## Границы реализации

- Клиентские фильтры ещё не реализованы (`TASK-120`). Для них выделена отдельная панель
  «Вид фильтров» с честным информационным состоянием. Работающих переключателей и
  фиктивного сохранения нет. Настройки представления подключаются в `TASK-120`;
  состав фильтров, характеристики и товарные значения остаются в Catalog.
- Точный Nuxt-предпросмотр сохранённого черновика остаётся `TASK-C005`.
- Прежние URL `/content?section=banners|sliders` сохраняют доступ к работающим редакторам
  до `TASK-C005`. Основные входы — контекстные controls страниц; верхние дублирующие
  вкладки убраны. Старые секционные настройки главной вне шапки/подвала сохранены.

## Проверки

| Проверка | Результат |
| --- | --- |
| Laravel полный SQLite suite | 349 tests / 4828 assertions — PASS |
| Appearance, home, pages, media на SQLite и изолированном PostgreSQL | 22 tests / 212 assertions на каждом — PASS; финальный appearance PostgreSQL: 6 / 59 — PASS |
| Composer strict validate, architecture, PHPStan | PASS; PHPStan проверил 490 файлов, size/complexity рекомендации architecture gate остаются неблокирующими |
| Laravel Pint | PASS, 588 файлов |
| OpenAPI lint, conventions, compatibility | PASS; compatibility учитывает migration plan, 5 Node tooling tests — PASS |
| Admin production build, ESLint | PASS на Windows и в изолированном Linux |
| Admin unit | 61/61 — PASS |
| Новые Admin appearance E2E | 10 сценариев — PASS, включая выбранный/in-flight upload и Back |
| Admin Linux full production E2E | 172 PASS / 4 FAIL в первом прогоне; все четыре прошли serial rerun с timeout 120s. Все 176 сценариев проверены успешно, visual mismatches нет |
| Admin Windows full production E2E | 122 PASS / 54 FAIL в первом прогоне; после исправления двух переходов banners/sliders их rerun 2/2 — PASS. Оставшиеся 52 — несвязанные старые/отсутствующие Windows visual baselines |
| Client typecheck и SSR production build | PASS |
| Client production E2E | 9/9 — PASS: независимое оформление, SSR/hydration, fallback, длинная навигация и меню из 16 ссылок |
| Изменённые Admin/Client файлы Prettier | PASS; в Linux-копии использован `--end-of-line auto` |
| Независимый UI Design Guard | Принято, блокирующих замечаний нет |
| Repository hygiene, Markdown links, git diff --check | PASS |

В первом Linux-прогоне два длинных циклических сценария превысили 30s при параллельной
нагрузке, ещё два использовали удалённые верхние кнопки баннеров/слайдеров. Переходы
исправлены на сохранённые URL; все четыре перепроверены последовательно и прошли.
Полный Windows-прогон не объявляется зелёным: его оставшиеся visual failures относятся
к эталонам других экранов, включая guest login. Несвязанные golden files не обновлялись.
Просмотрены и обновлены ровно восемь Content baseline PNG (четыре состояния × Windows/Linux).

Responsive/accessibility проверены на 320/640/768/1024/1280 px: новые Admin панели,
Client `/`, `/contacts`, `/about`, `/catalog`, длинные строки и меню. Axe — 0 violations,
horizontal overflow отсутствует. Guard просмотрел 15 Admin и 20 Client снимков, затем
перепроверил длинную навигацию и максимальное меню после исправлений.

Глобальные format gates обнаруживают прежние CRLF/формат-различия вне задачи:
Admin Windows — около 199 файлов; Linux после учёта EOL/cache — `AGENTS.md`,
`UiKitShowcase.vue`, `SlidersWorkspace.vue`, `uiKitShowcase.test.ts`; Client —
`base.css`, `tokens.css`, `UiButton.vue`. Массовая нормализация не выполнялась.
`composer audit --locked` не завершился на host и в Docker из-за Packagist curl 28 timeout;
актуальный security audit зависимостей этим запуском не подтверждён.

Production E2E используют синтетические API fixtures. Backend проверен отдельно реальными
feature tests. PostgreSQL — временный контейнер с отдельной test-базой и safety guard;
контейнер удалён, рабочая база не сбрасывалась. Linux Admin использовал read-only исходники
и отдельную рабочую копию/сборку; временный контейнер также удалён.
Evidence остаётся в игнорируемых `.tmp/c004-ui/`, `.tmp/c004-linux/`,
`.tmp/c004-windows-baseline/`, `.tmp/client-e2e/` и test-results; generated artifacts
в tracked files не добавлялись. Commit не создавался.

## Изменённые файлы

- Backend: `app/Http/Controllers/Api/V1/{Admin/,}SiteAppearanceController.php`,
  `app/Http/Requests/Api/V1/Admin/UpdateSiteAppearanceRequest.php`,
  `app/Queries/SiteAppearanceQuery.php`, `app/Services/SiteAppearanceManagementService.php`,
  `app/Http/Resources/PageResource.php`, `app/Queries/HomePageContentQuery.php`,
  `app/Services/HomePageManagementService.php`, `app/Services/PageManagementService.php`,
  `app/Support/PageBlocks.php`, `routes/api/v1/admin.php`, `routes/api/v1/public.php`,
  `tests/Feature/Api/{SiteAppearanceManagementTest,HomePageManagementTest,PageBlockContentTest}.php`.
- Admin `src/features/appearance/`: `components/AppearanceWorkspace.vue`,
  `components/SiteChromeEditor.vue`, `composables/useAppearance.ts`,
  `services/appearance.ts`, `types/appearance.types.ts`.
  Прежний `src/features/homepage/components/HomePageChromeEditor.vue` перенесён в новый feature.
- Admin: `src/views/ContentView.vue`, `src/layouts/components/AdminSidebar.vue`,
  `src/router/index.ts`, `src/features/homepage/components/HomePageWorkspace.vue`,
  `src/features/homepage/services/homepage.ts`, `src/features/homepage/types/homepage.types.ts`,
  `src/features/stores/components/StoresWorkspace.vue`, `src/features/stores/composables/useStores.ts`.
- Admin tests: `e2e/appearance.spec.ts`, `e2e/homepage.spec.ts`, `e2e/banners.spec.ts`,
  `e2e/sliders.spec.ts`; в `e2e/adminBaseline.spec.ts-snapshots/` только
  `route-content{,-loading,-empty,-error}-chromium-{win32,linux}.png`.
- Client: `app/app.vue`, `app/layouts/default.vue`,
  `app/components/shared/SiteHeader.vue`, `app/components/shared/SiteFooter.vue`,
  `app/composables/useSiteAppearance.ts`, `app/services/siteAppearance.ts`,
  `app/services/homePage.ts`, `e2e/content-pages.spec.ts`, `e2e/mock-api.mjs`.
- Документация: `docs/API.md`, `docs/openapi.json`, `docs/OPENAPI_MIGRATION_PLAN.md`,
  `docs/CLIENT_UI_KIT.md`, `docs/CONTENT_WORKSPACE_UX.md`, `docs/CURRENT_STATE.md`,
  `docs/TASK_C004_REPORT.md`, `frontend/admin/README.md`, `frontend/client/README.md`,
  `tasks/TODO.md`, `tasks/IN_PROGRESS.md`, `tasks/DONE.md`.
