# Windows follow-up: пункты 1, 3, 5 и 6 — 2026-10-14

Основание — поручение владельца исправить оставшиеся пункты после
[первой Windows-проверки](WINDOWS_VERIFICATION_2026-10-13.md).
Базовый commit `817846d`, ветка `codex/admin-seller-redesign`.
[План](superpowers/plans/2026-10-14-windows-followup-fixes.md).

Пункты 1, 3 и 6 закрыты. Пункт 5 выполнен в части доступных совместимых
обновлений: Admin audit чистый, Client сохраняет 11 high findings двух
неисправленных upstream advisories. Общий security-статус не зелёный.

## 1. XLSX ресурсы на Windows

OpenSpout 4.32.0 закрывает архив и cache в `Reader::close()`, но активный
sheet XMLReader закрывает только при достижении EOF. Early return metadata
validator оставляет ZIP stream открытым через циклические callbacks. Это
воспроизвело шесть прежних ошибок немедленного `unlink` на Windows.

Новый `backend/app/Support/XlsxReaderCleanup.php` закрывает активный XMLReader
до archive/cache cleanup в `finally`. Адаптер подключён к category validator
и template reader; production `gc_collect_cycles()` удалён. Четыре реальные
регрессии проверяют немедленное удаление после success, early return и
validation exceptions с временно отключённым автоматическим GC. Исходное
состояние GC восстанавливается. Failed-open subprocess также удаляет файл
до завершения процесса.

