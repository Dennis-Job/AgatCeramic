# Общие Client UI-компоненты и сохранение уточнений владельца

Дата: 2026-10-20. Задача: исключить повторное согласование уже отработанных
состояний и устранить разные крестики закрытия в меню и callback.

Актуальный источник правил — каталог компонентов и реестр UI-001–005 в
[`CLIENT_UI_KIT.md`](CLIENT_UI_KIT.md). Этот отчёт сохраняет только результаты
работы; не является альтернативным источником стилей.

Введён общий `UiCloseButton`; меню, поиск и `UiDialog` используют его вместо
локальных реализаций. В Client AGENTS закреплены обязательное чтение каталога
для каждой UI-задачи, перенос уточнений владельца в актуальный реестр,
изменение общих primitives и проверка потребителей без локальных копий CSS.
Регламент независимого UI Design Guard дополнен проверкой этих требований.

## Проверки

- Build, typecheck, format:check, `git diff --check` PASS.
- Полный production E2E: 31 PASS; development E2E: 2 PASS при повторе.
  Первый dev-прогон завершился timeout ожидания API в iframe, без изменения
  кода повтор прошёл; это не считается доказанной ошибкой компонента.
- Новый UI-kit E2E: три контекста × пять ширин, default/hover, геометрия,
  иконка и цвет SVG. Дополнительно три повтора PASS (45 сочетаний).
- Callback проверяет disabled без hover и клавиатурную навигацию; прежние
  проверки активных полей, маски, отсутствия autofocus и axe сохранены.
- В одном первичном screenshot поиска отсутствовал X. Добавлены проверка
  computed SVG stroke/размера и ожидание двух кадров перед снимком;
  три повтора отображают X корректно, стили приложения не менялись.
- Независимый UI Design Guard: код/документы и 15 сочетаний контекста/ширины
  accepted; замечание к снимку закрыто после проверки трёх повторов.
- Client dev-сервис перезапущен. API, БД и миграции не менялись.

## Изменённые файлы

- `frontend/client/app/components/ui/UiCloseButton.vue`
- `frontend/client/app/components/ui/UiDialog.vue`
- `frontend/client/app/components/shared/SiteHeader.vue`
- `frontend/client/e2e/ui-kit.spec.ts`
- `frontend/client/e2e/callback.spec.ts`
- `frontend/client/AGENTS.md`
- `docs/CLIENT_UI_KIT.md`
- `docs/UI_DESIGN_REVIEW.md`
- `docs/CLIENT_UI_REUSE_2026-10-20.md`
- `tasks/DONE.md`
