# Реактивные фильтры — 2026-11-18

Владелец запросил обновление товаров при выборе фильтра и явно выбрал вариант:
на компьютере автоматически, на телефоне сохранить «Применить».

## Поведение

От 1024 px sidebar применяет checkbox, цвет, размер и выбранный select сразу;
поля цены, текста, числовых/датовых диапазонов — через 350 ms после ввода.
Enter применяет валидный ввод сразу. Desktop-кнопка «Применить» скрыта;
сброс остаётся. Native validity, согласованные min/max характеристик и точное
сравнение денежных границ не позволяют Enter обойти проверку обратных цен.

Авто-применение запускается только пользовательскими событиями. Восстановление
URL/истории не создаёт новый запрос применения. При смене фильтра сбрасывается
страница, сохраняются сортировка и прочие ограничения. URL, chips/reset,
reload и history поддерживаются как прежде. Checkbox также применяет актуальную
цену, если её debounce ещё не завершился и форма валидна.

Во время обновления сохраняются карточки и справочники текущего scope;
счётчик сообщает «Обновляем…», контейнер имеет aria-busy. Controls доступны
для следующего уточнения. При смене категории/бренда устаревшие характеристики
недоступны до загрузки нового scope. Старый ответ не заменяет новую выдачу.
Автоматическое уточнение на том же пути не переводит scroll к якорю.

Mobile drawer продолжает работать с черновиком: только «Применить» меняет
выдачу, сброс остаётся локальным, закрытие отменяет изменения. Таймер desktop
отменяется на route/resize/reset/unmount. Одинаковый механизм используется
в каталоге, на странице бренда и в защищённом preview.

## Файлы

- `CatalogFilterForm.vue` — auto mode, debounce, validity, cleanup и кнопки.
- `CatalogAttributeFilter.vue` — пользовательские change-события и min/max.
- `CatalogPageBody.vue` — подключение sidebar и сохранение выдачи при pending.
- `useCatalog.ts` — auto navigation state и cache метаданных того же scope.
  Все четыре файла в `frontend/client/app/features/catalog/`.
- `frontend/client/app/plugins/catalog-scroll.client.ts` — узкая обёртка Nuxt
  scrollBehavior после app:mounted; стандартная функция сохраняется для
  остальных переходов. Фаза выбрана после чтения установленного Nuxt runtime:
  в app:created он ещё заменяет scrollBehavior.
- `frontend/client/e2e/catalog-auto-filters.spec.ts` — auto, debounce/validity,
  Enter, delayed response, history, scroll, resize и mobile cancellation.
- `catalog.spec.ts`, `catalog-sale.spec.ts`, `catalog-sizes-colors.spec.ts`,
  `catalog-brand-scope.spec.ts` — прежние desktop-тесты адаптированы под auto;
  сценарии неприменённого выбора брендов/коллекций сохранены в mobile drawer.
- `docs/CLIENT_UI_KIT.md` — актуальные UI-035/036 и новый UI-037;
  план `docs/superpowers/plans/2026-11-18-catalog-reactive-filters.md`, `tasks/DONE.md`.

## Проверки

- Red: до реализации desktop требовал Apply; отдельно воспроизведён обход
  обратных денежных границ клавишей Enter. Регрессии после исправления PASS.
- `npm run typecheck`, `npm run format:check`, `npm run build` — PASS.
- 39 production E2E — PASS: auto-filters, catalog, sale, sizes/colors, mobile
  draft, brand scope и preview. Включены пять ширин, keyboard, touch, axe,
  SSR/hydration, loading/error/retry и старые ссылки.
- Независимый UI Design Guard: code и пять свежих PNG — PASS. Замечание об
  Enter устранено и повторно проверено. Mobile Apply виден, desktop скрыт,
  layout/clipping blockers нет; интерактивные состояния подтверждены E2E.
- `git diff --check` — PASS. Логи `.tmp/catalog-auto-build.log`,
  `.tmp/catalog-auto-e2e.log`.

Прежнее исключение axe по контрасту `.image-empty` сохранено. Полный Client
suite и backend suite не запускались: проверены затронутые сценарии; API,
миграции, данные и зависимости не менялись. Локальный Nuxt-контейнер обновлён.
Коммит не создавался.
