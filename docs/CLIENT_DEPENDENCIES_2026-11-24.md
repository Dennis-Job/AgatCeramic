# Повторная проверка зависимостей Client — 2026-11-24

## Область и результат

Работа продолжает [проверку 2026-11-10](CI_FOLLOWUP_2026-11-09.md).
Согласованное переопределение для `archiver@8.0.0` закрывает оставшуюся
ветку `minimatch`. Полный и production audit после чистой установки показывают
0 уязвимостей. История проверки и условия снятия переопределения приведены ниже.

На первом этапе исправление glob было внесено только в
`frontend/client/package-lock.json`. Nuxt 4.6.4, Nitro 2.13.6, прямые
зависимости, Node engines, конфигурация и интерфейс сохраняются.

Audit исходного lock на 2026-11-24: 10 moderate, 0 low/high/critical.
Полный и production audit после первого этапа: 8 moderate, 0 low/high/critical.
Это число затронутых узлов с зависимыми пакетами, а не восемь независимых
первопричин. Полный `npm audit` возвращает 1; production-проверка с порогом
high возвращает 0. Эти результаты относятся к дереву до переопределения.

## Upstream и минимальное обновление

| Цепочка | Проверенный результат |
| --- | --- |
| deepmerge 4.3.1 | [GHSA-gcqx-w48m-w2h4](https://github.com/advisories/GHSA-gcqx-w48m-w2h4) отозван 2026-11-16: advisory не описывает security vulnerability. Пакет не обновляется и не исключается из audit вручную. |
| Nitro → @vercel/nft → glob | Nitro 2.13.6 разрешает `@vercel/nft ^1.11.0`. Обновление 1.11.1 → 1.11.12 меняет upstream range на `glob ^14.0.6`: glob 13.0.7 → 14.0.6, его minimatch → 11.0.9, path-scurry 2.0.2 → 3.0.0. Новый minimatch требует `@isaacs/nonrecursive-glob-match 1.1.0`. |
| Nitro → archiver 8.0.0 → readdir-glob 3.0.0 → minimatch 10.2.6 | Эта ветка остаётся affected. Последний опубликованный archiver 8.0.0 требует `readdir-glob ^3.0.0`, который требует `minimatch ^10.2.2`. Исправленный readdir-glob 4.0.0 использует `minimatch ^11.0.1`, но находится вне разрешённого диапазона archiver. |

[Glob advisory](https://github.com/advisories/GHSA-3g3m-2fjr-6pw4)
указывает исправление от 14.0.2;
[minimatch advisory](https://github.com/advisories/GHSA-g3hv-75wg-m249) — от 11.0.1.
Опубликованные metadata проверяются через `npm view`; исходники родителей:
[@vercel/nft](https://github.com/vercel/nft/blob/main/package.json),
[archiver](https://github.com/archiverjs/node-archiver/blob/master/package.json).

Появившийся Nuxt 4.7.0 и Nitro 2.14.0 не решают ограничение archiver:
последний Nitro также требует `archiver ^8.0.0`.
Экспериментальный Nitro v3 из
[release notes Nuxt 4.7.0](https://github.com/nuxt/nuxt/releases/tag/v4.7.0)
не включается. Предложенный audit downgrade Nuxt до 4.6.3 не применяется.
На первом этапе не применяются overrides, suppressions, force, перенос
runtime dependencies в dev и изменение порога high.

## Воспроизведение

Копии manifest/lock в игнорируемой `.tmp` позволяют проверить минимальный
candidate без изменения установленного дерева:

```powershell
npm update @vercel/nft --package-lock-only --ignore-scripts --no-fund
npm audit --package-lock-only --json
```

Diff candidate затрагивает ровно пять записей пакетов: три обновления и две
новые записи. Существующие platform optional entries сохраняются. Candidate
lock переносится в Client, затем выполняются штатные проверки приложения.

## Проверки

- Чистый `npm ci --no-fund` на Windows, Node 24.19.0 — PASS.
- `npm ls --depth=0`, дерево deepmerge/glob/minimatch/nft/path-scurry — PASS.
- Полный audit — 8 moderate, exit 1; production audit с порогом high — PASS.
- Client typecheck, format check и SSR build — PASS.
- Node tests выбора/подписей вариантов товара — 7 PASS.
- Production E2E: 229 PASS / 1 FAIL. Падает клавиатурный выбор бренда в
  `catalog-brand-scope.spec.ts:48` на ожидании URL с `brand=atlas`.
  Первый запуск с ранней остановкой: 51 PASS / 1 FAIL / 178 не запущены;
  отдельный неизменённый сценарий проходит три повтора подряд (3/3).
  Полный повтор без ранней остановки снова выявляет тот же сбой.
- Для сравнения собрана отдельная копия исходного HEAD
  `7e10c241a579d504b94a4a86dc698a1700394e01` со старым lock:
  все 71 клиентский JS/CSS-файл совпадают побайтно с новой сборкой.
  На той же последовательности baseline E2E повторяет идентичную ошибку:
  51 PASS / 1 FAIL / 6 не запущены. Сбой предшествует dependency diff.
  Исправление и повторная приёмка описаны ниже.
- Dev E2E — 2 PASS (19.5 s).
- Независимое dependency review — блокирующих P1/P2/P3 нет: metadata,
  integrity, ranges, engines и все 80 optional entries проверены отдельно.
- При первом прогоне repository hygiene выявляет четыре ошибки проверки
  ссылок на UI-kit и три отслеживаемых `.pyc`. Повторная приёмка ниже.

Браузерные проверки используют существующий установленный Chromium через
временные конфигурации в `.tmp` с неизменными assertions. Запуск вне Windows
sandbox необходим для loopback и завершения локальных тестовых серверов.
Синтетический API не обращается к рабочей БД. Локальные logs/audit JSON и
Playwright artifacts находятся в `.tmp/client-dependency-followup-20261124/`.

## Исправление ошибок проверки

Тест выбора бренда ждёт появления фильтра «Цвет» для категории «Плитка» и
доступного варианта Atlas в раскрытом списке. В тестовой фикстуре цвет и
бренды приходят одним ответом. Клавиатурный выбор, URL, фокус и история
по-прежнему проверяются. Последовательность, в которой возникал сбой,
проходит 58/58 сценариев. Повторный полный production E2E проходит 230/230,
dev E2E — 2/2. Повторные typecheck, format check и 7 Node-тестов проходят.

Проверка ссылок сохраняет по одному дефису на каждый пробел, как GitHub.
Это устраняет четыре ложные ошибки для заголовков с тире и косой чертой.
Три сгенерированных `.pyc` исключены из Git, `__pycache__/` добавлен в
`.gitignore`. Полный repository hygiene проходит.

## Закрытие ветки minimatch

Повторная сверка npm подтверждает, что стабильные Nuxt 4.7.0 и Nitro 2.14.0
по-прежнему приводят к `archiver 8.0.0` с диапазоном `readdir-glob ^3.0.0`.
Владелец разрешает единственное переопределение: `archiver@8.0.0` использует
`readdir-glob 4.0.0`. Обе ветки, `glob` и `readdir-glob`, разрешаются на
`minimatch 11.0.9`. В lock-файле обновляются `readdir-glob` и верхний
`minimatch`, а одинаковая вложенная копия `minimatch` удаляется при дедупликации.
Порог CI остаётся high. [Релиз readdir-glob 4.0.0](https://github.com/Yqnn/node-readdir-glob/releases/tag/v4.0.0).

Чистый `npm ci`, `npm ls`, полный и production audit проходят. У обоих audit
0 уязвимостей. `archiver@8.0.0` создаёт ZIP через `glob()` и `directory()`.
Оба архива проверены по именам, содержимому и CRC файлов. Typecheck, Prettier,
SSR build и 7 Node-тестов проходят.
Повторный production E2E после переопределения проходит 230/230, dev E2E —
2/2. Независимое ревью подтверждает область действия селектора, диапазоны
Node, данные npm registry и отсутствие посторонних изменений lock-файла.
Проверка репозитория и `git diff --check` проходят.

При смене версии `archiver` селектор `archiver@8.0.0` перестаёт действовать.
При очередном обновлении `archiver` проверяется его диапазон `readdir-glob`.
Когда нативная цепочка устанавливает исправленный `minimatch`, точечное
переопределение удаляется. Удаление подтверждают `npm ci`, `npm ls`, audit,
повтор ZIP-проверки, SSR build и E2E.
