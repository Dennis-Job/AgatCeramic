# Маленький знак бренда вместо символа °

Последующее решение владельца: маленький SVG отменён; актуальный UI-008
требует чистую текстовую надпись без декоративных знаков. Ниже сохранён
исторический отчёт первоначального варианта.

Текущая правка: `UiWordmark.vue` содержит только текст AGAT/CERAMIC с весами
600/400. Изображение и emblem-CSS удалены, SVG-файл сохранён. Обновлены
UI-008, Client AGENTS и существующая проверка `e2e/ui-kit.spec.ts` на отсутствие
img/svg/sup в трёх местах на пяти ширинах. Build/typecheck/format PASS;
17 релевантных E2E PASS, финальный wordmark-тест после форматирования PASS.
Независимый UI Design Guard принял чистую надпись в шапке/меню/подвале
на 320/1280 px. Типографические альтернативы только предложены в чате;
внедрён текущий стиль Inter с утверждённой разрядкой без значка.

Дата: 2026-10-20. Владелец попросил заменить ° сверху справа от текстового
AGATCERAMIC маленьким логотипом сайта.

Использован существующий `frontend/client/public/images/logo.svg`; файл не
менялся. Общий `UiWordmark` содержит название и декоративный SVG 10×10 px.
Шапка, боковое меню и подвал используют один компонент; старые sup и их CSS
удалены. Размеры текста наследуются от потребителей. Существующие ветки
основного загруженного logoUrl сохранены по коду.

Актуальное правило — UI-008 в `CLIENT_UI_KIT.md`, обязательное использование
компонента закреплено в Client AGENTS.

Проверки: build/typecheck/format и `git diff --check` PASS; 17 релевантных
production E2E PASS (контентные маршруты, preview/iframe и UI-kit).
Новая проверка подтверждает SSR, загрузку SVG, размер/позицию во всех трёх
местах на пяти ширинах 320–1280 px. Screenshots шапки/меню/подвала просмотрены;
независимый UI Design Guard accepted. Client dev перезапущен.
API, БД и миграции не менялись.

Изменены:

- `frontend/client/app/components/ui/UiWordmark.vue`
- `frontend/client/app/components/shared/SiteHeader.vue`
- `frontend/client/app/components/shared/SiteFooter.vue`
- `frontend/client/e2e/ui-kit.spec.ts`
- `frontend/client/AGENTS.md`
- `docs/CLIENT_UI_KIT.md`
- `docs/CLIENT_WORDMARK_2026-10-20.md`
- `tasks/DONE.md`
