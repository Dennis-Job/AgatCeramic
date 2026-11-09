# Покупка товаров вне распродажи — 2026-11-09

Статус: выполнено.

В каталоге у активных товаров с ценой и остатком кнопка покупки была
недоступна, если `is_on_sale = false`. Поле означает «Распродажа» в Admin
и импорте, но Client и серверная корзина ошибочно использовали его как
разрешение продавать товар. В карточке показывалось «Продажа приостановлена»,
а Schema.org сообщал OutOfStock. Та же проверка исключала обычные товары
из вариантов, рекомендаций и сопутствующих.

Исправлен общий источник: участие в распродаже влияет только на плашку.
Активность товара/категории, наличие цены, достаточный остаток и целое
количество по-прежнему проверяются сервером. Формы API и БД не менялись;
миграций нет. Утверждённые CSS, tokens и палитра сохранены.

Изменённые файлы:

- `backend/app/Services/CartItemManagementService.php` — add/update без условия распродажи.
- `backend/app/Queries/PublicProductQuery.php` — обычные участники групп и связей.
- `backend/tests/Feature/Api/GuestCartTest.php` — обе отметки add → update → checkout, прежние ограничения.
- `backend/tests/Feature/Api/PublicProductTest.php` — обычные варианты/рекомендации.
- `frontend/client/app/features/product/components/ProductCard.vue` — общий grid/row.
- `frontend/client/app/features/product/components/ProductPurchase.vue` — покупка и статус наличия.
- `frontend/client/app/pages/products/[slug].vue` — Schema.org availability.
- `frontend/client/e2e/catalog-card-standard.spec.ts` — обычный товар в каталоге и корзине.
- `frontend/client/e2e/product-pages.spec.ts` — обычный товар/row, статус и SSR.
- `frontend/client/e2e/fixtures/product-api.mjs`, `cart-api.mjs` — согласованные сценарии.
- `docs/API.md`, `docs/openapi.json`, `docs/CLIENT_UI_KIT.md` — актуальный смысл флага и UI-023.
- `tasks/DONE.md` — результат задачи.

Проверки:

- До исправления: 3 ожидаемых backend failure; 2 browser failure на disabled и неверном статусе.
- Client `npm run typecheck`, `npm run build` — PASS.
- Playwright catalog-card-standard/product-pages/product-collections/cart/checkout — 41 PASS.
- После уточнения обычной row: отдельный тест с чистой корзиной — PASS;
  финальные карточки/SSR/адаптивность — 4 PASS. Снимки 320/640/768/1024/1280.
- Backend GuestCartTest/PublicProductTest/OrderCreationTest/PublicCatalogCardTest/
  PublicProductGroupIdentityTest — 32 всего, 30 PASS, 2 PostgreSQL-only SKIP; 312 assertions.
- Scoped Prettier и Pint, PHPStan, architecture guard, OpenAPI lint/conventions,
  compatibility с HEAD и `git diff --check` — PASS.
- Независимый UI Design Guard — PASS; замечания к тесту обычной row и реестру устранены.
  Визуально просмотрены каталог 320/1280, row 320 и товар 1280; остальные ширины — E2E.
- Live localhost:3000: после перезапуска Client отмеченный Hansgrohe 71222000
  добавлен из каталога и виден в корзине с количеством 1; прежние позиции сохранены.
  Кнопки клея и плитки без распродажи также стали активными. Товары без остатка
  остаются недоступными. Заказ при ручной проверке не оформлялся.

Исходные планы от 2026-10-29, трактующие `is_on_sale` как запрет покупки,
устарели в этой части; текущий контракт описан в API.md и CLIENT_UI_KIT.md.
