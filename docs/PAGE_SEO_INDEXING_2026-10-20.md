# Индексация и SEO управляемых страниц

Дата: 2026-10-20. Статус: реализовано и проверено локально, commit не создан.

## Поведение

- При создании страницы чекбокс «Разрешить индексацию поисковыми системами»
  выключен. Все существовавшие страницы сохраняют прежнюю индексацию.
- SEO-вкладка доступна для всех страниц, включая «О нас». SEO title первоначально
  берётся из названия страницы; пустые описания вызывают рекомендацию, но не
  блокируют сохранение. Выключение индексации не удаляет метаданные.
- Сохранение меняет черновик. Публикация страницы активирует `is_indexable` из
  сохранённого snapshot. Общее оформление не публикует индексацию страницы.
- Опубликованная страница с запретом доступна по ссылке и получает в SSR HTML
  `noindex,follow`. Разрешённая получает `index,follow`. Ошибки, preview и
  пагинация каталога сохраняют ограничения.
- Новые публичные `/{slug}` используют существующий Nuxt renderer. `/home`
  перенаправляется на `/`; `preview` зарезервирован для защищённого предпросмотра.
- `/sitemap.xml` читает все страницы публичной коллекции и включает только
  опубликованные разрешённые URL. Используются опубликованный slug и дата
  публикации. При сбое любой страницы API возвращается 503, без частичного XML.
