# TASK-C005 — точный предпросмотр сохранённого черновика

Завершено 2026-10-01. Независимый UI Design Guard принял Admin и Client без
блокирующих замечаний.

## Реализовано

- Правая область страниц и общего оформления в Admin встраивает настоящий Nuxt
  `/preview/<slug>`; главная — `/preview/home`. Доступны компьютер 1280 px,
  телефон 375 px, обновление, полноразмерный просмотр и отдельная публичная ссылка.
  Широкий viewport прокручивается внутри именованной области с видимым фокусом.
  Сохранение перезагружает iframe; локальные изменения и pending файлы явно
  отмечаются как не вошедшие в просмотр. Публикация остаётся отдельным действием.
- Nuxt переиспользует публичные `HomePageBody`, `ContentPageBody`, `PageBlocks`,
  `SiteHeader` и `SiteFooter`: стили, медиа, порядок, анимации и reduced motion
  совпадают. Активная навигация следует публичному пути. Именованные main/nav
  позволяют различать содержимое Admin и сайта внутри iframe.
- Новый `GET /api/v1/admin/content-preview/{slug}` читает сохранённые черновики
  страницы и общего оформления. Каждый запрос проверяет Sanctum, активность
  сотрудника и `content.manage`. Проекции блоков, медиа, SEO и опубликованных
  общих слайдеров используют существующий публичный resolver.
- Черновики остаются в памяти браузерной страницы: их нет в SSR HTML/payload,
  shared state, persistent storage, URL, postMessage или публичном API.
  Preview HTML/API имеют no-store/noindex; документ и внешние ссылки — no-referrer.
  API fetch передаёт только origin для распознавания Sanctum SPA, включая
  same-origin GET. Preview token не создаётся.
- Доступ открытого просмотра проверяется каждые 15 секунд. Ошибка/отказ убирает
  черновик и показывает retryable состояние. Уход вкладки в фон/pagehide очищает
  данные сразу; возвращение требует новой проверки. Неизменившийся ответ
  сохраняет состояние слайдера. Есть loading, denied, missing и network error.
- Главная сразу открывает типизированные блоки и SEO. Секционный UI закрыт;
  существующие данные продолжают редактироваться через каноническую Page и
  синхронизацию legacy HomePage. `section=banners|sliders` перенаправляется к
  страницам с сохранением page/block; ресурсы редактируются в контексте блока.
  Файлы блока и SEO имеют независимые dirty/busy flags; редактор размонтируется
  при принятой смене страницы. Управление SEO-медиа требует `media.manage`.

## Контракт и настройка

OpenAPI v4.2, [API.md](API.md) и [migration plan](OPENAPI_MIGRATION_PLAN.md) обновлены.
DTO: `{data: {page: {title, slug, body, blocks, seo}, appearance: {header, footer}}}`.
Ошибки 401/403/404 не раскрывают черновик и имеют private/no-store/noindex headers.
Публичные endpoints и schema БД не менялись; новых миграций нет.

Admin `VITE_CLIENT_URL` задаёт HTTP(S) URL клиента без credentials/query/fragment.
Admin, Nuxt и API используют совместимую same-site cookie конфигурацию; точный
Nuxt hostname/port должен входить в `SANCTUM_STATEFUL_DOMAINS`, origin — в
`CORS_ALLOWED_ORIGINS`. Defaults дополнены портом 3000; существующий локальный
`backend/.env` также дополнен этими origins без изменения остальных значений.
Для других доменов настройки изложены в README; независимые sites с блокировкой
third-party cookies не получают обхода через публичную capability.

## Проверки

| Проверка | Результат |
| --- | --- |
| Laravel полный SQLite suite | 355 tests / 4980 assertions — PASS |
| PostgreSQL полный randomized feature suite | 275 tests / 4672 assertions — PASS |
| PostgreSQL migrations и существующие integration groups | PASS через поддерживаемый guarded runner |
| Preview/home/pages/appearance targeted PostgreSQL | 26 tests / 314 assertions — PASS |
| Composer validate, architecture, PHPStan, Pint | PASS |
| OpenAPI lint/conventions/backward compatibility | PASS; Node tooling — 5 tests PASS |
| Admin lint и production build | PASS |
| Admin полный unit suite | 64/64 — PASS |
| Admin итоговый Content production E2E/axe/responsive/visual | 26/26 — PASS |
| Content Linux production build и visual/axe rerun | 4/4 — PASS |
| Client Nuxt typecheck и production SSR build | PASS |
| Client полный production E2E | 15/15 — PASS |
| Изменённые Admin/Client source/test файлы Prettier | PASS (`--end-of-line auto` для проверки Windows EOL) |
| Независимый UI Design Guard | Admin и Client приняты, blockers нет |
| Repository hygiene/Markdown links и git diff --check | PASS |

Client suite проверяет четыре публичных маршрута и четыре preview-маршрута на
320/640/768/1024/1280 px, SSR/hydration публичных страниц, отсутствие draft в
SSR, cookie credentials, отказ/отзыв доступа, retry, безопасный текст и hidden
blocks, анимации и reduced motion. Дополнительный сценарий встраивает настоящий
Nuxt в iframe редактора и проверяет axe, клавиатурное меню/Escape/focus return.
Admin проверяет сохранение/публикацию, права медиа, dirty guards, два одновременно
выбранных файла, legacy redirects, размер просмотра и четыре Content baselines.

