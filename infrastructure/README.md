# Development environment

Docker Compose starts the local Laravel API, Vue Admin, Nuxt Client, PostgreSQL, and Redis.

## Requirements

- Docker Desktop running with Linux containers;
- Docker Compose v2+.

## Start

```powershell
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

The Compose defaults are development-only and do not contain production credentials. Environment-variable templates and secret-management rules are handled in TASK-006. Laravel's use of PostgreSQL and Redis is configured in TASK-010 and TASK-011 respectively.
