# Orders redesign implementation plan

**Goal:** привести заказы к утверждённой странице обращений и удалить две отмеченные поясняющие строки из обращений.

**Architecture:** существующие Seller primitives, feature composables и API services. Вкладки статусов из API, компактные фильтры, таблица в контейнере с page-sticky header, правая панель заказа вместо деталей под списком. Сохраняются оплата, история, комментарии, permissions и deep links.

**Agreed statistics:** всего, новые, завершённые и отменённые заказы по дате поступления в выбранном московском календарном месяце; текущие статусы, сравнение с предыдущим месяцем, как в обращениях. Владелец выбрал эти показатели в чате. Фильтры списка не влияют на статистику. Нулевая база даёт «Нет базы», два нуля — 0%.

**Constraints:** утверждённая палитра и typography; без новых dependencies и миграций; не создавать commit. Работа в текущем checkout для проверки существующего localhost preview. Новая read-only статистика требует отдельного endpoint и синхронного OpenAPI. Не считать загруженную страницу всей выборкой.

## Tasks

- [x] Backend: tests first для `GET /admin/order-statistics?month=YYYY-MM`, разрешение orders.view, московские границы, текущие статусы, zero-base и validation. Тонкий controller/request/query по ContactStatistics. Обновить API.md/openapi.json, выполнить профильные backend/contract/static checks.
- [x] Frontend statistics: service/types/composable/компонент по ContactStatistics, защита от запоздалых ответов, retry/loading/zero, обновление после смены статуса. Удалить отмеченные строки обращений и устаревшее вычисление periodNote.
- [x] Orders workspace/list/details: вкладки от 1024 px и select ниже; поиск и оплата одной строкой на desktop; строка открывает drawer с исходными controls. Escape/outside close, focus return, dirty comment/payment/status confirmation и busy guard. Закрытие инвалидирует запрос деталей. Сохранить URL order и список/прокрутку.
- [x] Проверить meaningful unit/E2E: месяцы и stale responses, фильтры, drawer/deep links/dirty guards, payment/status/comment workflows, view-only, loading/error/empty, размеры 320/640/768/1024/1280 и отсутствие overflow. Проверить build/lint/format/unit, API и diff.
- [x] Независимый UI Design Guard: код и screenshots; устранить blocking findings. Обновить отчёт и tasks/DONE.md с фактическими результатами.

## Review focus

Поздний detail response после закрытия; несохранённая оплата/комментарий при Escape, browser back и смене заказа; мобильная панель и длинные данные; ошибка statistics не блокирует очередь; блокировка закрытия и стабильный focus при сохранении.

## Result

Реализация и независимое ревью завершены. Результаты и ограничения полной регрессии: [ADMIN_ORDERS_REDESIGN_2026-11-05.md](../../ADMIN_ORDERS_REDESIGN_2026-11-05.md). Коммиты не создавались.
