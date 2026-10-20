# Стабильная ширина страницы при открытии слоёв

Дата: 2026-10-20. Владелец сообщил о сдвиге сайта при исчезновении/возврате
вертикальной полосы прокрутки.

Причина подтверждена Chromium с классическим scrollbar: при viewport 640 px
ширина header/main менялась с 625 до 640 px после открытия меню. В обычном
headless с hide-scrollbars этот дефект не воспроизводится.

Общее исправление в `base.css`: `html { scrollbar-gutter: stable }` сохраняет
место под полосу. `body` ограничивает minimum доступной шириной через
`min-width: min(320px, 100%)`, устраняя переполнение узкого classic viewport.
Scroll lock, modal lifecycle и анимации не менялись. Правило закреплено в
UI-007 `CLIENT_UI_KIT.md` и Client AGENTS.

Свойство описано в [MDN](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Properties/scrollbar-gutter)
и [CSS Overflow](https://drafts.csswg.org/css-overflow-3/#scrollbar-gutter-property).

## Проверка

- До исправления regression воспроизводил сдвиг 15 px; после — геометрия
  страницы одинакова до/во время/после меню, поиска и native modal.
- Классические scrollbars реально включены в Chromium. Проверены 320/640/768/
  1024/1280 px и отсутствие горизонтального overflow. Отдельно проверена
  короткая страница на 320 px; оба overflow assertions падали до body fix.
- Перед измерением viewport тест ждёт два кадра для завершения media reflow.
- Build, typecheck, format:check, `git diff --check` PASS.
- Полный production E2E: 37 PASS, включая оба classic-scrollbar regression,
  прежние motion, focus, callback, SSR/iframe preview и responsive проверки.
- UI Design Guard: код, UI-007 и screenshots page/menu/modal на 320/1280 px —
  accepted; исполнитель просмотрел те же состояния. Client dev перезапущен.
- API, БД и миграции не менялись.

## Изменённые файлы

- `frontend/client/app/assets/css/base.css`
- `frontend/client/e2e/scrollbar-stability.spec.ts`
- `frontend/client/AGENTS.md`
- `docs/CLIENT_UI_KIT.md`
- `docs/CLIENT_SCROLLBAR_STABILITY_2026-10-20.md`
- `tasks/DONE.md`
