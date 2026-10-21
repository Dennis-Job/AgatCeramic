# Client: уведомления об успешной отправке — 2026-10-21

Выполнено по запросу владельца. Успех заявок на звонок и партнёрство
показывается общим toast по образцу рассылки/checkout в `exampleSite.html`.
Ошибки, валидация, сохранение ввода при ошибке и API-контракт сохранены.

## Поведение

- Ink-фон, белый текст 12 px, tracking .1em, padding 15×28 px.
- Положение снизу по центру доступной ширины, отступ 32 px плюс safe area.
  На узких экранах остаются поля по 20 px, текст переносится.
- Движение снизу за .5 s; reduced motion отключает смещение.
  Скрытие через 2600 ms, следующий успех заменяет текст и перезапускает таймер.
- Callback сначала закрывает native dialog штатной анимацией и возвращает
  фокус инициатору, затем показывает подтверждение.
- Partnership очищает поля, сохраняет форму и положение прокрутки.
- Постоянный `role=status` с `aria-atomic`, без захвата фокуса и pointer events.
  Состояние изолировано Nuxt `useState`; таймер очищается при unmount.

## Изменённые файлы

- `frontend/client/app/components/ui/UiSuccessNotification.vue` — общий toast.
- `frontend/client/app/composables/useSuccessNotification.ts` — общее состояние.
- `frontend/client/app/app.vue` — единственный host, включая preview.
- `frontend/client/app/features/callback/components/CallbackRequestDialog.vue`.
- `frontend/client/app/features/partnership/components/PartnershipSection.vue`.
- `frontend/client/app/features/partnership/composables/usePartnerRequest.ts`.
- `frontend/client/e2e/success-notification.spec.ts` — lifetime, повторный успех,
  normal/reduced motion, оформление и возврат фокуса.
- `frontend/client/e2e/{callback,cooperation,overlay-motion,phone-mask}.spec.ts` —
  обновлены проверки успешной отправки; проверки ошибок и защиты ввода сохранены.
- `docs/CLIENT_UI_KIT.md` — каталог и правило UI-011; этот отчёт и `tasks/DONE.md`.

## Проверки

- Nuxt typecheck, production build, format:check и Prettier изменённых E2E — PASS.
- Полный Client E2E: 48 PASS; один новый geometry assertion исправлен после
  измерения `scrollbar-gutter` (headless clientWidth включает резерв, реальная
  ширина root меньше). Повтор исправленного теста на пяти ширинах — 1 PASS.
  Все 49 сценариев проверены; нового полного прогона после правки assertion не было.
- Проверены 320/640/768/1024/1280 px, normal/reduced motion, SSR/hydration,
  callback/partner success/loading/error, блокировка повторной отправки,
  сохранность ввода и отсутствие PII в URL/storage.
- Независимый UI Design Guard: accepted, P1/P2 нет; просмотрены все пять
  responsive PNG и normal/reduced motion PNG.
- `git diff --check` — PASS. API и схема БД не менялись; миграций нет.

E2E использует изолированный mock API, реальные письма не отправлялись.
Build и финальные E2E выполнены вне Windows sandbox из-за EPERM в `.output`
и ограничений завершения тестовых процессов. Commit не создавался.
