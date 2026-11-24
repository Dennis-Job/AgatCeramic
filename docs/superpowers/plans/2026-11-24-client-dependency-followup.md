# Проверка upstream-исправлений Client

**Цель:** применить доступные поддерживаемые исправления из задачи ожидания
и точно зафиксировать оставшиеся ограничения.

**Основание:** [задача ожидания](../../../tasks/TODO.md),
[предыдущая проверка](../../CI_FOLLOWUP_2026-11-09.md),
[правила CI](../../CI.md).

**Подход:** сначала проверить candidate lock в игнорируемой `.tmp`, затем
перенести только проверенное изменение в `frontend/client/package-lock.json`.
Nuxt и прямые зависимости сохраняются, если транзитивного обновления достаточно.

## Ограничения

- Порог audit — high; без overrides, suppressions, force и downgrade.
- Без смены server engine, API, схемы БД, интерфейса и unrelated dependencies.
- Результаты записываются в существующую таблицу DONE; нерешённая часть
  остаётся в TODO с конкретной upstream-причиной. Коммит не создаётся.

## Шаги

- [x] Сверить исходный audit, установленные версии, upstream advisories и ranges.
- [x] Проверить минимальное обновление `@vercel/nft` и его glob/minimatch
  в candidate lock; проверить diff и повторный audit.
- [x] Перенести candidate lock, выполнить clean install, `npm ls`, полный audit
  и production audit с порогом high.
- [x] Выполнить Client typecheck, format check, SSR build и релевантные
  production/dev E2E на синтетическом API.
- [x] Пройти независимое ревью dependency diff и результатов, обновить
  TODO/IN_PROGRESS/DONE и отчёт; проверить ссылки и `git diff --check`.

## Фокус проверки

- Manifest/lock согласованы; прямые версии и Node engines сохранены.
- Исправленный glob разрешён диапазоном опубликованного родителя.
- Отсутствуют обходы audit и ошибочное заявление об устранении minimatch
  в другой ветке дерева.
- SSR tracing включает runtime-зависимости; API/SEO, hydration и preview
  сохраняют поведение по существующим E2E.
- Clean install воспроизводит итоговый lock на Windows; platform optional
  entries и integrity не удаляются вручную.

## Решения и проверки

- Это maintenance существующей задачи. Candidate проверяется отдельно в `.tmp`,
  проверенный узкий diff переносится в текущий checkout без создания ветки/коммита.
- Регрессионный сигнал — текущие npm audit и существующие проверки приложения;
  отдельный тест, повторяющий advisory database, не добавляется.
- Чистая установка, npm ls, typecheck, format, SSR build и 7 Node tests — PASS.
- Независимое dependency review проверяет live registry metadata, integrity,
  ranges, engines, все 80 optional entries и источники; блокирующих findings нет.
- Полный production E2E: 229 PASS / 1 FAIL. Та же ошибка клавиатурного выбора
  бренда воспроизведена со старым lock в отдельной копии исходного HEAD
  (51 PASS / 1 FAIL в начальной последовательности); 71 JS/CSS-файл совпадает
  побайтно. Независимый одиночный повтор — 3/3 PASS. Затем E2E синхронизирован
  с загрузкой вариантов. Повторные production 230/230 и dev 2/2 проходят.
- Владелец согласует точечное исключение для `archiver@8.0.0`: `readdir-glob`
  4.0.0 и `minimatch` 11.0.9. Чистая установка, полный и production audit
  (0 уязвимостей), два ZIP-сценария, typecheck, format и SSR build проходят.
  Итоговые проверки после исключения и условия его снятия фиксируются в
  [отчёте](../../CLIENT_DEPENDENCIES_2026-11-24.md).
