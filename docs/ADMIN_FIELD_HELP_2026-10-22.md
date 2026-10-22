# Подсказки полей Admin — 2026-10-22

## Поведение

По указанию владельца пояснения `UiField help` во всей админке перенесены из
текста под control в иконку «!» в кружке справа от label. Используется
`CircleAlert` из существующего набора Lucide. Ошибки валидации остаются видимыми
под полем с `role=alert`; пояснения секций, статусы и предупреждения не скрываются.

Общий `UiField` сохраняет implicit label/input association. CSS grid размещает
текст подписи и sibling help-button в одной строке; button не вложен в label,
не активирует input и имеет `type=button`. Длинная подпись переносится, колонка
иконки сохраняет место и не расширяет поле.

Переиспользована существующая tooltip directive/оформление. Opt-in `.help`
поддерживает hover (300 ms), keyboard focus, click/tap, Escape и outside click.
Переход курсора в окно сохраняет его открытым. Escape оставляет фокус на trigger,
первый Escape внутри dialog закрывает hint, следующий — dialog. Portal остаётся
в ближайшем landmark/dialog, учитывает viewport и сохраняет aria-describedby.
Режим обычных action tooltips остаётся pointer-only. Палитра и tokens не менялись.

## Проверки

- TDD: пять новых unit-сценариев наблюдались RED до реализации, затем GREEN.
- Admin check: lint, format, 242 unit tests, TypeScript/production build PASS.
- Новые browser-проверки: все пять ширин 320/640/768/1024/1280, axe, hover popup,
  keyboard/Escape, touch toggle/outside, отсутствие input activation, dialog scope.
- В E2E уточнены locators до textbox: `getByLabel` с substring теперь закономерно
  находил и input, и help button с именем поля. Проверки прежних inline helpers
  переключены на открытие tooltip. Старые action tooltip сценарии прошли.
- FullPage capture меняет геометрию/scroll и закрывает fixed tooltip; для окна
  подсказки используются viewport PNG, для страницы — обычные full-page PNG.
- Полный первоначальный Linux-прогон: 644/657 PASS. Пять ожидаемых visual diffs,
  семь тестов адаптированы к новому helper UI. Ещё один axe-сбой редактора команды
  воспроизведён без изменений задачи на чистом `HEAD` в отдельной временной копии
  `/tmp/agat-field-help-before.YhDHrs/frontend/admin`: тот же button и contrast
  violation с вычисляемым фоном около `#6b8cc7`. Попытки ждать готовности/animations
  не устранили его и отменены; `teamAccess.spec.ts` оставлен без изменений.
  Утверждённые цвета, contrast exceptions и проверки не менялись.
- Финальный полный Linux-прогон: **656/657 PASS**. Один сбой был внесён слишком
  строгим test-only locator заполненного поля в существующем backdrop-сценарии;
  его исходный locator возвращён. Отдельный финальный Linux repeat **1/1 PASS**,
  macOS copy/backdrop **3/3 PASS**. Продуктовый код не менялся после полного прогона.
  Все 657 сценариев покрыты успешными проверками, но это полный прогон плюс
  один профильный повтор, а не единый зелёный запуск 657/657.
- Финальные профильные macOS проверки: **9/9 PASS** (help/settings/action),
  принятые macOS baseline **5/5 PASS**, итого **14/14**. Linux baseline и все
  help/settings/action сценарии прошли в полном прогоне.
- В финальном Linux-прогоне прежний teamAccess axe-сценарий прошёл. Отдельное
  воспроизведение на чистом HEAD/macOS подтвердило его нестабильное замечание
  контраста вне этой задачи; палитра и contrast guard сохранены.
- После финальных test-only правок lint/format и `git diff --check` PASS.

## Независимое ревью и эталоны

UI Design Guard принял реализацию по исходникам и пяти актуальным PNG:
расположение иконки, viewport clamp, читаемость, native label, ошибки, focus,
hover, touch и Escape. В UI-kit записаны оба режима tooltip.

UI Guard отдельно просмотрел и разрешил принять все десять actual/diff:
`/settings`, `/media`, media loading/empty/error на Linux/macOS. Обновлены только
эти десять изображений. Darwin дополнительно синхронизирует устаревшие снимки
с уже действующим UI-kit; посторонних visual changes не вносили. Windows runner в текущем окружении
недоступен; Windows эталоны не подменяются другой ОС и требуют review/update
на настоящем Windows runner. Никаких ослаблений snapshot tolerance нет.

Артефакты: `frontend/admin/.tmp/field-help-*`; журналы проверок —
`/tmp/agat-field-help-*.log`.

## Файлы

- `frontend/admin/src/components/ui/UiField.vue`, `tooltip.ts` — общий паттерн.
- `frontend/admin/src/components/shared/UiKitShowcase.vue`, `UI_KIT.md` — пример и контракт.
- `frontend/admin/tests/fieldHelp.test.ts`, `e2e/fieldHelp.spec.ts` — новые проверки.
- `frontend/admin/e2e/settings.spec.ts`, `catalog.spec.ts`, `media.spec.ts`,
  `pages.spec.ts`, `content-blocks.spec.ts`, `slider-blocks.spec.ts` — адаптация сценариев к новому UI.
- `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-{settings,media,media-loading,media-empty,media-error}-chromium-{linux,darwin}.png` — 10 принятых эталонов.
- Этот отчёт и `tasks/DONE.md` — результаты задачи.

Backend, API, БД и migrations не менялись. Commit не создавался.
