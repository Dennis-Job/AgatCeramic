# Утверждённая палитра: межплатформенные проверки

2026-10-13 владелец подтвердил все текущие цвета, утверждённые при работе на
macOS, и поручил применять те же значения на Windows. Источник оформления —
общие Admin tokens и компоненты, без отдельной Windows-палитры.

## Контракт

`frontend/admin/e2e/contracts/approvedPalette.ts` хранит 99 фиксированных
значений цветов, включая semantic aliases, focus/halo и backdrop. Это
независимый ожидаемый набор: тесты и обновление screenshot baseline его не
перегенерируют. Browser guard сравнивает фактические CSS colors с литералами;
эквивалентные записи `#fff`, `#ffffff` и `rgb()` нормализуются браузером.

`approvedPalette.spec.ts` проверяет на 320/1280 px реальные default/hover/
disabled/focus цвета кнопок и шесть tones бейджей. Отдельная mutation-проверка
сначала подтверждает исходный контракт, затем меняет primary token и требует,
чтобы guard отверг изменение. Unit-тесты проверяют fail-closed contrast policy.

## Известные контрастные ограничения

Утверждение цветов не означает соответствие WCAG AA. `approvedAccessibility.ts`
выполняет axe scan без отключения `color-contrast`; перед каждым scan проверяет
все утверждённые цветовые tokens. Только следующие точные пары вынесены в
известные owner-approved findings:

| Текст     | Фон       | Применение                                |
| --------- | --------- | ----------------------------------------- |
| `#12b76a` | `#ecfdf3` | Success badge/feedback                    |
| `#0ba5ec` | `#f0f9ff` | Additional badge/feedback                 |
| `#ffffff` | `#f04438` | Danger button                             |
| `#f04438` | `#ffffff` | Danger ghost/error text                   |
| `#f79009` | `#fffaeb` | Warning badge/feedback                    |
| `#f04438` | `#fef3f2` | Danger badge/feedback                     |
| `#0ba5ec` | `#e0f2fe` | Soft-blue button/feedback                 |
| `#0ba5ec` | `#f2f4f7` | Inline code in additional import feedback |
| `#667085` | `#ebf4ff` | Selected pale-blue controls               |
| `#667085` | `#d9eaff` | Active/hover navigation and import tabs   |

Raw violations и принятые nodes сохраняются в JSON attachment
`owner-approved-contrast-findings`, тест получает соответствующую annotation.
В `approvedContrastViolations` остаются доступны утверждённые findings для
точных проверок конкретных targets. Новые пары, mixed-node нарушения,
неполные данные и все остальные axe rules продолжают проваливать тесты.
Это явные исключения приёмки палитры, а не blanket отключение доступности.

## Платформенные эталоны и CI

Windows/Linux/macOS используют собственные PNG: platform suffix сохраняется.
Часовой пояс тестового браузера фиксирован как `Europe/Moscow`, чтобы даты
fixtures не менялись между Windows host и UTC CI runner.
Запрещено подменять Windows эталоны Darwin/Linux снимками. Actual PNG сначала
проходят независимый UI Design Guard; затем принимаются и повторно проверяются
с `updateSnapshots: none` и прежним пределом 30 px. Palette contract сравнивает
точные значения независимо от screenshot tolerance.

Windows PNG считаются эталонами hosted GitHub Windows runner. Desktop Windows
может отличаться системным monospace fallback и нативным placeholder поля даты
даже при той же locale; совпадение всех пикселей между этими средами не обещается.
Для `/products`, `/roles`, `/permissions`, `/settings` приняты отдельно
проверенные UI Design Guard hosted actuals из run 39253140410. Источник,
bounding boxes и ограничение локального запуска описаны в
[отчёте PR #5](CI_PR5_REPAIR_2026-10-19.md#hosted-windows-follow-up).
Из-за этих четырёх различий desktop screenshot failure сам по себе не доказывает
регрессию hosted CI; сравнивать actual/diff и проверять canonical workflow.

Основной Linux CI включает новый palette spec в полный E2E. Workflow
`.github/workflows/admin-windows-ui.yml` отдельно выполняет native Windows
unit/build, palette spec и все `adminBaseline.spec.ts` проверки. Изменение
GitHub workflow само по себе не является доказательством успешного remote run.

```powershell
cd frontend/admin
npm.cmd run test:unit -- --maxWorkers=2
npm.cmd run build
npm.cmd exec playwright test -- approvedPalette.spec.ts adminBaseline.spec.ts --workers=2
```

Для смены утверждённой палитры требуются прямое указание владельца, обновление
литерального контракта и UI Design Guard до принятия новых snapshots.
