# Catalog Brand Scope Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans for the UI and a
> narrowly scoped backend implementer; independent UI Design Guard before delivery.

**Goal:** Реализовать выбранный первый вариант каталога и оба пути выбора товаров.

**Architecture:** Laravel Query/Resource добавляют независимую навигационную
проекцию; Nuxt feature использует её для контекста, категорий и черновика.
Существующие общие UI primitives и карточки переиспользуются.

**Tech Stack:** Laravel/PHP/PostgreSQL; Nuxt/Vue/TypeScript; PHPUnit/Playwright.

**Spec:** [Согласованный дизайн](../specs/2026-11-17-catalog-brand-scope-design.md).

## Global Constraints

- Алфавитное меню брендов остаётся глобальным.
- Текущие палитра, Inter, ProductCardList, PageIntro и UI primitives сохраняются.
- Мобильный drawer применяет изменения только по «Применить».
- SSR/preview используют общую реализацию; никаких миграций и коммитов.

## Review Focus

- Бренд без товаров и нулевая выдача не теряют название и выбранный контекст.
- Родительские категории считают потомков; скрытые товары не раскрываются.
- Цена, наличие и пагинация не меняют категории/счётчики.
- Назад/вперёд и отмена мобильного черновика восстанавливают корректный выбор.
- Длинные имена и 320/640/768/1024/1280 px не дают overflow и перекрытия фокуса.

## Task 1: Backend navigation projection

**Files:** PublicCatalogNavigationQuery + Resource, CatalogController,
backend feature/contract tests, OpenAPI.
**Interface:** `category_navigation: {brand: {id,name,slug}|null,total:number,
categories: {id,name,slug,parent_id,product_count}[]}`.

- [x] Написать тесты стабильности scope/count, иерархии и пустого бренда; увидеть FAIL.
- [x] Реализовать Query/Resource и additive API, обновить OpenAPI.
- [x] Выполнить feature/contract tests, Pint, static analysis, PostgreSQL где доступен.

## Task 2: Nuxt catalog UI and navigation

**Files:** features/catalog types/service/composables/components;
e2e fixtures + catalog-brand-scope.spec.ts и затронутые regressions.
**Consumes:** Task 1 navigation projection.
**Produces:** две строки навигации, контекст, независимые chips и scope-aware reset.

- [x] Написать поведенческие E2E и выполнить до production-правок: ожидаемый FAIL.
- [x] Добавить навигационные типы и черновые метаданные, не подменяя выдачу.
- [x] Реализовать desktop/mobile и правила сброса; привести старые тесты к новому UX.
- [x] Выполнить typecheck, build, format и релевантные E2E.

## Task 3: Review and delivery

**Files:** CLIENT_UI_KIT, tasks, docs/CATALOG_BRAND_SCOPE_2026-11-17.md.

- [x] Открыть реальный каталог; проверить пять ширин и основные переходы.
- [x] Независимый UI Design Guard/code review и исправление блокирующих замечаний.
- [x] Обновить правила UI-kit и статус задачи; проверить diff и перечислить результаты.

## Execution decisions

- Пользователь уже утвердил визуальный вариант и прямо поручил реализацию;
  дополнительное согласование плана не требуется.
- Работа в текущем чистом checkout: именно его обслуживает локальный каталог
  пользователя. Новый worktree/branch не создаётся; commit запрещён без указания.
- Backend, тестовая fixture и UI распределены по непересекающимся файлам.
