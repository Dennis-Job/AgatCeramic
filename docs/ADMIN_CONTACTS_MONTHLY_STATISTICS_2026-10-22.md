# Месячная статистика обращений и закреплённая шапка

Владелец запросил четыре показателя над таблицей по предоставленному референсу,
выбор месяца/года и закрепление заголовков при прокрутке документа, как у товаров.

## Согласованные значения

Все четыре показателя относятся к обращениям, **поступившим в выбранном месяце**.
Это отдельно подтверждено владельцем для завершённых и отклонённых: учитывается
их текущий статус, а не месяц перехода в этот статус. Типы partner/callback
считаются независимо от статуса; completed/rejected — по всем типам обращений.
Фильтры и вкладки списка не меняют статистику.

Границы календарного месяца определяются в Europe/Moscow и преобразуются в UTC
для выборки `created_at`: начало включено, начало следующего месяца исключено.
Выбранный месяц сравнивается с предыдущим полным календарным месяцем:
`(count - previous_count) / previous_count * 100`, округление до одного знака.
Если база равна нулю, ненулевой показатель получает «Нет базы» вместо выдуманного
процента; оба нуля дают 0%. Текущий незавершённый месяц явно отмечен в интерфейсе.
Исторические статусы могут изменяться после поступления обращения — показатели
завершения/отклонения за прошлый месяц отражают актуальное состояние его когорты.

## Интерфейс

- Четыре ячейки в общей рамке: партнёры, перезвонить, завершено, отклонено.
  Четыре колонки от 1024 px, две от 640 px, одна ниже; разделители адаптируются.
- `UiMonthPicker` использует существующие UiPopover/UiInput/UiButton:
  двенадцать месяцев, ручной ввод года и стрелки года, keyboard/Home/End/PageUp/
  PageDown, Escape, outside close и возврат фокуса, включая resize на 320 px.
  Годы 1900–9998 одинаково валидируются frontend/backend, позволяя безопасно
  вычислить предыдущую/следующую календарную границу.
- Ошибка статистики не блокирует таблицу; доступны retry, честные loading/zero
  состояния. Новые периоды и закрытие scope инвалидируют старые ответы.
  Сохранение статуса сразу запрашивает свежую статистику.
- ContactsList использует существующий `UiTable stickyHeader="page"`:
  прокручивается документ, исходный thead следует под сжимаемой шапкой сайта,
  локальный horizontal scroll сохраняется, у конца таблицы закрепление снимается.
- Витрина UI-kit включает новый primitive в инвентарь; общий стиль и остальные
  поля/диалоги не переопределялись. Контактные эталоны детерминированы по месяцу;
  loading snapshot ждёт завершения отдельного запроса статистики.

## Адаптивный выбор статуса — 2026-10-22

По запросу владельца переключатель статусов обращений на ширинах до 1024 px
заменён существующим `UiSelect`; от 1024 px сохранены вкладки. Список содержит
«Все» и статусы из API, счётчики показаны рядом с названиями, включая нулевые.
Оба режима используют одно состояние фильтра; изменение ширины сохраняет выбор.
До загрузки справочника control отключён. На узких экранах область результатов
имеет собственное доступное имя вместо ссылки на отсутствующую вкладку.

Проверены 320/640/768/1023/1024/1280 px, отсутствие горизонтального overflow,
выбор с запросом фильтрации, Enter/Home/стрелки, Escape, возврат фокуса и клик
снаружи. Независимый UI Design Guard принял код и скриншоты. Lint, format,
build, 237 unit и 16 релевантных E2E — PASS. Тест закрепления шапки теперь
ожидает нужный control после смены ширины перед измерением таблицы.
Дополнительный axe-прогон очереди с открытым списком на 320/768 px не выявил
нарушений. На 1024/1280 px остаются замечания `color-contrast` существующих
вкладок утверждённой палитры; стандартный E2E с согласованными исключениями
проходит. Цвета сохранены, их коррекция требует отдельного указания владельца.
API, миграции и утверждённая палитра не изменялись.

## Поле года без нативных стрелок — 2026-10-22

По скриншоту владельца в `UiMonthPicker.vue` поле года переведено с
`type="number"` на `type="text" inputmode="numeric"`. Нативные стрелки и
изменение года клавишами вверх/вниз в поле отсутствуют; собственные кнопки,
ручной ввод, PageUp/PageDown в сетке и проверка 1900–9998 сохранены.
Правило отражено в `frontend/admin/src/components/shared/UI_KIT.md`.

