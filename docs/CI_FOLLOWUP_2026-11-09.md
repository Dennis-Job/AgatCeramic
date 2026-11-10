# Проверка оставшихся пунктов CI — 2026-11-09

Основание: два октябрьских пункта `tasks/IN_PROGRESS.md`. Начальное состояние:
чистый `main`, `0016f3eb4eb149a75eae097af0955a643a8fbf04`.
Работа начата 2026-11-09, финальные проверки продолжены 2026-11-10.

## PR #5: подтверждение hosted Windows

[PR #5](https://github.com/Dennis-Job/AgatCeramic/pull/5) уже merged 2026-10-19.
На его последнем head `24fc72dee70a537b9046ae0213178e814ae0df35`
[Windows run 39254274462](https://github.com/Dennis-Job/AgatCeramic/actions/runs/39254274462)
прошёл: 230 tests, включая palette и platform baselines. Это закрывает ожидание
hosted follow-up из [октябрьского отчёта](CI_PR5_REPAIR_2026-10-19.md).
[Основной run 39254274236](https://github.com/Dennis-Job/AgatCeramic/actions/runs/39254274236)
имел семь успешных jobs, включая Linux E2E 631 tests; падал только Client audit.

## Client: зависимости и audit

Исходный lock уже содержал `node-forge 1.4.2` вместо октябрьского `1.4.0`.
[Braces advisory](https://github.com/advisories/GHSA-vfj7-8cjw-p6xm) отозван
2026-10-23. На исходном lock текущий `npm audit --omit=dev --audit-level=high`
вернул exit 0: 0 high/critical, 10 moderate и 2 low findings. Старое утверждение
о блокирующих 11 high больше не описывает текущий graph/advisory database.
Это не означает, что audit вообще не находит уязвимостей.

Выполнено поддерживаемое обновление Nuxt 4.5.2 → 4.6.4 и Nitro 2.13.5 → 2.13.6.
[Официальный security release Nuxt](https://github.com/nuxt/nuxt/releases/tag/v4.6.4)
закрывает advisories Nuxt/Nitro. Manifest сохраняет точный pin Nuxt; Node engine
приведён к upstream `^22.22.3 || ^24.15.0 || >=26.0.0`, текущий CI Node 24.19.0
соответствует требованиям. В Client изменены manifest/lock и README;
компоненты, конфигурация приложения и production UI не менялись.

После обновления audit high gate PASS: 0 high/critical, 6 moderate и 6 low.
Остаются три первопричины и зависимые от них узлы:

- [deepmerge](https://github.com/advisories/GHSA-gcqx-w48m-w2h4) через
  `@rollup/plugin-node-resolve` → Nitro: актуальный leaf 4.3.1 остаётся affected.
- [glob](https://github.com/advisories/GHSA-3g3m-2fjr-6pw4) через `@vercel/nft`:
  поддерживаемая цепочка использует `glob ^13.0.0`, исправление 14.0.4 вне range.
- [minimatch](https://github.com/advisories/GHSA-g3hv-75wg-m249) через glob и
  `readdir-glob`: требуются обновления upstream ranges на major 11.

`npm audit fix --package-lock-only --ignore-scripts` не убрал эти findings.
Предложенный `--force` downgrade Nuxt до 4.1.3 не применяется. Overrides,
suppressions, перенос runtime dependencies в dev, ослабление порога и переход
на другой server engine не выполнялись. Workflow audit по-прежнему блокирует
high/critical; оставшиеся moderate/low требуют будущего upstream follow-up.

## Новые падения CI текущего main

Историческое подтверждение PR #5 не является подтверждением текущего `main`.
В [run 42627503083](https://github.com/Dennis-Job/AgatCeramic/actions/runs/42627503083)
для `0016f3e` Client checks уже PASS; Admin full-stack smoke ищет отсутствующий
label «Новый статус обращения». Текущий `ContactStatusActions` использует
кнопку «Начать обработку». Исправление ограничено существующим smoke-тестом;
реальное обновление статуса через API и проверки доступа сохраняются. Новый
сценарий ожидает PATCH до нажатия кнопки, проверяет HTTP 200, JSON ID/status,
следующее доступное действие и историю перехода. Inspector закрывается до logout;
проверки Sanctum session, 401 после выхода и 403 без permission сохранены.

В [Windows run 42627503091](https://github.com/Dennis-Job/AgatCeramic/actions/runs/42627503091)
224 tests PASS и 6 screenshot failures: media loading/empty/error, orders,
media и settings. Это новые изменения после PR #5. Evidence:
[artifact 13679247132](https://github.com/Dennis-Job/AgatCeramic/actions/runs/42627503091/artifacts/13679247132),
SHA256 `c5a4c16d94c5ee44a70ef8dfd9cf0f6a4b5041df8b62f3d7a5ef3b3ad01a31bb`.
Независимый UI Design Guard просмотрел все 18 полных expected/actual/diff и
settings crop в исходном разрешении. Blocking P1/P2 нет. Приняты ровно шесть
hosted win32 actual PNG, с проверкой SHA256 каждого source/target:

| Снимки | Подтверждённая причина |
| --- | --- |
| `route-media{,-empty,-error,-loading}-chromium-win32.png` | Утверждённые help tooltips из `cd98f03`; Windows adoption был ранее отложен |
| `route-orders-chromium-win32.png` | Утверждённый warning payment badge из `182c959`; остальные пиксели совпадают |
| `route-settings-chromium-win32.png` | Только native date placeholder `dd.mm.yyyy` → `mm/dd/yyyy`; вне `(662,2146)–(744,2159)` пиксели совпадают |

Evidence/manifest и полное независимое ревью сохранены локально в
`.tmp/ci-followup-20261109/windows/`. Палитра, production UI, tolerance 30,
`retries: 0`, `updateSnapshots: none` и assertions сохранены. Desktop Windows
не pixel-equivalent hosted runner; новый hosted прогон остаётся необходимым.

Основной run для `0016f3e` завершился: шесть jobs PASS, два FAIL (smoke и
Admin E2E). В Linux Admin E2E 724 PASS / 2 screenshot failures (`/orders`,
`/settings`), остальные install/audit/lint/format/unit/build PASS.
[Linux artifact 13680242643](https://github.com/Dennis-Job/AgatCeramic/actions/runs/42627503083/artifacts/13680242643),
SHA256 `aaf682a60f0bfa3a38f7acc0b79ca525313bb640161377344abf8895f321aadd`.
Второй независимый UI Guard просмотрел все шесть Linux expected/actual/diff,
сверил source/history и совпадение expected с tracked Linux PNG. Приняты только
`route-orders-chromium-linux.png` (warning payment badge, `182c959`) и
`route-settings-chromium-linux.png` (утверждённый rich editor, `d9ddfea`, +119 px).
Blocking P1/P2 нет; Windows evidence не использовался для приёмки Linux.
Manifest/review сохранены в `.tmp/ci-followup-20261109/linux/`.

## Проверки

- Clean Windows `npm ci` на Node 24.19.0 — PASS. Первые попытки ограниченного
  sandbox завершились EPERM; после остановки удерживавшего esbuild локального
  Nuxt и запуска установки с разрешённым доступом установка завершилась.
- `npm ls --depth=0`, `npm run typecheck`, `npm run format:check`,
  `npm run build` — PASS.
- `npm audit --omit=dev --audit-level=high` — PASS, с оставшимися findings выше.
- Client production E2E — 135 PASS; dev-preview E2E — 2 PASS; Node tests
  `product-variant-choices`/`product-variant-labels` — 7 PASS.
- Локальный dev-сервер Client восстановлен на исходном `127.0.0.1:3000`, HTTP 200.
- Независимое code/config/dependency review — P1/P2 нет; проверены согласованность
  manifest/lock, engines CI/Docker, optional platform entries, integrity,
  отсутствие обходов audit и сохранение реальных smoke assertions.
- Repository hygiene в read-only Linux-контейнере — PASS: 198 Markdown /
  1806 files, включая новые отчёт и план. Native Windows Python отсутствует;
  проверка выполнена тем же проектным validator в контейнере.
- Admin full-stack smoke — RED на старом отсутствующем locator, затем GREEN:
  1 PASS (10.7 s), exit 0, на реальных Laravel/PostgreSQL/Redis без browser API
  mocks. Изолированный проект `agatceramic-ci-followup-20261109`:

  ```sh
  docker compose -p agatceramic-ci-followup-20261109 --env-file .env.example \
    --profile admin-smoke run --rm admin-smoke-e2e
  ```

- ESLint и Prettier изменённого smoke, `git diff --check` — PASS.
- Строгие Linux Playwright baseline tests `/orders` и `/settings` после adoption —
  2 PASS (5.4 s). Использованы существующие config/assertions, production dist
  из smoke bootstrap и отдельные node_modules/browser volumes. Весь Admin E2E
  локально повторно не запускался: текущий hosted исходный прогон дал 724 PASS,
  а обе выявленные screenshot-регрессии проверены отдельно после исправления.
- SHA256 всех 6 Windows и 2 Linux принятых файлов совпадают с manifest Guard.
  Завершённый isolated Compose project и его тестовые volumes удалены;
  рабочие данные не затрагивались.

## Окончательное подтверждение CI — 2026-11-10

Изменения опубликованы в `d5ef27c306c6e03e63093530ab1838cb02363db5`.
[Основной CI 42767832304](https://github.com/Dennis-Job/AgatCeramic/actions/runs/42767832304)
и [Windows CI 42767832283](https://github.com/Dennis-Job/AgatCeramic/actions/runs/42767832283)
завершились `completed / success` на этом SHA. Это подтверждает hosted gates
после исправления smoke и adoption Linux/Windows baselines; CI follow-up закрыт.

Ожидание upstream-исправлений оставшихся moderate/low findings перенесено в
`tasks/TODO.md`: оно отложено и не считается устранением уязвимостей.
`tasks/IN_PROGRESS.md` очищен; активных задач нет.

## Изменённые файлы

- `frontend/client/package.json`, `package-lock.json`, `README.md` — security
  update и требования к Node.
- `frontend/admin/smoke/adminFullStack.spec.ts` — актуальный контактный workflow.
- PNG `frontend/admin/e2e/adminBaseline.spec.ts-snapshots/` — только перечисленные
  выше same-platform эталоны, независимо проверенные по hosted evidence.
- `docs/CI.md`, `CI_PR5_REPAIR_2026-10-19.md`, `WINDOWS_FIXES_2026-10-14.md`,
  этот отчёт и `docs/superpowers/plans/2026-11-09-in-progress-followup.md` —
  разделение исторических и текущих результатов.
- `tasks/IN_PROGRESS.md`, `tasks/DONE.md`, `tasks/TODO.md` — актуальные статусы.

API, production UI, палитра и миграции не менялись. Исходные исправления
подтверждены hosted CI на `d5ef27c`; последующая правка статусов документации
выполнена локально без дополнительного commit/push.
