# TASK-C003 — редактор блоков и медиа внутри страниц

Завершено 2026-09-30. Evidence конкретного запуска; обязательные CI-команды остаются в [CI.md](CI.md).

## Реализовано

- Типизированные блоки всех разрешённых API типов: добавление, порядок, включение,
  удаление с подтверждением, локальные настройки. Текстовый блок можно повторять;
  остальные типы доступны по одному, предел — 40. Произвольного HTML-редактора нет.
- Редактор встроен в выбранную страницу `/content`. Главная использует тот же
  редактор через «Блоки и порядок». Страница/блок и режим главной восстанавливаются
  по URL. Прежние входы сохранены для следующих этапов C004/C005.
- Выбор и загрузка изображений на месте с `media.manage`, alt, preview/fallback,
  пустым состоянием, loading, ошибкой и повтором. `content.manage` позволяет увидеть
  выбранный ресурс через существующее разрешённое чтение без controls медиатеки.
  Каталожные формы сохраняют прежний доступ к выбору существующих файлов.
- В слайдерном блоке создаются/редактируются баннеры и слайдеры; существующие ресурсы
  переиспользуются. Новый баннер включается в состав после отдельного сохранения
  слайдера. Пользователь видит предупреждение о немедленном изменении опубликованного
  общего ресурса во всех местах использования.
- Сохранение блоков меняет только черновик; публикация требует отдельного действия.
  Несохранённые правки блокируют публикацию и перезагрузку редактора. Переключение
  блоков сохраняет локальные значения; уход требует подтверждения, активная загрузка
  блокирует уход. Ошибка сохранения сохраняет форму для повтора.
- Стабильные локальные ключи строк материалов сохраняют выбранный upload при правке
  кода/перестановке. Вложенное подтверждение приостанавливает нижний dialog; Escape,
  Tab и возврат фокуса проверены. Переход между прежним и новым редакторами главной
  обновляет данные, исключая устаревший кеш.

## Проверки

- Admin unit: 61 тест в 10 файлах прошёл (включая 4 новых теста page draft workflow
  и 7 контекстного слайдера).
- Admin production E2E: 59 сценариев прошли — новые блоки и контекстные ресурсы,
  существующие страницы/главная, медиатека, баннеры, слайдеры и каталог.
- Новый UI: проверки 320/640/768/1024/1280 px без horizontal overflow, axe —
  0 violations; на обычной странице также проверено 2560 px. Новые сценарии проверяют
  отказ от ухода, pending/in-flight upload, retry, сохранение значений при перестановке,
  видимость controls без `media.manage`, URL главной и свежесть данных прежнего редактора.

- Backend: профильные PageBlockContent, HomePageManagement, PageManagement,
  BannerManagement, SliderManagement — 20 тестов / 216 assertions; MediaLibrary —
  3 теста / 39 assertions. SQLite in-memory внутри существующего backend-контейнера.
- Admin production build и ESLint прошли. Глобальный `format:check` обнаруживает
  существующие CRLF-различия в несвязанных файлах; массовая нормализация не выполнялась.
  Все изменённые и новые Admin-файлы проверяются Prettier отдельно.
- Независимый UI Design Guard проверил исходники и снимки блоков/слайдеров на
  320/640/768/1024/1280 px, а также блоки главной; блокирующих замечаний нет.
  Проверка SSR/hydration не применяется: Client-код не изменён.
- Backend/schema/OpenAPI не изменены; миграции не требуются. Контрактные проверки
  выполнялись существующими тестами; рабочая PostgreSQL-база не сбрасывалась.
- Repository hygiene и `git diff --check` прошли; generated/test artifacts остаются
  игнорируемыми. Повторный UI Design Guard подтвердил последние изменения прав медиа
  и исправленный тест сохранения текста без блокирующих замечаний.

На Windows использован native config loader:

```sh
npm run lint
npm run build -- --configLoader native
npx vitest run --configLoader native --pool threads --maxWorkers 1 --testTimeout 20000
npx playwright test e2e/content-blocks.spec.ts e2e/slider-blocks.spec.ts e2e/pages.spec.ts e2e/homepage.spec.ts e2e/banners.spec.ts e2e/sliders.spec.ts e2e/media.spec.ts e2e/catalog.spec.ts --workers=1
```

Браузерные API fixtures синтетические. Медиа/контентное поведение backend проверено
реальными PHP tests отдельно. Снимки и trace лежат в игнорируемых `.tmp/c003-ui/` и
`frontend/admin/test-results/`; snapshots эталонных маршрутов не менялись.

## Изменённые файлы

- `frontend/admin/src/features/pages/`: `components/PagesWorkspace.vue`,
  `components/PageBlocksEditor.vue`, `components/BlockSettings.vue`,
  `components/SliderBlockEditor.vue`, `composables/usePageBlocks.ts`,
  `composables/useSliderBlock.ts`, `types/page.types.ts`, `types/block.types.ts`,
  `validation/blocks.ts`.
- `frontend/admin/src/features/homepage/components/HomePageWorkspace.vue`,
  `frontend/admin/src/features/homepage/components/HomePageBodyEditor.vue`.
- `frontend/admin/src/features/media/components/MediaReferenceField.vue`,
  `frontend/admin/src/features/media/composables/useMediaOptions.ts`,
  `frontend/admin/src/features/media/composables/useInlineMediaUpload.ts`.
- `frontend/admin/src/features/banners/components/BannerFormDialog.vue`,
  `frontend/admin/src/features/sliders/components/SliderFormDialog.vue`.
- `frontend/admin/e2e/content-blocks.spec.ts`, `frontend/admin/e2e/slider-blocks.spec.ts`,
  `frontend/admin/tests/pageBlocks.test.ts`, `frontend/admin/tests/sliderBlock.test.ts`.
- `frontend/admin/README.md`, `docs/API.md`, `docs/CONTENT_WORKSPACE_UX.md`,
  `docs/CURRENT_STATE.md`, `docs/TASK_C003_REPORT.md`, `tasks/TODO.md`,
  `tasks/IN_PROGRESS.md`, `tasks/DONE.md`.

Общее оформление и точный авторизованный предпросмотр
сохранённого черновика остаются C004/C005.
