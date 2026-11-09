# Визуальный редактор юридических документов

По двум замечаниям владельца на `/settings` статусы документов выровнены
grid-колонкой, а textarea заменена визуальным редактором. Уточнение владельца:
выравнивание абзацев по левому/правому краю и по ширине. Цвета сохранены.

## Поведение

- Абзацы, H2/H3, жирный, курсив, списки, ссылки, undo/redo и left/right/justify.
- Форматирование видно при вводе и в предпросмотре сохранённой версии.
- Сохранение создаёт черновик; публикация по-прежнему отдельная операция.
- Ошибка не очищает ввод. Пустой документ и превышение лимита отклоняются.
- Текущие публичные страницы `/privacy-policy/` и `/personal-data/` выводят
  форматирование при SSR; у старых plain-документов сохранён буквальный текст.

## Данные и безопасность

Добавлено nullable JSON `legal_document_versions.content`. `body` остаётся
обычным текстом для совместимости и вычисляется сервером из rich content.
Обычные запросы без `content` продолжают работать. Старые версии не переписаны.
Добавочная локальная миграция применена после проверки на отдельной PostgreSQL.
Проверены up/down/up; откат на пользовательской БД не выполнялся.

Разрешён только ограниченный Tiptap JSON: типы блоков, атрибуты заголовков,
списков, выравнивание и href. HTML, images, произвольные CSS/атрибуты и
небезопасные схемы ссылок отклоняются. Ограничения: 200 000 байт JSON UTF-8,
глубина 16, 5 000 узлов. Client строит разрешённые Vue-узлы без `v-html`.
Значимые пробелы в inline-узлах сохраняются несмотря на TrimStrings.

## Изменённые области

- Backend: request/rule, LegalDocumentContent, management service, model/resource,
  миграция и LegalDocumentContentTest; API.md/OpenAPI обновлены аддитивно.
- Admin: UiRichTextEditor/richText, SettingsWorkspace/composable/types/service,
  UiKitShowcase/UI_KIT, unit и settings E2E.
- Client: UiFormattedText/formattedText, legal page/service, UI-kit,
  legal E2E и изолированный mock API.

## Проверка

Независимый UI Design Guard — PASS после исправления двух замечаний:
контракты описаны в UI-kit, Client использует script setup. Вручную просмотрены
Admin 320/1280 и preview, Client все пять ширин. Промежуточные Admin ширины
проверены E2E. Реальная страница `/settings` открыта и визуально проверена.

- Backend: полный PHPUnit — 803 теста / 9 062 assertions / 7 SKIP; для
  существующего большого fixture импорта использован `php vendor/bin/phpunit
  -d memory_limit=1G --no-progress` (параметр `php -d` перед artisan не
  распространяется на тестовый PHP-процесс). Pint — 712 файлов PASS;
  PHPStan — PASS. Отдельная PostgreSQL: 13 тестов / 209 assertions PASS,
  включая миграцию down/up, rich/plain, права и публикацию.
- Admin: полный unit suite — 311 PASS до дополнительного теста ссылок;
  lint/format/build PASS. Полный E2E — 715 PASS / 10 failures: 9 Windows
  screenshots и один timeout SEO. Только новый `/settings` baseline просмотрен
  и принят UI Guard; после его обновления settings/UI-kit/SEO — 13 PASS.
  Финальный тест нормализации ссылок — PASS (3 теста редактора).
- Client: typecheck/format/build PASS; полный E2E — 133 PASS / 2 failures;
  оба теста прошли при отдельном повторе. Финальный legal SSR/E2E — 4 PASS
  после перехода renderer на script setup; SSR/hydration, пять ширин и axe.
- Независимое code review — PASS. Произвольные HTML/атрибуты не исполняются,
  legacy-текст и checkout `[string, string]` сохранены.

Остальные Windows snapshot mismatches не обновлялись. На примере media
подтверждено: прежний эталон содержит help-параграфы, тогда как неизменённый
HEAD использует tooltip (исторический commit `cd98f03` обновил только
darwin/linux). Products/roles отличаются локально; точная историческая причина
остальных расхождений не установлена. Новые стили ограничены `.ui-rich-text`
и `.ui-formatted-text`; глобальные CSS/tokens не менялись.

## Файлы

- `backend/app/Http/Requests/Api/V1/Admin/StoreLegalDocumentVersionRequest.php`
- `backend/app/Http/Resources/LegalDocumentVersionResource.php`
- `backend/app/Models/LegalDocumentVersion.php`
- `backend/app/Rules/LegalDocumentContentRule.php`
- `backend/app/Services/LegalDocumentManagementService.php`
- `backend/app/Support/LegalDocumentContent.php`
- `backend/database/migrations/2026_11_09_000001_add_content_to_legal_document_versions.php`
- `backend/tests/Feature/Api/LegalDocumentContentTest.php`
- `docs/API.md`
- `docs/CLIENT_UI_KIT.md`
- `docs/LEGAL_DOCUMENT_EDITOR_2026-11-09.md`
- `docs/openapi.json`
- `docs/superpowers/plans/2026-11-09-legal-editor.md`
- `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/route-settings-chromium-win32.png`
- `frontend/admin/e2e/settings.spec.ts`
- `frontend/admin/package-lock.json`
- `frontend/admin/package.json`
- `frontend/admin/src/components/shared/UI_KIT.md`
- `frontend/admin/src/components/shared/UiKitShowcase.vue`
- `frontend/admin/src/components/ui/richText.ts`
- `frontend/admin/src/components/ui/UiRichTextEditor.vue`
- `frontend/admin/src/features/settings/components/SettingsWorkspace.vue`
- `frontend/admin/src/features/settings/composables/useSettingsWorkspace.ts`
- `frontend/admin/src/features/settings/services/settings.ts`
- `frontend/admin/src/features/settings/types/settings.types.ts`
- `frontend/admin/tests/uiKitShowcase.test.ts`
- `frontend/admin/tests/uiRichTextEditor.test.ts`
- `frontend/client/app/components/ui/UiFormattedText.vue`
- `frontend/client/app/features/legal/components/LegalDocumentPage.vue`
- `frontend/client/app/features/legal/services/legalDocuments.ts`
- `frontend/client/app/utils/formattedText.ts`
- `frontend/client/e2e/legal-documents.spec.ts`
- `frontend/client/e2e/mock-api.mjs`
- `tasks/DONE.md`
