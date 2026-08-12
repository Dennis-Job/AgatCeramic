# Redis

## Development configuration

Docker Compose runs Redis 7 as the `redis` service and persists its data in the
named `redis_data` volume. The backend waits for the Redis healthcheck before it
starts.

Laravel uses the `phpredis` extension. In Docker, the backend receives
`REDIS_HOST=redis`; outside Docker, configure `REDIS_HOST` and `REDIS_PORT` in
`backend/.env` for an available Redis instance (defaults: `127.0.0.1:6379`).

## Usage

Redis is the default cache store (`CACHE_STORE=redis`). Database `0` is the
default Redis connection and database `1` is reserved for cache; override them
using `REDIS_DB` and `REDIS_CACHE_DB` when needed.

Queue processing uses Redis (`QUEUE_CONNECTION=redis`). The queue connection is
isolated in Redis database `2` (`REDIS_QUEUE_DB`), separate from the default
connection and cache. See [`QUEUE.md`](QUEUE.md) for worker, retry, failure, and
scheduler operation.

Do not put production Redis credentials into `.env.example`, Compose files, or
project documentation.
