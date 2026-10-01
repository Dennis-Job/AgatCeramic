# UI-kit Admin

`components/ui` содержит примитивы без знания доменов. `components/shared` —
повторно используемые композиции, не привязанные к feature. После финальной
приёмки TASK-A030 все переходные `Base*` и shared-state adapters удалены:
код импортирует `Ui*` и shared-компоненты напрямую.

С 2026-10-01 визуальная основа — Seller UI-kit по предоставленным владельцем
референсам Ozon Seller и [`плану редизайна`](../../../../../docs/ADMIN_SELLER_REDESIGN_PLAN.md).
Бренд AgatCeramic сохраняется. TailAdmin — исторический контекст.

Белый фон, primary `#005BFF` и серо-голубые подложки задаются tokens.
По уточнению владельца от 2026-10-01 прежняя типографика сохраняется:
Nunito для основного текста, Poppins для display, существующие размеры и
межстрочные интервалы. Controls имеют минимальную высоту 32/36/40 px (`sm/md/lg`); длинный
текст может увеличивать высоту. Кнопки и поля имеют радиус 8 px; dropdown —
16 px, карточки — 20 px. У полей и карточек нет тени; меню и диалоги используют
мягкую тень. Вторичные кнопки — светлые, статусы — компактные прямоугольные badges.

`UiCard` поддерживает `surface=white` (по умолчанию: белая поверхность с границей)
и `surface=muted` (светлая подложка без видимой рамки) независимо от `padded`.
Примеры обоих вариантов и актуальная типографика представлены в витрине.
Навигация и режимы ширины будут перенесены отдельными этапами плана.

Прозрачные shadow tokens у карточек/полей используют валидную zero-shadow запись вместо
`none`, чтобы Tailwind корректно совмещал её с focus ring.

| Компонент                                        | Варианты / состояния                                                                                                               |
| ------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------- |
| `UiButton`                                       | `primary`, `secondary`, `danger`, `ghost`, `danger-ghost`; `sm/md/lg`, disabled, loading                                           |
| `UiInput`                                        | default, populated/clear, search, password, focus, error через `UiField`, native disabled; disabled блокирует input и clear-action |
| `UiSelect`                                       | placeholder, search/no-results, clear, keyboard, teleport menu; disabled блокирует trigger/clear и закрывает открытое menu         |
| `UiTextarea`                                     | default, focus, native disabled, error через `UiField`                                                                             |
| `UiDatePicker`                                   | input/calendar, Escape/focus return, responsive popup; disabled блокирует input/clear и закрывает открытый calendar                |
| `UiCheckbox`, `UiRadio`                          | boolean/array mode, selected/unselected, keyboard focus, disabled через native control                                             |
| `UiDialog`, `ConfirmDialog`                      | open/close, Escape, backdrop, focus trap/return, busy, error                                                                       |
| `UiAlert`, `UiBadge`, `UiCard`, `UiTable`        | semantic tone / padded-unpadded surface / именованный responsive scroll-region с containment и inset focus ring                    |
| `UiField`                                        | label, required, help, error (`role=alert`)                                                                                        |
| `UiLoadingState`, `UiEmptyState`, `UiPagination` | status announcement, optional empty-state action; first/middle/last/zero/loading pagination                                        |
| `UiImagePreview`                                 | изображение, пустое состояние и сообщение об ошибке загрузки                                                                       |
| `AuthCard`                                       | общий guest-auth form shell: branding, title, description и card surface; `headingTag=h2` только для embedded preview              |
| `PageHeader`                                     | eyebrow, title, description, actions slot                                                                                          |

Все визуальные константы берутся из `src/styles/tokens.css`; Tailwind theme в
`styles/index.css` отображает эти значения для общих utility classes. Семантические
цвета текста и контролов подобраны с контрастом не ниже WCAG AA на закреплённых
фоновых тонах; disabled-состояния используют отдельные токены цвета без снижения
opacity. Общий focus outline также задаётся токенами и не должен отключаться без
равноценной контрастной замены.

Живая витрина доступна авторизованному пользователю по `/ui-kit` и через sidebar →
«Разработка» → «UI-kit». Она собрана в `components/shared/UiKitShowcase.vue` и показывает все
17 `Ui*` primitives, `AuthCard`, `ConfirmDialog`, `PageHeader`, их значимые варианты и состояния,
а также все CSS custom properties из `styles/tokens.css`. Layout- и feature-компоненты не являются
частью UI-kit и на витрину не выносятся.

Полнота инвентаря и tokens закреплена `tests/uiKitShowcase.test.ts`. E2E проверяет search/no-results,
teleport menu, confirm busy/error, disabled-контракт, keyboard/focus, axe и responsive widths
320/640/768/1024/1280 px.