- Динамический `robots.txt` содержит Sitemap и запрет обхода preview. Страницы
  с `noindex` доступны обходу: иначе робот не прочитает директиву.
  [Документация Google](https://developers.google.com/search/docs/crawling-indexing/block-indexing).

Это реализация SEO для управляемых контентных страниц. SEO товаров, категорий и
брендов, расширенные sitemap, redirects и остальные задачи Phase 8 остаются отдельно.
На production необходимо задать `NUXT_PUBLIC_SITE_URL` с публичным origin.

## Данные, API и локальное применение

Миграция `2026_10_20_000000_add_page_indexing.php` добавляет boolean:
существующие строки получают true, последующие INSERT — false по умолчанию.
Старые опубликованные snapshots без ключа трактуются как true и не становятся
«изменёнными» из-за самого обновления. Контент страниц не переписывается.

Миграция проверена на SQLite и изолированном PostgreSQL, затем применена к
локальному Docker backend. После добавления новых Nitro routes и изменения
редактора перезапущены только локальные client/admin dev-сервисы.
Проверены настоящие HTTP-ответы: API возвращает boolean, `/about` — 200 и
`index,follow`, `/sitemap.xml` — 200 с четырьмя существующими страницами,
`/robots.txt` — 200 со ссылкой на sitemap, Vite отдаёт новый SEO-редактор.

OpenAPI `info.version` повышена до v5.0: прежние закрытые response-схемы не
разрешали дополнительное поле. URL API остаётся `/api/v1`. Migration/rollout и
rollback описаны в `OPENAPI_MIGRATION_PLAN.md`; compatibility gate принимает
описанное изменение major-редакции.

## Проверки

| Проверка | Результат |
| --- | --- |
| Backend SQLite, полный набор | 495 PASS / 1 SKIP, 5924 assertions |
| Backend PostgreSQL Feature, полный набор | 415 PASS, 5608 assertions |
| Composer validate / audit | PASS, advisories не найдены |
| Pint / architecture guard / PHPStan | PASS |
| OpenAPI lint / conventions / compatibility policy | PASS |
| Admin lint / format / build | PASS |
| Admin unit | 32 files / 165 PASS |
| Admin E2E: SEO, pages, blocks, homepage, workspaces | 21 PASS |
| Client typecheck / format / build | PASS |
| Client production E2E | 20 PASS |
| Client development iframe E2E | 2 PASS |
| Responsive / axe / hydration | PASS на 320/640/768/1024/1280 px |
| Независимые backend review и UI Design Guard | Approved, blocking findings нет |
| git diff --check | PASS |

SQLite пропускает PostgreSQL-only поиск брендов с кириллицей; полный PostgreSQL
набор его проверяет. Первоначальный параллельный backend-прогон пересёкся по
временным XLSX-файлам. Попытка отдельного Windows storage потребовала создания
папки app и выявила ZipArchive rename error; итоговые последовательные прогоны
на штатном storage прошли полностью. Production-код импортов не менялся.
Существующее предупреждение Admin о размере build chunk сохраняется.
Полная несвязанная Admin visual-baseline suite не запускалась: layout/tokens не
менялись. Admin E2E использует mock API; настоящий Nuxt проверен отдельно,
backend — на PostgreSQL, локальные публичные endpoints — напрямую по HTTP.

Пятнадцать проверенных UI Design Guard PNG сохранены в `.tmp/page-seo-review/admin/`
и `.tmp/client-e2e/page-indexing-generic-page-44cfb-and-hydrates-without-errors/`.
Временный PostgreSQL-контейнер с tmpfs удалён после проверок; рабочая база не
использовалась для тестовых данных.

## Исправление nullable URL изображения после пользовательской проверки

Пользователь обнаружил падение SEO-вкладки «О нас»: допустимый API-ответ
`og_image_url: null` попадал в строковый `UiInput`, где вычислялась `.length`.
Предыдущие Admin fixtures подставляли пустую строку и не покрывали этот ответ.
Два regression-теста сначала воспроизвели тот же TypeError.

Admin DTO теперь отражает `string | null`; только при отображении поля URL
преобразуется в `''`. Исходный null остаётся в модели до пользовательского ввода,
медиа-ссылка не меняется, а ответ сохранения с null снова отображается безопасно.
Стили, общий UiInput, backend-контракт и данные страницы не менялись.

Полный Admin unit-набор 165/165, lint/format/build и 21 E2E прошли повторно.
E2E теперь получает настоящий nullable формат SEO и проверяет отсутствие
pageerrors при открытии, сохранении, публикации и повторной загрузке.
Responsive/axe пройдены на всех пяти ширинах. Новые screenshot evidence находятся
в `.tmp/page-seo-null-review/admin/`. Локальная Vite-админка перезапущена для сброса
устаревшего dev-кэша компонента.
Независимый UI Design Guard принял исправление и все десять новых снимков;
blocking findings нет. HTTP-проверка Vite подтвердила, что отдаётся новая обработка null.

## Изменённые файлы этой доработки

Backend:

- `database/migrations/2026_10_20_000000_add_page_indexing.php`
- `app/Models/Page.php`, `app/Support/PageBlocks.php`
- `app/Services/PageManagementService.php`
- `app/Queries/{PublishedPageQuery,HomePageContentQuery}.php`
- `app/Http/Resources/PageResource.php`
- `app/Http/Requests/Api/V1/Admin/{StorePageRequest,UpdatePageRequest}.php`
- `app/Http/Requests/Api/V1/Admin/Concerns/ValidatesPageContent.php`
- `tests/Feature/Api/PageIndexingTest.php`

Admin (`frontend/admin/`):

- `src/features/pages/{types/page.types.ts,validation/seo.ts}`
- `src/features/pages/composables/{usePages,usePageBlocks}.ts`
- `src/features/pages/components/{PageFormDialog,PageBlocksEditor}.vue`
- `src/features/homepage/components/HomePageSeoEditor.vue`
- `src/features/homepage/types/homepage.types.ts`
- `tests/{pages,pageBlocks,pageSeo}.test.ts`
- `e2e/{page-seo,content-blocks}.spec.ts`

Client (`frontend/client/`):

- `app/features/content/components/ContentPageView.vue`
- `app/pages/{index,[slug]}.vue`
- `app/services/{contentPages,homePage}.ts`, `app/types/{contentPage,homePage}.ts`
- `server/routes/{sitemap.xml,robots.txt}.get.ts`, `shared/utils/pageUrls.ts`
- `public/robots.txt` удалён в пользу динамического маршрута
- `e2e/{page-indexing.spec.ts,mock-api.mjs}`, `README.md`

Документы: `docs/{API.md,CONTENT_WORKSPACE_UX.md,OPENAPI_MIGRATION_PLAN.md,openapi.json}`,
этот отчёт, `tasks/{DONE,TODO}.md`. Ранее сделанное исправление 422 при сохранении
слайдера и его тесты/отчёт сохранены в рабочем дереве без изменений по существу.
