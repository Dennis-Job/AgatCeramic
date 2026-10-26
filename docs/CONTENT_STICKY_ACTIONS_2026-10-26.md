# Закрепление действий редакторов контента

Статус: выполнено 2026-10-26 по Browser Comment владельца.
По последующему уточнению владельца применяется также к «Общему оформлению»:
редакторам шапки, подвала и доступной публикации в разделе вида фильтров.

«Сохранить черновик» и «Опубликовать» закрепляются на 8 px ниже фактической
шапки, когда исходная группа уходит под неё. При возврате наверх группа
возвращается в toolbar. Один набор существующих `UiButton` переносится через
Teleport; placeholder удерживает место, состояния и обработчики сохраняются.
Фокус восстанавливается без прокрутки, обработчики и observer удаляются при уходе.
На узком экране кнопки переносятся; навигация и dialog остаются выше панели.

## Изменённые файлы

- `frontend/admin/src/components/shared/AdminStickyActions.vue` — общий механизм закрепления.
- `frontend/admin/src/features/pages/components/PagesWorkspace.vue` — подключение к действиям страниц.
- `frontend/admin/src/features/appearance/components/AppearanceWorkspace.vue` — такое же подключение к общему оформлению.
- `frontend/admin/e2e/contentStickyActions.spec.ts` — responsive, axe, focus, resize, busy, save/publish, unmount.
- `frontend/admin/e2e/appearance.spec.ts` — сохранение и публикация шапки/подвала из закреплённой панели.
- `frontend/admin/src/components/shared/UI_KIT.md` — контракт компонента.
- `docs/CONTENT_WORKSPACE_UX.md` — уточнение поведения редактора.
- `docs/CONTENT_STICKY_ACTIONS_2026-10-26.md` — результаты проверки.
- `tasks/DONE.md` — статус задачи.

## Проверки

- `npm run lint`, `npm run format:check`, `npm run build`: PASS.
- `npm run test:unit`: 48 файлов, 248 тестов PASS. Для Windows sandbox TEMP/TMP
  направлены в `frontend/admin/.tmp/vitest-temp` после EPERM временного cache.
- Профильный production E2E после подключения «Общего оформления»: 42/42 PASS
  (`contentStickyActions`, `appearance`, `contentWorkspaces`); responsive/axe
  страниц, шапки и подвала на 320, 640, 768, 1024, 1280 px.
- Полный `npm run test:e2e -- --workers=4` на первом этапе (редактор страниц):
  657 PASS / 8 FAIL. Все восемь падений —
  screenshot mismatches Windows baseline: `/media` (loading/empty/error/default),
  `/products`, `/roles`, `/permissions`, `/settings`. Эталоны не обновлялись.
  Контент, сохранение/публикация, переходы, адаптивность и остальные проверки прошли.
- Независимый UI Design Guard: approved после просмотра десяти top/scrolled PNG
  и кода; дополнительное ревью функционального теста также без blocking findings.
  Уточнение «Общее оформление»: отдельно приняты двадцать PNG шапки/подвала и
  оба функциональных теста; общий компонент закрепления не менялся.
- Живые `/content?page=home` и `/content?section=appearance`: панель проверена под шапкой (65 + 8 px).
  Локальный контейнер Admin перезапущен для сброса устаревшего Vite module cache.
- API-контракт и миграции не изменены. Commit не создавался.

Локальные QA-артефакты: `frontend/admin/.tmp/sticky-actions/`
(PNG, `full-e2e.log`, `appearance-e2e.log`),
`frontend/admin/test-results/` (diff/trace восьми baseline failures).
