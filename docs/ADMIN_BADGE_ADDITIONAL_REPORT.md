# Голубой вариант бейджа Admin

Дата: 2026-10-03

## Изменение

В общий `UiBadge` добавлен semantic tone `additional`. Он использует
существующую палитру `blue-light`: фон `blue-light-50` (`#F0F9FF`) и текст
`blue-light-700` (`#0B4A6F`). В `/ui-kit` новый вариант показан подписью
«Дополнительный» рядом с «Основной». Публичные API и существующие варианты
бейджа не менялись. Бейдж категории в таблице товаров также переключён на новый
тон; статусы активности и распродажи сохраняют свои semantic tones.

## Проверки

- Admin lint, build и unit tests — passed (16 файлов, 91 тест).
- Prettier для изменённых файлов и `git diff --check` — passed.
- UI Design Guard принял изменение без замечаний: контраст текста около 8,8:1,
  подпись не полагается только на цвет, витрина использует flex-wrap.
- Маршрут `/ui-kit` открыт в браузере; accessibility tree содержит новый
  вариант. Отдельный визуальный скриншот не проверялся.

## Изменённые файлы

- `frontend/admin/src/components/ui/UiBadge.vue`
- `frontend/admin/src/components/shared/UiKitShowcase.vue`
- `frontend/admin/src/components/shared/UI_KIT.md`
- `frontend/admin/src/features/products/components/ProductEditor.vue`
- `frontend/admin/tests/uiBadge.test.ts`
- `tasks/DONE.md`
- `docs/ADMIN_BADGE_ADDITIONAL_REPORT.md`
