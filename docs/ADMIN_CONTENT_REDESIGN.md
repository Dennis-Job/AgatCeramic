# TASK-C006 — «Контент» и «Общее оформление»: вариант «Инспектор»

2026-10-17. Реализован выбранный владельцем третий вариант.

## Результат

Рабочая область занимает доступную ширину. На широком экране слева список страниц/разделов
240 px, посередине инспектор, справа настоящий Nuxt-preview около 50% ширины.
В страницах вкладки «Блоки», «Настройки», «SEO» сохраняют смонтированные формы и файлы.
Название, slug, обычный текст и блоки сохраняются одним черновиком. SEO главной перенесено
во вкладку; остальные страницы честно показывают отсутствие этих параметров.

Сохранение и публикация вынесены в общую строку действий. Снятие с публикации/удаление
находятся в настройках. В общем оформлении выбор шапки, подвала и фильтров слева,
поля посередине. Черновики разделов, permissions, upload/unsaved guards сохранены.
Предпросмотр масштабирует viewport 1280/375 px по ширине панели, телефон центрируется.
Верхняя глобальная шапка, утверждённые шрифты/цвета и клиентский сайт сохранены.
API, миграции, backend, Nuxt-код, зависимости не менялись.

## Изменённые файлы

| Область                 | Файлы                                                                                                                        |
| ----------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| Shared layout и вкладки | `AdminWorkspace.vue`, `AdminEditorLayout.vue`, новый `components/ui/UiTabs.vue`                                              |
| Страницы                | `features/pages/components/PagesWorkspace.vue`, `PageBlocksEditor.vue`, `features/homepage/components/HomePageWorkspace.vue` |
| Оформление/preview      | `features/appearance/components/AppearanceWorkspace.vue`, `features/content-preview/components/DraftPreview.vue`             |
| UI-kit                  | `components/shared/UiKitShowcase.vue`, `UI_KIT.md`, `tests/uiKitShowcase.test.ts`                                            |
| E2E                     | `content-blocks`, `contentWorkspaces`, `pages`, `homepage`, `containerLayout`, `adminBaseline`, `e2e-preview/contentPreview` |
| Baseline                | четыре macOS screenshot `/content`: default/loading/empty/error                                                              |
| Документация            | `CONTENT_WORKSPACE_UX.md`, `ADMIN_SELLER_REDESIGN_PLAN.md`, `design-qa.md`, `tasks/DONE.md`                                  |

Пути компонентов и тестов в таблице относительно `frontend/admin/src/` и `frontend/admin/`.

## Проверки и ограничения

- `npm run build`, lint, format:check — PASS; build включает TypeScript.
- Unit — 158/158 PASS.
- Финальный целевой прогон Content/Appearance/Pages/Homepage и container layout — 49/49 PASS.
- Настоящий Nuxt iframe — 24/24 PASS, дополнительно три captures 1920×1080.
- Независимый UI Design Guard — PASS, блокирующих P1/P2 нет; четыре macOS baseline утверждены.
- Полный `npm run test:e2e -- --workers=4`: 546 PASS, 67 FAIL из 613.
  Из них 61 несовпадение старых visual baseline других страниц, четыре contrast-сценария
  каталога/команды, один tooltip-сценарий и один старый селектор SEO главной.
  Селектор главной обновлён под вкладку SEO, отдельный повторный сценарий — PASS.
  Пять grouped-characteristic сценариев каталога при отдельном последовательном повторе — PASS.
  Все функциональные сценарии изменённых редакторов прошли.

Для расследования использована чистая копия `git archive HEAD` с теми же зависимостями,
отдельной сборкой и портом 4177. На коде **до редизайна** воспроизведены visual failures
`/brands`, `/products loading`, UI-kit disabled/date picker (320 px), а также tooltip товаров и contrast-сценарий диалогов команды.
Actual screenshots брендов и открытого date picker побайтно совпали с текущими actual.
Несвязанные baseline не обновлялись, доступность не ослаблялась ради зелёного прогона.

В параллельном каталожном сценарии audit сообщает 4.48:1 на `gray-500` и светло-синем фоне
(ожидает 4.5:1); это не изменение палитры в TASK-C006. Цвета владельца сохранены.
Работа с этими старыми visual/contrast/tooltip проверками относится к отдельной задаче.
Linux/Windows screenshot-baseline не обновлялись и требуют отдельной проверки этих платформ.

Скриншоты актуального Nuxt-preview: `frontend/admin/.tmp/content-redesign/`.
В них API/сессия тестовые, сам рендер — настоящий Nuxt.

## Уточнение шапки по Browser annotations

Переключатель разделов и toolbar объединены в один desktop-ряд в общем контейнере
1280 px; нижняя рабочая область остаётся full-width. `ContentView.vue` передаёт nav
через `toolbar-navigation` slot страницам/оформлению, магазины сохраняют прежнее
расположение nav. Фокус клавиатуры восстанавливается после успешного переключения;
loading/error оформления сохраняют тот же header host. Изменены ContentView,
AdminEditorLayout, PagesWorkspace, AppearanceWorkspace, StoresWorkspace и проверки
contentWorkspaces/containerLayout. Обновлены только четыре macOS content baseline.
Live capture: `frontend/admin/.tmp/content-redesign/toolbar-container-live.png`.

Проверка уточнения: build/typecheck, lint/format и 158 unit — PASS;
46 E2E contentWorkspaces/appearance/navigation, включая focus regression — PASS;
12 content baseline/responsive — PASS. Независимый UI Design Guard повторно
утвердил live capture и четыре macOS baseline, открытых P1/P2 нет.

По следующей Browser annotation добавлен desktop-разделитель между переключателем
разделов и заголовком, как во втором референсе. Правило локализовано в
`AdminEditorLayout.vue`, использует `gray-200`/spacing/control tokens и выключено
на узкой компоновке. Live capture: `.tmp/content-redesign/toolbar-divider-live.png`.
Проверка разделителя: build/lint/format и 20 workspace/baseline E2E — PASS;
UI Design Guard утвердил live capture и четыре обновлённых baseline.

## Окно добавления страницы

По уточнению владельца `PageFormDialog.vue` расширен с 672 до 768 px. После
повторного уточнения высота подстраивается под содержимое, ограничена 90dvh
и не растягивается на весь экран. На viewport 1551×1139 окно имеет высоту около
692 px; поле текста сохраняет 12 строк и возможность изменения размера.
Содержимое формы имеет отдельную вертикальную прокрутку; заголовок и footer
остаются доступными. «Отмена» и «Сохранить» объединены в неразрывный ряд, в том
числе на 320 px. Сохранение по-прежнему меняет черновик; пояснение и доступное
название кнопки сохранены. API, публикация и общие UI-примитивы не изменялись.

Проверки: build/typecheck, lint/format — PASS; unit — 158/158 PASS;
`e2e/pages.spec.ts` — 3/3 PASS, включая создание/публикацию/удаление и отдельную
проверку доступности действий при прокрутке на высоте 400 px, ширины
320/640/768/1024/1280 px и возврат фокуса после закрытия. UI Design Guard
просмотрел live capture и все responsive captures: открытых P1/P2 нет.
Live capture: `frontend/admin/.tmp/content-redesign/page-dialog-compact-live.png`.
