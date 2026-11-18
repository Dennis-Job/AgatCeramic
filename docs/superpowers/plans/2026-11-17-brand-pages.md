# Brand Pages Implementation Plan

> **For agentic workers:** Use executing-plans inline for Nuxt UI; separate
> backend and test/sitemap subtasks; independent UI Design Guard before delivery.

**Goal:** Реализовать уточнённую владельцем навигацию и ЧПУ страницы брендов.
**Architecture:** Laravel active-only Brand Query/Resource; Nuxt brand route
передаёт профиль в общий каталог, бренд берётся из route slug. PageIntro получает
opt-in brand variant; обычные страницы сохраняют прежний вид.
**Tech Stack:** Laravel, Nuxt/Vue/TypeScript, PHPUnit, Playwright.
**Spec:** [Дизайн](../specs/2026-11-17-brand-pages-design.md).

## Global Constraints

- 55 px верхний отступ, центрированные алфавит/категории, прежние tokens.
- Публичны только активные профили и public image media; без миграций и PII.
- Preview остаётся защищённым, mobile только Apply применяет черновик.
- Предыдущие незакоммиченные изменения сохраняются и уточняются без git reset.

## Review Focus

- Смена route slug обновляет описание, картинку, каталог и метаданные.
- Query brand не может подменить бренд пути; фильтры не теряются при пагинации.
- Null/broken image, пустой и длинный текст, 404/503 имеют честный интерфейс.
- 320/640/768/1024/1280 px, центрирование и sticky не перекрывают фокус.
- Canonical/redirect/sitemap не создают индексируемые дубли и не раскрывают draft.

## Task 1: Public brand API

**Files:** backend public Brand Query/Controller/Resource/routes/tests; OpenAPI/API docs.
**Interface:** GET brands и brands/{slug}; {id,name,slug,description,logo_url,logo_alt}.
- [x] RED: отсутствующие маршруты, private media, inactive/unknown и empty brands.
- [x] Реализовать active-only API и документацию.
- [x] PHPUnit/Pint/PHPStan и PostgreSQL на allowlisted test DB.

## Task 2: Nuxt pages and navigation

**Files:** pages/brand/[slug].vue, brands types/service, Catalog feature,
PageIntro.vue и catalog.vue.
- [x] RED: бренд из алфавита должен открывать /brand/atlas и новый intro.
- [x] Реализовать SSR профиль/404/503, PageIntro brand variant и SEO.
- [x] Перевести links/filter navigation и legacy redirect на новый маршрут.
- [x] Восстановить одинаковый алфавит, центрировать категории, padding-top 55 px.
- [x] typecheck/format/build + catalog/brand/mobile/preview E2E.

## Task 3: Discoverability and verification

**Files:** sitemap route, test fixtures/new E2E, UI-kit/tasks/report/design-qa.
- [x] Добавить активные бренды в sitemap с прежней no-partial политикой.
- [x] Проверить пять ширин, SSR/meta, реальные данные, null image и переходы.
- [x] UI Design Guard, исправления, итоговый diff и статус задачи.

Execution: пользователь прямо поручил эту конкретную переработку; дополнительное
согласование ранее отвергнутого макета не требуется. Работа в текущем checkout,
обслуживающем localhost:3000; коммит запрещён без отдельного указания.
