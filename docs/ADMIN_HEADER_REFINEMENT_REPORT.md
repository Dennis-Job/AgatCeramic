# Доработка TASK-A058/A062 — шапка Admin

Дата: 2026-10-02. Ветка: `codex/admin-seller-redesign`.
Статус: завершена; UI Design Guard принял результат, строгие macOS/Linux
и real Nuxt gates пройдены.

## Результат

По второму референсу владельца глобальный поиск удалён из общей шапки.
Бренд, навигация и нижний разделитель следуют ранее принятому контейнеру 1280 px.
Поиск в рабочих списках сохраняется.

Серые иконки колокольчика и пользователя открывают окна при наведении.
Окно сотрудника показывает актуальные имя/email, «Мой профиль», «Настройки»
при `settings.manage` и «Выйти». Роли не хардкодятся; Premium/язык и данные
чужого сервиса из референса не добавлены. Уведомления сохраняют существующее
честное empty state: новый backend-сценарий уведомлений не вводится.

Desktop-подменю также открываются наведением. Количество колонок равно
количеству разрешённых непустых групп. Semantic tokens задают колонку 216 px,
ширину окна иконки 320 px; gap/padding 24 px дают подменю 264/504/744 px
для 1/2/3 групп. Максимум дополнительно ограничен viewport с gutters 16 px.

Общий недоменный `UiPopover` управляет поведением: hover не забирает фокус,
задержка закрытия 180 ms позволяет пройти зазор, Enter/Space и стрелки открывают
окно с фокусом, Home/End/стрелки перемещают его, Tab наружу закрывает окно.
Escape возвращает фокус при управлении из окна; из рабочего input закрывает
hover-окно без перехвата фокуса. Клик по другому control сохраняет его фокус.
На touch иконки переключаются tap; компактная навигация сохраняет `UiDialog`
с focus trap, inert и возвратом фокуса. Header допускает одно открытое окно
и закрывает его при смене маршрута.

При недостатке места снизу popup открывается вверх; избыток высоты имеет
внутренний scroll. Позиция пересчитывается при resize/scroll. Длинные имя/email
переносятся. Витрина UI-kit содержит новый primitive, его tokens и пример.

Проверка выхода выявила прежнее игнорирование HTTP error в auth service.
Использован существующий `throwApiError`: при ошибке сессия не очищается,
меню показывает alert, повторный выход доступен; busy-кнопка предотвращает
повторный запрос. API-контракт, backend и миграции не менялись.

## Изменённые файлы

- `src/layouts/components/AdminHeader.vue`, `AdminNavigation.vue`,
  `AdminNotifications.vue`, `AdminUserMenu.vue`: композиция общей шапки.
- `src/components/ui/UiPopover.vue`: общий popup, focus/hover/touch/viewport.
- `src/styles/navigation.css`, `tokens.css`, `utilities.css`: разделитель,
  иконки, account actions, колонки и удаление старого search rule.
- `src/services/auth.ts`: обработка неуспешного ответа logout.
- `src/components/shared/UiKitShowcase.vue`, `UI_KIT.md`,
  `tests/uiKitShowcase.test.ts`: primitive, tokens и инвентарь 18 Ui-компонентов.
- `e2e/headerPopovers.spec.ts`: 8 сценариев шапки, popup, permissions,
  long data, touch, keyboard, short viewport, logout error/busy/retry.
- `e2e/navigation.spec.ts`, `catalog.spec.ts`: уникальные ID desktop-панелей;
  `smoke/adminFullStack.spec.ts`: выход через новое меню сотрудника.
- Platform baseline, план редизайна, `CURRENT_STATE.md`, отчёты A058/A062
  и task statuses: актуальная спецификация и результаты проверки.

Все `src/`, `tests/`, `e2e/`, `smoke/` пути здесь относятся к `frontend/admin/`.
Предыдущие изменения общего контейнера сохранены и описаны отдельно в
[`ADMIN_CONTAINER_REFINEMENT_REPORT.md`](ADMIN_CONTAINER_REFINEMENT_REPORT.md).

## Проверки и независимое ревью

| Проверка | Результат |
| --- | --- |
| macOS: `npm run check` | lint, format, 64 unit, TypeScript/build — пройдены |
| Linux: `npm run check` | lint, format, 64 unit, TypeScript/build — пройдены |
| macOS: полный `npm run test:e2e -- --workers=2` | 250/250, строгие эталоны |
| Linux: полный `npm run test:e2e -- --workers=2` | 250/250, строгие эталоны |
| Реальный Admin/Nuxt: `npm run test:e2e:preview` | 3/3, home/about/appearance, восемь ширин |
| Новый header/popover regression | 8/8; в том числе 320×400, logout error/busy/retry |
| `git diff --check`, repository hygiene, локальные Markdown-ссылки | Пройдены |

Полный набор включает матрицу 20 маршрутов × восемь ширин с axe без
исключений, loading/empty/error и UI-kit. Новые проверки окон используют
full axe, permissions, длинные русские строки, mouse/keyboard/touch.
Full-stack smoke helper обновлён для нового меню; отдельный backend smoke
в этой UI-доработке не запускался.

UI Design Guard независимо просмотрел шапку, hover-панели 320–2560 px,
одну/две/три колонки, длинные данные, logout busy/error и короткий viewport.
P2 по вертикальному позиционированию закрыт: flip/clamp/scroll, расчёт позиции
до фокуса с `preventScroll`, внутренний scroll при клавиатуре. Обнаруженное
пересечение popup spacing со sticky-anchor `scroll-margin-top` устранено;
regression дополнительно проверяет границу `banner.bottom + gap`.
Блокирующих замечаний нет.

56 Darwin + 56 Linux before/actual/diff снимков приняты до копирования.
Значимые изменения ограничены шапкой; размер снимков одинаков, ниже y110
остаются лишь четыре случая с max RGB delta=1. Обновлены только эти 112
кандидатов. Финальные прогоны используют `updateSnapshots: none` и прежний
`maxDiffPixels: 30`; остальные эталоны не заменены.

Во время промежуточного macOS-прогона параллельная пересборка временно
удалила dist и вызвала один 404. Прогон повторён на неизменяемой сборке:
250/250. Assertion и browser-issue guard не ослаблялись.
Сохраняется прежнее предупреждение Vite о размере bundle.

Логи: `/private/tmp/agat-header-check-final.log`,
`agat-header-macos-final.log`, `agat-header-linux-final.log`,
`agat-header-preview-final.log`. Linux выполнен в изолированной копии
`/private/tmp/agat-header-linux` с каноническим Docker-образом Admin E2E.
Windows в этой среде не проверен; его снимки не обновляются.

Evidence текущих состояний: `frontend/admin/.tmp/header-popovers/`:
`admin-user-panel-<width>.png`, `admin-notifications-panel-<width>.png`,
`submenu-<columns>-<name>-1920.png`, `user-long-data-1920.png`,
`ui-popover-flip-320.png`. Поддержанные ширины: 320, 640, 768, 1024, 1280,
1440, 1920 и 2560 px. Сохранённые before/actual/diff platform-кандидаты:
`/private/tmp/agat-header-review/{darwin,linux}/manifest.json`.

Коммиты не создавались.