Первые запуски выявили неоднозначные landmarks в iframe; исправлены доступные
названия. Ошибки селекторов тестов и UTF-8 fixture исправлены; итоговые прогоны
зелёные. Визуально просмотрены и одобрены ровно восемь Content baseline PNG
(normal/loading/error/empty × Windows/Linux); остальные эталоны не менялись.

Глобальные format gates всё ещё находят прежние CRLF/формат-различия вне задачи:
Admin — несвязанные файлы, Client — `base.css`, `tokens.css`, `UiButton.vue`.
Массовая нормализация не выполнялась; все изменённые исходники проверены отдельно.
Composer audit сообщил отсутствие advisories, но использовал cache после сетевого
Packagist timeout; свежесть полного live audit этим запуском не подтверждена.

Браузерные E2E используют синтетические API fixtures; backend проверен отдельно
реальными feature/session tests. Полный Admin suite других экранов не входил
в Content-прогон. PostgreSQL использовал только allowlisted test databases,
рабочая база не сбрасывалась. PHP pdo_pgsql включался только в процессах тестов;
системный PHP не менялся. Linux сборка выполнялась в временном контейнере с
read-only source/dependency mounts и отдельной копией `/work`; контейнер удалён.

Evidence сохранён в игнорируемых `.tmp/client-e2e/`, `.tmp/c005-admin-acceptance/`,
`.tmp/c005-admin-home/`, `.tmp/c005-admin-e2e/`, `.tmp/c005-linux/` и build logs.
В tracked files добавлены только код, документация, тесты и одобренные baselines.

## Изменённые файлы

### Исправление development-предпросмотра после приёмки

- Рабочий Docker API принимал сессионные запросы от `localhost:5173`, но не
  от `localhost:3000`: процесс `artisan serve --no-reload` сохранил старое
  окружение Sanctum. Выполнены `config:clear` и перезапуск только backend.
  HTTP-проверка после перезапуска подтверждает session/XSRF cookies для обоих
  origin; рабочая БД и права пользователей не менялись.
- `app/plugins/preview-devtools.client.ts` выключает клиент Nuxt DevTools
  до его обращения к cross-origin parent только для embedded development
  `/preview/`; в отдельном окне DevTools остаётся включённым.
- Session feature test теперь использует encrypted-cookie roundtrip с новыми
  guards/session stores: вход из Admin, доступ из Nuxt по Origin/Referer,
  отказ без идентификации SPA и отказ после logout. 6 тестов / 154 assertions.
- Добавлены отдельные `playwright.dev.config.ts`, `e2e-dev/preview.spec.ts`
  и `npm run test:e2e:dev` для реального development iframe на другом порту.
  CORS origin синтетического mock API задаётся тестовой конфигурацией.
  Оба сценария прошли: iframe без console/page errors с cookie/Origin/Referer
  и standalone Nuxt с включённым DevTools. Независимый UI Design Guard принял
  исправление и screenshot `.tmp/client-dev-e2e/**/cross-origin-preview.png`.
  Финальные проверки: 2 dev E2E, 6 production preview E2E, 6 backend tests /
  154 assertions, Nuxt typecheck/build, Pint и Prettier — успешно.

- Backend: `bootstrap/app.php`, `config/sanctum.php`, `routes/api/v1/admin.php`,
  `app/Http/Controllers/Api/V1/Admin/ContentPreviewController.php`,
  `app/Http/Middleware/ProtectContentPreviewResponse.php`,
  `app/Queries/ContentPreviewQuery.php`, `tests/Feature/Api/ContentPreviewTest.php`.
- Admin: `.env.example`, `AGENTS.md`, `README.md`, `src/views/ContentView.vue`,
  `src/features/content-preview/{components/DraftPreview.vue,services/previewLinks.ts}`,
  `src/features/appearance/components/AppearanceWorkspace.vue`,
  `src/features/homepage/components/{HomePageWorkspace,HomePageSeoEditor}.vue`,
  `src/features/pages/components/{PagesWorkspace,PageBlocksEditor}.vue`.
- Admin tests: `tests/contentPreview.test.ts`,
  `e2e/{fixtures,homepage,content-blocks,banners,sliders}.ts` (сценарии — `.spec.ts`),
  восемь `e2e/adminBaseline.spec.ts-snapshots/route-content*.png` для Windows/Linux.
- Client: `AGENTS.md`, `README.md`, `nuxt.config.ts`, `public/robots.txt`,
  `package.json`, `playwright.dev.config.ts`, `e2e-dev/preview.spec.ts`,
  `app/plugins/preview-devtools.client.ts`,
  `app/components/shared/SiteHeader.vue`, `app/pages/index.vue`,
  `app/pages/preview/[slug].vue`,
  `app/features/preview/{types/preview.ts,services/preview.ts,composables/useDraftPreview.ts}`,
  `app/features/content/components/{ContentPageView,ContentPageBody}.vue`,
  `app/features/home/components/{HomePage,HomePageBody}.vue`,
  `e2e/mock-api.mjs`, `e2e/preview.spec.ts`.
- Документация: `docs/{API,OPENAPI_MIGRATION_PLAN,CLIENT_UI_KIT,CONTENT_WORKSPACE_UX,
  UI_DESIGN_REVIEW,CURRENT_STATE,TASK_C005_REPORT}.md`, `docs/openapi.json`,
  `tasks/{TODO,IN_PROGRESS,DONE}.md`.