У OpenSpout v4 нет публичного iterator close API:
[upstream RowIterator](https://raw.githubusercontent.com/openspout/openspout/4.x/src/Reader/XLSX/RowIterator.php).
Reflection изолирован в адаптере; проверяются major v4 и точный тип приватного
`RowIterator::$xmlReader`. Это осознанная compatibility-зависимость от locked
4.32.0: при будущем обновлении обязательны Windows immediate-unlink regressions.
Нормальный GC продолжает освобождать циклические объекты; файл освобождается
явно без ожидания GC. Независимое backend review — approved, blockers нет.

## 3. Шапка, focus и реальный preview

- Сдвиг 713→667 px был гонкой тестовой fixture: generic filter-counts response
  снимал loading badges во время измерения геометрии на 320 px. Валидные counts
  и ожидание доступного описания счётчика уже добавлены в предыдущем palette
  follow-up; все восемь ширин и threshold/focus assertions теперь прошли.
- Исторический `softFocus` timeout ожидал отсутствующую кнопку «Прозрачная»
  (`locator.focus`), а не фиксировал медленный focus приложения. Текущее
  утверждённое имя «Синяя прозрачная» уже исправлено в предыдущем follow-up;
  полный набор и отдельная native regression проверка прошли.
- Реальный Nuxt preview выполнял восемь ширин и 16 снимков внутри одного
  60-секундного case. По trace timeout возникал в середине matrix после
  screenshot, при cold Vite compilation и параллельной нагрузке. Матрица
  разделена на 24 независимых case: три маршрута × восемь ширин. Сохранены
  настоящий Nuxt renderer/API, keyboard scroll, локальный overflow, 1280/375 px,
  центрирование mobile и прежний timeout 60 секунд. Возврат «Телефон → Компьютер»
  теперь явно проверяет 1280 px и page overflow в каждом case.

Оба real-preview прогона прошли 24/24; первый выполнялся параллельно с полным
Admin E2E и cold Docker. Первый cold case занял 38.9 секунды, остальные около
двух секунд; это не обещание отсутствия любых будущих нагрузочных задержек.
UI Design Guard просмотрел все 48 PNG, закрыл P2 отсутствующей проверки
возврата в desktop и одобрил окончательные tests. Production UI, палитра и
эталоны не менялись содержательно.

## 5. Совместимые npm обновления и оставшийся audit

Обновлены два lock-файла без `--force`, overrides, смены audit threshold или
переклассификации зависимостей. Содержимое обоих package manifests и pin Nuxt
4.5.2 сохранены; Admin package.json дополнительно приведён к LF.

Ключевые разрешённые обновления: Vue 3.5.41→3.5.44, source-map-js 1.2.1→1.2.2,
postcss 8.5.26→8.5.30. Обновлены также уязвимые транзитивные dev dependencies;
Client devtools 3.4.1→3.4.3 убрал simple-git/argv-parser chains. Clean `npm ci`,
dependency-tree consistency и сборки прошли для обоих проектов.

| Production audit | До                              | После                                |
| ---------------- | ------------------------------- | ------------------------------------ |
| Admin            | 3 high                          | 0, exit 0                            |
| Client           | 1 moderate, 16 high, 3 critical | 11 high, 0 moderate/critical; exit 1 |

Число findings означает узлы dependency graph, а не отдельные подтверждённые
эксплойты. Остаются две первопричины:

- `braces@3.0.3` через Nitro→globby→micromatch: stack exhaustion от глубоко
  вложенных недоверенных patterns. [GHSA-vfj7-8cjw-p6xm](https://github.com/advisories/GHSA-vfj7-8cjw-p6xm)
  указывает patched versions: none. [Обсуждение maintainer](https://github.com/micromatch/braces/issues/70)
  рассматривает trusted globs/maxLength. Nitro использует globby для
  настроенных source/public asset scans; приложение не принимает glob через API.
- `node-forge@1.4.0` через Nuxt CLI/Nitro→listhen: RSA signature verification.
  [GHSA-86w9-cpqp-85rv](https://github.com/advisories/GHSA-86w9-cpqp-85rv)
  также не имеет patched release; [PR 1152](https://github.com/digitalbazaar/forge/pull/1152)
  ещё не опубликованный поддерживаемый fix. Listhen использует forge для
  HTTPS certificate/key handling, не вызывает `.verify` в исследованном коде.

Registry latest этих leaves остаются 3.0.3/1.4.0. Предложенный npm Nuxt 4.6.1
сохраняет обе цепочки через Nitro 2.13.4 и не устраняет gate failure, поэтому
не включён как бесполезное расширение scope. В свежем `.output` не найдены
названия этих пакетов; это evidence текущего build/dev пути, не универсальное
доказательство неэксплуатируемости. Audit gate остаётся blocking. Требуется
поддерживаемый upstream release либо отдельное рассмотрение проверенного fork/patch.

Composer dependencies не менялись. Native и container `composer audit --locked`
не завершили проверку из-за Packagist curl 28, exit 100. Это неизвестный статус,
а не ноль advisories и не ошибка application tests.

## 6. Время, cold bootstrap, LF и ссылки

- `GuestCartTest` фиксирует время через Laravel `freezeTime()`: ожидание TTL
  больше не зависит от пересечения секунды между request и assertion.
- Compose выделяет `admin-smoke-backend-bootstrap` и `admin-smoke-bootstrap`:
  Composer/guard/migrations/fixtures ограничены 300 секундами, npm/build — 360,
  TERM kill grace — 10 секунд. Runtime зависит от `service_completed_successfully`;
  прежний HTTP health budget сохранён и начинается после подготовки.
- `admin_smoke_dist` разделяется только bootstrap/runtime test services:
  Docker build больше не перезаписывает native host dist. После обнаружения
  legacy bind-output выполнены новая native Admin build и regression 17/17.
- Новый `scripts/test-admin-smoke-bootstrap.mjs` проверяет конфигурацию,
  deadlines, отдельные failures обоих bootstrap, mount isolation, настоящий
  clean startup и cleanup. Config gate добавлен в существующий Compose CI job.
  Optional Composer audit теперь передаёт свой nonzero exit вызывающему процессу.
- `.gitattributes` закрепляет LF для frontend text при auto-detection binary
  assets. Нормализованы 257 frontend text files, отдельно Admin manifest;
  production UI после CRLF→LF byte normalization полностью совпадает с HEAD.
  Форматирование `.tmp` исключено через Admin `.prettierignore`. Обычные
  `npm run format:check` проходят без `--end-of-line auto`.
- Девять непереносимых ссылок design QA заменены сохранёнными plain-text
  идентификаторами исторических локальных файлов. Добавлены ссылки на реальные
  tracked native эталоны и пояснение отсутствия macOS PNG/двух JPEG. Новые
  изображения не выдаются за исторические доказательства приёмки.

Config/mount regressions были RED до исправлений и GREEN после. Injected
backend exit 23 и Admin exit 24 блокируют соответствующие runtime services.
Первый clean cold run: 129.5 s; окончательный с изолированным dist: 102.9 s.
Backend bootstrap 88.2 s, Admin bootstrap 79.4 s — оба превысили прежние ~65 s,
но теперь старт прошёл автоматически. Real full-stack browser smoke — 1/1.
Исходный clean/stale dependency harness также прошёл.

## Итоговые проверки

| Проверка                                                   | Результат                                            |
| ---------------------------------------------------------- | ---------------------------------------------------- |
| Native backend, два полных SQLite прогона                  | 403/403 каждый, 5331 assertions                      |
| Backend targeted cleanup regressions                       | 28/28, 309 assertions; исходный RED сохранён         |
| Pint / PHPStan / architecture / Composer validate+platform | PASS                                                 |
| Admin clean install / lint / unit / build                  | PASS; 125/125 unit                                   |
| Admin полный Windows E2E                                   | 523/523, один полный запуск, без retries/skips/flaky |
| Native final build + palette/focus/scroll regression       | PASS + 17/17                                         |
| Real Nuxt preview final                                    | 24/24; также 24/24 до последнего Guard assertion     |
| Client clean install / typecheck / SSR build               | PASS                                                 |
| Client production / development E2E                        | 15/15 + 2/2                                          |
| Admin/Client обычные format gates                          | PASS                                                 |
| Cold bootstrap + failure propagation + real browser smoke  | PASS; browser 1/1                                    |
| Existing dependency clean/stale bootstrap                  | PASS                                                 |
| Repository hygiene                                         | PASS                                                 |
| Admin production audit                                     | PASS, 0                                              |
| Client production audit                                    | FAIL, 11 high из двух unpatched leaves               |
| Composer audit                                             | UNVERIFIED: Packagist curl 28, exit 100              |

## Файлы, доказательства и границы

Содержательные изменения: `backend/app/Support/XlsxReaderCleanup.php`, два
`ProductImportTemplate*` reader/validator services, три backend test files;
Admin `e2e-preview/contentPreview.spec.ts`; оба npm lock-файла; `compose.yaml`,
`scripts/test-admin-smoke-bootstrap.mjs`, `.github/workflows/ci.yml`;
`.gitattributes`, Admin `.prettierignore`, `design-qa.md`, этот отчёт/план,
`docs/CI.md`, `docs/CURRENT_STATE.md` и task status. Остальные frontend diffs
только CRLF→LF, включая manifest/config/source/docs; binary assets не изменялись.

Raw logs/JSON/PNG/RED evidence: `.tmp/windows-fixes-2026-10-14/`, включая
`final-browser-summary.json`, `repository/production-ui-eol-proof.json`,
dependency analysis/registry/audit JSON, backend JUnit/logs и infrastructure
timestamps/mount/final-inventory. Это ignored generated artifacts.

Root независимо подтвердил 0 containers/networks/volumes для проектов
`agatceramic-coldfix-20261014` и `agatceramic-dependency-smoke-local-1578`.
Development services/рабочая БД не затрагивались. Миграции выполнялись только
в test-only PostgreSQL; schema/API/migration source не менялись. Порог snapshots,
retries и утверждённые contrast exceptions сохранены; PNG не обновлялись.
macOS runner и полный Linux visual suite в этом follow-up не запускались.
Документы владельца о бэкапе сохранены. Проверки завершены до отдельного
поручения владельца создать коммиты и отправить изменения в репозиторий.
