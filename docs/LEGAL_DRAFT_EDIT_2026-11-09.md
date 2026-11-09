# Редактирование черновиков юридических документов

На `/settings` сохранённый черновик можно открыть действием «Редактировать»
в строке списка или в предпросмотре. Форма загружает версию, текст и форматирование.
«Сохранить изменения» обновляет тот же ID; «Отменить редактирование» оставляет
сохранённый документ прежним. Тип документа фиксирован.

Публикация остаётся отдельным действием. Пока открыт редактор черновика,
публикация заблокирована до сохранения или отмены. Опубликованные версии
защищены и интерфейсом, и сервером. Ошибка сохранения сохраняет введённые
правки; начатая форма новой версии восстанавливается при завершении edit.
Фокус перемещается в редактор, затем возвращается к действию строки.
Используется общий header-aware scroll margin, чтобы шапка не скрывала форму.

По последующему замечанию владельца предпросмотр расширен с 672 до 1024 px.
Ширина примечания в desktop-footer ограничена, чтобы три действия оставались
в одном ряду на 1024/1102/1280 px. На узких экранах сохраняется перенос.
Регрессия расположения кнопок воспроизведена до правки; settings E2E 5 PASS,
314 unit tests, build/lint/format PASS. Независимый UI Design Guard — PASS.
Проверены также отключённые действия при открытом редакторе (1102 × 883).

`PATCH /admin/legal-documents/{legalDocumentVersion}` требует `settings.manage`,
принимает обязательные `version`/`body`, необязательный `content` и совместимое
поле `type` только с прежним значением. Rich content проверяется тем же allowlist,
что при создании; plain body вычисляется сервером. Без `content` старое
форматирование очищается. Уникальность версии учитывает тип и исключает текущий ID.
Update и publish используют одну блокировку строки в транзакции. Автор и
публикация не меняются; аудит `legal-document.updated` не содержит текста.
Схема БД не менялась, миграции не требуются.

## Проверка

- RED → GREEN: Admin 2 regression tests; backend 5 tests, 56 assertions.
- Admin `npm run check`: lint, format, 314 unit tests, TypeScript/build PASS.
  Сборка сохраняет предупреждение о размере основного JS bundle.
- Settings/pages E2E: 8 PASS. Проверены rich/plain, ошибка/retry, тот же ID,
  отсутствие POST при edit, reload/preview, cancel, отдельная публикация,
  клавиатурный фокус, пять ширин 320–1280, отсутствие overflow и axe.
- `/settings` visual baseline и responsive: 9 PASS, эталон не переписывался.
- Backend full suite: 808 tests, 9 137 assertions, 7 PostgreSQL-only SKIP.
  Отдельно SQLite legal/OpenAPI: 15 tests, 2 974 assertions PASS.
- Изолированная PostgreSQL `agatceramic_feature_test`: 11 legal tests,
  197 assertions PASS. Пользовательская БД в тестах не использовалась.
- Pint, PHPStan, architecture, Composer validate/audit и diff whitespace PASS.
- Независимые UI Design Guard и code review: PASS после исправления
  перекрытия начала формы шапкой. Просмотрены пять ширин edit и preview 320/1280.
- Реальная страница `localhost:5173/settings` обновлена перезапуском Vite.
  Черновик «Политика ПДн · V2», ID 4 открыт для владельца в форме;
  его текст, версия и публикация не менялись.

Полный Admin E2E suite не запускался: изменён только settings feature,
shared primitives и design tokens сохранены. Коммит не создавался.

## Изменённые файлы

- `backend/app/Http/Controllers/Api/V1/Admin/LegalDocumentController.php`
- `backend/app/Http/Requests/Api/V1/Admin/UpdateLegalDocumentVersionRequest.php`
- `backend/app/Services/LegalDocumentManagementService.php`
- `backend/app/Support/AuditLabels.php`
- `backend/routes/api/v1/admin.php`
- `backend/tests/Feature/Api/LegalDocumentDraftUpdateTest.php`
- `frontend/admin/src/features/settings/components/SettingsWorkspace.vue`
- `frontend/admin/src/features/settings/composables/useSettingsWorkspace.ts`
- `frontend/admin/src/features/settings/services/settings.ts`
- `frontend/admin/src/features/audit-log/utils/auditActions.ts`
- `frontend/admin/tests/settingsDraft.test.ts`
- `frontend/admin/e2e/settings.spec.ts`
- `docs/API.md`, `docs/openapi.json`
- `docs/LEGAL_DRAFT_EDIT_2026-11-09.md`
- `docs/superpowers/plans/2026-11-09-legal-draft-edit.md`
- `tasks/DONE.md`
