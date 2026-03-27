# Station transfer events (PetroApp task)

Laravel 12 API that ingests station transfer events idempotently and exposes per-station reconciliation summaries.

## Solution overview

This project implements a **reconciliation API** for station transfer events: clients can post batches of events, and operators can query **per-station totals** (approved amount and event count) in a way that stays correct under duplicates and concurrent requests.

### Core approach

- **Idempotent ingestion** using a unique `event_id` and `INSERT`-style semantics that ignore duplicates, so the same event can be retried or delivered twice without corrupting totals.
- **Live reconciliation** via `GET /api/v1/stations/{station_id}/summary`, which aggregates directly from `transfer_events`. That endpoint is the straightforward “read from the source of truth” view.
- **Cached aggregates** in the `station_summaries` table, modeled by [`App\Models\StationSummary`](app/Models/StationSummary.php). The cache is **not** updated inside the HTTP request body of `POST /transfers`; instead we **dispatch a domain event** after a successful batch insert, and a **queued listener** refreshes one row per affected station using a **stored routine** in the database (see below).

### Cache refresh: event, listener, and stored procedure

**1. After each ingest batch** — [`TransferIngestionService`](app/Services/Transfer/TransferIngestionService.php) collects the **distinct `station_id` values** present in the normalized batch, runs `insertBatch`, then dispatches [`StationsUpdateEvent`](app/Events/StationsUpdateEvent.php) with that **unique station id list**. That way we only enqueue refresh work for stations that could have changed, not for the whole system.

**2. In the listener** — [`StationsSummariesUpdate`](app/Listeners/StationsSummariesUpdate.php) implements `ShouldQueue`, so summary refresh runs **asynchronously** on the queue worker and does not slow down the ingest response. For each station id, the app calls [`refreshStationSummary`](app/Repositories/DatabaseTransferEventRepository.php), which executes the database routine `refresh_station_summary` (a **MySQL stored procedure** or **PostgreSQL function**, defined in [`database/migrations/2026_03_27_121330_create_refresh_station_summary_procedure.php`](database/migrations/2026_03_27_121330_create_refresh_station_summary_procedure.php)). That routine **recomputes** `total_approved_amount` and `events_count` from `transfer_events` for that station and **upserts** the corresponding row in `station_summaries`.

**3. Why use a stored procedure / function (instead of only Laravel / Eloquent)?**

- **One round-trip per station** — The engine runs aggregation (`SUM` / `COUNT` with the same rules as live reconciliation) and the `INSERT … ON DUPLICATE KEY` / `ON CONFLICT` upsert in a **single call**, instead of multiple queries or pulling large event sets into PHP.
- **Same logic next to the data** — The refresh definition lives in the database next to `transfer_events` and `station_summaries`, which keeps the “how we roll up a station” expression **centralized** and easy to reason about for DBAs and deployments.
- **Offload heavy work** — For stations with many events, letting the database server scan and aggregate is typically more efficient than materializing rows in application memory.
- **Clean ingest path** — The HTTP layer only records events and fires an event; **queue workers** own calling the routine, so spikes in ingest traffic do not multiply aggregate queries on the web process.
- **Concurrency (race conditions)** — A naive PHP refresh (`SELECT` aggregates, then `UPDATE` / `INSERT` the cache) leaves a window where **`transfer_events` can change between queries**, so the summary you write may not match the data you aggregated (**time-of-check / time-of-use**, **lost updates** on cached totals). The routine does a **full recount** from `transfer_events` and **upserts** `station_summaries` in **one executed statement** (`INSERT … ON DUPLICATE KEY UPDATE` / `ON CONFLICT … DO UPDATE`), so there is no application-level gap between “read the truth” and “write the cache,” and no unsafe incremental “add to the counter” that concurrent workers could interleave. Competing refreshes still order arbitrarily, but each pass replaces the row with totals recomputed from the source table.

*(PHPUnit uses SQLite in memory; that migration **skips** creating the routine on SQLite, so tests rely on live aggregation / fallbacks where applicable.)*

### Second read endpoint: `GET .../summary-report`

We added **`GET /api/v1/stations/{station_id}/summary-report`**, which **prefers the persisted row** from `StationSummary` / `station_summaries` and returns the **same JSON shape** as the live summary (`station_id`, `total_approved_amount`, `events_count`).

**Why a separate endpoint (and why read from `StationSummary`)?**

1. **Performance and scalability** — Dashboards, reports, and high-frequency polling can hit a **small, indexed row** per station instead of running `COUNT` / `SUM` over `transfer_events` every time.
2. **Clear contract** — Callers that want “what we last materialized for reporting” use `summary-report`; callers that want “recompute from raw events now” use `summary`.
3. **Resilience** — If no cached row exists yet (cold start, listener delayed, or cache not warmed), `summary-report` **falls back** to the same live aggregation as `summary`, so clients always get a valid response and a stable response shape.

