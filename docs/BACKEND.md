# Backend

Laravel используется только как API и единое бизнес-ядро для Admin и Client.

## Правила

- Не использовать Blade UI.
- Не использовать серверный рендеринг storefront.
- Соблюдать REST, SOLID и CRUD.
- Использовать API Resources, validation, authorization и tests.
- Поддерживать OpenAPI-документацию.

## Инициализация

Laravel-приложение инициализировано в `backend/` в рамках TASK-002.

Версия фреймворка выбирается и фиксируется в `backend/composer.json`; прикладные зависимости добавляются только в соответствующих задачах.
