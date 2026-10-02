# Компактная шапка при прокрутке — 2026-10-02

## Результат

При вертикальной прокрутке навигация перемещается из второго ряда в первый,
между логотипом AgatCeramic и кнопками уведомлений/сотрудника. После прохождения
высоты второго ряда (44 px) шапка занимает 65 px вместо 109 px: для рабочих
таблиц освобождается ещё 44 px. При возвращении вверх десктопная двухстрочная
компоновка восстанавливается. Перенесённое меню центрируется между брендом и
действиями. На экранах уже 1024 px шапка всегда занимает одну строку (65 px):
бургер рядом с уведомлениями и профилем открывает все доступные разделы.

Меню остаётся тем же экземпляром `AdminNavigation`: ссылки, permissions,
активный раздел, раскрытие hover/keyboard и focus не дублируются. CSS grid
меняет только размещение. Шапка не анимируется, поэтому перестановка не
создаёт переходного перекрытия controls и не требует отдельного reduced-motion.

Spacer компенсирует удалённый ряд в потоке документа: scrollY, высота документа
и положение таблицы не меняются при переключении. Это исключает скачки и
повторное переключение состояния из-за scroll anchoring.
`AdminLayout` меняет общий `--admin-shell-height` на актуальную высоту.
Заголовки таблицы товаров, scroll margins, предел высоты локальных таблиц и
компактное меню используют тот же token. Существующий rAF `UiTable` читает
новую границу после обновления Vue; дополнительный observer не требуется.

## Изменённые файлы

- `frontend/admin/src/layouts/components/AdminHeader.vue` — scroll state,
  размещение brand/navigation/actions и spacer, cleanup обработчика.
- `frontend/admin/src/layouts/AdminLayout.vue` — актуальная shell height.
- `frontend/admin/src/layouts/components/AdminNavigation.vue` — доступный
  бургер с общим оформлением иконок шапки, прежний dialog разделов.
- `frontend/admin/src/styles/navigation.css` — двухстрочная и компактная grid.
- `frontend/admin/src/main.ts`, `frontend/admin/src/styles/index.css` — прямое
  подключение CSS шапки как отдельного Vite module для актуального dev/HMR.
- `frontend/admin/e2e/scrollHeader.spec.ts` — переключение 44/45 px, геометрия,
  неизменность scrollY/размеров документа, таблица, меню, keyboard/Escape и axe.
- `frontend/admin/e2e/headerPopovers.spec.ts` — ожидаемый Tab-переход в содержимое
  страницы после меню сотрудника согласно порядку brand/navigation/actions.
- `frontend/admin/e2e/productPageScroll.spec.ts` — проверка изменяемой границы меню.
- `frontend/admin/e2e/containerLayout.spec.ts` — положение мобильного бургера
  рядом с действиями и однострочная шапка на всех проверяемых маршрутах.
- `frontend/admin/src/components/shared/UI_KIT.md` — контракт поведения.
- `docs/ADMIN_SELLER_REDESIGN_PLAN.md`, этот отчёт и task ledger.

Backend, миграции и API-контракт не менялись.

## Проверки

- `npm run build` — passed; прежнее предупреждение JS chunk >500 kB.
- `npm run lint` — passed.
- `npm run test:unit` — 73/73 passed.
- Форматирование исходников `prettier src e2e tests --check` — passed.
- Полный `npm run format:check` — 65 прежних `.tmp` test artifacts не соответствуют
  Prettier; исходники проверяются отдельно.
- Профильные E2E scrollHeader/headerPopovers/productImport — 27/27 passed.
  ProductPageScroll — 1/1; ProductWorkspace дополнительно проверен, 3/3 passed.
  Новая проверка сохраняет открытое меню и фокус при пересечении порога,
  проверяет обратное разворачивание и resize 1024 → 320 px.
- В первой полной регрессии адаптировано старое ожидание Tab и устранён
  transient overflow desktop-меню при смене viewport до завершения Vue media
  event. CSS сразу скрывает desktop nav на compact breakpoint. Код и
  keyboard-проверки повторно переданы независимому Guard.