Together, **live `summary` + cached `summary-report`** separate “always accurate from raw data” from “fast path from precomputed storage,” without duplicating business rules in the JSON layer.

## Tech stack

- PHP 8.2+, Laravel 12
- **MySQL 8** or **PostgreSQL** for real environments (driver from `.env` `DB_CONNECTION`)
- **Docker Compose** in this repo: **PostgreSQL 16** + PHP app (see [Setup](#setup-without-docker-vs-docker))
- PHPUnit (tests use **SQLite in-memory** via `phpunit.xml`, independent of your production DB)

## API (v1)

Base path: **`/api/v1`** (Laravel’s `api` route group adds **`/api`**; [`routes/api.php`](routes/api.php) then groups everything under **`v1`**.)

### Why version the API in the URL?

- **Stable contracts for clients** — Integrations can target **`/api/v1/...`** and rely on a **documented, predictable** request/response shape. If we later introduce **breaking** changes (different fields, semantics, or auth), we can ship **`/api/v2/...`** while **keeping v1 available** for existing consumers until they migrate.
- **Explicit over implicit** — The version is visible in every request and in logs, traces, and proxies, which makes support and debugging easier than relying only on headers or content negotiation.
- **Code layout** — Controllers for this surface live under [`App\Http\Controllers\Api\V1`](app/Http/Controllers/Api/V1), mirroring the URL so a future `V2` namespace can sit beside `V1` without entangling old and new behavior.

This project currently exposes **only v1**; the prefix is there so evolution of the HTTP API does not require breaking current callers.

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/api/v1/transfers` | Ingest a batch of events |
| `GET` | `/api/v1/stations/{station_id}/summary` | Live reconciliation summary (aggregated from `transfer_events`) |
| `GET` | `/api/v1/stations/{station_id}/summary-report` | Summary from [`StationSummary`](app/Models/StationSummary.php) / `station_summaries` when present; otherwise same as live `summary` |

OpenAPI: generate with `php artisan l5-swagger:generate`, then open Swagger UI at [`/api/documentation`](http://localhost:8010/api/documentation) (same host as the app).

## Design

### Idempotency

- `event_id` has a **unique** database constraint.
- Inserts use Laravel’s **`insertOrIgnore`**, which maps to **`INSERT IGNORE`** on MySQL (and equivalent behavior on other drivers), so duplicates never overwrite or double-insert.

### Concurrency

- Overlapping requests with the same `event_id` race at the database; the unique index ensures **at most one row** per `event_id`.
- No application-level mutex is required for correctness.

### Validation

Validation runs in [`StoreTransferEventsRequest`](app/Http/Requests/Api/V1/StoreTransferEventsRequest.php) **before** any row is written.

- **Fail-fast (whole batch):** if **any** event in `events` fails a rule, the handler returns **400** and **no** events from that request are ingested. The JSON body is `{ "message": "Validation failed.", "errors": { ... } }`, where Laravel keys errors by field (e.g. `events.0.amount`, `events.1.created_at`).

| Field | Rules |
|--------|--------|
| `events` | Required; must be a JSON **array** (may be empty). |
| `events.*.event_id` | Required **string**; must contain at least one non-whitespace character; max 191 characters. |
| `events.*.station_id` | Same string rules as `event_id`. |
| `events.*.amount` | Required; **numeric**; **≥ 0** (non-negative integers or decimals). |
| `events.*.status` | Required non-empty string (non-whitespace); max 64 characters. **No enum:** unknown values are accepted and stored; only `"approved"` affects totals. |
| `events.*.created_at` | Required non-empty string; must match **`Y-m-d\TH:i:s\Z`** (UTC with a literal `Z`). |

**Note on `created_at`:** the assignment allows ISO8601 in general; this API intentionally accepts **one canonical wire format** (no fractional seconds, no `+00:00` offset—use `Z`). Clients should normalize to that string before calling `POST /transfers`.

### Reconciliation rules

- **`total_approved_amount`**: sum of `amount` where `status === "approved"` (unknown statuses are stored but do not contribute).
- **`events_count`**: count of **all** stored events for that station (all statuses).

### Performance

- **Bulk inserts**: rows are inserted in chunks (500 per statement) using `insertOrIgnore`, minimizing round-trips for large batches.
- **Indexes**: unique on `event_id`, index on `station_id` for summary lookups.
- **Summary**: one aggregate query (`COUNT` + conditional `SUM`) per station.
- **Cached summary (`summary-report`)**: reads the `station_summaries` table (Eloquent model `StationSummary`). When that row is missing, the handler falls back to the same live aggregation used by `/summary`, so clients always get a consistent JSON shape (`station_id`, `total_approved_amount`, `events_count`).

## Setup (without Docker vs Docker)

You can run the API **on the host** with your own database, or **entirely in Docker** using the provided Compose file.

### Without Docker (host PHP + your database)

**Requirements:** PHP 8.2+, Composer, and a PDO extension matching your DB (`pdo_mysql` for MySQL, `pdo_pgsql` for PostgreSQL).

1. Create a database and user (MySQL example):

```bash
mysql -u root -p -e "CREATE DATABASE petro_app_task; CREATE USER 'app'@'%' IDENTIFIED BY 'secret'; GRANT ALL ON petro_app_task.* TO 'app'@'%';"
```

2. Configure **`.env`** (start from `.env.example` if you have one):

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

3. Install, key, migrate, optional Swagger artifact:

```bash
make install                    # or: composer install
cp .env.example .env            # if needed
php artisan key:generate
php artisan migrate
make swagger                    # or: composer swagger — writes storage/api-docs/api-docs.json
```

4. **Run the API:** `make run` (runs [`scripts/run.sh`](scripts/run.sh); default port **8010**, override with `PORT=...`).

5. **Run tests on the host:** `make test` — uses SQLite in-memory from `phpunit.xml`, not your MySQL/Postgres URL.

For local Swagger UX you may set `L5_SWAGGER_GENERATE_ALWAYS=true` in `.env` so `/api/documentation` regenerates on each request (avoid in production).

### With Docker (Compose: Postgres + app)

**Requirements:** Docker with Compose v2.

1. Adjust **[`.env.docker`](.env.docker)** if needed. **Postgres** gets **`POSTGRES_*`** only from `.env.docker` (Compose `env_file`). **Laravel** reads the bind-mounted **`.env`** in the project root. Those must agree: **`DB_USERNAME` / `DB_PASSWORD` / `DB_DATABASE`** in `.env` must equal **`POSTGRES_USER` / `POSTGRES_PASSWORD` / `POSTGRES_DB`** in `.env.docker`. If you are unsure, delete `.env` and start the stack so [`scripts/docker-entrypoint.sh`](scripts/docker-entrypoint.sh) can copy `.env.docker` → `.env`.

2. Start stack (builds images, starts **Postgres** and **app** in the background):

```bash
make run-docker
# equivalent: docker compose up -d --build
```

3. **Entrypoint** ([`scripts/docker-entrypoint.sh`](scripts/docker-entrypoint.sh)): if the bind-mounted project has no **`.env`**, it copies **`.env.docker` → `.env`**, then `php artisan key:generate --force`, `migrate`, and `php artisan serve` on **0.0.0.0:8010**.

4. **API:** `http://localhost:8010`  
5. **Postgres on the host:** `localhost` and **`POSTGRES_PORT`** from `.env.docker` (default **5432**) map to the container.

6. **Run tests in a throwaway app container** (still SQLite per `phpunit.xml`): `make run-test-docker`

7. **Stop and remove containers** (and drop the named volume so Postgres data is wiped): `make down-docker` or `make down-v-docker` (both run `docker compose down -v` in this Makefile).

### Troubleshooting (Docker)

**App cannot reach Postgres**

- **`DB_HOST`** must be **`postgres`** (the Compose service name), not `127.0.0.1`, inside the app container.
- If Laravel still connects to **`127.0.0.1`**, clear cached config: **`php artisan config:clear`** (on the host or inside the container). A previous **`config:cache`** on the host can leave a stale **`bootstrap/cache/config.php`** on the bind-mounted project and override **`.env`**.

**Postgres `FATAL: password authentication failed for user "…"`**

This almost always means **the user or password Laravel uses does not match the cluster that already exists in the `pgdata` volume**, or **two env files disagree**.

1. **Two files, two consumers** — The **Postgres** container only reads **`POSTGRES_*`** from **[`.env.docker`](.env.docker)** (Compose `env_file`). **Laravel** reads **`DB_*`** from the bind-mounted **`.env`**. If you edit one and not the other (e.g. set `DB_USERNAME=newapp` in `.env` but leave `POSTGRES_USER=oldapp` in `.env.docker`), Postgres still runs as the old user while the app connects as the new one → authentication fails. **Fix:** make **`DB_USERNAME` / `DB_PASSWORD` / `DB_DATABASE`** in **`.env`** identical to **`POSTGRES_USER` / `POSTGRES_PASSWORD` / `POSTGRES_DB`** in **`.env.docker`**, or delete **`.env`** and restart so **[`scripts/docker-entrypoint.sh`](scripts/docker-entrypoint.sh)** copies **`.env.docker` → `.env`** (when `.env` is missing).

2. **Credentials are “baked in” at first boot** — When the **`pgdata`** named volume is created, the official Postgres image creates the superuser and database once. **Changing env vars later does not rewrite existing data.** If you change **`POSTGRES_USER`**, **`POSTGRES_PASSWORD`**, or **`POSTGRES_DB`**, you must recreate the volume so Postgres initializes again: **`docker compose down -v`** (or **`make down-docker`**), then **`docker compose up -d --build`**.

3. **Literal values in `.env.docker`** — **`POSTGRES_USER`**, **`POSTGRES_PASSWORD`**, and **`POSTGRES_DB`** must be **plain strings** in **`.env.docker`**. Do not use shell defaults such as **`${DB_USERNAME:-safwat}`** in that file: Compose’s **`env_file`** is not a shell and will not expand them reliably for the Postgres image, which can produce a mismatched or invalid setup.

**Quick checklist:** Compare **`DB_USERNAME`** in **`.env`** with **`POSTGRES_USER`** in **`.env.docker`** → if they differ, align them (or reset **`.env`**) → if you changed Postgres identity, run **`docker compose down -v`** before bringing the stack up again.

---

## Makefile targets

| Target | Description |
|--------|-------------|
| `make install` | Runs **`composer install`** — PHP dependencies for local development. |
| `make run` | Starts the API with **`./scripts/run.sh`** on the host (uses `.env`; default port **8010**). |
| `make test` | Runs **`php artisan test`** on the host; test DB is **SQLite `:memory:`** from `phpunit.xml`. |
| `make swagger` | Runs **`composer swagger`** to generate `storage/api-docs/api-docs.json` for Swagger UI. |
| `make run-docker` | **`docker compose up -d --build`** — Postgres + app in the background. |
| `make run-test-docker` | **`docker compose run --rm app php artisan test`** — same test suite inside the `app` image (ephemeral container). |
| `make down-docker` | **`docker compose down -v`** — stop stack and remove the Postgres volume. |
| `make down-v-docker` | Same as **`down-docker`** in this repo (both use `-v`). |
| `make logs-docker` | **`docker compose logs -f`** — follow container logs. |
| `make ps-docker` | **`docker compose ps`** — container status. |

Equivalent raw commands are shown in the table above where useful.

---

## Tests (what they cover)

Tests live under **`tests/`**; feature tests use **`RefreshDatabase`**. PHPUnit is configured for **SQLite in-memory** so CI and local runs do not require MySQL/Postgres.

| Suite / file | What it verifies |
|--------------|------------------|
| **`tests/Feature/TransferApiTest.php`** | **Ingest:** `inserted` / `duplicates` counts; **idempotency** (duplicate `event_id` does not change approved totals); **ordering** of events (out-of-order and reverse order same totals); **concurrency-style** duplicate post ends with a single row; **per-station live `summary`** JSON; **validation** fail-fast `400` and whitespace-only strings; **duplicate `event_id`s inside one batch** counted once. |
| **`tests/Feature/StationSummaryReportApiTest.php`** | **`summary-report`** returns a **cached** `station_summaries` row when present; when the cache row is **missing** (listener faked), **`summary-report`** matches the **live** `summary` payload. |
| **`tests/Unit/StationSummaryResourceTest.php`** | **`StationSummaryResource`** exposes `station_id`, amounts, and counts in its array shape. |

**Commands:** `make test` (host) or `make run-test-docker` (inside Docker).

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

Live summary:

```bash
curl -sS http://localhost:8010/api/v1/stations/S1/summary
```

Cached summary report (from `station_summaries` when present):

```bash
curl -sS http://localhost:8010/api/v1/stations/S1/summary-report
```

## Project structure

- `App\Contracts\Repositories\TransferEventRepositoryInterface` — persistence port
- `App\Repositories\DatabaseTransferEventRepository` — Laravel query builder (MySQL in production; any PDO driver Laravel supports)
- `App\Services\Transfer\TransferIngestionService` — ingestion use case
- `App\Services\Station\StationSummaryService` — live summary use case
- `App\Models\StationSummary` — persisted per-station aggregates (`station_summaries`), used by `GET .../summary-report`
- `App\Http\Controllers\Api\V1\*` — HTTP adapters

## Security

API routes under **`/api/v1`** use Laravel **`throttle:100,1`**: up to **100 requests per minute** per IP (see [`routes/api.php`](routes/api.php)). Over the limit returns **429**. This is basic abuse protection only, not authentication.
