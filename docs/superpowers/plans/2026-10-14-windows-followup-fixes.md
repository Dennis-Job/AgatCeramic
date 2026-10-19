# Windows follow-up: пункты 1, 3, 5 и 6

Основание: прямое поручение владельца от 2026-10-14 и список исправлений
после `WINDOWS_VERIFICATION_2026-10-13.md`. Утверждённые цвета, API и рабочие
данные сохраняются. Незакоммиченные документы о бэкапе БД не относятся к этой
задаче и должны быть сохранены. Коммит/отправка не входят в новый запрос.

## Независимые блоки

1. Backend: воспроизвести шесть Windows XLSX ошибок, добавить проверку
   немедленного удаления после success/early return/exception, закрыть ресурсы
   без forced GC. Зафиксировать время GuestCartTest. Проверить профильные и
   полный native SQLite набор, форматирование и статический анализ.
2. Dependencies: обновить совместимые npm locks Admin/Client по фактическим
   advisory chains, без force/ослабления audit. Проверить clean install,
   production audit, сборки и тесты; непатченные upstream advisory явно указать.
3. Docker: отделить cold bootstrap от HTTP health, сохранить safety guards и
   ограничить длительность bootstrap. Проверить чистые отдельные volumes,
   успешный старт и блокировку runtime при failure; очистить только свой project.
4. UI/test harness: проверить исправленный counts/layout race шапки, причины
   focus/preview timeout по logs/traces. Разделить длинные матрицы на независимые
   cases при сохранении assertions и timeout. Production UI менять только при
   доказанном дефекте; результат проверить независимым UI Design Guard.
5. Repository checks: согласовать LF в checkout/formatter, исключить `.tmp`,
   исправить недоступные локальные ссылки исторического design QA без подмены
   прежних доказательств. Выполнить обычные format gates и repository hygiene.

## Владение файлами и порядок

Backend, npm manifests/locks и Compose/scripts реализуются отдельными агентами;
основной агент владеет UI tests, formatting policy, design-qa и документацией.
Никаких одновременных изменений одного файла. Browser verification проводится
после завершения npm installs/builds. Docker использует отдельный test project.
Root проверяет diff, результаты и независимое review перед сдачей.

## Итоговая проверка

Native backend full suite; Admin lint/format/unit/build/full E2E; Client
format/typecheck/build/production/dev E2E; real Nuxt preview; audit обоих npm
проектов; cold full-stack и dependency bootstrap; repository hygiene,
`git diff --check`, отсутствие изменений palette/API/migrations и чужих данных.
Исторические результаты не переписываются. Новые результаты, ограничения,
изменённые файлы и cleanup записываются в отдельный отчёт и tasks.
