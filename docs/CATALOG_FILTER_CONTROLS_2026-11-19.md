# Правки controls каталога по референсам

Реализованы светлая сортировка, светлый фон квадратных checkbox,
переключатели наличия и распродажи, внутренние префиксы цены и двойной ползунок.
Границы цены — реальные min/max публичного category/brand scope до остальных
фильтров и pagination. Пустые поля используют границы как placeholder.
Диапазон без вариации цены оставляет текстовые поля. Desktop auto-apply,
mobile draft/Apply, URL/history, reset и доступность сохранены.

## Изменённые файлы

- `frontend/client/app/components/ui/UiCheckbox.vue` — общий switch и наследуемый светлый фон.
- `frontend/client/app/components/ui/UiTextField.vue` — префикс/placeholder и mobile16.
- `frontend/client/app/components/ui/UiRange.vue` — два связанных native ползунка.
- `frontend/client/app/features/catalog/components/CatalogFilterForm.vue` — связывание controls и цены.
- `frontend/client/app/features/catalog/components/CatalogPageBody.vue` — фон сортировки.
- `frontend/client/app/features/catalog/types/catalog.ts` — price_range.
- `frontend/client/e2e/catalog-filter-controls.spec.ts` — новые keyboard/pointer/mobile/reset проверки.
- `frontend/client/e2e/catalog.spec.ts`, `frontend/client/e2e/fixtures/catalog-api.mjs` — актуальные checkbox/price fixtures.
- `backend/app/Queries/PublicCatalogQuery.php` — SQL aggregate границ.
- `backend/tests/Feature/Api/PublicCatalogPriceRangeTest.php` — scope/visibility/decimals/null/zero.
- `docs/API.md`, `docs/openapi.json`, `docs/CLIENT_UI_KIT.md`, `tasks/DONE.md` — контракт и статус.

## Проверки

Client typecheck, production build, format:check и 38 браузерных тестов PASS.
Пять ширин 320/640/768/1024/1280, axe/SSR/hydration, keyboard, mouse drag,
включая раздвигание совпавших ползунков, mobile draft и отмена проверены.
Backend SQLite — 39 passed, 1 PostgreSQL-specific skip, 146 assertions.
Pint, PHPStan, architecture guard, OpenAPI lint/conventions и 5 tooling tests PASS.
Реальный API керамогранита вернул диапазон 1342.50–4600.00.
PostgreSQL-прогон не выполнен — контейнерный host отклонён существующим
test allowlist, локальный MAMP не загружает pdo_pgsql. Правила тестовой
изоляции сохранены. Миграций и изменения данных нет.

Независимый UI Guard проверил реализацию и снимки. Исправлены touch-зоны,
шрифт мобильных полей, раздвигание совпавших handles и normal-size switch.
Итог осмотрен на localhost. Снимок — `.tmp/catalog-warm/catalog-controls-final.jpg`.
