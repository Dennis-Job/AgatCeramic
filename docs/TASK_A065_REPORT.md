# TASK-A065 — модальные окна Admin по референсу

Дата: 2026-10-03.

## Результат

Общий `UiDialog` получил светлую холодную вуаль с backdrop blur 12 px. Панели
диалогов скруглены радиусом 24 px, используют более заметную мягкую тень и
внутренние отступы до 32 px. Кнопки закрытия получили нейтральный круглый фон и
размер 44 × 44 px. Липкие футеры выравниваются с внутренними отступами и
сохраняют скругление нижних углов.

Поддержаны viewport-зависимая высота и внутреннее прокручивание. Навигационный
popover исключён из blur и сохраняет прежнее затемнение. Круглая кнопка закрытия
добавлена в подтверждение удаления, демо обычного диалога в UI-kit и просмотр
документа в настройках. Независимое UI Design Guard-ревью выявило три нюанса:
повторный padding у формы сотрудника/роли, отступ footer предпросмотра документа
и слабую различимость disabled-крестика. Все замечания исправлены, повторный
ревью завершился статусом **Accept**.

## Проверки

- Локальный визуальный preview общего `UiDialog` просмотрен на ширинах 320,
  640, 768, 1024 и 1280 px. Текст переносится, футер складывается в колонку на
  узком экране, панель остаётся в пределах viewport.
- `npm run lint` — пройден.
- Prettier check изменённых Vue/CSS-файлов и плана редизайна — пройден.
- `npm run build` — пройден. Сборщик вывел существующее предупреждение о JS
  chunk размером больше 500 kB.
- Unit и E2E не запускались. Поэтому baseline снимок destructive dialog не
  обновлялся; изменение его визуального вида ожидаемо из-за новой кнопки X и
  скругления.

## Файлы

- `frontend/admin/src/components/ui/UiDialog.vue`
- `frontend/admin/src/components/shared/ConfirmDialog.vue`
- `frontend/admin/src/components/shared/UiKitShowcase.vue`
- `frontend/admin/src/features/employees/components/EmployeeFormDialog.vue`
- `frontend/admin/src/features/access-control/components/RoleFormDialog.vue`
- `frontend/admin/src/features/settings/components/SettingsWorkspace.vue`
- `frontend/admin/src/styles/tokens.css`
- `frontend/admin/src/styles/utilities.css`
- `docs/ADMIN_SELLER_REDESIGN_PLAN.md`
- `tasks/TODO.md`, `tasks/DONE.md`
