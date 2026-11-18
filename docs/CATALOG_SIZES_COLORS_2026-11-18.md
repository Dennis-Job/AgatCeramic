# Размеры и цвета в фильтрах — 2026-11-18

По замечаниям владельца:

- «Размер» показывает checkbox-список всех уникальных размеров из характеристик
  публичных товаров выбранного раздела до пагинации, а не свободное поле.
- «Цвет» показывает кружки в ряд по сохранённым HEX-кодам; название доступно
  при наведении и скринридеру. Выбранный цвет отмечен границей и контуром.

В реальном керамограните API возвращает `300х300`, `300х301`, `600х300`, цвета
`#FFFFFF` / Белый, `#EFDFCE` / Песочный и `#808080` / Серый. Значения проверены
заново в локальном API. Формат хранения и товарные данные не менялись.

## Реализация

`PublicCatalogAttributeQuery` получает DISTINCT строковые значения `razmer`
(string/text) по всему публичному category scope. Сохраняются фильтруемость,
явное назначение выбранной категории, исключение скрытых товаров/категорий и
прежний scope справочников. Продукты целиком в PHP не загружаются.

`PublicCatalogRequest` принимает для такого размера `{values:[...]}` — точные
строки OR, без нормализации и совпадения по префиксу. Старое `{text:...}`
сохраняется, смешанная форма отклоняется. Остальные строковые характеристики
работают по-прежнему. Условия разных характеристик соединяются AND.

Общий `UiColorSwatch` содержит native checkbox с label/title, keyboard focus,
checked и inherited disabled. Круг 24 px, цель 32 px и touch 44 px, ряды
переносятся. HEX валидируется до CSS; неизвестные старые коды показываются
текстовым checkbox без подстановки выдуманных оттенков. Палитра интерфейса
сохранена. `CatalogAttributeFilter` переиспользует этот primitive и обычный
UiCheckbox для размеров; общая форма обслуживает каталог, бренд и preview.
Draft/Apply, мобильная отмена, chips, reset, history и reload сохранены.

## Изменённые файлы

- `backend/app/Http/Requests/Api/V1/PublicCatalogRequest.php`;
- `backend/app/Queries/PublicCatalogAttributeQuery.php`;
- `backend/tests/Feature/Api/PublicCatalogSizeFilterTest.php`;
- `docs/openapi.json`, `docs/API.md`, `docs/CLIENT_UI_KIT.md` (UI-036);
- `frontend/client/app/components/ui/UiColorSwatch.vue`;
- `frontend/client/app/utils/hexColor.ts`;
- `frontend/client/app/features/catalog/components/CatalogAttributeFilter.vue`;
- `frontend/client/e2e/catalog-sizes-colors.spec.ts`, `e2e/fixtures/catalog-api.mjs`;
- план `docs/superpowers/plans/2026-11-18-catalog-sizes-colors.md`, `tasks/DONE.md`.

## Проверки

- Red: API не выдавал размеры в options и отклонял exact values; UI не находил
  размер из товара за пределами первой страницы. Затем соответствующие тесты PASS.
- Backend focused SQLite — 74 tests / 493 assertions, один PostgreSQL-only skip.
- PostgreSQL — 71 tests / 330 assertions, без skips, guard тестовой БД
  `agatceramic_feature_test`, отдельный disposable PHP runtime.
- Pint, PHPStan, architecture guard и diff check — PASS.
- Client typecheck, build, format — PASS.
- 36 production E2E — PASS: размеры/цвета, sale, catalog, mobile draft, brand scope,
  preview. Новые сценарии проверяют exact OR/AND, first-page independence,
  legacy text/fallback codes, chips/reload, keyboard, touch, пять ширин и axe;
  существующие сценарии — также SSR/hydration, loading/error и отмену черновиков.
- Независимый UI Design Guard: код и пять свежих form PNG на
  320/640/768/1024/1280 px — PASS, blocking findings нет. Проверены размеры,
  переносы, выбранный белый круг, безопасность HEX и наследование disabled.
  Focus/disabled — review кода; интерактивная проверка — E2E.
- Логи `.tmp/catalog-sizes-colors-build.log`, `.tmp/catalog-sizes-colors-e2e.log`.
  Скриншоты: `.tmp/client-e2e/catalog-sizes-colors-size--fd25a-mobile-Apply-cancels-drafts/`.

Полный backend suite и PostgreSQL load suite не повторялись: проверены затронутые
каталоговые сценарии. В axe сохраняется прежнее исключение контраста
`.image-empty` у карточек с отсутствующим изображением; новой ошибки нет.
Локальный Nuxt-контейнер перезапущен для обновления файлов Windows.
Миграций, изменений Admin, мутаций товарных данных и коммита нет.
