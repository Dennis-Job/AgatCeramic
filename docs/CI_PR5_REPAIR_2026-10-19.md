# Исправления CI для PR #5 — 2026-10-19

Статус: исправления опубликованы в `aca2c57`; новый CI подтвердил Backend,
PostgreSQL, Redis, repository security, smoke и Compose. Windows CI выявил
четыре различия native rendering; приняты independently reviewed hosted actuals
для следующего прогона. Client audit остаётся блокирующим по решению владельца.

## Исходные ошибки

Для `ba6d597e0bd9b89858e14ca61ef529d4d983d4cf` оба workflow завершились с failure:
[основной CI](https://github.com/Dennis-Job/AgatCeramic/actions/runs/39226747541)
и [Windows UI](https://github.com/Dennis-Job/AgatCeramic/actions/runs/39226747745).
Четыре jobs прошли, пять упали. Linux E2E: 588 passed / 43 failed;
Windows palette/baseline: 218 passed / 12 failed.

## Изменения

- Заголовок локальной CORS-диагностики и ссылка в `tasks/DONE.md` используют
  однозначный anchor `local-saved-draft-preview-cors-and-401`. Repository hygiene
  после исправления проходит.
- `symfony/http-foundation` обновлён с `v7.4.18` до `v7.4.21`; изменены только
  шесть строк `backend/composer.lock`, manifest и constraints сохранены.
  [Symfony security advisory](https://symfony.com/blog/cve-2026-108082-redos-in-headerutils-split-reachable-through-the-cache-control-request-header)
  описывает исправление CVE-2026-108082 и последующее исправление whitespace
  regression в 7.4.21. Источник metadata — официальный
  [Packagist API](https://packagist.org/packages/symfony/http-foundation.json),
  commit `58c89144c7a36dd89c3d01917e9fa3a72d8aaf31`. Прямой Composer update
  не получил доступ к `repo.packagist.org`; metadata сверены отдельно,
  Composer install, validate и audit исправленного lock прошли.
- `scrollHeader.spec.ts` и `softFocus.spec.ts` раскрывают существующую панель
  «Фильтры» перед обращением к radio controls. Исходные проверки прокрутки,
  pointer/keyboard focus и forced colors сохранены: целевые 14 тестов проходят.
- Приняты только независимо просмотренные actual PNG той же платформы:
  33 Linux (товары, категории, характеристики, команда/доступ, контент) и
  9 Windows (товары и контент). UI Design Guard проверил восемь comparison
  sheets, полноразмерные representative screenshots и diff; blocking P1/P2
  не обнаружены. Изменения отвечают утверждённым redesign-документам.
- Оба Admin workflow сохраняют `test-results/` как failure artifacts на семь
  дней. Это нужно для трёх Windows CI расхождений `/roles`, `/permissions`,
  `/settings`, которые локальный Windows-прогон не воспроизвёл. Их отсутствие
  локально не считается доказательством исправления remote CI.

Палитра, production UI, API, retries=0 и tolerance 30 pixels не менялись.
Snapshots не переносились между Windows/Linux/macOS.

Изменённые файлы: `backend/composer.lock`,
`frontend/admin/e2e/scrollHeader.spec.ts`, `frontend/admin/e2e/softFocus.spec.ts`,
45 PNG в `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/`,
`.github/workflows/ci.yml`, `.github/workflows/admin-windows-ui.yml`,
`docs/CI.md`, `docs/ENVIRONMENT.md`, `docs/ADMIN_APPROVED_PALETTE_TESTS.md`, этот отчёт,
`docs/superpowers/plans/2026-10-19-pr5-ci-repair.md`,
`tasks/DONE.md` и `tasks/IN_PROGRESS.md`.

## Проверки

| Проверка | Результат |
| --- | --- |
| Repository hygiene | PASS, включая новый отчёт и план |
| Composer validate --strict | PASS |
| Composer audit --locked | PASS: no security vulnerability advisories found |
| Composer install в Linux | PASS: обновлён один пакет |
| Admin production build | PASS |
| Целевые scrollHeader/softFocus, Windows | PASS: 14 tests |
| Полный backend SQLite suite, два последовательных прогона | PASS: каждый 487 tests / 5817 assertions, 1 skipped |
| Backend Pint / architecture / Larastan | PASS; прежний неблокирующий size/complexity report сохранён |
| OpenAPI tooling tests / semantic lint | PASS: 5 tests, валидный контракт |
| OpenAPI compatibility с main | PASS: backward compatible |
| Admin lint / format / unit | PASS: 30 files / 159 tests |
| Запрет database artifacts во всех Git refs | PASS |
| Gitleaks 8.30.1, --all / --redact=100 | PASS: 321 commits, no leaks found |
| Полный desktop Windows E2E после первого adoption | PASS: 631 tests, retries=0; до принятия четырёх hosted эталонов ниже |
| Полный Linux E2E после adoption | PASS: 631 tests, retries=0 |
| GitHub Actions на aca2c57 | 6 jobs PASS, Linux Admin E2E выполняется; Client audit FAIL; Windows 226 PASS / 4 screenshot FAIL |

Windows E2E использовал локальный production preview на `127.0.0.1:4174`:
стандартный порт был занят. Временная игнорируемая конфигурация меняла только
baseURL/webServer и путь к штатным Windows snapshots; tracked Playwright config
не менялся. Linux E2E выполнен в Compose с чистой установкой зависимостей
и отдельной копией source/build, чтобы не затронуть Windows preview.

Независимое code/config review не выявило внесённых P1/P2: проверены
минимальность lock update, сохранение E2E assertions, условия failure uploads,
права workflow и bind-mounted artifact paths. Оба полных локальных E2E-прогона
пройдены до hosted follow-up; это не приёмка оставшегося Client audit.

## Hosted Windows follow-up

[Windows run 39253140410](https://github.com/Dennis-Job/AgatCeramic/actions/runs/39253140410)
на `aca2c5702b99be4489d43a75782c247e63158442` сохранил artifact
`admin-windows-browser-evidence`, id `12186855731`, SHA256
`29b6ef438098f704367caf1b193686d6b27f55aa28d46588eb1859561604fa0a`.
Runner: Windows Server 2025, image `windows-2025-vs2026`, version `20261007.264.1`.

Все отличия confined к системным monospace glyphs SKU `MONTE-1` и permission
code `catalog.manage`, а также native date placeholder `dd.mm.yyyy` (desktop)
против `mm/dd/yyyy` (hosted). Traces уже задают locale `en-US` и timezone
`Europe/Moscow`; только locale не нормализует Windows native control.
Точные resolved font names не проверялись. Все пиксели вне этих bounding boxes
совпадают (координаты x0,y0,x1,y1): products `(455,378,514,389)`,
roles `(336,613,437,625)`, permissions `(40,571,141,583)`,
settings `(662,1947,745,1961)`.

Независимые diagnosis/code review и UI Design Guard подтвердили причину;
Guard просмотрел full actuals, diff и увеличенные comparisons и принял все
четыре hosted снимка без blocking P1/P2. Они приняты как canonical Windows CI
эталоны. Всего изменены 33 Linux / 12 Windows PNG; products входит в оба
Windows adoption и считается один раз. Production UI, palette, tolerance 30,
retries=0 и accessibility assertions сохранены.

Desktop Windows не pixel-equivalent hosted runner: после этого adoption локальные
четыре screenshot comparisons ожидаемо отличаются. Прежние 631 local PASS
относятся к состоянию до hosted adoption. Новое подтверждение Windows CI
ожидается после публикации follow-up; не считать desktop PASS доказательством
прохождения hosted baseline gate.

## Оставшийся Client audit

На момент проверки `braces 3.0.3` и `node-forge 1.4.0` не имеют опубликованных
patched releases: [braces advisory](https://github.com/advisories/GHSA-vfj7-8cjw-p6xm),
[node-forge advisory](https://github.com/advisories/GHSA-86w9-cpqp-85rv).
Обычный `npm audit --omit=dev --audit-level=high` воспроизводит 11 high findings.

Проверено совместимое обновление Nuxt 4.5.2 → 4.6.2 в manifest/lock. Оно
убирает прямую CLI-цепочку listhen, но обязательный
[`@nuxt/nitro-server 4.6.2`](https://github.com/nuxt/nuxt/blob/v4.6.2/packages/nitro-server/package.json)
сохраняет `nitropack ^2.13.4`. Последний стабильный
[`nitropack 2.13.5`](https://github.com/nitrojs/nitro/blob/v2.13.5/package.json)
содержит globby/micromatch/braces и listhen/node-forge. Audit остаётся красным;
trial update отменён. Независимый dependency reviewer подтвердил вывод.

Подмена package names, suppressions, перевод dependencies в devDependencies,
понижение порога audit, downgrade Nuxt и экспериментальная замена server engine
не применялись. Поддерживаемого lock-only исправления сейчас нет. Владелец
2026-10-19 явно выбрал сохранить блокирующий audit до upstream-исправления.
Миграция server engine и поддержка security-форка не входят в текущую задачу.
Это принятие ограничения, а не признание зависимостей безопасными или
разрешение ослабить CI.