Lint/format/build, 237 unit, браузерная проверка 320/640/768/1024/1280 px и
независимый UI Design Guard — PASS/accepted. Проверены собственные стрелки,
ручной год, отсутствие spinbutton, блокировка 1899/9999/abc и выбор месяца.

Полный macOS E2E: 591/654 PASS, 63 failures. Из них 60 — screenshot baseline
в `adminBaseline.spec.ts` (в том числе экраны без открытого календаря), два —
контраст в `catalog.spec.ts` (`grouped product identifies product-specific and
shared characteristics at 1280px`) и `teamAccess.spec.ts` (`team access editors
preserve keyboard close, focus and responsive accessibility`). Утверждённые
цвета и эталоны не изменялись. Оставшийся failure — старое ожидание мобильных
вкладок в `contactsRedesign.spec.ts`; сценарий обновлён под выпадающий список.
Повторный целевой прогон `contactsMonthlyStatistics.spec.ts` и
`contactsRedesign.spec.ts`: 9/9 PASS. Полный прогон повторно не запускался.
Полный лог текущей сессии: `/tmp/agat-month-picker-e2e.log`.
Backend/API и миграции не затронуты.

## API и файлы

Добавлен `GET /admin/contact-statistics?month=YYYY-MM` при `contacts.view`.
Ответ содержит month/previous_month/timezone и четыре объекта
count/previous_count/change_percent. Старые контракты сохранены, OpenAPI/API.md
обновлены. Миграций, новых зависимостей и коммитов нет.

- Backend: ContactStatisticsController, ContactStatisticsRequest,
  ContactStatisticsQuery, routes/api/v1/admin.php, ContactStatisticsTest.
- Frontend: ContactStatistics.vue, UiMonthPicker.vue, utils/month.ts,
  useContactStatistics.ts; ContactsWorkspace/ContactsList, contacts service/types.
- UI-kit: UiKitShowcase.vue, UI_KIT.md и uiKitShowcase.test.ts.
- Tests: monthPicker.test.ts, contactStatistics.test.ts;
  E2E contactStatisticsFixtures.ts, contactsMonthlyStatistics.spec.ts,
  adminBaselineApi/adminBaseline, accessSalesContacts, contactNotifications,
  navigation, sellerWorkspaces и восемь Windows/Linux contacts snapshots.
- Документация: API.md, openapi.json, ADMIN_SELLER_REDESIGN_PLAN.md,
  этот отчёт и tasks/DONE.md.

## Проверки

- Admin build, ESLint, Prettier и полный unit: 237 tests / 45 files PASS.
  Сохраняется прежнее предупреждение Vite о размере bundle.
- Backend suite: 551 тест / 6186 assertions, один ожидаемый SQLite Unicode LOWER
  skip. Фокусные API/OpenAPI: 18 тестов / 2635 assertions PASS.
- Изолированный PostgreSQL: 14 тестов / 39 assertions PASS. Использовался
  временный контейнер с пустой БД и tmpfs; после проверки контейнер удалён,
  рабочая БД не менялась.
- PHPStan, architecture guard, Pint, Composer validate/audit, OpenAPI lint,
  conventions/tooling и backward compatibility PASS.
- Новые три браузерных сценария и Seller contacts: 4/4 PASS на Windows.
  Linux профиль (контактные baseline и месячные сценарии): 15/15 PASS.
- Независимый UI Guard принял 4/2/1 раскладку, календарь на 320/640 px,
  sticky header, error/zero состояния и восемь Windows/Linux actual до обновления
  соответствующих snapshots. Фокус после Escape/resize подтверждён повторным E2E.
- Полная Windows-регрессия: 651/654 PASS (5.4 min). Все сценарии обращений,
  sticky header, календарь, адаптивность, состояния и профильные snapshots PASS.
  Остались три прежние Windows pixel-разницы на `/products`, `/roles`,
  `/permissions`; эти эталоны и допуски не менялись.

Артефакты: `.tmp/contacts-monthly-e2e-final/`, `.tmp/contacts-monthly-full.log`,
`.tmp/contacts-monthly-linux-final.log`. Darwin snapshots не обновлялись:
macOS runner недоступен.
