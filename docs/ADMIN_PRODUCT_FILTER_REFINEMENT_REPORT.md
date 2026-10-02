# Фильтры товаров — 2026-10-02

## Изменения

По референсу владельца убраны видимые лейблы поиска, категории и бренда на
`/products`; сохранены `aria-label`/`accessible-name` и текущие placeholders.
Активность и распродажа используют новый общий `UiSegmentedControl`: светлая
единая подложка, белый выбранный сегмент, синий текст, существующие Seller tokens.
Сохранены все три состояния, фильтрация API, debounce, счётчик и сброс.

Primitive не зависит от products, поддерживает disabled группы/опций,
fieldset/legend и native radio (Tab, стрелки, Space). В forced-colors selected
использует системные Highlight/HighlightText, disabled — Canvas/GrayText. На 320 px ширина сегментов
учитывает длину слов; «Не распродажа» переносится по пробелу. Примеры активного
и disabled состояния добавлены в `/ui-kit`. API и миграции не менялись.

## Проверка

- Lint, build/typecheck и unit: 69/69 пройдены.
- Форматирование исходников пройдено командой
  `npx prettier . --check --ignore-path ../../.gitignore --ignore-path .prettierignore`.
  Обычный `npm run format:check` видит 26 старых ignored отчётов в `.tmp`;
  эти несвязанные артефакты не изменялись.
- Сценарные catalog/product E2E: 51/51; после корректировки responsive повторные
  product workspace: 3/3. Проверены выбор, query, сброс, клавиатура, focus,
  disabled и axe; ширины 320/640/768/1024/1280, таблица до 2560.
- Полный macOS Chromium strict E2E: 268/268; пять baseline обновлены после
  независимого просмотра. Полный Linux Compose strict E2E: 268/268; production build пройден.
- Независимый UI Design Guard принял код и актуальные responsive/focus/disabled
  снимки, blocking findings нет. Все пять macOS и пять Linux baseline утверждены до обновления.

Снимки: `frontend/admin/.tmp/product-filter-review/`; отчёты E2E — локальные
ignored артефакты. Windows в этой доработке не запускался; его baseline не заменяются
снимками другой платформы.

## Изменённые файлы

- `frontend/admin/src/components/ui/UiSegmentedControl.vue`
- `frontend/admin/src/features/products/components/ProductEditor.vue`
- `frontend/admin/src/components/shared/UiKitShowcase.vue`
- `frontend/admin/src/components/shared/UI_KIT.md`
- `frontend/admin/tests/uiKitShowcase.test.ts`
- `frontend/admin/e2e/adminBaseline.spec.ts`
- `frontend/admin/e2e/productWorkspace.spec.ts`
- `frontend/admin/e2e/softFocus.spec.ts`
- macOS/Linux baseline `/products`: normal/loading/empty/error/forbidden
- этот отчёт, `tasks/TODO.md`, `tasks/DONE.md`
