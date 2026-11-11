# Исправления CI для b36eaa6

Предыдущее исправление синхронизации appearance E2E вошло в
`b36eaa6076e9e117e1b16dbe71d25b6b97283fb2` и подтверждено hosted Linux
Admin checks: 729/729 E2E и 319/319 unit tests PASS.
В [основном run 42978333916](https://github.com/Dennis-Job/AgatCeramic/actions/runs/42978333916)
упал другой шаг backend; остальные семь jobs прошли.
В [Windows run 42978333827](https://github.com/Dennis-Job/AgatCeramic/actions/runs/42978333827)
отдельно упала браузерная навигация.

## PostgreSQL: повтор случайного бренда

`PostgresReadableOrderNumberTest::test_checkout_waiting_for_last_order_cleanup_gets_a_new_customer_id`
(`anonymize`) остановился ещё при подготовке товара: `brands_name_unique`,
повтор случайного названия «ЗАО Телеком».

`ProductFactory` автоматически создаёт бренд. `BrandFactory` использовал
`fake()->unique()->company()`, но Laravel сбрасывает историю Faker unique при
создании нового application container, а integration tests сохраняют бренды
между тестами. Проверка уникальности Faker не учитывает эти строки БД.

В `BrandFactory` название и производный slug получают UUID-суффикс, как
в существующей `RoleFactory`. Регрессионный `BrandFactoryTest` сохраняет
первый бренд, сбрасывает unique и повторяет Faker seed `48048`, затем создаёт
второй бренд. До изменения это воспроизводит unique violation; после изменения
оба объекта сохраняются с различными name/slug.

## Windows: ошибка Chromium при навигации

229 из 230 проверок прошли. Оставшийся тест `/employees` на 320 px остановился
в `page.goto` с `net::ERR_NO_BUFFER_SPACE`; до UI assertions он не дошёл.
По [определению Chromium](https://github.com/chromium/chromium/blob/main/net/base/net_error_list.h)
это отсутствие доступного места в буферах сокета. Точную причину нехватки
ресурсов на hosted runner исходный журнал не фиксирует.
Для проверки воспроизводимости запущен hosted rerun того же job на том же SHA.
Коннектор GitHub вернул `403 Resource not accessible by integration`, поэтому
повтор запущен через существующую локальную авторизацию Git (HTTP `201`).

[Повторный job 130388487005](https://github.com/Dennis-Job/AgatCeramic/actions/runs/42978333827/job/130388487005)
завершился `completed / success` на исходном `b36eaa6`, без изменения
тестов/конфигурации Windows: 230/230 browser tests и 319/319 unit tests PASS.
Это подтверждает, что исходная ошибка не
воспроизвелась на новом hosted runner; точный механизм системного сбоя
из журналов установить нельзя.

## Проверки backend

- Регрессия повторного названия: RED на исходной factory, GREEN после изменения.
- Независимый повтор нового regression test на SQLite: 1 test / 3 assertions PASS;
  PHP syntax check изменённой factory — PASS.
- Шесть PostgreSQL integration files на отдельной БД после guarded
  `migrate:fresh`: 32 tests / 220 assertions PASS, включая все 6 сценариев
  readable order number concurrency.
- Полный Pint — PASS (719 files); PHPStan — PASS (572 files).
- Composer manifest validate и architecture guard — PASS; неблокирующие
  архитектурные рекомендации сохранены.
- Полный SQLite suite в отдельном runner: 815 tests / 9227 assertions,
  808 passed / 7 skipped, exit 0.
- Полный PostgreSQL feature suite в отдельном runner с seed `48048`:
  717 tests / 8908 assertions PASS, exit 0.
- `git diff --check` — PASS.

Итоговые полные suites использовали разные контейнеры и временные storage/cache.
Первые параллельные попытки с общим storage признаны недостоверными:
`Storage::fake` одного suite удалял временные файлы другого. После разделения
runner оба полных прогона прошли без изменения приложения.
Локальный `composer audit --locked` не завершился из-за Packagist SSL timeout
`curl 28`; hosted audit на этом же lock-файле прошёл до падения integration test.
Зависимости не менялись. Все созданные тестовые контейнеры удалены.

Production-код, схема/ограничения БД, API, палитра, snapshots и retries сохранены.
Commit/push новых изменений не выполнялись.
