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
