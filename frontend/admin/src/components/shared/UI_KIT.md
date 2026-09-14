# UI-kit Admin

`components/ui` содержит примитивы без знания доменов. `components/shared` —
повторно используемые композиции, не привязанные к feature. После финальной
приёмки TASK-A030 все переходные `Base*` и shared-state adapters удалены:
код импортирует `Ui*` и shared-компоненты напрямую.

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
16 `Ui*` primitives, `AuthCard`, `ConfirmDialog`, `PageHeader`, их значимые варианты и состояния,
а также все CSS custom properties из `styles/tokens.css`. Layout- и feature-компоненты не являются
частью UI-kit и на витрину не выносятся.

Полнота инвентаря и tokens закреплена `tests/uiKitShowcase.test.ts`. E2E проверяет search/no-results,
teleport menu, confirm busy/error, disabled-контракт, keyboard/focus, axe и responsive widths
320/640/768/1024/1280 px.
