# Уточнение контактов и начального фокуса

Дата: 2026-10-20. Изменения прямо запрошены владельцем в чате.

После последнего уточнения владельца порядок: «заказать звонок» со строчной
буквы, email, телефон. Подчёркивания нет. Контакты отделены символом `·` с
отступами 12 px. Разделитель скрыт от screen reader,
переносится вместе со следующим контактом и отсутствует для пустого контакта.

При открытии формы фокус получает само окно без обводки. В меню и поиске
применено такое же правило. Поля и кнопки не выделяются автоматически;
видимый focus при Tab/Shift+Tab, удержание внутри панели, Escape, фон и возврат
фокуса сохранены. Глобальные стили focus controls не изменены.

## Проверка

Последнее изменение активного поля по footer «Ваш e-mail» в `exampleSite.html`:
общий Client-паттерн меняет цвет существующей границы 1 px на ink за 0.3 s,
без внешней рамки и тени. `UiTextField` и поиск используют
`--field-border-color`; у поиска сохраняется нижняя граница. Клик и Tab имеют
одинаковый стиль, reduced motion отключает переход. Начальный фокус контейнера
и индикация кнопок/ссылок сохранены.

Для этой правки: build/typecheck/format PASS, полный production E2E 30 PASS,
включая активное поле при клике/Tab и axe на пяти ширинах. Screenshots активного
поля 320/1280 px просмотрены исполнителем; независимый UI Design Guard проверил
код и screenshots всех пяти ширин — accepted. Client dev-сервис перезапущен.
Дополнительно к списку ниже изменён `frontend/client/app/assets/css/base.css`.
API, БД и миграции не менялись.

Последняя перестановка контактов отдельно проверена: build/typecheck/format
PASS, 10 callback/phone E2E PASS; пять ширин проверены браузерными тестами,
свежие screenshots 320/1280 px просмотрены. UI Design Guard по коду accepted.
Ниже — проверки предыдущей полной правки.

- Client: typecheck, build и format:check PASS.
- Production E2E: 30 PASS; development E2E: 2 PASS.
- Регрессии воспроизведены новыми тестами до исправления; проверены отсутствие
  начального фокуса на controls, Tab/Shift+Tab и видимая клавиатурная обводка.
- Ширины 320/640/768/1024/1280 px: screenshots, отсутствие overflow и axe PASS.
- Реальный localhost:3000: контакты из настроек, начальный фокус окна,
  маска, закрытие, hydration и мобильный overflow PASS; заявка не отправлялась.
- Независимый UI Design Guard: accepted, замечаний нет. Проверены код и
  screenshots topbar 320/1280 px. Скриншот формы при открытии также просмотрен.
- API, БД и миграции эта правка не меняет. `git diff --check` PASS.

## Изменённые файлы этой правки

- `frontend/client/app/features/callback/components/StorefrontHeader.vue`
- `frontend/client/app/features/callback/components/CallbackRequestDialog.vue`
- `frontend/client/app/components/ui/UiDialog.vue`
- `frontend/client/app/components/shared/SiteHeader.vue`
- `frontend/client/e2e/callback.spec.ts`
- `frontend/client/e2e/phone-mask.spec.ts`
- `frontend/client/AGENTS.md`
- `docs/CLIENT_UI_KIT.md`
- `docs/PHONE_STANDARD_2026-10-20.md`
- `docs/CALLBACK_UI_REFINEMENTS_2026-10-20.md`
- `tasks/DONE.md`
