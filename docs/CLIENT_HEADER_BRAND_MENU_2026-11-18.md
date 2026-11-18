# Шапка и алфавит брендов — 2026-11-18

Выполнены три замечания владельца к `/brand/kerama-marazzi`:

- Мобильный бургер центрирован в круге hover 44×44 px. Причина: локальный
  `inline-flex` заменял общий `grid` из UiIconButton; возвращён `display: grid`.
- Постоянное подчёркивание выбранной буквы удалено; ink-цвет, общий hover
  и видимый keyboard focus сохранены.
- Общий CatalogBrandNavigation скрыт до 1024 px включительно в каталоге,
  страницах брендов и preview. От 1025 px остаются четыре колонки dropdown.
  При переходе на узкий экран открытый popover закрывается.

## Изменённые файлы

- `frontend/client/app/components/shared/SiteHeader.vue`
- `frontend/client/app/features/catalog/components/CatalogBrandNavigation.vue`
- `frontend/client/e2e/ui-kit.spec.ts`
- `frontend/client/e2e/catalog-brand-menu.spec.ts`
- `frontend/client/e2e/brand-pages.spec.ts`
- `docs/CLIENT_UI_KIT.md` — актуальные UI-009/UI-032.
- Этот отчёт и `tasks/DONE.md`.

## Проверки

Typecheck, format:check, проверка формата трёх E2E-файлов и production build
прошли. Три замечания сначала подтверждены падающими проверками: смещение
11 px, `underline` вместо `none`, видимый алфавит на узком экране.

Набор: ui-kit, header-navigation, header-menu-placement, catalog-brand-menu,
brand-pages и catalog-brand-scope: **32/32 PASS**, exit 0 (30.7 s).
Проверены 320/640/704/768/1024/1025/1280 px,
keyboard/Escape/outside, touch на широком экране, normal/reduced motion,
SSR/hydration и axe. Resize проверен с фокусом в открытой панели и курсором
вне букв, чтобы повторный pointerenter не подменял проверку закрытия.

Независимый UI Design Guard принял diff и responsive PNG без блокирующих
замечаний. Скриншоты находятся в `.tmp/client-e2e/`.

Реальная Kerama Marazzi проверена в IAB при 704 px: смещение иконки по обеим
осям равно 0, алфавит отсутствует. Docker Client dev-сервер перезапущен:
watcher видел новые файлы, но продолжал отдавать прежние стили.

Первый sandbox build остановился на Windows EPERM внутри `.output`;
разрешённый повтор завершился успешно. Для Playwright задан путь к уже
установленному Chromium; финальный прогон завершён с доступом к cleanup
дочерних тестовых серверов. `git diff --check` прошёл.

API, база данных, миграции и зависимости не менялись. Коммит не создавался.
Полные Backend/Admin и все остальные Client E2E не запускались.
