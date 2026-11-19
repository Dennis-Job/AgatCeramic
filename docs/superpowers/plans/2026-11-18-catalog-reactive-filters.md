# Реактивные фильтры каталога

Владелец выбрал: desktop обновляет товары сразу, mobile сохраняет «Применить».
Checkbox/select применяются сразу, поля ввода — после 350 ms паузы. Только
пользовательские изменения запускают авто-применение; восстановление из URL
и истории его не запускает. Невалидный ввод/обратные цены не отправляются.

- [x] Form: autoApply у sidebar, change-события характеристик, debounce и
  cleanup при route/resize/unmount/reset; mobile прежний. Desktop Apply скрыт.
- [x] Catalog: применённый URL/history, page reset, прежняя сортировка;
  старые карточки видны при pending, controls доступны. Сохранить scroll
  только для автоматического обновления на том же route через history state
  и делегирование прежнему Nuxt scrollBehavior для остальных переходов.
- [x] Регрессии auto/debounce/validity/races/history/resize, адаптация прежних
  desktop E2E, mobile draft без изменения. Build/typecheck/format, UI Guard
  на свежих screenshot-артефактах, обновить актуальные UI-правила и DONE.

API и данные не меняются. Без коммита.
