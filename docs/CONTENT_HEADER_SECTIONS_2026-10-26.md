# Секции настроек шапки и навигации

Дата: 2026-10-26. Запрос владельца: первый из трёх предложенных визуальных вариантов.

## Результат

«Контент → Общее оформление → Шапка и навигация» разделён на три постоянно
видимые секции: верхняя строка, логотип и навигация. Использован существующий
`UiCard` с серой шапкой и белым содержимым. Удалено повторное вступление
«Шапка сайта»; пояснение источника телефона/email находится у верхней строки.

Логотип использует существующий `MediaImageEditor` и утверждённый `UiFileDropzone`:
выбор/перетаскивание JPEG/PNG/WebP до 10 МБ, выбор из загруженных, описание,
pending/upload/error/retry состояния. Компактная миниатюра и специальная подпись
загрузки включаются только в этом использовании. Отмена файла возвращает фокус
в область загрузки. Без `media.manage` остаётся preview выбранного файла без
controls выбора, удаления или загрузки; пустое значение не вызывает media request.

Навигация оформлена единым списком с разделителями. Сохранены названия, ссылки,
добавление, удаление и порядок; кнопки используют доступные имена и Lucide-иконки.
На узких экранах поля размещаются последовательно. Реальные значения и количество
ссылок сохранены. Предпросмотр показывает настоящий Nuxt, черновик/публикация
остаются отдельными действиями.

## Изменённые файлы

- `frontend/admin/src/features/appearance/components/SiteChromeEditor.vue` — три секции и список меню.
- `frontend/admin/src/features/media/components/MediaImageEditor.vue` — opt-in restriction/compact/uploadLabel, возврат фокуса.
- `frontend/admin/tests/appearanceLogo.test.ts` — права и read-only preview, прежний default-контракт.
- `frontend/admin/e2e/appearance.spec.ts` — новый uploader, named sections, pending/axe/overflow/focus на 320–1440 px.
- `docs/CONTENT_WORKSPACE_UX.md` — согласованная группировка и uploader.
- `docs/superpowers/plans/2026-10-26-header-settings-sections.md` — план и границы изменения.
- `design-qa.md` — добавлен отчёт, предыдущие ревью сохранены.
- `tasks/DONE.md` — статус запроса после проверок.

## Проверки

- Unit: 47 файлов, 245 тестов PASS, включая три новых permission/preview tests.
- Lint: PASS. Build/typecheck: PASS; остаётся предупреждение Vite о размере существующего bundle.
- Проверки оформления, категорий и брендов: 37/37 PASS в финальном полном прогоне
  (16 appearance, 14 category images, 7 brand documents).
- Responsive/axe: 320/640/768/1024/1280/1440 CSS px, включая pending file и узкий desktop inspector.
- Independent UI Design Guard: accepted, блокирующих замечаний нет.
- Полный E2E: 658/659 PASS за 530,6 с. Единственный сбой — тайм-аут 30 с
  неизменённого `page-seo.spec.ts` / `page indexing controls stay usable and accessible
across required widths`. Отдельный повтор без конкурирующих workers: 1/1 PASS
  за 16,9 с. В код SEO-редактора изменения не вносились. Это не единый полностью
  зелёный прогон 659/659; повтор закрывает конкретный тайм-аут.
- Итоговый `npm run format:check` и `git diff --check`: PASS. Созданные этим запуском
  browser artifacts перенесены в `.tmp`, чтобы они не попадали в source formatting.

## Визуальные evidence и ограничения

`.tmp/content-header-design/final-live-1440.png`, `final-live-full.png`,
`comparison.png`, `comparison-inspector.png` — live UI и сравнение с выбранным макетом.
Во время первой сверки исправлена обрезка подписи пустой миниатюры: переиспользован
имеющийся compact fallback `UiImagePreview` с доступным скрытым текстом.

Иллюстративный логотип и три пункта меню из макета не подменяют реальные данные.
Трёхзонная сетка, утверждённые цвета/шрифты, backend, API-контракт и миграции не менялись.
Публикация данных и commit не выполнялись.

Локальный Windows E2E-запуск первоначально не нашёл Chromium в sandbox-профиле;
для browser validation использован штатный Linux Compose runner.
Полный JSON-отчёт: `.tmp/header-full-e2e.json`; отдельный повтор:
`.tmp/header-seo-retry.json`; responsive screenshots: `.tmp/header-settings-full-results/`.
