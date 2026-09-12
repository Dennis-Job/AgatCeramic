# UI-kit Admin

`components/ui` содержит примитивы без знания доменов. `components/shared` —
повторно используемые композиции, не привязанные к feature. После финальной
приёмки TASK-A030 все переходные `Base*` и shared-state adapters удалены:
код импортирует `Ui*` и shared-компоненты напрямую.

| Компонент | Варианты / состояния |
| --- | --- |
| `UiButton` | `primary`, `secondary`, `danger`, `ghost`, `danger-ghost`; `sm/md/lg`, disabled, loading |
| `UiInput`, `UiSelect`, `UiTextarea`, `UiDatePicker` | default, focus, disabled, error через `UiField`; select: search, clear, keyboard; date: input, calendar, Escape/focus return |
| `UiCheckbox`, `UiRadio` | selected, keyboard focus, disabled через native control |
| `UiDialog`, `ConfirmDialog` | open/close, Escape, backdrop, focus trap/return, busy |
| `UiAlert`, `UiBadge`, `UiCard`, `UiTable` | semantic tone / surface / responsive table shell |
| `UiField` | label, required, help, error (`role=alert`) |
| `UiLoadingState`, `UiEmptyState`, `UiPagination` | status announcement; pagination disabled/loading |

Все визуальные константы берутся из `src/styles/tokens.css`; Tailwind theme в
`styles/index.css` отображает эти значения для общих utility classes.

Временная живая витрина доступна авторизованному пользователю по `/ui-kit` и через
sidebar → «Разработка» → «UI-kit · временно». Она собрана в
`components/shared/UiKitShowcase.vue`; при удалении витрины нужно удалить также
маршрут и sidebar-пункт.
