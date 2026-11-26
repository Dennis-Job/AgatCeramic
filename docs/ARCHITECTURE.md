# Архитектура AgatCeramic

## 1. Приложения и границы

```text
Vue Admin SPA ─┐
Nuxt Client ───┴→ Laravel API ─┬→ PostgreSQL
                               ├→ Redis (кэш и очередь)
                               └→ Файловое хранилище
```

Laravel — API-only модульный монолит. Он владеет данными и бизнес-правилами
доступа, каталога, корзины, заказов, обращений, контента, настроек и аудита.
Nuxt отвечает за SSR витрины, Vue Admin — за интерфейс сотрудников. Worker и
scheduler запускают вынесенные из HTTP фоновые сценарии того же приложения.
Топология локального запуска описана в [OPERATIONS.md](OPERATIONS.md).

[DOCUMENTATION.md](DOCUMENTATION.md) определяет источники по темам. Общие
требования к изменениям закреплены в [корневом AGENTS.md](../AGENTS.md),
правила слоёв и каталогов — в AGENTS.md соответствующего приложения.

## 2. Backend

[Backend AGENTS.md](../backend/AGENTS.md) определяет правила разработки. Запись
проходит через Route, Form Request для нетривиальной валидации, Controller и
application service. Сервис владеет инвариантами и транзакцией. При чтении
Controller использует Query object для сложной выборки или Model для простого
чтения и возвращает API Resource. Ресурс формирует ответ из подготовленных
данных. Eloquent Model отвечает за связи, casts, scopes и локальное поведение.
Отдельный repository нужен, когда сценарий требует собственной границы доступа
к данным.

- [app/Http](../backend/app/Http) — входная валидация, авторизация, вызов
  сценария и представление ответа.
- [app/Services](../backend/app/Services) — прикладные сценарии, транзакции и
  координация интеграций.
- [app/Queries](../backend/app/Queries) и [app/Models](../backend/app/Models) —
  выборки, persistence и локальное поведение сущностей.
- [app/Jobs](../backend/app/Jobs) и [app/Console](../backend/app/Console) —
  входы для фоновых и эксплуатационных сценариев. В очередь, которой нужны
  зафиксированные данные, задания передаются после commit.

### Автоматические границы

`composer architecture` из `backend/` разбирает `app/**/*.php` через PHP Parser.
Блокирующий шаг [CI](CI.md) реализован в
[ArchitectureGuard.php](../backend/scripts/architecture/ArchitectureGuard.php).

| Код | Что блокирует guard |
| --- | --- |
| `Http/Controllers` | DB facade, распознаваемые прямые Eloquent mutations, `app()`, `resolve()`, глобальные `request()` и `auth()`. |
| `Services` | Зависимости от `App\Http\*` и HTTP-типов Request, Resource и Response, перечисленных в guard. |
| `Models` | Зависимости от HTTP, Services, Queries, Jobs, Console и Mail. |
| `Queries` | Зависимости от HTTP, Services, Jobs, Console и Mail. |
| `Http/Resources`, `Http/Responses` | Зависимости от Services, Queries, Jobs, Console и Mail. |
| `Jobs`, `Console`, `Mail`, `Logging` | Зависимости от Controllers, Requests, Resources и Responses. |

Класс длиннее 250 строк, метод длиннее 40 строк или с cyclomatic complexity
выше 10 требует review разделения ответственности. Guard сообщает об этих
порогах отдельно от блокирующих нарушений. Временное исключение в
[architecture-allowlist.php](../backend/scripts/architecture-allowlist.php)
указывает точные `path`, `rule`, `line`, причину и задачу удаления долга.
Некорректная или устаревшая запись блокирует проверку.

### API

Laravel регистрирует публичные и административные маршруты в
[реестре API v1](../backend/routes/api/v1.php). Admin использует cookie/session
аутентификацию Sanctum с CSRF. Backend проверяет доступ независимо от
маршрутизации Admin. Гостевая корзина использует отдельный токен.
Форматы запросов, ответов и ошибок задаёт [OpenAPI](openapi.json).
Сценарии интеграции описаны в [API.md](API.md).

### Границы предметных сценариев

- Каталог хранит самостоятельные продаваемые позиции. Группы связывают
  позиции для навигации по вариантам, а коллекции принадлежат брендам.
  Инварианты и модель связей описаны в [DATABASE.md](DATABASE.md).
