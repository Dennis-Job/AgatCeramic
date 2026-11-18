# Каталог бренда — 2026-11-17

Исторический отчёт первого этапа. Владелец заменил этот визуальный вариант
уточнением со страницами брендов; актуальное состояние —
[BRAND_PAGES_2026-11-18.md](BRAND_PAGES_2026-11-18.md) и UI-032/UI-033.

Реализован выбранный владельцем первый вариант: глобальный алфавит брендов
над контекстом и категориями, затем фильтры и товары. Реальные PageIntro,
карточки, фотографии, цены, Inter и утверждённая палитра сохранены.

## Поведение

- Меню брендов начинает подбор всего ассортимента нового бренда.
- Категории бренда со счётчиками остаются стабильными при дополнительных
  фильтрах. Родитель считает потомков; пустые ветки выбранного бренда скрыты.
- Категория сохраняет бренд/цену/наличие/сортировку, очищает страницу,
  коллекцию и характеристики. «Все товары бренда» снимает категорию.
- Бренд и категория снимаются независимо. «Сбросить фильтры» сохраняет их;
  «Весь каталог» очищает весь подбор, сохраняя сортировку.
- Вход от категории сохраняется: select бренда уточняет категорию. Когда
  бренд уже выбран, повторный select скрыт; доступны меню и снятие значения.
- На телефоне выбор в drawer остаётся черновиком до «Применить».
  Закрытие отменяет правки и незавершённый lookup до перехода на desktop.
- Название пустого бренда сохраняется. Пустая выдача предлагает снять
  дополнительные ограничения, не очищая контекст автоматически.

## Реализация и контракт

`GET /api/v1/catalog` дополнен `category_navigation`:
`{brand: {id,name,slug}|null, total, categories: [{id,name,slug,parent_id,product_count}]}`.
Старые глобальные `categories` и `brand_navigation` сохранены. Счётчики
получаются группировкой публичных продуктов, предки обходятся в памяти без
N+1; циклы ограничены visited. Правила публикации товаров не менялись.
Редакция OpenAPI v23.0 учитывает строгие response schemas; rollout/rollback
описаны в migration plan. Миграций и изменений рабочих данных нет.

Изменённые файлы:

- Backend: `app/Queries/PublicCatalogNavigationQuery.php`,
  `app/Http/Resources/PublicCatalogNavigationResource.php`,
  `app/Http/Controllers/Api/V1/CatalogController.php`,
  `tests/Feature/Api/CatalogCategoryNavigationTest.php`,
  `tests/Unit/OpenApiCatalogContractTest.php`.
- Client, `app/features/catalog/components/`: CatalogBrandNavigation,
  CatalogCategories, CatalogCategoryBranch, CatalogFilterForm, CatalogPageBody.
- Client, `app/features/catalog/composables/`: useCatalog,
  useCatalogAppliedFilters, useCatalogDraftFilters; `types/catalog.ts`.
- Client E2E: новый `catalog-brand-scope.spec.ts`, `fixtures/catalog-api.mjs`,
  catalog, catalog-brand-menu, catalog-mobile-draft и preview specs.
- Документы: API.md, openapi.json, OPENAPI_MIGRATION_PLAN.md,
  CLIENT_UI_KIT.md (UI-031/032/033), дизайн/план, design-qa.md, tasks.

## Проверки

| Проверка | Результат |
| --- | --- |
| Client typecheck, format:check, production build | PASS |
| Каталог + меню + sticky + mobile + cards + preview E2E | 44/44 PASS |
| SSR, hydration, 320/640/768/1024/1280 px, keyboard, axe | PASS в указанных E2E |
| Backend full PHPUnit (SQLite in-memory) | 852 PASS, 8 PostgreSQL-only SKIP; 9446 assertions |
| Связанный backend набор после рефакторинга | 45 PASS, 1 SKIP; 371 assertions |
| PostgreSQL, связанные feature-тесты | 43 PASS, 210 assertions |
| PostgreSQL, финальная новая выборка | 5 PASS, 18 assertions |
| Полный Pint, PHPStan (577 файлов), architecture | PASS; прежние advisory complexity warnings вне новой выборки |
| Composer validate --strict | PASS |
| OpenAPI tooling tests / Redocly / conventions / compatibility | PASS; утверждённый major v23.0 |
| Независимый UI Design Guard | Выполнен; два P2 устранены и покрыты RED → GREEN |
| Реальный каталог в IAB | Kerama Marazzi: всего 5, керамогранит 4, ступени 1; смена бренда проверена |
| git diff --check | PASS |

Новые E2E сначала подтвердили отсутствие контекста. Отдельно воспроизведены
и исправлены отменённый mobile draft после resize, неполное accessible name
chips и закрытие меню из-за принудительной прокрутки «Сменить бренд».
В старом preview iframe-тесте проверка скрытого на 768 px topbar-текста
заменена проверкой видимого заголовка черновика; сам responsive UI не менялся.

Windows Playwright использует уже установленный Chromium через
`PLAYWRIGHT_BROWSERS_PATH`. PostgreSQL проверялся временным контейнером в
сетевом пространстве postgres, на localhost и строго allowlisted
`agatceramic_feature_test`, с CI/testing и штатной проверкой безопасности;
рабочая БД не использовалась. Полный SQLite suite выполнен до внутреннего
выделения метода подсчётов; затем повторены затронутые тесты, PostgreSQL,
Pint и static analysis.

Ограничение: `composer audit --locked --no-cache` не получил сведения
Packagist из-за curl timeout. Это не успешный аудит; зависимости не менялись.
Полные Client E2E вне затронутых каталог/preview и Admin не запускались.

Артефакты: `.tmp/catalog-scope-*.log`,
`.tmp/catalog-brand-scope-final.jpg`, responsive screenshots в
`.tmp/client-e2e/`. Итоговая визуальная сверка — в `design-qa.md`.
Коммит не создавался.
