# Мобильные категории и применение фильтров

Владелец уточнил готовый каталог: размер текста категорий как у обычных
строк фильтра (13 px), родитель «Сантехника» раскрывает дочерние категории
по образцу «Услуг» в боковом меню, выбор не закрывает панель и не меняет
выдачу до «Применить». Desktop переключатель сохраняет немедленную навигацию.

- [x] Изучить текущее состояние Git, правила Client/UI-kit, SiteNavigation,
  фильтры и URL-composable. Сохранить существующие незакоммиченные изменения.
- [x] Отдельное дерево с generic parent disclosure, общим motion, inert/aria,
  автоматическим открытием предков, Escape и возвратом фокуса; 13 px.
- [x] Добавить category в draft и отдельную загрузку choices по draft category/brand.
  Серверные metadata не заменяют applied listing. Старые ответы отменяются;
  несовместимые характеристики/коллекция сбрасываются, цена/наличие сохраняются.
- [x] Apply записывает category и все фильтры в URL, сбрасывает page и закрывает
  панель. Reset внутри панели очищает только draft; закрытие без Apply
  сохраняет применённую выдачу. Ошибки choices имеют retry, Apply блокируется.
- [x] Новые E2E: раскрытие всех детей, размер текста, неизменность URL/карточек
  до Apply, продолжение фильтрации, сброс draft, Escape, metadata retry/races.
- [x] Typecheck/build/format, регрессии каталога/preview и независимый UI Guard.
- [x] Обновить UI-031 и статус задачи; локальный Nuxt перечитывает финальный код.

Основные файлы: CatalogCategories.vue, CatalogCategoryBranch.vue,
utils/categoryTree.ts, useCatalog.ts, useCatalogDraftFilters.ts,
CatalogPageBody.vue, CatalogFilterForm.vue, types/catalog.ts, catalog E2E
и fixture. Backend/API/БД не изменяются, коммит не создаётся.

RED выполнен на предыдущей сборке: нет раскрывающей кнопки «Сантехника».

## Проверки

- Client typecheck, build, format — PASS.
- Playwright catalog-mobile-draft, catalog-sticky-categories, catalog, preview:
  **25 passed (28.8s)**. Включены неизменная выдача до Apply, дальнейший выбор
  характеристик, все дети «Сантехники», Escape/focus, reset только draft,
  retry при ошибке metadata, прежние race/URL/SSR/axe и все пять ширин.
- Независимый UI Design Guard — PASS: `.tmp/catalog-top-navigation/draft-ui-review.md`.
  Итоговый лог — `draft-e2e.log`, снимок `mobile-draft-plumbing.png` и мобильные
  320/640/768/1023 + desktop 1024/1280 в `client-final`.
- Дерево отдельно проверено агентом: пустой список, порядок, вложенность,
  сироты и циклы, отсутствие изменения входного массива.
- Ни backend-контракт, ни БД не менялись. API metadata запрашивается отдельным
  чтением и не заменяет применённый список товаров. Новых зависимостей нет.
