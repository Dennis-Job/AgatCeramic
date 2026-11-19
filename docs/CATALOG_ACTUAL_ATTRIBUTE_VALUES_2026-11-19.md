# Готовые значения всех характеристик — 2026-11-19

Продолжение замечания владельца от 18 ноября: ширину шва и остальные
характеристики необходимо выбирать из значений товаров, без ручного ввода.

## Результат

В `CatalogAttributeFilter` больше нет текстовых, числовых и date-полей.
Все типы характеристик используют реальные options API и общий UiCheckbox;
HEX-цвета сохраняют UiColorSwatch. Boolean больше не добавляет «Да/Нет»
самостоятельно: показывается только то, что встречается у товаров.
Пустое множество сообщает «Нет доступных значений». Выбранные старые значения
сохраняются для снятия, с читаемыми boolean-подписями.

Цена остаётся отдельным бюджетным диапазоном. Desktop-auto и mobile-Apply,
chips/reset/history, цвета и размеры controls сохраняются. На реальном
`/catalog?category=zatirka` проверены «1-10» (2 товара) и «1-15» (1 товар)
в характеристике ширины шва. Мутаций товарных данных не выполнялось.

API извлекает DISTINCT фактические значения всех типов по публичным товарам
category scope до pagination. Условия явного назначения и is_filterable,
исключение скрытых товаров/категорий и прежний независимый scope метаданных
сохранены. Полные строки товаров не загружаются в PHP.

`values` поддерживается всеми типами: OR внутри характеристики, AND между ними.
Числовые эквиваленты нормализуются без дублей, boolean/нечисловой мусор не
приводится к нулю; строки сохраняются точно, даты проверяются как ISO.
Старые text/min/max ссылки продолжают работать; смешанная форма отклоняется.
Лимит JSON увеличен с 16384 до 65536, чтобы выбрать одно корректное длинное
text-значение с экранированием; лимиты 20 атрибутов/50 выбранных значений
сохранены. Per-value пределы: обычные строки 255, text 10000, числа 1024.

## Файлы этой доработки

- `backend/app/Queries/PublicCatalogAttributeQuery.php` — варианты и typed exact match.
- `backend/app/Support/CatalogAttributeOptionValue.php` — общий контракт
  нормализации/ограничений options и выбранных значений.
- `backend/app/Http/Requests/Api/V1/PublicCatalogRequest.php` — формы и лимиты.
- `backend/tests/Feature/Api/PublicCatalogAttributeChoiceTest.php`, адаптированные
  `PublicCatalogFilterTest.php`, `PublicCatalogSizeFilterTest.php` и
  `backend/tests/Unit/OpenApiCatalogContractTest.php`.
- `docs/openapi.json`, `docs/API.md`, `docs/CLIENT_UI_KIT.md` (UI-038).
- `frontend/client/app/features/catalog/components/CatalogAttributeFilter.vue`.
- `frontend/client/e2e/catalog-attribute-values.spec.ts`, `e2e/fixtures/catalog-api.mjs`;
  адаптированные `catalog.spec.ts` и `catalog-auto-filters.spec.ts`.
- план `docs/superpowers/plans/2026-11-18-catalog-actual-values.md`, `tasks/DONE.md`.

Предыдущие незакоммиченные изменения реактивных фильтров сохранены.

## Проверки

- Client typecheck/build/format — PASS; 42 production E2E — PASS (18 ноября,
  frontend-код после этого не менялся). Проверены exact zero/decimal/date,
  длинный текст, OR/AND, значение за пределами первой страницы, отсутствие
  ручных полей, реальный boolean-набор, desktop auto/mobile draft и старые URL.
- API затирки и соответствующие checkbox в открытом браузере подтверждены
  19 ноября: оба значения ширины шва выводятся и фильтруют 2/1 товар.
- Независимый UI Guard — PASS, code и пять свежих PNG на
  320/640/768/1024/1280 px. Desktop-значения и длинный текст без overflow;
  на mobile видимая часть проверена визуально, нижние элементы — E2E.
  Новые стили primitives не добавлялись, keyboard/disabled наследуются.
- Client-логи: `.tmp/catalog-actual-values-build.log`,
  `.tmp/catalog-actual-values-e2e.log`.
- Backend focused SQLite — 103 tests / 583 assertions PASS, один ожидаемый
  PostgreSQL-only Unicode skip. Guarded PostgreSQL 17.9 — 100 tests /
  421 assertions PASS без skips, изолированная `agatceramic_feature_test`.
- PHPStan (582 файла), Pint изменённых backend-файлов, architecture guard
  и `git diff --check` — PASS.
- Дополнительно проверены повреждённые scalar-значения: настоящий NUL не
  создаёт обрезанный/ложный вариант, literal `\\u0000` и emoji сохраняются,
  NUL в любом выбранном значении отклоняется до SQL. Реальный API раньше
  ошибочно сопоставлял `1-10` с NUL-суффиксом, теперь возвращает 422.
  PostgreSQL-каст новых scalar-типов
  защищён CASE с `pg_input_is_valid` (проверено на установленном PG 17.9).
  Исходные значения в БД не исправляются и не удаляются.
- После последнего исправления NUL-параметра повторно пройден целевой choice
  suite: SQLite 31 tests / 90 assertions, PostgreSQL 31 / 92; Pint/PHPStan/diff
  повторно PASS. Более широкие 103/100 suite относятся к предыдущему шагу;
  после двух новых регрессий целиком не повторялись.

Полные unrelated suites и production-scale load не запускались.
Существующее исключение axe для `.image-empty` сохраняется.
Миграций, изменений Admin, зависимостей и коммита нет.
