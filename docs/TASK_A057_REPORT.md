# TASK-A057 — Seller tokens и UI-kit

Дата: 2026-10-01. Ветка: `codex/admin-seller-redesign`.
Статус: завершена; UI Design Guard принял первоначальную реализацию.

Уточнение владельца от 2026-10-01: восстановлена прежняя типографика
Nunito/Poppins, удалены новые глобальные размеры текста и межстрочные интервалы.
Тесты и проверки после уточнения не проводились по прямому указанию владельца.
Результаты проверок ниже относятся к реализации до этого уточнения;
визуальные snapshots после возврата типографики не обновлялись.

## Результат

Admin получил общую визуальную основу по предоставленным референсам Ozon Seller:
белый фон, primary `#005BFF`, серо-голубые вспомогательные поверхности,
прежнюю типографику Nunito/Poppins и компактные controls. Изменения выполнены через tokens
и общие primitives, без копирования стилей в features.

- Основной текст — Nunito; display — Poppins. Существующие размеры текста,
  межстрочные интервалы и подключение локальных шрифтов сохранены.
- Высоты controls — минимум 32/36/40 px. Кнопки, input, select, datepicker,
  checkbox и radio согласованы по плотности; длинный текст увеличивает высоту.
- Вторичная кнопка использует светлую подложку. Badges стали компактными,
  прямоугольными, с семантическими цветами и читаемыми подписями.
- `UiCard` использует радиус 20 px и поддерживает `surface=white|muted`
  независимо от `padded`. Меню имеют радиус 16 px и мягкую тень.
- У полей и карточек нет видимой тени. Zero-shadow tokens записаны прозрачной
  тенью: `none` ломает составной `box-shadow` Tailwind и скрывает focus ring.
- Composite-поля имеют один видимый focus ring вокруг всего control.
  Внутренние input помечены `data-composite-control`; native outline других
  controls и focus кнопок очистки сохраняются.
- Цвет текущего логотипа следует общей палитре через token фильтра. UI-kit
  показывает новые surfaces, прежнюю типографику и все tokens.

Глобальная навигация и ширина страниц относятся к следующим этапам.
Маршруты, permissions, API, формы и бизнес-сценарии сохранены. Backend, клиент,
OpenAPI и миграции не изменялись; проверка diff это подтверждает.

## Проверки первоначальной реализации до уточнения типографики

| Проверка                                        | Результат                                                                      |
| ----------------------------------------------- | ------------------------------------------------------------------------------ |
| `npm run lint`                                  | Пройдено                                                                       |
| Prettier изменённых файлов                      | Пройдено                                                                       |
| `npm run test:unit`                             | 64/64, 11 файлов                                                               |
| `npm run build`                                 | Пройдено; прежнее предупреждение о JS chunk >500 kB                            |
| Windows полный Playwright, 2 workers            | 177/177                                                                        |
| Windows strict baseline после currency fallback | 77/77, без перезаписи                                                          |
| Linux полный Playwright, изолированный Docker   | 177/177; затем 77/77 strict baseline без перезаписи                            |
| UI-kit responsive harness, Windows и Linux      | 320/640/768/1024/1280/1440/1920/2560 px, без page overflow; обычные поля 36 px |
| Repository hygiene                              | Пройдено                                                                       |
| `git diff --check`                              | Пройдено                                                                       |
| UI Design Guard                                 | Принято, blocking findings нет; Windows и Linux визуальные изменения одобрены  |

Полный E2E охватывает controls, permissions, клавиатуру, axe, длинные строки,
контент/dirty guards, импорт/экспорт и существующие маршруты. Первоначальный
Windows-прогон имел ожидаемые визуальные различия/отсутствующие platform
baseline и один appearance timeout при 4 workers. Повторный полный прогон
при 2 workers прошёл без изменения теста и повышения timeout.

Windows/Linux snapshots обновляются только после просмотра и независимого
одобрения намеренных изменений. Исторические macOS snapshots не обновляются
на другой OS: macOS runner в этой среде отсутствует, их актуализация требует
отдельного запуска на macOS. Admin CI и локальный Windows проверяются реально.

Общая команда `npm run format:check` выявила существующие CRLF/LF расхождения,
временный `.tmp/playwright-transform-cache` и форматирование двух неизменённых
файлов — `features/sliders/components/SlidersWorkspace.vue` и
`tests/uiKitShowcase.test.ts`. Это не результаты данного редизайна.
При проверке с `--end-of-line auto` остаются кэш и эти два файла;
проверка всех изменённых source/documentation файлов проходит. Массовое
переформатирование несвязанных features не выполнялось.

## Изменённые файлы

Пути ниже относятся к `frontend/admin/`, если не указан другой корень:

- `src/styles/tokens.css`, `typography.css`, `index.css`, `reset.css`,
  `utilities.css` — палитра, типографика, тени, размеры, focus и цвет логотипа.
- `src/components/ui/UiButton.vue`, `UiInput.vue`, `UiSelect.vue`,
  `UiDatePicker.vue`, `UiCheckbox.vue`, `UiRadio.vue`, `UiBadge.vue`, `UiCard.vue`
  — общие controls и поверхности.
- `src/components/shared/UiKitShowcase.vue`, `UI_KIT.md` — витрина и контракт.
- `tests/uiDisabled.test.ts` — проверка disabled-состояния больше не зависит
  от удалённого CSS-класса фиксированной высоты `h-11`.
- `e2e/adminBaseline.spec.ts-snapshots/*-chromium-win32.png` и
  `*-chromium-linux.png` — просмотренные визуальные эталоны.
- Корневой `AGENTS.md`, `frontend/admin/AGENTS.md` — Seller-визуальное направление.
- `docs/ADMIN_SELLER_REDESIGN_PLAN.md`, этот отчёт и task ledger — статус и evidence.

Номера нового плана исправлены на `TASK-A057`–`TASK-A062`, поскольку `TASK-A056`
уже принадлежит завершённому Client dependency audit. Commit и push не создавались.

## Последующая доработка 2026-10-02

Уточнение владельца: единый контейнер меню и нетабличных блоков всех страниц,
широкие рабочие таблицы. Итог и актуальные проверки описаны в
[отчёте доработки](ADMIN_CONTAINER_REFINEMENT_REPORT.md).
Результаты выше относятся к первоначальной приёмке и сохранены как история.
