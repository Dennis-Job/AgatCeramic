# Страницы брендов и уточнение каталога — 2026-11-18

Реализовано прямое уточнение владельца от 2026-11-17. Первый визуальный
референс заменён; текущие правила UI-032/UI-033 обновлены, а прежний отчёт
CATALOG_BRAND_SCOPE_2026-11-17.md остаётся историей первого этапа.

## Результат

- В каталоге и на страницах брендов алфавит оформлен одинаково и расположен
  по центру. Категории также центрированы. Верхний padding секции — 55 px.
- Выбор бренда открывает `/brand/<slug>` без прежних фильтров и якоря.
  PageIntro показывает сохранённые название, описание и картинку бренда.
  Логотип отображается целиком через contain; на телефоне — под текстом.
  При пустом описании/картинке данные не придумываются; сломанная картинка
  убирается без подстановки общего баннера каталога.
- Удалены контекстная плашка, «Сменить бренд» и повторный заголовок «Товары…».
  Категории ограничены ассортиментом бренда; счётчики и мобильный черновик
  сохраняют прежнюю проверенную логику.
- Бренд зафиксирован slug пути; фильтры, сортировка и пагинация не добавляют
  `brand` в query. Посторонний query brand не меняет ассортимент страницы.
- Выбор бренда из фильтра категории открывает его ЧПУ страницу, сохраняя
  категорию и ограничения. Список вариантов теперь active-only, чтобы не
  предлагать страницу выключенного бренда; товары общего каталога не скрыты.
- Старые `/catalog?brand=…` дают 301 с сохранением прочих параметров.
  Защищённый preview сохраняет свой путь и ограничения доступа.
- Чистые страницы имеют SSR метаданные/canonical и Brand JSON-LD в
  CollectionPage.about. Фильтры — noindex,follow. Sitemap включает все активные
  бренды с пагинацией и отказывает целиком при ошибке источника.
- Активный пустой бренд доступен; выключенный/неизвестный — 404/noindex.
  Ошибка профиля — 503 с retry, без выдачи чужого каталога.

## API и файлы

Новые `GET /api/v1/brands` и `GET /api/v1/brands/{slug}` возвращают только
публичный профиль `{id,name,slug,description,logo_url,logo_alt}`. В список входят
все активные бренды независимо от `show_in_catalog_menu`; 25 на страницу,
стабильный порядок name/id. Изображение допускается только из public image
media с image MIME; private paths/metadata и media IDs не раскрываются.

Основные файлы нового этапа:

- Backend: BrandController, PublicBrandListRequest, PublicBrandQuery,
  PublicBrandResource, public routes, PublicCatalogQuery (active brand choices),
  PublicBrandPageTest и OpenApiPublicBrandContractTest.
- Client: `app/pages/brand/[slug].vue`, `app/pages/catalog.vue`,
  `app/features/brands/{types/brand.ts,services/brands.ts}`,
  CatalogPageBody, CatalogBrandNavigation, CatalogCategories, useCatalog,
  `utils/catalogLocation.ts`, `components/shared/PageIntro.vue`.
- Sitemap: `server/routes/sitemap.xml.get.ts`.
- Tests: brand-pages, catalog-brand-scope, catalog-brand-menu,
  catalog-sticky-categories, page-indexing и brand-api/mock-api fixtures.
- Документы: OpenAPI/API/migration plan, CLIENT_UI_KIT UI-032/033,
  дизайн/план, design-qa.md, tasks и этот отчёт.

OpenAPI v23.1 учитывает совместимые новые endpoints и входящее в общий
незакоммиченный release расширение v23.0 `category_navigation`. Cumulative
breaking change относительно v22.0 явно описан в migration plan. Миграций,
изменений рабочих брендов, новых Admin-форм и коммитов нет.

## Проверки

| Проверка | Результат |
| --- | --- |
| Client typecheck / format:check / production build | PASS |
| Итоговый браузерный набор, каталог + brand pages + intro + preview + indexing | 75/75 PASS |
| 320/640/768/1024/1280 px, центрирование и точный отступ 55 px | PASS |
| SSR/canonical/robots, history, implicit brand, pagination, mobile Apply/cancel | PASS |
| Null/broken image, длинное описание, буквальный HTML без исполнения, 503/retry | PASS |
| Backend full PHPUnit, SQLite in-memory | 859 PASS, 8 SKIP; 9513 assertions |
| Финальные PostgreSQL brand/catalog/filter tests | 43 PASS, 179 assertions |
| Полный Pint / PHPStan (581 файлов) / architecture | PASS; прежние advisory complexity warnings |
| Composer validate --strict | PASS |
| OpenAPI tooling, Redocly/conventions, route/contract tests и compatibility | PASS |
| Независимый UI Design Guard | Выполнен, единственный P2 исправлен RED → GREEN |
| git diff --check | PASS |

TDD: отсутствующие API routes и прежний query URL вместо ЧПУ сначала дали
ожидаемый FAIL. Sitemap до изменения не включал бренд. P2 ревью — выключенный
бренд в select, ведущий на 404 — воспроизведён отдельным тестом и исправлен.
503/retry и broken/long-description сценарии прошли как дополнительные
characterization tests без production-изменений. Два устаревших ожидания title/H1
доставки в page-indexing приведены к существующей fixture «Доставка»;
производственный интерфейс доставки не менялся.

SQLite выполнялся в памяти, PostgreSQL — строго на allowlisted
`agatceramic_feature_test`, с CI/testing и штатной проверкой безопасности.
Рабочая БД не использовалась для тестов. Короткая проблема автоматического
approval review из-за лимита была устранена при продолжении 2026-11-18;
повторные разрешённые проверки выполнены штатно, защита не обходилась.

Реальная страница Kerama Marazzi проверена в IAB до завершения browser-сессии:
имя, сохранённый логотип, ЧПУ, одинаковый алфавит и две товарные категории.
Описание в проверенной рабочей карточке не заполнено; контент берётся из
существующего поля description, а не генерируется. Финальные responsive
скриншоты используют изолированные fixtures и находятся в `.tmp/client-e2e/`;
логи — `.tmp/brand-pages-*.log`, реальный каталог —
`.tmp/brand-pages-catalog-final.jpg`.

## Ограничения

Composer audit завершился с одним **low** advisory Laravel:
[CVE-2026-128083 / GHSA-63hm-g96r-2m2q](https://github.com/advisories/GHSA-63hm-g96r-2m2q).
Зависимости не менялись; устранение advisory не входит в переработку каталога.
Admin и все Client E2E вне затронутых маршрутов не запускались. Нативный
browser-control инструмент стал недоступен при продолжении; финальные browser
проверки завершены штатным Playwright-набором, без подмены результатов HTTP health.
