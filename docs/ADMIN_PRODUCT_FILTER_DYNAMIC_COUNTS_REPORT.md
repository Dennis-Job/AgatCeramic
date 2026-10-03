# Динамические счётчики и устранение 429 на странице товаров — 2026-10-03

## Результат

Четыре значения счётчиков учитывают пересечение выбранных фильтров:

- «Активные» и «Скрытые» учитывают выбранный тип распродажи;
- «Распродажа» и «Не распродажа» учитывают выбранную активность;
- поиск, категория и бренд применяются ко всем значениям.

Причиной ответов 429 был объём запросов: смена фильтра запускала четыре
отдельных запроса счётчиков, запрос товаров и повторную загрузку категорий и
брендов. Общий API limit составляет 60 запросов в минуту на IP.

Счётчики теперь запрашиваются одним защищённым endpoint
GET /admin/products/filter-counts; значения возвращаются одной пачкой и
зависят от выбранных фильтров. Таблица продолжает загружаться отдельным запросом.
Категории и бренды загружаются лениво один раз за время открытой страницы;
неуспешная загрузка сбрасывает кеш для повтора. Таблица и счётчики используют
согласованную задержку при переключении фильтров: 250 ms, поиск — 350 ms.
Из-за отказа счётчиков список товаров остаётся доступным; все четыре бейджа
показывают недоступное значение, а сообщение 429 объявляется как role="alert".

Endpoint использует существующую фильтрацию, валидацию и catalog.view
authorization. OpenAPI и API guide обновлены; схему БД и rate limit не меняли.

## Проверки

- UI Design Guard: независимое ревью пройдено, blocking findings нет.
- Admin lint, Prettier check изменённых исходников, production build/typecheck.
- Admin unit: 90/90.
- Целевой Playwright E2E: 2/2, включая сетевые запросы, кеш справочников,
  responsive widths 320/640/768/1024/1280, axe и 429.
- Laravel Pint пройден.
- Целевые Backend API и OpenAPI contract tests: 5 passed, 181 assertions.
- OpenAPI JSON parsing и git diff --check пройдены.

## Основные изменённые файлы

- backend/app/Queries/ProductQuery.php
- backend/app/Http/Controllers/Api/V1/Admin/ProductController.php
- backend/routes/api/v1/admin.php
- backend/tests/Feature/Api/ProductSearchFilterTest.php
- backend/tests/Unit/OpenApiCatalogContractTest.php
- docs/openapi.json, docs/API.md
- frontend/admin/src/features/products/services/productFilterCounts.ts
- frontend/admin/src/features/products/composables/useProductFilterCounts.ts
- frontend/admin/src/features/products/composables/useProductEditor.ts
- frontend/admin/tests/productFilterCounts.test.ts
- frontend/admin/e2e/productFilterCounts.spec.ts
- frontend/admin/e2e/catalogApi.ts
- этот отчёт и task ledger.
