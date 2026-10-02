# Фильтры товаров: счётчики и сброс — 2026-10-02

Продолжение [первой доработки фильтров](ADMIN_PRODUCT_FILTER_REFINEMENT_REPORT.md)
по дополнительному запросу владельца.

## Результат

- Видимые заголовки «Активность»/«Распродажа» скрыты через `hideLabel` общего
  `UiSegmentedControl`; legend остаётся доступным скринридеру.
- Активная кнопка «Сбросить» использует новый `UiButton variant="surface"`:
  белая поверхность, тонкая граница, светло-синий hover и синий текст.
  Disabled остаётся прозрачным и не принимает hover.
- Четыре числовых бейджа добавлены к «Активные», «Скрытые», «Распродажа» и
  «Не распродажа». По явному выбору владельца они учитывают поиск, категорию
  и бренд, независимо от выбранных activity/sale, сортировки и страницы.

Количество берётся из `meta.total` действующего paginated API четырьмя
ограниченными запросами `per_page=1`. Строки всей выборки не выгружаются.
API-контракт и миграции не менялись. Запросы изолированы в feature service,
loading/race/debounce — в отдельном feature composable. При изменении поиска
используется debounce 350 ms; статусные переключатели не перезагружают counts.
После сохранения/удаления/публикации/скрытия товара counters обновляются.

Loading показывает «…», частичный сбой — «—» только у недоступного счётчика
и видимое сообщение; настоящий ноль сохраняется. Ошибка counters не блокирует
список товаров. Старые ответы не перезаписывают текущие counts.
Скринридер получает неизменное имя варианта и описание числа через
`aria-describedby`. На 320 px бейдж может перейти под подпись целиком.
В forced-colors используются системные цвета; transitions отключены, чтобы
selected текст сразу был контрастным.

## Проверки

- Lint, production build/typecheck и 73 unit пройдены.
- Форматирование исходников пройдено с исключением уже ignored `.tmp`:
  `npx prettier . --check --ignore-path ../../.gitignore --ignore-path .prettierignore`.
  Стандартный скрипт видит старые временные отчёты; они не менялись.
- Targeted E2E: 10/10; counter recheck 2/2, catalog 48/48.
- Unit покрывают scope, totals за пределами страницы, zero/partial failure,
  debounce/race и повторный refresh после mutation.
- E2E проверяют hidden legends, reset enabled/disabled/hover, независимость
  цифр от переключателей, обновление по search/category, настоящий 0,
  loading/partial failure, keyboard/focus, system color contrast и axe.
- Независимый UI Design Guard принял responsive 320/640/768/1024/1280,
  loading/partial failure/forced-colors и пять macOS baseline до обновления.
- Полный строгий E2E: macOS 270/270 и Linux Compose 270/270. Linux
  production build пройден. Пять Linux baseline также одобрены до обновления.
- `git diff --check` пройден; backend/API и миграции не затрагивались.
- Windows не запускался; его snapshots не заменяются другой платформой.

Снимки находятся в `frontend/admin/.tmp/product-filter-counts-review/`;
E2E используют контролируемые mock API, реальный каталог не изменяется.

## Файлы этой доработки

- `frontend/admin/src/components/ui/UiSegmentedControl.vue`
- `frontend/admin/src/components/ui/UiButton.vue`
- `frontend/admin/src/components/shared/UiKitShowcase.vue`
- `frontend/admin/src/components/shared/UI_KIT.md`
- `frontend/admin/src/features/products/components/ProductEditor.vue`
- `frontend/admin/src/features/products/composables/useProductEditor.ts`
- `frontend/admin/src/features/products/composables/useProductFilterCounts.ts`
- `frontend/admin/src/features/products/services/productFilterCounts.ts`
- `frontend/admin/src/features/products/types/product.types.ts`
- `frontend/admin/tests/productFilterCounts.test.ts`
- `frontend/admin/e2e/productFilterCounts.spec.ts`
- `frontend/admin/e2e/catalog.spec.ts` — ожидания запросов таблицы исключают
  отдельные count-запросы с `per_page=1`.
- Пять macOS и пять Linux `/products` baseline.
- Этот отчёт, `tasks/TODO.md`, `tasks/DONE.md`.


## Уточнение кнопки сброса от 2026-10-02

По следующему запросу владельца border удалён из `UiButton variant="surface"`.
В приложении этот вариант используется у «Сбросить», а также в демонстрации
UI-kit. Белый фон, голубой hover, синий текст и keyboard focus сохраняются.
Обновлены `UiButton.vue`, `UI_KIT.md`, этот отчёт и task ledger.
Тесты, сборка и обновление snapshots после этой правки не выполнялись по
прямому указанию владельца; результаты выше относятся к предыдущей версии.
Независимый UI Design Guard принял правку по коду; blocking findings нет.
