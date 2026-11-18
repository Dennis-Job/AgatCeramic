# Фильтр «Распродажа» — 2026-11-18

По запросу владельца сразу после «В наличии» добавлен checkbox «Распродажа».
Используется существующий общий `UiCheckbox size="compact"`, без изменения
утверждённых цветов, размеров и поведения остальных controls.

Уточнение владельца: промежуток между «В наличии» и «Распродажа» уменьшен
с 16 до 4 px общей группой checkbox. Внешние отступы до цены/характеристик
и собственная высота controls (включая touch 44 px) сохранены.
После уточнения повторно прошли typecheck/build/format и 4 E2E:
`catalog-sale` и responsive catalog (пять ширин/axe/hydration).
Логи: `.tmp/catalog-sale-spacing-build.log`, `.tmp/catalog-sale-spacing-e2e.log`.
Независимый UI Guard повторно проверил код и свежие PNG на пяти ширинах:
PASS, обрезки, пересечения и blocking findings нет.

## Поведение

`is_on_sale=1` оставляет товары, у которых установлен тот же признак, что
управляет бейджем «Распродажа». Наличие старой цены не служит признаком
распродажи. `0` или отсутствие параметра не ограничивают выдачу.
Фильтр сочетается с наличием, ценой, категорией, брендом и характеристиками;
не сужает навигацию и варианты фильтров. Некорректные значения возвращают 422.

Общая форма действует в `/catalog`, `/brand/<slug>` и `/preview/catalog`.
До «Применить» checkbox меняет только черновик; применение сбрасывает страницу,
сохраняет сортировку и добавляет chip «Распродажа». Chip можно удалить отдельно.
История и reload восстанавливают выбор. Общий сброс снимает распродажу,
сохраняя scope; «Весь каталог» снимает также бренд/категорию. В мобильной
панели закрытие отменяет черновик, а её кнопка сброса не меняет выдачу до Apply.

## Файлы задачи

- `backend/app/Http/Requests/Api/V1/PublicCatalogRequest.php` — boolean validation.
- `backend/app/Queries/PublicCatalogQuery.php` — ограничение выдачи до pagination.
- `backend/tests/Feature/Api/PublicCatalogSaleFilterTest.php` — реальная фильтрация,
  stock/category/visibility, страницы, независимые facets/navigation, ошибки.
- `backend/tests/Unit/OpenApiCatalogContractTest.php`, `docs/openapi.json`,
  `docs/API.md` — optional query parameter и контракт.
- `frontend/client/app/features/catalog/components/CatalogFilterForm.vue` — checkbox.
- `frontend/client/app/features/catalog/types/catalog.ts`,
  `composables/useCatalog.ts`, `composables/useCatalogAppliedFilters.ts` того же
  feature — draft, URL, применение и chip.
- `frontend/client/e2e/catalog-sale.spec.ts`, `e2e/fixtures/catalog-api.mjs` —
  смешанная тестовая выдача, stock, история, reset, mobile, SSR и brand.
- `docs/CLIENT_UI_KIT.md` — UI-035; план задачи и `tasks/DONE.md`.

Существующие изменения компактности controls, сделанные до этой задачи,
сохранены. Миграции и изменения Admin не требуются. Коммит не создавался.

## Проверки

- Red: backend sale=1 включал обычные товары, невалидные значения давали 200;
  frontend test не находил checkbox «Распродажа».
- Backend focused SQLite: 55 tests / 432 assertions, один существующий skip.
- Backend full SQLite: 874 tests / 9554 assertions, 8 skips; после финальных
  трёх OpenAPI assertions повторно пройден контракт: 3 tests / 169 assertions.
- PostgreSQL focused: 52 tests / 271 assertions, без skips, изолированная тестовая БД.
- Backend Pint, PHPStan, architecture guard и composer validate — PASS.
- Client typecheck, build, format:check — PASS.
- Production E2E: 32 PASS, `catalog-sale`, `catalog`, `catalog-mobile-draft`,
  `catalog-brand-scope`, `preview`. Включены пять ширин, keyboard, motion,
  SSR/hydration и axe с существующим исключением контраста image-empty.
- Реальный API localhost:8000: в выбранном керамограните 4 товара, все 4
  уже относятся к распродаже и доступны; обычные/недоступные товары в
  соответствующих отфильтрованных ответах отсутствуют. Поэтому счётчик этой
  категории при включении нового фильтра не уменьшается.
- Логи сборки/E2E: `.tmp/catalog-sale-build.log`, `.tmp/catalog-sale-e2e.log`.
- Независимый UI Design Guard: code review и визуальная проверка PNG на
  320/640/768/1024/1280 px — PASS, blocking findings нет. Расположение,
  размеры, подписи и цвета checkbox совпадают с «В наличии», обрезки и
  добавленного overflow нет. Скриншоты показывают unchecked-состояние;
  интерактивные состояния подтверждены E2E, а не ручным обходом ревьюера.
  Исполнитель отдельно подтвердил оба checkbox в DOM реального localhost:3000.

Ограниченная Windows-среда блокировала запись в существующую `.output` и
завершение Playwright test servers; повторные сборка/E2E вне ограничения
завершились с exit code 0. Локальный Nuxt-контейнер перезапущен для обновления
файлов из Windows. Dependency audit обнаружил прежний low Laravel
CVE-2026-128083; зависимости в этой задаче не менялись.
