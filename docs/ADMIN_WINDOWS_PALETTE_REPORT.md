# Утверждённая палитра macOS на Windows — 2026-10-13

Выполнены пункты 2 и 4 [Windows-проверки](WINDOWS_VERIFICATION_2026-10-13.md)
по прямому указанию владельца: все текущие цвета, утверждённые на macOS,
сохранены и стали проверяемым контрактом Windows/Linux. Базовый commit —
`5d7b58b`; предыдущие незакоммиченные отчёты сохранены.

## Изменения

- Production palette, CSS tokens, UI primitives и бизнес-поведение сохранены.
  В `UiKitShowcase.vue` дополнены inventory, пять dialog tokens и демонстрация
  существующего `UiProgressBar` без нового визуального паттерна.
- Исправлены пять старых unit expectations, CSS ожидания hover/focus/badge,
  названия кнопок/regions и селекторы прежней `aside`-разметки.
- Заморожены 99 цветовых значений в `e2e/contracts/approvedPalette.ts`.
  Проверяются реальные computed styles на 320/1280 px и отказ при намеренной
  подмене primary token. Snapshot update не меняет этот контракт.
- Все Admin axe consumers используют общий guard. Удалены blanket исключения
  `color-contrast`; десять точных текущих пар цветов документированы как
  owner-approved ограничения. Raw findings сохраняются в attachments.
  Новые пары, mixed nodes и остальные axe rules остаются blocking.
- Длинные responsive loops разделены на независимые viewport tests: лимит
  30 секунд и retries не увеличены, прежние assertions сохранены. Итоговый
  набор содержит 523 E2E; каждая ширина теперь достигается независимо.
- Уведомления удерживаются реальным пользовательским фокусом; stack audit
  проверяет именно живой stack и требует пять элементов после axe.
  Photo editor request assertion начинается после initial filter-counts load.
  Контраст открытого select измеряется после достижения точного утверждённого
  hover-цвета, без добавления промежуточных кадров анимации в exceptions.
- Добавлен native Windows workflow `admin-windows-ui.yml`: clean install,
  unit/build, Chromium palette и все baseline проверки. Test browser timezone
  фиксирован как `Europe/Moscow`, независимо от timezone CI host.

Контракт и точные ограничения: [ADMIN_APPROVED_PALETTE_TESTS.md](ADMIN_APPROVED_PALETTE_TESTS.md).

## Независимый UI Design Guard

Приняты 70 Windows кандидатов: 65 существовавших actual PNG и пять отдельных
кандидатов products-error/media. Из них четыре media Windows PNG отсутствовали
раньше. Приняты также 66 native Linux actual PNG и все восемь новых UI-kit crops
на 320/1280 px. Windows и Linux изображения не подменяют друг друга;
Darwin PNG не менялись.

Ревью охватило surfaces, формы, header, auth, dialogs, populated/loading/error/
empty, узкие controls, progress states и тестовый contrast policy. Blocking
findings устранены. Known contrast сохранён по прямому указанию владельца;
это не заявление о полном WCAG AA compliance.

## Проверки

| Проверка                                                        | Результат                                                                    |
| --------------------------------------------------------------- | ---------------------------------------------------------------------------- |
| Windows unit                                                    | 125/125 PASS                                                                 |
| Windows ESLint                                                  | PASS                                                                         |
| Windows TypeScript/Vite build                                   | PASS                                                                         |
| Дополнительный typecheck palette/E2E helpers и изменённых specs | PASS                                                                         |
| Scoped Prettier, 40 изменённых/новых TS/Vue файлов              | PASS по настройкам проекта                                                   |
| Windows workflow YAML                                           | Синтаксис проверен Prettier; remote job ещё не запускался                    |
| Windows полный strict E2E                                       | 521 PASS / 2 FAIL из 523 до последних двух fixes                             |
| Windows последние исправленные группы                           | 10/10 PASS, оба сбоя закрыты                                                 |
| Windows покрытие окончательного набора                          | 513 неизменённых PASS + 10 повторно проверенных PASS = 523 сценария          |
| Windows/Linux palette + baseline subset                         | 230/230 PASS на каждой ОС                                                    |
| Linux clean install / unit / build                              | PASS, 125/125 unit                                                           |
| Linux palette, 320/1280 + tamper guard                          | 3/3 PASS                                                                     |
| Linux полный strict E2E                                         | 514 PASS / 2 FAIL из 516 до первых synchronization fixes; все snapshots PASS |
| Linux повтор затронутых групп                                   | 59/59 PASS, оба прежних сбоя закрыты                                         |