- ScrollHeader/axe: 320/640/768/1024/1280/1440/1920/2560 px.
- Независимый UI Design Guard — accepted, blocking findings отсутствуют.
  Проверены код и восемь `.tmp/scroll-header/condensed-*.png`.
- Итоговый полный macOS Chromium E2E — 268/273 passed. Только пять прежних
  product visual baseline (default/loading/forbidden/empty/error) не совпадают
  после ранее внесённых отступов/«Сбросить», уже зафиксированных в
  `ADMIN_PRODUCT_PAGE_SCROLL_REPORT.md`. Число отличающихся пикселей default
  осталось тем же (7029); шапка в исходном состоянии сохраняет прежний вид.
  Новых функциональных или визуальных регрессий не обнаружено, snapshots
  в этой доработке не изменялись. Linux прогон не выполнялся.

## Уточнение после проверки открытой dev-страницы

По повторному замечанию владельца проверена именно открытая
`http://localhost:5173/products`. Новый Vue template уже был загружен, но CSS
`navigation.css` внутри Tailwind entry оставался старым даже после reload:
`header-inner` имел `display: block`, а brand/menu/icons располагались вертикально.
Предыдущая production-проверка этого расхождения dev-страницы не обнаружила.

`navigation.css` теперь импортируется непосредственно в `main.ts` после
`index.css`; вложенный CSS import удалён, правила не дублируются. Живая страница
после HMR использует grid. При реальном wheel-scroll brand/navigation/actions
имеют одинаковый top=0, header bottom≈65 px; меню находится между логотипом и
иконками. Снимок: `.tmp/scroll-header/live-header-scroll-verified.png`.

Повторные build/lint/source format — passed, unit — 73/73 passed;
полный macOS Chromium E2E — 268/273 passed с теми же пятью прежними
product baseline mismatch. Полный format check сохраняет 65 прежних
несоответствий в `.tmp`; исходники и отчёт проходят scoped проверку.
Независимый UI Design Guard проверил подключение CSS и актуальный live-снимок:
accepted, блокирующих замечаний нет. Снимок подтверждает компактную шапку;
фиксация заголовков таблицы проверена E2E на восьми ширинах.

## Центрирование и мобильный бургер

По следующему уточнению владельца перенесённая десктопная навигация
центрируется внутри свободной grid-колонки между логотипом и действиями.
До прокрутки desktop сохраняет исходное выравнивание второго ряда.

Вместо текста «Меню» на ширинах меньше 1024 px используется Lucide Menu
24 px с толщиной 1.5 и общим `admin-header-icon`: тот же target 40×40 px,
цвет, hover и keyboard focus. Бургер расположен непосредственно перед
уведомлениями и профилем с теми же интервалами 8/12 px. Мобильная шапка
однострочная сразу, её высота и shell token — 65 px, spacer — 0.
Сохраняются aria-label/expanded/controls, focus trap, Escape, закрытие по
backdrop и возврат фокуса. Threshold44 берётся из token, независимо от
мобильного размещения навигации.

На открытой dev-странице проверены одна строка и открытие/закрытие бургера.
Live-снимок: `.tmp/scroll-header/live-burger-centered.png`. Production:
`.tmp/scroll-header/burger-320-verified.png` и `centered-1440-verified.png`.

Итоговые build/lint/source format — passed; unit — 73/73 passed.
Полный macOS Chromium E2E — 268/273 passed, только те же пять прежних
product baseline mismatch. Проверены desktop centering, mobile icon targets
и интервалы, неизменность геометрии при scroll, меню/keyboard/Escape/axe
на 320/640/768/1024/1280/1440/1920/2560 px, контейнеры всех маршрутов.
Полный format check сохраняет 65 прежних несоответствий в `.tmp`.
Независимый UI Design Guard проверил код, live-снимок и новые production
снимки 320/1440: accepted, блокирующих замечаний нет.
