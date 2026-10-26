# Меню в шапке сайта — 2026-10-26

Запрос владельца: показывать навигационное меню в хедере по `exampleSite.html`
и управлять им в «Контент → Общее оформление».

## Результат

Управление ссылками уже существовало в «Шапка и навигация»: название, URL,
добавление, удаление, порядок, сохранение черновика и отдельная публикация.
Причиной отсутствия desktop-меню было условие `navigation.length <= 4`:
при пяти и более ссылках вся горизонтальная навигация исчезала.

Ограничение снято. Выше 1024 px управляемые ссылки находятся между логотипом
и иконками. Длинные названия и дополнительные пункты переносятся в центральной
области; её высота ограничена `min(144px, 25dvh)`, чтобы крайний разрешённый
объём не перекрывал страницу. Для переполнения используется нативная вертикальная
прокрутка, доступная с клавиатуры; CSS не скрывает scrollbar, но ОС может показывать
её только при прокрутке. Боковое меню со всеми пунктами доступно на любой ширине;
до 1024 px оно остаётся основным способом навигации.

Редактор получил заголовок «Меню сайта» и пояснение о расположении ссылок,
редактировании и публикации. Утверждённые палитра, шрифты, underline,
иконки и shared primitives сохранены. API, разрешения, БД, миграции и сохранённые
черновики/опубликованные данные не менялись; новый demo-контент на сайт не добавлялся.

## Проверки

- Client: `npm run typecheck`, `npm run format:check`, `npm run build` — PASS.
- Admin: `npm run lint`, `npm run format:check`, `npm run build` — PASS.
- Admin: `npm run test:unit` — 242/242 PASS; `appearance.spec.ts` — 15/15 PASS.
- Client: финальный полный `npx playwright test` — 51/51 PASS, включая два новых теста меню.
- `git diff --check` — PASS.
- Независимый UI Design Guard — accepted после просмотра финального diff и screenshots.

Регрессии воспроизведены до исправления: отсутствие меню с пятью ссылками;
шапка высотой 901 px при 16 предельно длинных названиях. Новые тесты проверяют
видимость пяти ссылок, active state, переход по Enter, боковое меню и возврат
фокуса на пяти ширинах, ограниченную высоту на 1280×900 и 1280×360,
прокрутку к последней ссылке, Shift+Tab и поиск под фактической шапкой.
Admin E2E проверяет добавление, редактирование, перемещение, удаление,
публикацию и повторную загрузку пяти ссылок. Общие регрессии охватывают
SSR/hydration, preview, overlays, reduced motion, axe и overflow.

Первоначальные sandbox-прогоны остановились на недоступном browser cache,
EPERM в Nuxt build output и временном кеше Vitest. Повторные проверки выполнены
вне sandbox с установленным Chromium. Для изменения высоты viewport новый тест
ожидает применение layout через два animation frame, как существующий UI-kit тест.
Сборки сохраняют предупреждения о размере Admin chunk и времени plugin hooks.
Полный Admin E2E и development iframe suite в рамках этой локальной правки не запускались.

## Визуальные свидетельства

- Client: `.tmp/header-nav-client-verified/` — полный финальный прогон.
- Admin: `.tmp/header-nav-admin/` — редактор на 320/640/768/1024/1280 px.
- Крайний desktop: `.tmp/header-nav-bounded-green/header-navigation-oversize-d0512-s-to-keyboard-focused-links/bounded-header-900.png`
  и `bounded-header-360.png` — независимо просмотрены UI Design Guard.

Использовались синтетические тестовые API; рабочая БД не изменялась.
Скриншоты в `.tmp` — локальные проверочные артефакты, не часть Git.

## Изменённые файлы

- `frontend/client/app/components/shared/SiteHeader.vue`
- `frontend/client/e2e/header-navigation.spec.ts`
- `frontend/client/e2e/mock-api.mjs`
- `frontend/admin/src/features/appearance/components/SiteChromeEditor.vue`
- `frontend/admin/e2e/appearance.spec.ts`
- `docs/CLIENT_UI_KIT.md`
- `docs/CONTENT_WORKSPACE_UX.md`
- `docs/HEADER_NAVIGATION_2026-10-26.md`
- `tasks/DONE.md`

API-контракт проверен по существующему пути `site-appearance` и редактору;
структура запросов/ответов не менялась, обновление OpenAPI и миграции не требуются.
Коммит не создавался.
