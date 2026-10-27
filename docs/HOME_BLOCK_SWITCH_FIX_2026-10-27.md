# Исправление переключения блоков главной

Дата: 2026-10-27. Сценарий: Admin `/content?page=home`, переключение между
настройками блоков, в том числе «Материалы» и «О проекте».

Причина: API допускает `image_url: null` (контракт `docs/openapi.json`), а общий
`UiInput` ожидал строку и вычислял `modelValue.length`. Монтирование поля
«Локальное изображение», даже внутри закрытого `details`, вызывало исключение.
Последующие ошибки Vue `vnode`/`subTree` были каскадом после сбоя рендера.

`UiInput` теперь принимает `string | null`, использует пустую строку для
отображения, определения заполненности и валидации. Эта нормализация не
эмитит изменение: неизменённый `null` остаётся в черновике и payload, открытие
блока не включает dirty. Ввод и очистка по-прежнему возвращают строку.
Тип `HomeImage.image_url` приведён к существующему nullable API-контракту.
Утверждённые стили, permissions, backend, API и миграции не изменялись.

Отдельное сообщение `401` из присланного лога не содержит URL/initiator.
Оно не доказывает связь с падением поля. Гостевой `/admin/auth/me` до входа
может отвечать 401; при переключении блока возможен запрос медиатеки, а
Nuxt-preview независимо проверяет сессию. Ошибки этих запросов обрабатываются
отдельно. Изменять аутентификацию без подтверждения источника оснований нет.

## Файлы

- `frontend/admin/src/components/ui/UiInput.vue` — общий nullable input.
- `frontend/admin/src/features/homepage/types/homepage.types.ts` — nullable URL.
- `frontend/admin/tests/uiInput.test.ts` — текстовый, телефонный и денежный
  режимы: пустое API-значение, отсутствие ложного изменения, ввод и очистка.
- `frontend/admin/e2e/content-blocks.spec.ts` — повторное переключение блоков
  с nullable URL, сохранение правок, перезагрузка, responsive и axe.
- `frontend/admin/src/components/shared/UI_KIT.md` — контракт поля.
- Этот отчёт и `tasks/DONE.md` — результаты и статус задачи.

## Проверки

- До исправления unit-тесты воспроизвели `.length`/`.trim` на `null`,
  браузерная регрессия — `null.length` в production-сборке.
- После исправления: 50 unit-файлов / 252 теста PASS, production build PASS,
  lint и format:check PASS. Сохранилось предупреждение о bundle >500 kB.
- Браузерная регрессия PASS: 320/640/768/1024/1280 px, без page overflow,
  ошибок console/runtime и axe violations. Preview в этом тесте — HTML fixture;
  эта задача не утверждает проверку реального Nuxt этим сценарием.
- UI Design Guard — accepted после устранения замечания: blur/Enter пустого
  nullable money-поля теперь также не создаёт изменение. Замечание сначала
  воспроизведено тестом, затем 28 профильных и все 252 unit-теста PASS.
  Ревьюер лично просмотрел все пять responsive screenshots.
- Полный Admin E2E/visual: 681 сценарий, 673 PASS / 8 FAIL (только baseline).
  Прогон выполнен перед дополнительной правкой сравнения в money commit;
  итоговая production-сборка PASS, повторные профильные E2E — 13/13 PASS
  (`content-blocks`, `homepage`, `money-input`). Эта проверка выполнена на
  окончательном коде после замечания ревью.
  Восемь Windows visual baseline расхождений воспроизводятся с исходным
  `UiInput` из HEAD в отдельной контрольной сборке: `/products`, `/roles`,
  `/permissions`, `/media`, `/settings`, а также media loading/empty/error.
  Для всех восьми сценариев actual PNG до/после исправления побайтно идентичны
  (SHA-256 в `.tmp/home-block-null/baseline-comparison.json`). Эти несоответствия
  baseline не вызваны данной правкой; эталоны не перезаписывались.

Evidence: `.tmp/home-block-null/regression/`,
`.tmp/home-block-null/full-e2e/`, `.tmp/home-block-null/full-e2e.log`,
`.tmp/home-block-null/final-e2e/`, `.tmp/home-block-null/final-e2e.log`.
Контрольная сборка: `.tmp/home-block-null/original-dist/`,
`original-baseline.log`, `original-media-states.log`, `baseline-comparison.json`.
Visual baseline не обновлялся. Реальный контент не сохранялся и не публиковался.
Commit не создавался.