- Checkout повторно проверяет актуальные цены и остатки. Транзакция создаёт
  заказ, снимки позиций и записи почтовых доставок. После commit очередь
  получает ID доставок. [Публичный checkout](API.md#заказы),
  [учёт оплаты в Admin](API.md#заказы-1).
- Сервисы импорта разделяют чтение файла, проверку, планирование, запись и
  генерацию отчёта. Оркестрация владеет транзакциями и checkpoint, а фоновые
  задания — обработкой пакетов и очисткой временных файлов. Подробности
  импорта и очистки медиатеки находятся в [QUEUE.md](QUEUE.md).
- Правила хранения аудита и исключения ПДн из обычных логов находятся в
  [LOGGING.md](LOGGING.md), а политика хранения и удаления ПДн — в
  [PERSONAL_DATA_LIFECYCLE.md](PERSONAL_DATA_LIFECYCLE.md).

## 3. Admin

Vue 3 SPA на TypeScript, Vite, Vue Router и Pinia использует декларативные
маршруты и feature-структуру. Обязательные правила находятся в
[Admin AGENTS.md](../frontend/admin/AGENTS.md).

| Каталог | Ответственность |
| --- | --- |
| [src/router](../frontend/admin/src/router), [src/views](../frontend/admin/src/views) | Маршруты, проверка доступа и тонкая композиция экрана. |
| [src/features](../frontend/admin/src/features) | Доменные сценарии. Компоненты показывают данные и передают события, composables координируют состояние и действия, services инкапсулируют endpoints и mapping. |
| [src/layouts](../frontend/admin/src/layouts), [src/stores](../frontend/admin/src/stores) | Оболочка приложения и глобальное состояние, включая сессию и права. |
| [src/components/ui](../frontend/admin/src/components/ui), [src/components/shared](../frontend/admin/src/components/shared) | Общие UI primitives и недоменные составные компоненты. Features используют их без обратных зависимостей. |
| [src/services](../frontend/admin/src/services) | Общий HTTP-транспорт, аутентификация и нормализация ошибок. Feature services используют [api.ts](../frontend/admin/src/services/api.ts). |
| [src/styles](../frontend/admin/src/styles) | Глобальные стили и design tokens Seller UI-kit. |

Визуальные источники — Seller UI-kit и design tokens проекта. Утверждённое
направление описывает [план Seller-редизайна](ADMIN_SELLER_REDESIGN_PLAN.md).
Правила приёмки общие для Admin и Client. [UI_DESIGN_REVIEW.md](UI_DESIGN_REVIEW.md).

### Загрузка маршрутов Admin

[Vue Router](../frontend/admin/src/router/index.ts) загружает экраны через
динамические импорты, включая UI-kit. Восстановление сессии и проверка прав
происходят до запроса кода защищённого экрана. Три рабочие области импорта
удерживаются в `KeepAlive` между SPA-переходами в рамках сессии и набора прав.

Если чанк текущего перехода недоступен, [App.vue](../frontend/admin/src/App.vue)
показывает диалог Seller UI-kit. При SPA-переходе можно остаться на открытой
странице. Действие «Обновить страницу» повторно открывает целевой адрес с
query-параметрами и фрагментом, включая ошибку при прямом входе.

## 4. Client

Nuxt 4 на Vue 3 и TypeScript отвечает за SSR публичной витрины. Тонкие
[app/pages](../frontend/client/app/pages) связывают параметры маршрута,
page-level загрузку, SEO и доменные сценарии из
[app/features](../frontend/client/app/features). Общие компоненты, composables
и API services находятся в `app/` на одном уровне с features. Nitro
[server/routes](../frontend/client/server/routes) обслуживает технические SEO
маршруты. Каталог, цены, наличие и оформление заказов остаются в Laravel.

Публичные страницы получают опубликованный контент. Для предпросмотра Admin
открывает тот же Nuxt-рендер в защищённом маршруте. Сохранённый черновик
загружается после подтверждения доступа и остаётся вне SSR payload.

Точные границы Nuxt определяет [Client AGENTS.md](../frontend/client/AGENTS.md),
визуальные паттерны — [CLIENT_UI_KIT.md](CLIENT_UI_KIT.md), а порядок
приёмки — [UI_DESIGN_REVIEW.md](UI_DESIGN_REVIEW.md).
