# Проверки Windows — 2026-10-13

Проверяемый commit: `5d7b58b`; перед началом Git working tree чистый.
Запрос: выполнить проверки, ранее оставшиеся непроверенными на Windows.
Источники ограничений: `CI.md`, `CURRENT_STATE.md`, отчёты TASK-A062,
доработок контейнера/шапки/уведомлений/товаров и TASK-C004/C005.

Проверка выполнена без изменений application code, тестов, lock-файлов,
палитры и visual baseline. Логи, JSON/JUnit, screenshots и traces находятся
локально в `.tmp/windows-verification-2026-10-13/`; они не добавляются в Git.

## Окружение и границы

- Native Windows: Node `24.19.0`, npm `11.17.0`, PHP `8.3.1`, Composer `2.8.5`.
- Использованы существующие `node_modules` и `vendor`; чистая установка
  native-зависимостей не выполнялась.
- Sandbox блокирует Vitest cache rename, создание Nuxt `.output`, PHP
  `is_readable`, Git Bash/MSYS и Docker API. Повторные необходимые запуски
  вне sandbox разрешены автоматической проверкой; первичные ошибки сохранены
  отдельно и не выдаются за ошибки application assertions.
- Native PHP не содержит `pdo_pgsql` и `redis`. PostgreSQL/Redis/full-stack
  проверяются в Linux-контейнерах Docker Desktop с Windows host. Это отдельное
  доказательство интеграций, а не native Windows PHP или Windows rendering.
- SQLite принудительно `:memory:`. Docker использует отдельные test-only
  projects/databases с safety guards; рабочая база `agatceramic` не используется.
- Playwright snapshots не обновлялись, retries не включались, пороги сравнений
  и правила axe не изменялись.

## Подтверждённые результаты

| Проверка | Результат | Exit |
| --- | --- | --- |
| Admin `npm run lint` | PASS | 0 |
| Admin `npm run build` | PASS, TypeScript + Vite | 0 |
| Admin `npm run test:unit -- --maxWorkers=2` | 114 PASS / 5 FAIL, 19 файлов, 119 тестов | 1 |
| Admin полный production Playwright, 2 workers | 224 PASS / 126 FAIL, 350 тестов; 0 skipped/flaky, 12.7 минут | 1 |
| Admin real Nuxt preview | Первый прогон: 2 PASS / 1 timeout; отдельный повтор: 3/3 PASS | 1 / 0 |
| Client `npm run typecheck` | PASS | 0 |
| Client `npm run build` | PASS, SSR/Nitro | 0 |
| Client production E2E | 15/15 PASS | 0 |
| Client dev E2E | 2/2 PASS | 0 |
| Native backend SQLite, два полных прогона | Каждый: 393 PASS / 6 ERROR, 399 тестов, 5287 assertions | 2 |
| Backend Pint / architecture / PHPStan | PASS; architecture содержит 73 advisory hotspots | 0 |
| Composer validate / platform / audit | PASS; audit: 0 advisories | 0 |
| OpenAPI test / lint / compatibility | PASS; 5 tooling tests, сравнение контракта с HEAD | 0 |
| PostgreSQL integration | 22/22 PASS, 88 assertions | 0 |
| PostgreSQL feature suite | 318/318 PASS, 5001 assertions, random seed 48048 | 0 |
| Linux SQLite, дополнительное сравнение | Первый: 398 PASS / 1 FAIL; второй: 399/399 PASS | 1 / 0 |
| Redis queue delivery | 1/1 PASS, 84 assertions | 0 |
| Admin full-stack smoke | 1/1 PASS после готовности сервисов; 17.3 секунды | 0 |
| Compose dependency bootstrap | PASS, clean/stale dependency volumes; Git Bash с `MSYS_NO_PATHCONV=1` | 0 |
| Git database artifact guard | PASS, branches/remotes/tags | 0 |
| Repository hygiene | FAIL: 9 некорректных ссылок в `design-qa.md` | 1 |
| Admin production npm audit | 3 high | 1 |
| Client production npm audit | 1 moderate, 16 high, 3 critical | 1 |

## Причины подтверждённых ошибок

### Native PHP / Excel

Оба полных SQLite-прогона воспроизводят шесть ошибок `CategoryProductImportTest`:
`unlink(...): Resource temporarily unavailable` на строках 50, 75, 96, 130,
152 и 403. Assertion failures и skips отсутствуют.

Минимальный диагностический XLSX без БД подтверждает удержание file handle после
`ProductImportTemplateCategoryValidator::validate`: удаление до GC возвращает
`false`, `gc_collect_cycles()` собирает 85 циклов, после чего удаление успешно.
Это воспроизводимая Windows-проблема lifetime ресурсов OpenSpout/XML; исправление
не включено в проверку. Доказательства: `backend/xlsx-lock-diagnostic.log`,
`backend/sqlite-run-1-elevated.log`, `backend/sqlite-run-2-elevated.log` и JUnit.

