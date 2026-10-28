# Бургер и положение подменю — 2026-10-28

Запрос владельца: показывать бургер только на маленьких экранах и открывать
desktop-подменю сразу под шапкой, не накладывая его на шапку.

## Результат

Используется действующий breakpoint: до 1024 px включительно виден бургер,
выше 1024 px — основная навигация без бургера. CSS скрывает control также
из accessibility tree и Tab-порядка, без SSR/hydration-различий.
При расширении окна открытая боковая панель закрывается с прежней анимацией,
scroll lock снимается после выхода; вместо скрытого инициатора фокус получает
видимый логотип. При обычном закрытии на малом экране фокус возвращается бургеру.

SiteHeader измеряет нижнюю границу всего header существующим ResizeObserver
и передаёт её desktop SiteNavigation. Та же граница используется поиском:
нет отдельных несовпадающих измерений. Подменю следует за sticky header при
scroll/resize и изменении высоты шапки. Верх подменю совпадает с низом header;
переворот вверх убран, на коротком экране используется оставшаяся высота
с прокруткой ссылок. Начальное смещение анимации направлено вниз на 6 px,
поэтому и промежуточный кадр остаётся ниже header.

Сохраняются .35 s motion, отсутствие стрелки в desktop и стрелка в drawer,
native popover, inert закрывающихся ссылок, reduced motion и меню из Admin.
Незакоммиченные изменения предыдущей задачи и ссылка логотипа `/` сохранены.

## Файлы

- Client source: `app/components/shared/SiteHeader.vue`, `SiteNavigation.vue`,
  `app/assets/css/overlays.css`.
- Новый `e2e/header-menu-placement.spec.ts`: breakpoint 1024/1025, шесть размеров,
  закрытие при расширении с focus/scroll cleanup; положение dropdown при
  начале/середине/конце анимации, sticky scroll и oversized short viewport.
- Актуализированы существующие E2E, ожидавшие drawer на desktop:
  `header-navigation`, `navigation-submenus`, `navigation-submenu-motion`,
  `callback`, `content-pages`, `preview`, `overlay-motion`,
  `scrollbar-stability`, `ui-kit`. Drawer-сценарии остаются на малых ширинах,
  desktop проверяет основную навигацию; modal/search сохраняют desktop coverage.
- `docs/CLIENT_UI_KIT.md` (UI-015), `docs/CONTENT_WORKSPACE_UX.md`, этот отчёт,
  `tasks/DONE.md`.

## Проверки

RED: оба новых теста воспроизвели видимый desktop-бургер и начало dropdown
на y=98 при нижней границе header y=120. GREEN: полный Client E2E 79/79 PASS,
включая новые 2 теста, все responsive/overlay/preview/axe-регрессии.
Client typecheck, build, format:check, форматирование затронутых тестов
и `git diff --check` PASS.

Независимый UI Design Guard — accepted, без замечаний. Просмотрены реальные
кадры 320/640/768/1024/1025/1280 px, dropdown во время анимации, sticky state,
короткий экран и preview. Evidence: `.tmp/header-menu-placement/full/`,
`.tmp/header-menu-placement-{red,build,full}.log`; артефакты не входят в Git.

Backend, API, миграции, данные и Admin не менялись. Новых зависимостей,
коммитов, push и публикации содержимого нет. Рабочая ветка `codex/submenu-motion`.
