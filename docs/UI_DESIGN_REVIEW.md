# UI Design Review

Этот документ — обязательный регламент для задач, которые меняют интерфейс в `frontend/admin/` или `frontend/client/`.

## Порядок работы

Перед реализацией прочитать этот документ и изучить затрагиваемые существующие компоненты. До сдачи задачи необходимо передать изменённые UI-элементы независимому UI Design Guard для ревью. Ревьюер не меняет код без отдельного поручения: он фиксирует несоответствия, которые исполнитель устраняет до завершения задачи.

При изменении только backend ревью не требуется. Если задача добавляет или меняет HTML/CSS/Vue/Nuxt-компоненты, layout, страницу, модальное окно, таблицу, форму либо интерактивный элемент — ревью обязательно.

## Admin: архитектура и источник UI-правил

Для любых изменений интерфейса в `frontend/admin/` перед началом работы обязательно прочитать:

- [`frontend/admin/AGENTS.md`](../frontend/admin/AGENTS.md)

Файл `frontend/admin/AGENTS.md` является главным источником архитектурных правил Admin-приложения и определяет:

- структуру `pages`, `features`, `components/ui`, `components/shared`, `layouts`, `composables`, `services`, `types` и `validation`;
- правила разделения page-level, feature-level и shared UI компонентов;
- правила использования и создания `Ui*` компонентов;
- правила работы с design tokens;
- правила размещения API-логики, composables, Pinia stores, типов и валидации;
- dependency direction между слоями;
- требования к переиспользованию компонентов;
- правила рефакторинга перегруженных Vue-компонентов;
- требования SOLID и clean code для Admin frontend.

`UI_DESIGN_REVIEW.md` не дублирует архитектурные правила из
[`frontend/admin/AGENTS.md`](../frontend/admin/AGENTS.md).

Если между этим документом и `frontend/admin/AGENTS.md` возникает расхождение в вопросах архитектуры, структуры компонентов, UI-kit или design tokens, для кода внутри `frontend/admin/` приоритет имеет `frontend/admin/AGENTS.md`.

### Порядок работы с Admin UI

Перед реализацией изменения в `frontend/admin/` исполнитель обязан:

1. Прочитать `frontend/admin/AGENTS.md`.
2. Изучить существующие компоненты и архитектуру затрагиваемого feature.
3. Проверить наличие существующего подходящего `Ui*` или shared-компонента.
4. Не создавать новый raw UI-паттерн, если подходящий компонент уже существует.
5. При глобальном визуальном изменении сначала определить, должно ли оно быть реализовано через общий UI-компонент или design token.
6. Не дублировать стили и UI-паттерны внутри страниц и feature-компонентов.
7. После реализации выполнить проверки, описанные в разделе `Обязательная проверка` этого документа.

### UI Design Review

Этот документ отвечает за качество и визуальную проверку результата, а не за описание структуры Admin-приложения.

UI Design Guard должен проверять:

- соответствие реализации правилам `frontend/admin/AGENTS.md`;
- переиспользование существующего UI-kit;
- визуальную консистентность;
- responsive-поведение;
- состояния компонентов;
- accessibility;
- отсутствие необоснованного дублирования UI и стилей.

При обнаружении архитектурного нарушения UI Design Guard должен ссылаться на соответствующее правило из `frontend/admin/AGENTS.md`.

Не дублировать перечисленные паттерны в представлениях. Если появляется новый повторно используемый
паттерн, сначала создать обобщённый компонент, выдержанный в существующих токенах. Не добавлять
произвольные цвета, шрифты, тени, радиусы и размеры в обход
[`frontend/admin/src/styles/tokens.css`](../frontend/admin/src/styles/tokens.css) и правил
[`UI_KIT.md`](../frontend/admin/src/components/shared/UI_KIT.md) без отдельного обоснованного изменения
дизайн-системы.

## Client: утверждённый UI-kit и архитектура

