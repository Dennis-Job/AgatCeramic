# Компактные формы страниц

Дата: 2026-10-27. Уточнение владельца к секционному оформлению от 2026-10-26:
обёрток UiCard недостаточно; нужны компактные загрузки по UI-kit, небольшие фото
и понятная связь полей с настраиваемым содержимым.

## Решение

Использован готовый редактор логотипа `MediaImageEditor compact`, без второй
копии upload UI. У материалов, блока «О проекте», Open Graph и баннера в контексте
страницы одно фото 80×80, просмотр по нажатию, `UiFileDropzone` и раскрываемый
список медиатеки. Исходный адрес редактируется отдельно; подпись источника
поясняет, почему показана исходная картинка либо файл из медиатеки.
Если выбран media id, но файл недоступен, другое фото не подставляется.
Локальные `/images/...` открываются с origin клиентского сайта из `VITE_CLIENT_URL`.

Порядок блоков размещён в двух колонках при достаточной ширине. Материал подписан
номером и названием, баннеры стали строками с миниатюрами. Связанные поля занимают
две колонки, промежутки уменьшены. Короткие описания используют штатный opt-in
`UiTextarea compact`, высота определяется `rows`; прежний default 112 px сохранён.
Пример добавлен на `/ui-kit`.

Сохранены API, значения и правила сохранения/публикации; pending/uploading
по разным полям, permissions, стабильные ключи материалов и guards. Явный
`disabled` блокирует также drop. Полноэкранный просмотр баннера временно
приостанавливает родительский dialog и возвращает фокус при закрытии.

## Файлы

- `frontend/admin/src/components/ui/UiTextarea.vue` — опциональная компактная высота.
- `frontend/admin/src/components/shared/UiKitShowcase.vue`, `UI_KIT.md` — образец и контракт.
- `frontend/admin/src/features/media/components/MediaImageEditor.vue` — единый source/fallback preview и слот связанных полей.
- `frontend/admin/src/features/media/utils/contentImageUrl.ts` — origin локальных изображений.
- `frontend/admin/src/features/homepage/components/HomePageBodyEditor.vue`, `HomePageSeoEditor.vue` — компактные формы и единая загрузка.
- `frontend/admin/src/features/pages/components/PageBlocksEditor.vue`, `BlockSettings.vue`, `SliderBlockEditor.vue` — плотность, строки баннеров и disabled.
- `frontend/admin/src/features/banners/components/BannerFormDialog.vue` — загрузка и вложенный просмотр в контексте страницы.
- `frontend/admin/tests/appearanceLogo.test.ts`, `pageSeo.test.ts`, `e2e/slider-blocks.spec.ts` — источник фото, отсутствие подмены, disabled drop, nullable URL и вложенный просмотр.
- `docs/CONTENT_WORKSPACE_UX.md`, этот отчёт, `design-qa.md`, `tasks/DONE.md` — уточнение и результаты.

## Проверки

- Production build — PASS; сохранено известное предупреждение Vite о bundle >500 kB.
- Unit — 49 файлов, 249 тестов PASS.
- Lint, format:check, форматирование затронутой документации, git diff --check — PASS.
- Расширенный профильный E2E — 57 PASS до добавления компактного textarea.
- Полный текущий Admin E2E/visual — 680 сценариев: 679 PASS, один timeout
  `page-seo.spec.ts:142` при четырёх workers (штатный лимит 30 s, результат 35,6 s).
  Все 10 responsive screenshots этого сценария сохранены, визуальных расхождений нет.
  Единственный сценарий повторён с одним worker и прежним default timeout/assertions:
  PASS за 18,9 s общего прогона. Первый полный прогон не отмечается как 680/680 PASS.
- Все strict visual baseline пройдены без обновления PNG.
- Независимый UI Design Guard — accepted, блокирующих замечаний нет.

UI Guard проверил текущий diff, реальные материалы/SEO/баннеры, fullscreen viewer,
список слайдов и UI-kit, а также responsive evidence 320/640/768/1024/1280 px.
Полная baseline-регрессия проверяет маршруты приложения до 2560 px.
Замечания о новых рамках вложенных поверхностей и фиктивном off-state исходной
картинки исправлены до финальной приёмки. API, backend и миграции не изменялись.

Evidence: `.tmp/pages-compact/full-e2e.json`, `seo-rerun.json`, `full-e2e/`,
`seo-rerun/`, `material-live.png`, `material-viewer-live.png`,
`material-library-live.png`, `material-320.png`, `material-320-viewport.png`,
`seo-live.png`, `seo-320.png`, `banner-live.png`, `banner-viewer-live.png`,
`slider-settled-live.png`, `ui-kit-compact-live.png`.
Реальные данные не сохранялись и не публиковались; commit не создавался.

Команды проверки: из `frontend/admin` — `npm run build`, `npm run test:unit`,
`npm run lint`, `npm run format:check`. Из корня после сборки —
`docker compose --profile test run --rm admin-e2e node node_modules/@playwright/test/cli.js test --workers=4`.
Текущий прогон использует reporter JSON и отдельный каталог артефактов;
намеренного обновления visual baseline не требуется.
