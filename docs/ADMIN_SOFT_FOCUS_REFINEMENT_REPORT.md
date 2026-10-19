# Доработка Seller UI-kit — мягкие active/focus состояния

Дата: 2026-10-02. Ветка: `codex/admin-seller-redesign`.
Статус: завершено. UI Design Guard и строгие macOS/Linux прогоны приняты.

## Результат

По референсу владельца «Input» и замечанию к переключателю «Все» на `/products`
убрано наложение насыщенной границы, ring 2 px, белого offset и outline 2 px.
Общие controls используют одну тонкую рамку и полупрозрачный ореол:
`--admin-focus-color: #647dd1`, край 1 px, offset 0, halo 4 px
`rgb(100 125 209 / 0.14)`.

Text fields, select, date и textarea показывают мягкий фокус; composite input
не рисует дополнительный native outline. Кнопки, ссылки и фокусируемые регионы
получают тонкий keyboard outline с halo. Таблицы сохраняют inset стратегию,
чтобы `contain: paint` не обрезал focus. Выбранные radio/checkbox wrappers
используют `primary-200/25`; контрастный dot/check сохраняется. При pointer
selection нет keyboard halo, при `focus-visible` он появляется.

Ошибка сохраняет красную рамку и alert, disabled не получает halo.
Forced colors использует системный Highlight outline. Независимое ревью
выявило прежний дефект: `text-transparent` у unselected glyph превращался в
системный цвет и все radio выглядели выбранными. Теперь невыбранный Circle/Check
имеет `visibility: hidden`, независимо от цвета; декоративные SVG `aria-hidden`.
Выбранный и disabled-selected индикаторы остаются видимыми.

Размеры, отступы, типографика, основной акцент, permissions и поведение CRUD
сохранены. Backend, миграции и API-контракт не менялись.

## Изменённые файлы

Все `src/`, `tests/` и `e2e/` пути здесь относятся к `frontend/admin/`.

- `src/styles/controls.css`, `index.css`, `tokens.css`, `reset.css`,
  `navigation.css`: единый visual contract и удаление прежних duplicate outlines.
- `src/components/ui/UiButton.vue`, `UiInput.vue`, `UiTextarea.vue`,
  `UiSelect.vue`, `UiDatePicker.vue`, `UiRadio.vue`, `UiCheckbox.vue`,
  `UiTable.vue`, `UiPopover.vue`: ссылки на shared styles; glyph visibility.
- `src/components/shared/AdminListDetail.vue`, `UiKitShowcase.vue`, `UI_KIT.md`:
  регион фокуса, tokens и актуальная спецификация.
- Ссылки на прежние focus classes заменены в `ContactsList.vue`, `OrdersList.vue`,
  `DraftPreview.vue`, `HomePageHeroEditor.vue`, `MediaReferenceField.vue`,
  `MediaWorkspace.vue`, `PagesWorkspace.vue`, а также Login/Forgot/Reset views.
  Локальные копии визуального стиля не добавляются.
- `tests/catalogComponents.test.ts`: UiTable contract использует общий inset class.
- `e2e/softFocus.spec.ts`: пять pointer/keyboard/fields/button/inset/forced-color
  сценариев, включая состояние ошибки, disabled, selected glyph и overflow.
- Platform baseline, план Seller, `CURRENT_STATE.md`, этот отчёт и task statuses.

## Проверки и независимое ревью

Итоговые проверки:
Первый полный прогон каждой OS: 247/255 passed, восемь ожидаемых screenshot
отличий, без функциональных падений. Real Admin/Nuxt preview: 3/3 passed.

| Проверка | Результат |
| --- | --- |
| macOS `npm run check` | lint/format, 64 unit, TypeScript/build — PASS |
| macOS финальный `npm run test:e2e -- --workers=2` | 255/255, строгие screenshots/axe — PASS |
| Linux isolated Docker `npm run check` | lint/format, 64 unit, TypeScript/build — PASS |
| Linux финальный `npm run test:e2e -- --workers=2` | 255/255, строгие screenshots/axe — PASS |
| Реальный Admin/Nuxt `npm run test:e2e:preview` | 3/3 — PASS |
| Дополнительный `npm run format:check` после документации UI-kit | PASS |
| `git diff --check`, repository hygiene и local Markdown links | PASS |

Пять новых focus-сценариев входят в полный набор. Проверены 320, 602, 640, 768, 1024,
1280, 1440, 1920 и 2560 px; 602×910 воспроизводит viewport замечания владельца.
Edge contrast и edge/halo contrast на white/gray50/primary25 не ниже 3:1.

UI Design Guard принял pointer/keyboard, поля, filled/secondary/danger кнопки,
ошибку, inset таблицы и forced-colors; P2 glyph visibility исправлен и повторно
принят. Все 16 platform before/actual/diff кандидатов независимо приняты до замены:
8 Darwin и 8 Linux для products default/loading/empty/error/forbidden и
login/forgot-password/reset-password. Размеры 1280×720 сохранены, изменения
ограничены focus Email либо двумя selected radio wrappers; прочие pixels
идентичны. После согласования скопированы только эти PNG;
`updateSnapshots: none` и tolerance 30 px сохраняются. Windows не проверен.

Проверены все Admin-маршруты из UI_DESIGN_REVIEW и `/ui-kit`. Существующее
предупреждение Vite о размере bundle сохранено; зависимостей не добавлено.

Evidence: `frontend/admin/.tmp/soft-focus/` — products pointer/keyboard,
input 320–1280, textarea/select/date, error, buttons, checkbox, table,
forced-colors и forced-colors-checkbox. Логи: `/private/tmp/agat-soft-focus-check.log`,
`agat-soft-focus-macos-final.log`, `agat-soft-focus-linux-final.log`,
`agat-soft-focus-preview.log`, `agat-soft-focus-format-final.log` в `/private/tmp/`.
Manifest и before/actual/diff: `/private/tmp/agat-soft-focus-review/`.
Коммит и отправка этой доработки не выполнялись.