Для любых изменений интерфейса `frontend/client/` перед началом работы читать
[`frontend/client/AGENTS.md`](../frontend/client/AGENTS.md) и
[`CLIENT_UI_KIT.md`](CLIENT_UI_KIT.md). Визуальный референс — предоставленный
пользователем [`exampleSite.html`](../exampleSite.html). `CLIENT_UI_KIT.md`
фиксирует визуальные правила; `frontend/client/AGENTS.md` — структуру Nuxt,
зависимости, переиспользование компонентов, SSR, SEO и работу с API. При
расхождении с общими примерами этого документа для Client-кода действуют
более конкретные клиентские правила.

Перед реализацией проверить существующие tokens, `app/components/ui/`,
`app/components/shared/` и затрагиваемый feature. Новый повторяемый паттерн
оформить как общий компонент и записать в UI-kit; не копировать markup/CSS
между страницами. UI Design Guard проверяет соответствие референсу и
клиентским правилам, а также реальные состояния, SSR/hydration, адаптивность,
клавиатурное управление и доступность. Demo-данные PIETRA и фиктивные действия
не служат критерием визуальной приёмки AgatCeramic.

Текущие точки входа: `frontend/client/app/app.vue`,
`frontend/client/app/layouts/default.vue`, `frontend/client/app/pages/index.vue`.

## Обязательная проверка

1. Переиспользование: используются существующие shared-компоненты или создан новый обобщённый компонент для нового паттерна.
2. Адаптивность: проверить ширины 320px, 640px, 768px, 1024px и 1280px; нет горизонтального overflow, текст не обрезается, таблицы и sidebar остаются usable.
3. Состояния: default, hover, focus-visible, disabled, loading, error, success, empty; для modal/dropdown — open/close, Escape и клик вне компонента.
4. Доступность: label либо `aria-label`, видимый focus, корректные `role=alert`/`role=status`, управление с клавиатуры.
5. Медиа: корректные preview/crop, содержательный `alt`, fallback и empty state.
6. Контент: проверить длинные русские строки и реальные данные.
7. Проверка: выполнить релевантную сборку и визуально открыть затронутые маршруты. При изменении токенов, layout или shared-компонентов перепроверить все маршруты из следующего списка.

Для Client дополнительно проверять, что индексируемое содержимое видно в SSR
HTML, первый рендер не даёт hydration mismatch, изображения не вызывают
заметный CLS, а motion учитывает `prefers-reduced-motion`. Главная страница,
каталог и checkout проверяются с реальными данными либо честными
loading/empty/error состояниями, без demo-контента референса.

Полный воспроизводимый Admin-прогон выполняется из корня репозитория:

```sh
cd frontend/admin
npm run build
npm run test:unit
npm run test:e2e
```

Linux Compose-проверка выполняется из корня репозитория:

```sh
docker compose --profile test run --rm admin-e2e
```

Для Client из `frontend/client/` после установки зависимостей (`npm ci`)
выполнять `npx nuxt typecheck` и `npm run build`, а также релевантные тесты и
lint/format checks, когда они добавлены в `package.json`. Проверки, для
которых пока нет скрипта, не отмечать как успешно пройденные.

## Маршруты для визуальной QA

Admin: `/login`, `/forgot-password`, `/reset-password`, `/`, `/profile`, `/products`, `/categories`, `/brands`, `/attribute-groups`, `/attributes`, `/employees`, `/roles`, `/permissions`, `/audit-log`, `/orders`, `/contacts`, `/content`, `/settings`.
UI-kit: `/ui-kit` — проверять при изменении `components/ui`, `components/shared` или design tokens.

Client: `/` и все изменённые или новые маршруты.

Для ручной проверки запустить `npm run dev -- --host 127.0.0.1 --port 5173` из
`frontend/admin/` либо `npm run dev -- --host 127.0.0.1 --port 3000` из `frontend/client/`.
Локальные адреса: Admin — `http://localhost:5173`, Client — `http://localhost:3000`.
