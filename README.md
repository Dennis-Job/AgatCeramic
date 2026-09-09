# AgatCeramic

Интернет-магазин керамической плитки, мозаики, керамогранита, сопутствующих материалов и сантехники.

## Архитектура

```text
AgatCeramic
├── backend/             Laravel API-only
├── frontend/
│   ├── admin/           Vue 3 + TypeScript + TailAdmin-style PIM/CRM
│   └── client/          Nuxt.js SEO-first storefront
├── docs/                Проектная документация
├── tasks/               Задачи разработки
└── infrastructure/      Docker/CI/CD/deployment
```

## Текущее состояние

Laravel — API и единое business core. Vue Admin реализует access control и Catalog,
а Nuxt Client пока является каркасом. Public cart/checkout, orders и contacts уже доступны
через API, но полноценные Admin workspaces для orders/contacts и публичный storefront ещё не
реализованы. Online payment и customer registration не предусмотрены.

Полная картина реализованного, ограничений и зависимостей Phases 7–11:
[`docs/CURRENT_STATE.md`](docs/CURRENT_STATE.md).

## Документация

Карта источников и правила их изменения: [`docs/DOCUMENTATION.md`](docs/DOCUMENTATION.md).
Перед изменением модуля прочитайте `AGENTS.md`, соответствующие канонические документы и
актуальную запись в [`tasks/TODO.md`](tasks/TODO.md).