### Admin unit

- `authCard.test.ts:44`: ожидает строку `shadow-dialog`, текущий shell использует
  `admin-panel--elevated`.
- `catalogComponents.test.ts:262`: ищет destructive-кнопку по CSS
  `button.bg-error-600`; компонент использует текущий `UiButton variant="danger"`.
- `uiBadge.test.ts:14`: ожидает `text-blue-light-700`, текущая утверждённая
  реализация использует `text-blue-light-500`.
- `uiKitShowcase.test.ts:19`: в inventory отсутствует существующий `UiProgressBar`.
- `uiKitShowcase.test.ts:61`: catalogue не содержит
  `--admin-dialog-backdrop-color` из текущего `tokens.css`.

Первые три проверки привязаны к прежнему оформлению; последние две показывают
неполный catalogue. Эти ошибки не следует устранять возвратом прежних цветов
или скрытием assertion. Лог: `admin-unit-native.log`.

### Admin E2E

Полный набор выполнялся с `updateSnapshots: none`, без retries, с двумя
workers. В `admin-e2e-full.json` зафиксировано 224 успешных и 126 упавших
тестов из 350. Машинная разбивка сохранена в `admin-e2e-failure-summary.json`.

- 61 упавший тест содержит screenshot assertion; в просмотренной паре dashboard
  Windows baseline показывает прежние поиск/профиль/поверхности/цену без копеек,
  actual — текущий интерфейс. Это содержательное расхождение, а не только
  rasterization. Baseline не заменён.
- 57 упавших тестов содержат axe `color-contrast`; четыре из них одновременно
  содержат screenshot failures. Например, бейдж «Активен»: `#12b76a` на
  `#ecfdf3`, контраст 2.48:1 при требовании 4.5:1. Других axe rule IDs в
  сохранённых ошибках этого прогона нет. Палитра сохранена согласно AGENTS.md.
- Остальные 12: шесть CSS-ожиданий цвета/hover/focus; отсутствующие controls/status
  в UI-kit и «Вид фильтров»; два поиска `aside` в деталях заказов/обращений;
  `scrollHeader.spec.ts:49` фиксирует сдвиг документа на 46 px; отдельный
  `softFocus.spec.ts:107` превышает 30 секунд. Эти результаты требуют сверки
  текущего UX и тестового контракта, не все являются доказанными дефектами продукта.

Существующая responsive-матрица имеет exception: `/categories` отключает
`color-contrast` в `adminBaseline.spec.ts`; поэтому даже PASS этого маршрута
не означает полное прохождение контрастной проверки. На остальных маршрутах
ошибка на первой ширине прекращает конкретный тест: не все восемь ширин
упавшего маршрута достигаются. Исключения и тесты при проверке не менялись.

### Форматирование и ссылки

Обычный Admin `format:check` обходит старые ignored `.tmp` artifacts и был
прерван. Проверка всех tracked текстовых Admin-файлов порциями по 35 аргументов
(ограничение Windows command-line length) с обычным EOL падает. Тот же набор
с `--end-of-line auto` проходит полностью. Client обычная проверка сообщает
о пяти файлах; повтор с `--end-of-line auto` также PASS. Поэтому EOL-результат
нельзя выдавать за успешный неизменённый CI format gate.

Repository hygiene находит в `design-qa.md` одну ссылку за пределы репозитория
на macOS-путь и восемь отсутствующих локальных images. Исторические отчёты
не переписывались задним числом.

### Real Nuxt preview

Первый проход `playwright.preview.config.ts` дал 2 PASS и timeout главной
на 60 секундах во время параллельных тяжёлых проверок. После завершения
полного Admin E2E выполнен отдельный повтор всего набора без изменений timeout
или retries: 3/3 PASS (главная — 51.5 сек, всего 1.4 минуты).
Первичный failure сохранён в `admin-preview.log`; успешный повтор —
`admin-preview-repeat.log` и `admin-preview-repeat.json`. Одного успешного
повтора недостаточно для утверждения, что исходный timeout устранён.

### Docker integrations и старт инфраструктуры

Выполнены миграции и safety guards только для allowlisted тестовых PostgreSQL
БД. PostgreSQL integration: 22/22 PASS, feature: 318/318 PASS с seed 48048;
Redis worker: 1/1 PASS, 84 assertions; настоящий Sanctum/Admin full-stack:
1/1 PASS. Логи и JUnit находятся в подпапке `backend/`.

