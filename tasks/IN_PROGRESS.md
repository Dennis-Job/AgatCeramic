# IN PROGRESS

- CI follow-up 2026-11-10: локальные исправления готовы. Устаревший contact
  smoke исправлен, real-stack GREEN 1/1; независимо приняты 6 Windows и 2 Linux
  hosted PNG утверждённых UI-изменений. Строгие Linux tests /orders и /settings
  PASS 2/2. Client Nuxt 4.6.4: clean install, typecheck/build/format, production
  E2E 135/135, dev E2E 2/2, Node tests 7/7 PASS. Code review и UI Guard без P1/P2.
  Осталось: публикация текущего diff и новый hosted Linux/Windows CI; без этого
  remote gate не считается пройденным. Commit/push текущих изменений не выполнены.
  Старое ожидание PR #5 закрыто фактическим успешным Windows run 39254274462.
  [Полный отчёт, файлы и evidence](../docs/CI_FOLLOWUP_2026-11-09.md).
- Client dependency upstream follow-up: старые 11 high больше не воспроизводятся;
  audit `--omit=dev --audit-level=high` PASS (0 high/critical). После security
  update Nuxt/Nitro остаются 6 moderate / 6 low findings deepmerge/glob/minimatch
  и зависимых узлов. Совместимый audit fix их не устраняет; требуются upstream
  releases/range updates. Порог high сохранён, suppressions/overrides/force и
  downgrade не применялись. [Причины и источники](../docs/CI_FOLLOWUP_2026-11-09.md).
