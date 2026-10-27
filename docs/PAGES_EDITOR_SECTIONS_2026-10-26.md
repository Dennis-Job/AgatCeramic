# Секционное оформление редактора страниц

Дата: 2026-10-26. Запрос владельца: применить к вкладке «Страницы» оформление шапки и подвала.

Последующее уточнение владельца о плотности форм и загрузках реализовано отдельно:
[компактные формы от 2026-10-27](PAGES_COMPACT_FORMS_2026-10-27.md).

## Результат

Использован существующий `UiCard` с серой шапкой и белым содержимым:

- «Блоки»: порядок/добавление блоков отдельно от настроек выбранного блока.
- «Настройки»: параметры страницы, текст и доступные действия публикации.
- SEO: индексация, поисковые метаданные, соцсети и изображение Open Graph.

Тот же редактор обслуживает главную и остальные страницы. Вложенный редактор блока
может скрывать повторный заголовок через opt-in `hideHeading`; поля и media state
не размонтируются. Для списков выбора типа блока и слайдера включён существующий
`teleport-menu`, чтобы overflow карточек не обрезал варианты.

Трёхзонный layout, approved tokens, вкладки с `v-show`, keys, черновики, публикация,
guards и закреплённые `AdminStickyActions` сохранены. Backend, API, миграции,
клиентский сайт и форма создания страницы не менялись. Данные рабочего сайта
не сохранялись и не публиковались.

## Проверки и ревью

- Build/typecheck, lint, format:check и git diff --check: PASS.
- Unit: 49 файлов / 247 tests PASS.
- 37/37 профильных E2E PASS: pages, content-blocks, homepage, page-seo,
  contentWorkspaces, contentStickyActions. Включены responsive/axe на
  320/640/768/1024/1280 px и проверки широких workspace/sticky states.
- Live UI обычной страницы и главной на 1440 px просмотрен с настоящим Nuxt-preview.
- Independent UI Design Guard: accepted, блокирующих замечаний нет.
- Первая axe-проверка обнаружила одинаковое имя у внешнего редактора и новой
  карточки блоков. Внешний регион получил отдельное имя «Редактор содержимого
  страницы»; повторный прогон всех 37 сценариев чистый. Иерархия заголовков
  настроек сохранена с учётом вложенного заголовка слайдера.
- Общие UI primitives/layout/tokens не менялись, поэтому полный E2E всех модулей
  и visual baseline не обновлялись. Остаётся прежнее предупреждение Vite о размере bundle.

Evidence: `.tmp/pages-sections/before.png`, `blocks-live-1440.png`,
`settings-live-1440.png`, `seo-top-live-1440.png`, `seo-bottom-live-1440.png`,
`home-materials-live-1440.png`, `e2e-final.json`, `e2e-final/**`.
В responsive tests используется fixture preview; доказательство настоящего Nuxt — live captures.

## Изменённые файлы

- `frontend/admin/src/features/pages/components/PageBlocksEditor.vue` — секции инспектора и доступные имена.
- `frontend/admin/src/features/pages/components/BlockSettings.vue` — opt-in скрытие повторного заголовка.
- `frontend/admin/src/features/pages/components/SliderBlockEditor.vue` — portal выбора слайдера.
- `frontend/admin/src/features/homepage/components/HomePageBodyEditor.vue` — совместимый `hideHeading`.
- `frontend/admin/src/features/homepage/components/HomePageSeoEditor.vue` — секции SEO и Open Graph.
- `docs/CONTENT_WORKSPACE_UX.md`, `design-qa.md`, `tasks/DONE.md` и этот отчёт — правила и результат.

Commit не создавался.