Первоначальный full-stack запуск остановился на health checks при cold install:
Composer, затем `npm ci` (54 секунды) и Vite/Tailwind build (около 147.6 секунды)
не укладывались в штатный health budget около 65 секунд. После подтверждения
`healthy` выполнен browser smoke без изменения source/config. Поэтому
функциональный smoke PASS не означает успешный clean cold bootstrap этого profile.

Отдельный `scripts/test-compose-dependency-bootstrap.sh` успешно проверил
clean/stale dependency volumes. На Windows Git Bash потребовался
`MSYS_NO_PATHCONV=1`, иначе аргумент `/opt/...` преобразуется в путь Git for Windows.

Промежуточные PostgreSQL harness-ошибки (не был смонтирован `docs/openapi.json`,
затем неподдерживаемый PHPUnit 12 флаг `--cache-result-file`) сохранены отдельно;
последний полный прогон использует read-only docs mount и `--do-not-cache-result`.
Они не учитываются как ошибки приложения и не подменяют окончательный 318/318 PASS.

Сравнение Linux SQLite: первый прогон — 398 PASS / 1 FAIL, 5299 assertions;
второй — 399/399 PASS, 5315 assertions. Первый failure —
`GuestCartTest.php:83`: тест сравнивает `expires_at` с новым `now()` до точности
секунды после HTTP request без freezeTime. Код подтверждает риск пересечения
границы секунды; это объяснение недетерминированности, а не исправленный дефект.
Шесть Windows XLSX errors в Linux не воспроизводятся.

Созданные test-only projects `agatceramic-windows-queue-20261013`,
`agatceramic-windows-smoke-20261013`, PostgreSQL test project и оба
`agatceramic-dependency-smoke-*` удалены вместе с их networks/volumes.
Cleanup и отсутствие оставшихся test resources проверены агентом и
независимым финальным Docker inventory основного агента (0 ресурсов);
development volumes и рабочая БД не удалялись.

### Dependency audit

Оба npm audit выполнены с `--omit=dev --audit-level=high`, без `audit fix`.
Числа относятся к результату audit на дату проверки и не доказывают
эксплуатируемость каждого advisory в текущем приложении. Полные зависимости
и advisory URLs сохранены в `admin-audit.log` и `client-audit.log`.

## Основные команды

Команды запускаются из соответствующей директории; перенаправления логов,
JSON/JUnit и outputDir опущены для читаемости. Обычные native-команды,
заблокированные sandbox, повторялись вне sandbox.

```powershell
# frontend/admin
npm.cmd run lint
npm.cmd run build
npm.cmd run test:unit -- --maxWorkers=2
npm.cmd exec playwright test -- --workers=2 --reporter=list,json
npm.cmd exec playwright test -- --config=playwright.preview.config.ts --reporter=list,json
npm.cmd audit --omit=dev --audit-level=high

# frontend/client
npm.cmd run typecheck
npm.cmd run build
npm.cmd exec playwright test -- --reporter=list,json
npm.cmd exec playwright test -- --config=playwright.dev.config.ts --reporter=list,json
npm.cmd run format:check
npm.cmd exec prettier -- --check 'app/**/*.{vue,ts,css}' nuxt.config.ts --end-of-line auto
npm.cmd audit --omit=dev --audit-level=high

# backend
php vendor/bin/phpunit --configuration=phpunit.xml --colors=never
php vendor/bin/pint --test
php scripts/check-architecture.php
php vendor/bin/phpstan analyse --memory-limit=1G --no-progress
composer validate --strict
composer check-platform-reqs
composer audit --locked

# backend/openapi-tooling
npm.cmd test
npm.cmd run lint
```

Pint `1.30.5` подтверждён как реальный PHAR: его AgentReporter печатает
`{"tool":"pint","result":"passed"}` при отсутствии ошибок; это не stub.

Для Docker использованы документированные `queue-integration` и `admin-smoke`
profiles с отдельными `-p` project names и безопасным `--env-file .env.example`.
PostgreSQL использует локально сохранённый изолированный harness
`.tmp/windows-verification-2026-10-13/backend/postgres.compose.yaml` и runner scripts;
полные команды/safety guards сохранены рядом с логами. Это не инструкция
запускать destructive reset против development Compose.

## Итог

Проверка завершена с обнаруженными ошибками; общий статус не зелёный.
Исторический пробел Windows закрыт фактическими native-прогонами, но текущие
unit/E2E/baseline требуют отдельных исправлений и согласования визуальных
изменений. Обновление snapshots и исправление палитры не выполнялось.

Полный Gitleaks history scan, native clean dependency install и браузеры,
отсутствующие в текущих Playwright projects, не запускались. Этот отчёт
не заявляет полную эквивалентность всем job GitHub CI.

Изменены только этот отчёт, ссылка в `CURRENT_STATE.md` и запись в `tasks/DONE.md`.
Application code, API, migrations, tests и baseline PNG в Git не изменены;
коммит не создавался.
