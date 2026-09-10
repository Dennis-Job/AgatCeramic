# Backend quality baseline

## Статус TASK-A007 (2026-09-10)

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

## Результат final run

| Проверка | Результат |
| --- | --- |
| PHP syntax (`app`, `config`, `database`, `routes`) | 321 files, 0 errors |
| Composer manifest | valid (`composer validate --strict`) |
| Composer security audit | 0 advisories (`composer audit --locked`) |
| Larastan/PHPStan level 8 | 333 files, 0 errors (`composer analyse`) |
| Backend test suite | 232 passed, 3,308 assertions (`php artisan test --compact`) |

`composer analyse` добавлен как блокирующий шаг backend job в
[`../.github/workflows/ci.yml`](../.github/workflows/ci.yml). Baseline-файл,
`ignoreErrors` и inline-подавления по-прежнему не используются.

## Закрытие

Критерии TASK-A007 выполнены: статический анализ проходит с exit code 0 на указанной
конфигурации и является blocking CI check. Публичный API при type-corrections не менялся;
документация OpenAPI остаётся актуальной.
