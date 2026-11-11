# Исправление Admin E2E CI для ae62a5e

В [CI run 42973045008](https://github.com/Dennis-Job/AgatCeramic/actions/runs/42973045008)
для `ae62a5e6bc895a0cbd07da845519e66de5ed4423` упал только job `Admin checks`,
шаг `Run Admin end-to-end tests`: 728 passed, 1 failed.
Остальные семь jobs прошли; Admin install/audit/lint/format/unit/build также PASS.

## Причина

В `appearance.spec.ts` сценарий переключения режима подменю после клика
«Сохранить черновик раздела» ожидал только disabled-кнопку. Но `useAppearance`
отключает её уже при `saving = true`, до завершения PATCH. Mock API ещё
не успевал записать payload, поэтому `api.patches[0]` оставался `undefined`.
В том же тесте второе сохранение использовало такое же ненадёжное ожидание.

20 обычных повторов исходного сценария локально прошли. Для проверки причины
в отдельной временной копии теста PATCH был удержан существующим deferred API:
запрос начался, кнопка была disabled, а исходный assertion детерминированно
упал с тем же `Received has value: undefined`, что и hosted CI.

## Изменение

Локальный helper `saveHeader` в этом сценарии явно удерживает каждое из двух
сохранений. Пока запрос заблокирован, проверяются disabled-кнопка и отсутствие
нового payload. Затем запрос освобождается, тест ждёт success status
«Черновик раздела сохранён.» и проверяет число записанных PATCH.
Проверки точного содержимого обоих payload, reload и публикации сохранены.

Production UI/код, API, БД, snapshots, retries и timeout-настройки не менялись.
Исправление касается только синхронизации теста; UI Design Guard не требуется.

## Проверка

Проверено в отдельной Linux-копии Admin на Node 24.19.0 с `npm ci` по текущему
lock-файлу; рабочие `dist` и `node_modules` не использовались для записи.

- Admin TypeScript/Vite build — PASS.
- ESLint без warnings и Prettier изменённого E2E файла — PASS.
- Исправленный сценарий с удержанием обоих PATCH: 20/20 PASS, 2 workers.
- Полный `appearance.spec.ts`: 28/28 PASS, 2 workers, включая axe и пять ширин.
- `git diff --check` — PASS.

Временный runner удалён после проверки. Полный набор из 729 Admin E2E локально
повторно не запускался: hosted run уже подтвердил остальные 728, а изменённый
сценарий и весь связанный spec проверены после исправления.
Коммит/push и новый hosted CI не выполнялись.
