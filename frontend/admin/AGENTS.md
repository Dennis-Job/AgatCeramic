# AgatCeramic Admin Architecture

Этот файл действует для всего Vue Admin-приложения в `frontend/admin/` и дополняет корневой
`AGENTS.md`. При расхождении правил для Admin-кода приоритет имеют более конкретные инструкции
из этого файла.

## Цель и стек

Admin — Vue 3 SPA на TypeScript, Vite, Vue Router, Pinia и Tailwind CSS. Архитектура должна
оставаться простой для сопровождения, редизайна и расширения. Соблюдать SOLID, DRY, KISS,
разделение ответственности и тестируемость без необоснованных enterprise-абстракций.

Текущий UI-kit и design tokens проекта являются визуальным source of truth. TailAdmin можно
использовать только как исторический контекст.

## Направление зависимостей

Предпочтительные цепочки:

```text
View/Page -> Feature -> Shared/UI -> Design tokens
Feature component -> Composable -> Feature service -> Backend API
```

- Shared/UI-компоненты не зависят от доменных features.
- Feature может использовать shared composables, utilities, stores и соседний feature только
  через его публично понятный service/type boundary.
- Endpoint URL и raw HTTP принадлежат service-слою. Vue-компоненты не используют `fetch` или
  HTTP-клиент напрямую.

## Route-level views

Файлы в `src/views/` — тонкие route-level композиции. Они могут читать параметры маршрута,
выбирать layout, подключать feature workspace и показывать page-level состояние.

В `views` не размещать:

- доменные DTO и типы;
- endpoint URL или raw HTTP;
- крупные формы и бизнес-логику;
- локальные копии кнопок, полей, select, checkbox, dialog, table, badges, feedback states и
  других существующих UI primitives.

Если route-level экран содержит самостоятельный бизнес-сценарий, его UI и состояние должны
находиться в `src/features/<feature>/`.

## Features

Доменные модули располагаются в `src/features/` и по необходимости содержат:

```text
features/<feature>/
├── components/
├── composables/
├── services/
├── types/
└── validation/
```

- Компоненты отвечают за представление и пользовательские события.
- Composables владеют локальным состоянием, загрузкой, submit-flow и координацией сценария.
- Services инкапсулируют API endpoints и transport mapping.
- Types/DTO не объявляются внутри Vue-файлов, кроме небольших component-only props.
- Validation и нормализация отделяются от visual implementation.
- Большая форма композируется из осмысленных секций; не делить код только ради line count и не
  создавать одноразовые wrapper-компоненты.

## UI-kit и shared components

UI primitives находятся в `src/components/ui/`, сложные недоменные паттерны — в
`src/components/shared/`.

Перед добавлением разметки обязательно проверить существующие `Ui*` и shared-компоненты.
Использовать или расширять их, если паттерн общий. Доменные правила не переносить в UI-kit.

Переходные `Base*`, layout, state, pagination и service re-export adapters удалены в TASK-A030.
Не восстанавливать legacy path: импортировать source-of-truth компонент или feature service
напрямую.

Общий visual change реализуется в UI primitive или design token, а не копируется по страницам.
Native control допустим внутри реализации соответствующего primitive либо для специального
браузерного элемента (`input[type=file]`, `progress`), если общий компонент не оправдан.

## Layouts, состояние и Pinia

- Application shell находится в `src/layouts/` и `src/layouts/components/`; страницы не
  дублируют header/sidebar.
- Pinia используется только для действительно глобального состояния, например текущей сессии и
  permissions.
- Локальное состояние формы или workspace принадлежит feature composable.
- Роли и permissions не хардкодить во frontend; backend остаётся авторитетным источником доступа.

## Стили и design tokens

- Design tokens и глобальные правила находятся в `src/styles/`.
- Не добавлять произвольные повторяющиеся цвета, размеры, радиусы, тени и transitions в feature,
  если значение должно быть глобальным token.
- Component-specific стили могут оставаться рядом с компонентом.
- Сохранять TailAdmin-derived визуальный язык текущего UI-kit.

## Accessibility и responsive

Каждое UI-изменение проверять по `../../docs/UI_DESIGN_REVIEW.md`.

Минимальный контракт:

- видимый `focus-visible` и управление с клавиатуры;
- label или `aria-label` для controls;
- `role=alert` для ошибок и `role=status` для loading/success/empty;
- dialog focus trap, Escape, безопасный backdrop close и возврат фокуса;
- usable layout без page-level horizontal overflow на 320, 640, 768, 1024 и 1280 px;
- корректные disabled/loading/error/success/empty states и длинный русский контент.

Табличный horizontal scroll допустим только как явная локализованная стратегия внутри table shell.
Для узких list/detail workspace предпочтительны breakpoint-specific cards или stack layout.

## Порядок изменения Admin UI

1. Изучить существующий feature, `src/components/ui/`, shared-компоненты и design tokens.
2. Сохранить route, permissions, API-contract и видимые состояния.
3. Разместить новую ответственность в правильном слое и не менять несвязанные модули.
4. Выполнить `npm run build`, `npm run test:unit` и релевантные production E2E/axe проверки.
5. При изменении shared UI, layout или tokens выполнить полный `npm run test:e2e` и visual
   baseline.
6. До сдачи передать UI-изменение независимому UI Design Guard согласно
   `../../docs/UI_DESIGN_REVIEW.md` и устранить blocking findings.

Visual snapshot обновлять только после просмотра и подтверждения намеренного изменения.

## Запрещённые shortcut-решения

- raw HTTP, endpoint URL или SQL-подобная data access логика в Vue-компонентах;
- domain DTO и validation внутри route view;
- новые локальные копии существующих UI primitives;
- shared-компонент, зависящий от products, categories, orders, users или другого feature;
- giant Pinia store для локальной формы;
- скрытие ошибок permissions или попытка заменить backend authorization видимостью controls;
- изменение API-contract без синхронного обновления документации и contract tests.
