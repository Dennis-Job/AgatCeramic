# Расширение закреплённых действий при загрузке

Запрос владельца: на `/content?page=home` кнопки сохранения и публикации
не должны переноситься из-за появления прелоадера; плавающий блок должен
растягиваться под содержимое.

## Изменение

В `AdminStickyActions` фиксированная ширина от anchor заменена на
`width: max-content` с ограничением шириной окна и отступом под белую подложку.
Панель сохраняет правую границу исходного блока, пока хватает места слева;
у края окна её положение корректируется. На узком экране сохранён перенос.
Placeholder удерживает фактическую ширину и высоту исходного блока.

Существующие `UiButton`, цвета, tokens, Teleport, восстановление фокуса,
обработчики сохранения и публикации сохранены. Исправление применяется также
к общему оформлению через тот же shared-компонент. API и миграции не менялись.

## Проверки

- До исправления regression E2E на 1059 px воспроизвёл проблему:
  ширина осталась 309.59 px после появления прелоадера, вторая кнопка
  перенеслась на следующую строку.
- `npm run build` — PASS; остаётся предупреждение Vite о размере JS chunk.
- `npm run lint`, `npm run format:check` — PASS.
- `npm run test:unit` — 256 PASS, 51 файл.
- `npx playwright test e2e/contentStickyActions.spec.ts --workers=4` — 22 PASS:
  страницы, шапка/подвал, прокрутка, фокус, resize, axe, сохранение/публикация
  с управляемой задержкой API. Loading проверен на 320, 640, 768, 1024,
  1059 и 1280 px; от 640 px панель растёт без увеличения высоты.
- Полный `npx playwright test --workers=4` — 681 PASS / 9 FAIL, 7.5 минуты:
  восемь известных Windows baseline differences (ниже) и один сбой
  `productListRedesign.spec.ts:207`,
  `collapsed product filters keep removable conditions and preserve sorting on reset`.
  После выбора поля сортировки ожидалось `Название: А–Я`, осталось
  `Создан: сначала новые`. Отдельный повтор этого же теста с одним worker —
  1 PASS; сбой повторно не воспроизведён. Код товаров не менялся.

Независимый UI Design Guard: **accepted**, блокирующих замечаний нет.
Проверены финальный diff и реальные idle/save/publish screenshots на шести
ширинах. Approved стили и shared reuse сохранены. Native Nuxt preview
изменением не затронут; Admin E2E изолируют его через fixture.

Локальные screenshots: `frontend/admin/.tmp/sticky-actions/loading-*.png`.
Лог полного прогона: `frontend/admin/.tmp/sticky-full-e2e.log`.
Лог отдельного повтора: `frontend/admin/.tmp/sticky-sort-recheck.log`.

## Ограничения полного Windows baseline

Независимый UI Design Guard проверил восемь несовпадений на маршрутах,
где `AdminStickyActions` не монтируется (компонент используется только
в `PagesWorkspace` и `AppearanceWorkspace`; стили scoped):

- `/media` default/loading/empty/error и `/settings`: Windows snapshots
  содержат старые inline help и порядок полей. Это ранее зафиксировано в
  [отчёте о подсказках](ADMIN_FIELD_HELP_2026-10-22.md#независимое-ревью-и-эталоны):
  обновлялись только Linux/Darwin snapshots, Windows остались старыми.
- `/products`, `/roles`, `/permissions`: diff ограничен системными monospace
  glyphs `MONTE-1` / `catalog.manage` (244 / 313 / 313 pixels). Различия
  local desktop и canonical hosted Windows описаны в
  [отчёте PR #5](CI_PR5_REPAIR_2026-10-19.md#hosted-windows-follow-up) и
  [правилах baseline](ADMIN_APPROVED_PALETTE_TESTS.md).

Snapshots и допуски сравнения в этой задаче не менялись. Полный suite
нельзя считать прошедшим; отдельный успешный повтор сортировки не заменяет
результат полного прогона.

## Изменённые файлы

- `frontend/admin/src/components/shared/AdminStickyActions.vue` — геометрия панели.
- `frontend/admin/e2e/contentStickyActions.spec.ts` — regression на loading.
- `frontend/admin/src/components/shared/UI_KIT.md` — контракт расширения.
- `docs/STICKY_ACTIONS_LOADING_2026-10-28.md` — результаты и ограничения.
- `tasks/DONE.md` — итоговый статус после завершения проверок.
