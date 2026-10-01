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
В TASK-A058 глобальная навигация перенесена в две строки верхней шапки.
Единая конфигурация `layouts/navigation.ts` фильтрует ссылки по permissions;
выпадающие группы и компактное меню используют текст без иконок.
Компактный вариант переиспользует `UiDialog` и общую разметку ссылок;
размеры шапки и её максимум задаются `--admin-shell-*` tokens.
`AdminWorkspace` выбирает `mode=overview/list/editor/form`: обзор ограничен
1280 px, простая форма — 960 px, список и редактор занимают доступную ширину.
Опциональный slot `intro` ограничивает вводную часть 1280 px независимо от
ширины данных. Общие gutters — 16 px до 640 px, затем 24 px; размеры задаются
`--admin-workspace-*` tokens. Товары, справочники каталога, сотрудники, роли, права, аудит и медиатека
используют `list`; заказы, обращения, страницы и общее оформление — `editor`.
Dashboard и магазины используют `overview`, профиль и настройки — `form`. Layout-компоненты не входят в инвентарь primitives.

`UiTable` по умолчанию сохраняет прежнюю локальную горизонтальную прокрутку.
Опциональные `stickyHeader`/`stickyEdges` задают ограниченный по высоте scroll
region с закреплёнными заголовками и крайними колонками от 1280 px. На узких
экранах колонки не закрепляются. Первый и последний столбцы должны служить
идентификации строки и действиям; feature задаёт ширины колонок.
Общий `tableClass="seller-table"` задаёт фиксированную раскладку, перенос длинных
значений, отступы 16 px через tokens, разделители и hover. Feature задаёт
колонки, ширины, данные и действия. В списках нет внешней `UiCard`; для узких
экранов используется именованный локальный scroll, у сотрудников и аудита —
карточки до 1280 px, у заказов и обращений — до 768 px. Числа выравниваются
вправо, даты используют `time`/`datetime`.
`AdminListDetail` композирует slots `list`/`detail`: до 1280 px зоны стоят друг
под другом, затем список использует свободную ширину, а детали —
`clamp(380px, 35vw, 720px)`. Это недоменная композиция, состояние принадлежит
feature composable. В медиатеке только форма загрузки/изменения ограничена
общим `--admin-workspace-form-max-width`, список занимает всю ширину.

`AdminEditorLayout` — недоменная композиция slots `navigation` (необязательный),
`editor` и `preview`. Container queries учитывают реальную ширину родителя:
до 960 px зоны стоят последовательно; от 960 px список страниц шириной 240 px
стоит рядом с редактором, preview занимает следующую строку; от 1360 px все три
зоны стоят рядом. Без списка страниц две зоны стоят рядом от 1120 px.
Размеры списка и минимальная ширина preview 420 px заданы `--admin-editor-*`
tokens. При gutters 24 px это соответствует viewport 1024/1440 px для страниц
и 1280 px для общего оформления. Feature владеет dirty guards и публикацией.
`DraftPreview` сохраняет настоящий Nuxt iframe 1280/375 px с локальным scroll;
режим телефона центрируется внутри доступной области.

`UiImagePreview compact` показывает квадратное фото 48 px с `object-fit: contain`
и компактным fallback для отсутствующего/повреждённого изображения.

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

Живая витрина доступна авторизованному пользователю по `/ui-kit` и через верхнее меню →
«Управление» → «Служебное» → «UI-kit». Она собрана в `components/shared/UiKitShowcase.vue` и показывает все
17 `Ui*` primitives, `AuthCard`, `ConfirmDialog`, `PageHeader`, их значимые варианты и состояния,
а также все CSS custom properties из `styles/tokens.css`. Layout- и feature-компоненты не являются
частью UI-kit и на витрину не выносятся.

Полнота инвентаря и tokens закреплена `tests/uiKitShowcase.test.ts`. E2E проверяет search/no-results,
teleport menu, confirm busy/error, disabled-контракт, keyboard/focus, axe и responsive widths
320/640/768/1024/1280 px.
