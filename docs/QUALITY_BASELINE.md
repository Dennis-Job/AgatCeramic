# Backend quality baseline

## Статус TASK-A007 (2026-09-09)

Laravel backend получил воспроизводимую конфигурацию Larastan/PHPStan 2.2.13 с
Larastan 3.10.0: [`../backend/phpstan.neon`](../backend/phpstan.neon). Она анализирует
`app`, `config`, `database` и `routes` на уровне 8 с проверками explicit/implicit
`mixed`, benevolent union types, чрезмерно широких public/protected return types и
отсутствующего `#[Override]`. Baseline-файл, `ignoreErrors` и inline-подавления не
используются.

Команда для локального и CI-использования:

```powershell
cd backend
composer analyse
```

## Результат initial run

| Проверка | Результат |
| --- | --- |
| PHP syntax (`app`, `config`, `database`, `routes`) | 321 files, 0 errors |
| Composer manifest | valid (`composer validate --strict`) |
| Composer security audit | 0 advisories (`composer audit --locked`) |
| Larastan/PHPStan level 8 | more than 1,000 errors; PHPStan stops its table output at the first 1,000 |

Нельзя добавить `composer analyse` как блокирующий CI step до устранения ошибок: это
сделает все pull request заведомо красными. Нельзя также сделать его зелёным через
baseline или ignore rules — это скроет уже обнаруженные нарушения и противоречит
TASK-A007.

## Направление исправлений

Анализ подтверждает, что работа разбивается по границам следующих задач, без
необоснованного переписывания кода в A007:

| Владелец | Наблюдения |
| --- | --- |
| TASK-A008 | `Auth::user()` остаётся nullable в контроллерах; query parameters и pagination поступают как `mixed`; callback signatures в `when()` не соответствуют inferred types. |
| TASK-A009 | Сервисы импортов/экспортов передают неуточнённые workbook rows и collections; требуются явные DTO/shape contracts и границы преобразования external input. |
| TASK-A010 | Eloquent relations, casts, pivots и factory generics нуждаются в корректных PHPDoc/type declarations. |
| TASK-A013 | Очереди/import lifecycle следует проверить отдельно; ошибки статического анализа в import services не должны исправляться удалением существующей обработки ошибок или retry semantics. |

Отдельно, даже level 0 при включённой проверке `#[Override]` находит 114 отсутствующих
атрибутов в Form Requests, API Resources, моделях, factory и provider. Это безопасная,
но массовая механическая правка, которую целесообразно включить в общий набор
type-corrections после согласования последовательности A008–A010.

## Критерий закрытия

TASK-A007 может быть закрыта только после `composer analyse` с exit code 0 на этой
конфигурации и добавления этой команды в backend job CI. Любое изменение публичного API
при исправлении типов требует синхронного обновления OpenAPI согласно TASK-A008/A014.
