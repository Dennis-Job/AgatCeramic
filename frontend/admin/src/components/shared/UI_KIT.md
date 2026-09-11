# UI-kit Admin

`components/ui` содержит примитивы без знания доменов. `components/shared` —
повторно используемые композиции, не привязанные к feature. В этой миграции
`Base*` остаются совместимыми публичными входами для действующих экранов;
новый код должен импортировать `Ui*` и shared-компоненты.

| Компонент | Варианты / состояния |
| --- | --- |
| `UiButton` | `primary`, `secondary`, `danger`, `ghost`; `sm/md/lg`, disabled, loading |
| `UiInput`, `UiSelect`, `UiTextarea` | default, focus, disabled, error через `UiField`; select: search, clear, keyboard |
| `UiCheckbox`, `UiRadio` | selected, keyboard focus, disabled через native control |
| `UiDialog`, `ConfirmDialog` | open/close, Escape, backdrop, focus trap/return, busy |
| `UiAlert`, `UiBadge`, `UiCard`, `UiTable` | semantic tone / surface / responsive table shell |
| `UiField` | label, required, help, error (`role=alert`) |
| `UiLoadingState`, `UiEmptyState`, `UiPagination` | status announcement; pagination disabled/loading |

Все визуальные константы берутся из `src/styles/tokens.css`; Tailwind theme в
`styles/index.css` отображает эти значения для legacy utility classes.
