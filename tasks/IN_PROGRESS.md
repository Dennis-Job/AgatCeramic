# IN PROGRESS

- Client dependency audit, оставшаяся часть пункта 5 Windows follow-up:
  11 high findings из braces/node-forge без опубликованных patched releases
  на 2026-10-14. Совместимые lock updates убрали critical/moderate; audit gate
  сохраняет failure до поддерживаемого исправления. Composer audit требуется
  повторить после восстановления доступа к Packagist (curl 28 / exit 100).
  [Отчёт и источники](../docs/WINDOWS_FIXES_2026-10-14.md).
