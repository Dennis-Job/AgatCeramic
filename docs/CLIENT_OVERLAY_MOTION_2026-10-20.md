# Плавное появление меню и модального окна

Дата: 2026-10-20. Прямое указание владельца — повторить плавность бокового
меню `exampleSite.html` и применить её к модальному окну.

Актуальный контракт записан в UI-006 [`CLIENT_UI_KIT.md`](CLIENT_UI_KIT.md).
Общие CSS-переходы находятся в `overlays.css`, длительности — в motion-токенах.
Меню выезжает справа; фон проявляется отдельно; пункты и нижняя часть появляются
с задержками из шаблона. Поиск использует движение сверху, как в том же шаблоне.
Для центрированного native modal применены opacity и небольшое движение вверх
с общей кривой. Обычное закрытие симметрично появлению.

`UiDialog` сохраняет native top layer и scroll lock до завершения leave.
Все действия закрытия, включая «Готово», идут через один путь; уходящее окно
inert. При переключении меню/поиска учитываются все уходящие панели, чтобы
последняя не разблокировала фон раньше времени. Cleanup при unmount сохранён.
Reduced motion отключает движение и задержки. Начальный фокус остаётся на
контейнере; стили controls и полей не менялись.

## Проверки

- Build, typecheck, format:check PASS; полный production E2E — 35 PASS,
  development E2E — 2 PASS.
- Новый тест воспроизвёл отсутствие движения до реализации. Normal-motion
  проверяет реальные CSS transitions 650 ms и промежуточные screenshots на
  ширинах 320/640/768/1024/1280; reduced-motion — отсутствие задержек/движения.
- Регрессии: быстрое menu → search → Escape, удержание scroll lock до последнего
  leave, закрытие modal во время enter и повторное открытие, плавный «Готово».
- Общие callback, UI-kit, iframe preview, SSR/hydration, axe и responsive тесты
  прошли. Исполнитель просмотрел промежуточные кадры меню 320 и modal 1280.
- Независимый UI Design Guard проверил код/документы и промежуточные состояния
  на пяти ширинах — accepted. Findings про «Готово», inert и перекрытие уходящих
  панелей исправлены и покрыты регрессиями.
- `git diff --check` PASS. Client dev-сервис перезапущен. API, БД, миграции
  не менялись.

## Изменённые файлы

- `frontend/client/app/assets/css/overlays.css`
- `frontend/client/app/assets/css/tokens.css`
- `frontend/client/app/assets/css/base.css`
- `frontend/client/nuxt.config.ts`
- `frontend/client/app/components/shared/SiteHeader.vue`
- `frontend/client/app/components/ui/UiDialog.vue`
- `frontend/client/app/features/callback/components/CallbackRequestDialog.vue`
- `frontend/client/e2e/overlay-motion.spec.ts`
- `frontend/client/AGENTS.md`
- `docs/CLIENT_UI_KIT.md`
- `docs/UI_DESIGN_REVIEW.md`
- `docs/CLIENT_OVERLAY_MOTION_2026-10-20.md`
- `tasks/DONE.md`
