# Независимые сетка и меню баннеров

Дата: 2026-10-27. Требование владельца: разделить общий контент блоков главной,
переименовать «Материалы» в «Сетка баннеров», «Фактуры» — в «Меню баннеров».

## Результат

Технические типы `categories` и `materials` сохранены. Меню получило собственный
`materials.items`: содержимое, изображения и порядок редактируются независимо от
сетки. Общий feature-редактор в Admin обслуживает оба списка без копирования
медиа-controls. Сохранены permissions, nullable URL и pending/uploading guards.
В меню допустим пустой список, в сетке остаётся минимум один баннер.

Client отображает меню только из его собственного списка, в том числе без сетки.
Выбор вкладки принадлежит меню; изменение сетки не меняет выбранный баннер меню.
Сетка больше не переключает чужой блок и отображает самостоятельные карточки.
Сохранены якоря `catalog`/`materials` и текущие стили. Сохранённые пользовательские
заголовки сайта не заменялись названиями типов блоков.

Для длинных независимых названий исправлено переполнение колонок/вкладок меню.
Fallback H1 главной/предпросмотра без слайдера переносится на узком экране.

## Существующие данные и локальная миграция

Миграция `2026_10_27_120000_separate_banner_menu_items.php` однократно копирует
старые элементы сетки только в отсутствующий список меню. Черновики страниц,
их опубликованные снимки и legacy `home_pages.content` обрабатываются отдельно.
Существующие собственные списки, включая `[]`, не перезаписываются. Runtime
fallback или дальнейшая синхронизация между блоками отсутствует. Медиа, на
которое ссылается только меню, защищено от удаления.

Перед применением создан backup вне репозитория с Windows DPAPI CurrentUser и
ограничением доступа текущему пользователю. Проверены расшифрование/checksum и
восстановление PostgreSQL custom archive в отдельную временную базу. Миграция
успешно выполнена на этой копии, повторный запуск безопасен, тестовая база удалена.
Backup ID: `banner-split-20261027T102937Z`, срок хранения — до 30 дней.

Затем только эта миграция применена к локальной PostgreSQL-базе. Дополнительная
проверка внутри транзакции подтвердила сохранность всех исходных полей и контента
пяти страниц и одной legacy-записи главной, timestamps и состояний публикации.
Реальный черновик не публиковался. Автоматический down запрещён, поскольку удаление
самостоятельного списка могло бы потерять последующие правки; порядок rollback
описан в `OPENAPI_MIGRATION_PLAN.md` (v7.0).

## Файлы

- Backend: `UpdateHomePageRequest.php`, `ValidatesPageContent.php`,
  `HomePage.php`, `HomePageContentQuery.php`, `config/home_page.php`, новая
  миграция и `BannerBlockIndependenceTest.php`; актуализированы минимальные
  fixtures `BrandLogoCleanupTest.php` и `CategoryImageCleanupTest.php`.
- Admin: `HomePageBodyEditor.vue`, `homepage.types.ts`, `validation/blocks.ts`,
  `homeBannerBlocks.test.ts`, `e2e/content-blocks.spec.ts`.
- Client: `PageBlocks.vue`, `HomeCategories.vue`, `HomeCategoryCard.vue`,
  `HomeMaterialShowcase.vue`, `HomePageBody.vue`, `services/homePage.ts`,
  `types/homePage.ts`, `e2e/mock-api.mjs`, `e2e/banner-independence.spec.ts`.
- Контракт/документация: `openapi.json` (v7.0, URL `/api/v1` сохранён),
  `OPENAPI_MIGRATION_PLAN.md`, `CONTENT_WORKSPACE_UX.md`, `CLIENT_UI_KIT.md`,
  implementation plan, этот отчёт и `tasks/DONE.md`.
- Предыдущее исправление nullable `UiInput` сохранено отдельным логическим
  изменением рабочей копии; его отчёт — `HOME_BLOCK_SWITCH_FIX_2026-10-27.md`.

## Проверки

- RED до реализации: Admin не имел редактируемых menu items; Client SSR показывал
  grid items в меню; backend отклонял собственный `materials.items`.
- Admin: build, lint, format:check PASS; 51 unit-файл / 254 теста PASS;
  итоговый профильный E2E — 30/30 PASS. Проверены независимые сохранение,
  публикация/перезагрузка, одинаковые коды в разных списках, файлы при reorder,
  guards, пять responsive ширин и axe.
- Backend: полный suite 570 PASS / 1 SKIP, 6370 assertions;
  PostgreSQL-профиль 22 PASS / 214 assertions, расширенный профиль 73 PASS;
  Pint, PHPStan, composer validate/architecture, OpenAPI lint/compatibility и
  5 tooling tests PASS. Composer audit не завершился из-за timeout Packagist.
- Client: typecheck, production build, format:check PASS;
  профиль независимости 10/10 PASS, включая SSR/hydration и настоящий Nuxt-preview
  с polling после изменения сохранённого черновика.
- Полный Client E2E — 61/61 PASS. После локальной правки пространства для
  клавиатурной обводки вкладок повторены build и профильные E2E: 10/10 PASS.
  Обрезание обводки сначала воспроизведено регрессионным тестом; затем проверена
  геометрия общего outline на всех пяти ширинах. Независимый UI Design Guard —
  accepted без оставшихся P1/P2/P3; лично просмотрены 27 Admin/Client screenshots,
  включая окончательные focused screenshots первого/последнего таба 320/1280 px.
- Live smoke после локальной миграции: API главной отдаёт собственный список
  меню, Client `/` отвечает HTTP 200 и включает меню в SSR.

Существующее замечание accessibility: подпись `.eyebrow` внутри панели меню
имеет цвета `#6e6b66`/`#e9e6e0`, контраст 4.25:1 при 11 px вместо 4.5:1.
Утверждённая палитра сохранена по AGENTS.md; остальные axe-нарушения не допускаются.
Изменение этого цвета требует прямого указания владельца.

Evidence: `.tmp/banner-blocks/admin-final/`, `client-final/`, `client-full/`,
`client-focus-final/`,
соответствующие `.tmp/banner-blocks-*.log`, предыдущие Client responsive captures
в `.tmp/client-e2e/`. Backup и данные БД в repository/evidence не сохранены.
Commit не создавался.
