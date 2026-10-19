# Исправления CI для PR #5 — 2026-10-19

Статус: локальные проверки исправлений пройдены; владелец явно разрешил commit/push
и проверку нового CI. Новый remote run ещё не запускался;
Client audit остаётся блокирующим по решению владельца.

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
42 PNG в `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/`,
`.github/workflows/ci.yml`, `.github/workflows/admin-windows-ui.yml`,
`docs/CI.md`, `docs/ENVIRONMENT.md`, этот отчёт,
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
| Полный Windows E2E после adoption | PASS: 631 tests, retries=0 |
| Полный Linux E2E после adoption | PASS: 631 tests, retries=0 |
| Новый GitHub Actions run | Не запускался |

Windows E2E использовал локальный production preview на `127.0.0.1:4174`:
стандартный порт был занят. Временная игнорируемая конфигурация меняла только
baseURL/webServer и путь к штатным Windows snapshots; tracked Playwright config
не менялся. Linux E2E выполнен в Compose с чистой установкой зависимостей
и отдельной копией source/build, чтобы не затронуть Windows preview.

Независимое code/config review не выявило внесённых P1/P2: проверены
минимальность lock update, сохранение E2E assertions, условия failure uploads,
права workflow и bind-mounted artifact paths. Оба полных E2E-прогона пройдены;
это не приёмка оставшегося Client audit и не подтверждение remote CI.

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
