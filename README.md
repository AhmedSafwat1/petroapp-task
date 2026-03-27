# Station transfer events (PetroApp task)

Laravel 12 API that ingests station transfer events idempotently and exposes per-station reconciliation summaries.

## Tech stack

- PHP 8.2+, Laravel 12
- **PostgreSQL** (application default connection)
- PHPUnit (tests use SQLite in-memory for speed via `phpunit.xml`)

## API (v1)

Base path: **`/api/v1`**

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/api/v1/transfers` | Ingest a batch of events |
| `GET` | `/api/v1/stations/{station_id}/summary` | Station reconciliation summary |

OpenAPI: generate with `php artisan l5-swagger:generate`, then open Swagger UI at [`/api/documentation`](http://localhost:8010/api/documentation) (same host as the app).

## Design

### Idempotency

- `event_id` has a **unique** database constraint.
- Inserts use Laravel’s **`insertOrIgnore`**, which maps to **`ON CONFLICT DO NOTHING`** on PostgreSQL (and equivalent behavior on other drivers), so duplicates never overwrite or double-insert.

### Concurrency

- Overlapping requests with the same `event_id` race at the database; the unique index ensures **at most one row** per `event_id`.
- No application-level mutex is required for correctness.

### Validation

- **Fail-fast**: if any event in the batch fails validation, the entire request returns **400** with `message` and `errors`.
- `created_at` must match `Y-m-d\TH:i:s\Z` (UTC `Z`).

### Reconciliation rules

- **`total_approved_amount`**: sum of `amount` where `status === "approved"` (unknown statuses are stored but do not contribute).
- **`events_count`**: count of **all** stored events for that station (all statuses).

### Performance

- **Bulk inserts**: rows are inserted in chunks (500 per statement) using `insertOrIgnore`, minimizing round-trips for large batches.
- **Indexes**: unique on `event_id`, index on `station_id` for summary lookups.
- **Summary**: one aggregate query (`COUNT` + conditional `SUM`) per station.

## Local setup

Requirements: PHP 8.2+ with **pdo_pgsql**, Composer, and a running **PostgreSQL** server.

1. Create a database and user (example):

```bash
createdb petro_app_task   # or use psql / a GUI
```

2. Configure `.env` (see `.env.example`):

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

3. Install dependencies and migrate:

```bash
cp .env.example .env   # if you do not have .env yet
php artisan key:generate
php artisan migrate
composer swagger   # writes storage/api-docs/api-docs.json for Swagger UI
```

For local development you may set `L5_SWAGGER_GENERATE_ALWAYS=true` in `.env` so `/api/documentation` regenerates the spec on each request (avoid in production).

### Run

```bash
make run
# or
./scripts/run.sh
```

### Tests

```bash
make test
# or
php artisan test
```

## Docker

Stack: **PHP app** + **PostgreSQL 16**. The app container runs `migrate` on startup, then `artisan serve`.

```bash
docker compose up --build
```

- API: `http://localhost:8010`
- Postgres: `localhost:5432` (defaults: user/password/database `laravel`; override with `POSTGRES_USER`, `POSTGRES_PASSWORD`, `POSTGRES_DB`, `POSTGRES_PORT` in a `.env` file next to `docker-compose.yml`)

Run tests (still uses SQLite in-memory from `phpunit.xml`, no DB container required for the test process):

```bash
docker compose run --rm app php artisan test
```

## API examples (curl)

Ingest:

```bash
curl -sS -X POST http://localhost:8010/api/v1/transfers \
  -H 'Content-Type: application/json' \
  -d '{
    "events": [
      {
        "event_id": "evt-001",
        "station_id": "S1",
        "amount": 100.5,
        "status": "approved",
        "created_at": "2026-02-19T10:00:00Z"
      }
    ]
  }'
```

Summary:

```bash
curl -sS http://localhost:8010/api/v1/stations/S1/summary
```

## Project structure

- `App\Contracts\Repositories\TransferEventRepositoryInterface` — persistence port
- `App\Repositories\DatabaseTransferEventRepository` — Laravel query builder (PostgreSQL in production; any PDO driver Laravel supports)
- `App\Services\Transfer\TransferIngestionService` — ingestion use case
- `App\Services\Station\StationSummaryService` — summary use case
- `App\Http\Controllers\Api\V1\*` — HTTP adapters

## Security

Do not commit `.env` or real secrets. Use `.env.example` only as a template.