Windows полный прогон после последних двух локальных test fixes отдельно не
повторялся: заключительный повтор охватил все десять cases двух изменённых
specs, остальные 513 прошли в полном прогоне. Это не единый зелёный запуск
523/523. Предшествующий Windows повтор четырёх других групп дал 59/59 PASS.
Linux также проверялся полным и отдельным targeted запуском; последние
ожидания readiness в `scrollHeader`/`slider-blocks` после его cleanup проверены
на Windows. Production UI от этих ожиданий не меняется.

Первичный Windows collection: 280 PASS / 73 FAIL из 353 до принятия baseline
и исправления оставшихся stale selectors/длинных loops. Первый Linux collection:
284 PASS / 69 FAIL из 353; его исходные logs сохранены. Прерванный Windows
516-прогон не объявляется завершённым. Следующий Windows прогон дал 512 PASS /
4 FAIL: промежуточный hover-кадр axe, общий budget восьми scroll widths,
поздний initial filter-counts request в photo assertion и единичный
`ERR_NO_BUFFER_SPACE` при загрузке шрифта. Первые три причины исправлены
в тестовой синхронизации/структуре; navigation без изменений прошла в повторной
проверке всей группы. Linux 514/516 выявил только hover и общий scroll budget.
Следующий полный Windows прогон 523 выявил ещё две гонки: измерение геометрии
до ответа initial counts (713→667 px на 320 px) и axe во время disabled→enabled
перехода кнопки удаления (`#c9514e` — промежуточный цвет). В scroll fixture
добавлен валидный counts response и ожидание доступного описания счётчика;
перед slider axe проверяется enabled и точный утверждённый конечный красный.
Guard одобрил оба изменения, повтор двух файлов дал 10/10 PASS.
Оригинальные результаты сохранены; retries остаётся 0.

Локальные доказательства: `.tmp/windows-palette-2026-10-13/`, включая
`adopted-windows-baselines.json`, `linux/native-adoption-manifest.json`,
Windows/Linux JSON reports, PNG, traces, unit/lint/build logs и guard galleries.
Generated артефакты не добавляются в Git. Финальный Windows JSON:
`windows-complete.json`, повтор: `windows-timing-regression.json`, подсчёт
без двойного учёта: `windows-final-coverage.json`. Смена CRLF→LF при приведении
к default Prettier не меняла содержимое 40 файлов после нормализации:
`format-normalization-proof.json`.

Изолированный Linux project `agatceramic-palette-linux-20261013` очищен:
`linux/cleanup-verified.json` подтверждает 0 собственных containers, volumes,
networks и image tags. Рабочие сервисы и данные не затрагивались.

## Изменённые файлы

- `frontend/admin/src/components/shared/UiKitShowcase.vue` — полнота каталога
  существующих primitives/tokens и примеры progress.
- `frontend/admin/tests/{authCard,catalogComponents,uiBadge,uiKitShowcase}.test.ts`
  и новый `approvedContrast.test.ts` — актуальные expectations и защита policy.
- `frontend/admin/e2e/contracts/{approvedPalette,approvedContrast}.ts`,
  `approvedAccessibility.ts`, `approvedPalette.spec.ts` — независимый контракт,
  computed CSS, mutation guard и сохранение raw axe findings.
- Admin `e2e/*.spec.ts` — подключение guard, текущие selectors/цвета,
  синхронизация и независимые viewport cases; `deferredApi.ts` — тип callback.
- `adminBaseline.spec.ts-snapshots/*-chromium-{win32,linux}.png` — только
  проверенные независимым Guard native actuals; SHA manifests совпадают со
  всеми 136 принятыми файлами.
- `frontend/admin/playwright.config.ts`, `.github/workflows/admin-windows-ui.yml`
  — детерминированные даты и Windows CI gate.
- `docs/{ADMIN_APPROVED_PALETTE_TESTS,ADMIN_WINDOWS_PALETTE_REPORT,CI,CURRENT_STATE}.md`,
  `tasks/{IN_PROGRESS,DONE}.md` — политика, доказательства и статус.

## Границы

Backend, migrations, API, dependency lock-файлы, аудит уязвимостей и рабочие
данные не изменялись. macOS runner в этой сессии не использовался: источником
утверждения macOS-стилей служит прямое указание владельца и текущий shared UI.
Darwin snapshots нельзя считать перепроверенными этим запуском.

Исправления backend XLSX, dependency audit и старых repository links остаются
вне выбранных пунктов 2 и 4. Коммит и публикация не выполнялись.
