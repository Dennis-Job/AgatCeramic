# Единый сегментированный переключатель Admin

Дата: 2026-10-15. Основание: референс владельца и запрос заменить контурные вкладки
на общий переключатель в импорте и фильтрах товаров.

## Результат

`UiSegmentedControl` используется в обоих сценариях: светлая подложка, белый
выбранный сегмент, синий текст и нижняя линия 2 px. Счётчики отображаются текстом.
Импорт сохраняет ширину по содержимому; фильтры распределяются по доступному месту.
На узком экране боковые отступы уменьшены, короткое «Все» не разрывается.
Палитра и типографика сохранены. Старый контурный компонент удалён из приложения
и UI-kit. Native radio, fieldset/legend, связи с именованными regions и Home/End
с пропуском недоступных вариантов сохраняют доступное клавиатурное управление.

## Изменённые файлы

Все пути ниже относительно `frontend/admin/`, кроме отдельно указанных документов.

- `src/components/ui/UiSegmentedControl.vue` — общий визуал, компактный режим и связи с regions.
- `src/components/ui/UiSegmentedTabs.vue` — удалён.
- `src/features/products/components/ProductImportWorkspace.vue` — миграция на общий control.
- `src/components/shared/UiKitShowcase.vue` и `src/components/shared/UI_KIT.md` — единый пример и документация.
- `tests/uiSegmentedControl.test.ts` — проверка связей, Home/End и disabled; `tests/uiSegmentedTabs.test.ts` удалён.
- `tests/uiKitShowcase.test.ts` — inventory из 25 primitives, без прежнего примера вкладок.
- `e2e/adminBaseline.spec.ts`, `e2e/productImport.spec.ts`, `e2e/softFocus.spec.ts` — актуальные semantics и визуальные проверки.
- `e2e/adminBaseline.spec.ts-snapshots/route-products{-loading,-forbidden,-empty,-error,}-chromium-win32.png` — пять просмотренных Windows эталонов.
- `docs/ADMIN_SEGMENTED_CONTROL_2026-10-15.md` и `tasks/DONE.md` в корне проекта — отчёт и статус.

## Проверки

- `npm run build`: PASS. Обычное предупреждение о размере JS bundle сохраняется.
- `npm run test:unit`: 147/147 PASS; расширенная проверка disabled-границ Home/End также PASS.
- Целевые production E2E: 30/30 PASS, включая импорт, фильтры, счётчики, axe, focus и forced colors.
- Финальный полный Windows production E2E: 592/592 PASS (5 минут, 6 workers).
- Финальные Admin lint/format: PASS.
- Независимый UI Design Guard: реализация и пять Windows эталонов одобрены после исправления 320 px.

Скриншоты 320/640/768/1024/1212/1280/2560 и settled focus:
`frontend/admin/.tmp/segmented-reference-review/`. Полный прогон использует отдельный
Vite preview в одном процессе с test runner для обхода Windows webServer teardown.
Unit runtime использует workspace TEMP. Linux/macOS E2E и их PNG в этой задаче
не запускались и не обновлялись. Backend, API и миграции не менялись. Commit не создавался.
