# Development environment

Docker Compose starts the local Laravel API, Vue Admin, Nuxt Client, PostgreSQL, and Redis.

## Requirements

- Docker Desktop running with Linux containers;
- Docker Compose v2+.

## Start

```powershell
Copy-Item .env.example .env
docker compose up --build
```

Available services:

| Service | URL / port |
| --- | --- |
| Laravel API | `http://localhost:8000` |
| Vue Admin | `http://localhost:5173` |
| Nuxt Client | `http://localhost:3000` |
| PostgreSQL | `localhost:5432` |
| Redis | `localhost:6379` |

Stop containers with `docker compose down`. Add `--volumes` only when local database and cache data may be discarded.

The Compose configuration does not contain database credentials: copy the root `.env.example` to `.env` before starting. See [`docs/ENVIRONMENT.md`](../docs/ENVIRONMENT.md) for the complete local setup and secret-management rules. Laravel's use of PostgreSQL and Redis is configured in TASK-010 and TASK-011 respectively.
