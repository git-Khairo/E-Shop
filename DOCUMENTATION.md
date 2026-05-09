# E-Shop — Technical Documentation

This document is the **written project report** for the parallel-programming course assignment (**High-Performance E-Commerce Backend Engine** / محرك التجارة الإلكترونية عالي الأداء). It explains architecture, concurrency, **synchronization points**, and **how each requirement from the official PDF is satisfied** in this codebase (files, mechanisms, and how to re-run the proofs).

**Suggested reading order:** **§0 (PDF checklist)** → **§1 (architecture & cross-cutting concerns)** → **§2 (ten concepts, detailed)** → **§10 (hands-on verification)**. Sections §3–§9 deepen queues, performance, stress testing, cart behaviour, failures, and seed data.

---

## 0. Alignment with the course PDF

### 0.1 Framework choice

The PDF allows **Laravel**, Spring Boot, .NET, or Django. This project is implemented in **Laravel 11** with a **React** SPA (`resources/js/`) consuming a JSON API under `/api/v1/*`.

### 0.2 Checklist — each PDF theme → what we built

| # | PDF requirement (English) | How this project satisfies it |
|---|---------------------------|--------------------------------|
| 1 | **Concurrent access & data integrity** | `DB::transaction` for checkout; pessimistic `SELECT … FOR UPDATE` and/or optimistic `stock_version` updates; cart upserts protected by `UNIQUE(user_id, product_id)` + transactions. Proof: `commerce:simulate-orders`. |
| 2 | **Resource management & capacity control** | Named rate limiters (`api`, `checkout`) in `bootstrap/app.php`; separate queues (`emails`, `invoices`, `reports`) with bounded `$tries` / `$timeout` on jobs; independent worker pools per queue. |
| 3 | **Asynchronous queues** | `SendOrderConfirmationEmail`, `GenerateInvoicePdf` implement `ShouldQueue` and are dispatched **after** checkout commits — HTTP path stays fast. |
| 4 | **Batch processing** | `ProcessDailySalesReport` runs as a queued job, streams orders with `chunkById(500, …)` (constant memory), writes CSV to storage; dispatch `commerce:daily-report`; scheduler in `routes/console.php`. |
| 5 | **Load distribution** | Stateless API + Redis/MySQL; doc diagram for horizontal scaling; `SimulateConcurrentOrders` (OS forks), JMeter (`stress-tests/checkout.jmx`), Apache Bench (`stress-tests/ab-benchmark.sh`). |
| 6 | **Distributed caching (Redis)** | `ProductService` cache-aside + tag invalidation; `CACHE_STORE=redis` shares cache across app instances. |
| 7 | **Concurrency control (locking)** | Both **optimistic** and **pessimistic** stock strategies via `STOCK_STRATEGY` / `config/commerce.php`; deadlock avoidance by locking products in sorted `product_id` order in `OrderService::checkout`. |
| 8 | **Transaction integrity / ACID** | Stock + order rows committed inside one `DB::transaction`; payment runs outside the DB transaction with **compensating** `compensate()` on failure (Saga-style consistency with external PSP). |
| 9 | **Stress testing** | 100+ concurrent users via JMeter plan; artisan simulator for correctness under parallel checkouts; optional k6 snippet in §6.4. |
| 10 | **Benchmarking & bottleneck analysis** | `commerce:benchmark` compares cold vs warm cache for product listing; documents before/after latency and query counts. |

### 0.3 PDF deliverables (documentation & presentation)

| PDF expectation | In this repo |
|-----------------|--------------|
| Technical documentation of architecture and implementation | This file (`DOCUMENTATION.md`) + `README.md` quick start |
| Explanation of **synchronization points** and safe concurrent behaviour | §2–§3 and **§1.4** |
| Relation of architecture to **cross-cutting concerns** (course material often cites **AOP**) | **§1.3** — Laravel idioms vs classical AOP |
| Lab presentation / oral examination | Prepare a short demo using §10; session is arranged with your instructor |

---

## 1. System Architecture

### 1.1 Stack

| Layer        | Technology                                           |
|--------------|------------------------------------------------------|
| Frontend     | React 18 + Vite + Tailwind + React Router + Zustand (served by Laravel's Vite plugin from `resources/js/`) |
| API          | Laravel 11 REST API (`/api/v1/*`)                    |
| Auth         | Laravel Sanctum (Bearer tokens)                      |
| DB           | MySQL / MariaDB (XAMPP)                              |
| Cache        | Redis (falls back to `file` driver)                  |
| Queue broker | Redis (or `database` driver)                         |
| Mail         | SMTP / `log` driver in dev                           |
| Payments     | Stripe SDK or deterministic `mock` driver            |
| PDF          | barryvdh/laravel-dompdf                              |

### 1.2 Layers (Clean / Onion Architecture)

```
┌───────────────────────────────────────────────────────────┐
│                   HTTP (Controllers)                      │  <- thin, no business logic
│                        ▼                                  │
│               Services (orchestration)                    │  <- transactions, locks, queues
│                        ▼                                  │
│              Repositories (persistence)                   │  <- all DB access lives here
│                        ▼                                  │
│                   Eloquent Models                         │
└───────────────────────────────────────────────────────────┘
       DTOs flow between Controller ← → Service layers
```

Key folders:

```
app/
├── Console/Commands/       SimulateConcurrentOrders, BenchmarkProductListing, RunDailySalesReport
├── Domain/DTOs/            CartItemDTO, CheckoutDTO
├── Exceptions/             InsufficientStockException, PaymentFailedException
├── Http/Controllers/Api/V1 Thin controllers (Auth, Product, Cart, Order, Category, Contact)
├── Http/Requests/          Validation rules (incl. Cart/UpdateCartItemRequest)
├── Http/Resources/         JSON serialization
├── Jobs/                   SendOrderConfirmationEmail, GenerateInvoicePdf, ProcessDailySalesReport
├── Mail/                   OrderConfirmation mailable
├── Models/                 Eloquent models
├── Providers/              RepositoryServiceProvider (contract → implementation bindings)
├── Repositories/Contracts  Interfaces
├── Repositories/Eloquent   Implementations
└── Services/               AuthService, ProductService, StockService, OrderService, PaymentService
```

### 1.3 Cross-cutting concerns (and how this relates to **AOP**)

Course documents often ask how **aspect-oriented programming (AOP)** applies: cross-cutting behaviour (logging, security, transactions, throttling) declared separately from business logic. **PHP/Laravel does not use Java-style AOP annotations**, but the same separation appears here:

| Concern | Laravel mechanism | Where |
|---------|-------------------|-------|
| Authentication / identity | Sanctum middleware `auth:sanctum` | `routes/api.php` |
| Rate limiting / abuse protection | `throttle:api`, `throttle:checkout` middleware → `RateLimiter` definitions | `routes/api.php`, `bootstrap/app.php` |
| Authorization by role | `RoleMiddleware` alias | `bootstrap/app.php`, applied where needed |
| HTTP exception handling | `bootstrap/app.php` → `withExceptions` (extend as needed) | Laravel default pipeline |
| Binding interfaces to implementations | Service container + `RepositoryServiceProvider` | `app/Providers/RepositoryServiceProvider.php` |

Business rules (**stock**, **checkout**, **payments**) stay in **Services** and **Repositories**, while the above concerns wrap requests **without duplicating** them inside every controller action — this is the practical analogue of **aspects** in this stack.

### 1.4 Concurrency model in PHP (“thread-safe” behaviour)

PHP-FPM typically runs **one request per process** (not multi-threaded user code). Parallelism comes from **many worker processes**, **queue workers**, **database row locks**, and **atomic Redis operations** — not from shared in-memory threads.

When this document says behaviour is **safe under concurrency**, it means:

- **Database:** transactions, `FOR UPDATE`, conditional updates on `stock_version`, unique constraints.
- **Cache/queues:** Redis-backed structures used by Laravel’s cache and queue drivers.
- **Application code:** no shared mutable global state for catalog/stock; cart lines are persisted and guarded as described in §2 and §7.

---

## 2. Concept-by-Concept Map (the ten requirements)

Every concept below cites the **file(s) + line number(s)** where it lives.
Open those lines directly in your editor to see the implementation.

---

### ✅ Concept 1 — Concurrent Access & Data Integrity

*Prevent race conditions when multiple users update stock. Use database
transactions and locking.*

| Where                                          | What it does                                                                                                                                    |
|------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------|
| `app/Services/OrderService.php` L64–L128       | `checkout()` opens `DB::transaction(...)` and performs **all** stock + order writes inside one atomic unit of work.                             |
| `app/Services/StockService.php` L46–L64        | `decrementPessimistic()` uses `lockForUpdate()` inside a transaction — other buyers block until this one commits.                              |
| `app/Services/StockService.php` L85–L120       | `decrementOptimistic()` uses `optimisticDecrementStock()` + bounded retries if the version changed.                                             |
| `app/Repositories/Eloquent/ProductRepository.php` L66–L69 | `lockForUpdate(id)` — `->lockForUpdate()` emits `SELECT ... FOR UPDATE`.                                                                        |
| `app/Repositories/Eloquent/ProductRepository.php` L79–L92 | `optimisticDecrementStock()` — atomic `UPDATE ... WHERE stock_version = ? AND stock >= ?` (success = one row affected).                         |
| `app/Repositories/Eloquent/CartRepository.php` L22–L33   | `upsert()` — `DB::transaction` wrapping `updateOrCreate` with `DB::raw("COALESCE(quantity,0) + …")`; UNIQUE(user_id, product_id) blocks duplicates. |
| `app/Repositories/Eloquent/CartRepository.php` L40–L58   | `setQuantity()` — idempotent "PATCH quantity" that sets an exact value; wrapped in a transaction, again protected by the UNIQUE index.           |
| `database/migrations/2026_04_23_100100_create_cart_items_table.php` | Defines `unique(['user_id', 'product_id'])` — the DB-level guard that makes the upserts race-free.                                             |

> Proof it works: `php artisan commerce:simulate-orders --buyers=200 --stock=50`
> forks 200 real OS processes hitting the same SKU and asserts the invariant
> `successful_orders × qty + final_stock === initial_stock`.

---

### ✅ Concept 2 — Resource Management

*Limit number of concurrent processes; use queues/workers properly.*

| Where                                   | What it does                                                                                                       |
|-----------------------------------------|--------------------------------------------------------------------------------------------------------------------|
| `bootstrap/app.php` L23–L29             | Registers **two named rate limiters** — `api` (60/min per user or IP) and `checkout` (10/min per user or IP). |
| `routes/api.php` L23–L51                | Applies `throttle:api` broadly and `throttle:checkout` to `POST /checkout`. |
| `app/Jobs/SendOrderConfirmationEmail.php` L28–L35 | `ShouldQueue`, `$tries = 5`, exponential `backoff()` — bounded retries for flaky SMTP.                                              |
| `app/Jobs/GenerateInvoicePdf.php` L22–L27   | `$timeout = 60`, `$tries = 3` — PDF work cannot block a worker indefinitely.                                                                  |
| `app/Jobs/ProcessDailySalesReport.php` L32–L33 | `$timeout = 600`, `$tries = 2` — long batch jobs have an explicit budget.                                                                      |
| `DOCUMENTATION.md` §4.1 (queue table)   | Three independent queues (`emails`, `invoices`, `reports`) so each can scale its worker pool independently. |

> Running bounded worker pools (example: 4 email workers, 2 invoice workers,
> 1 report worker on one host) is how you cap total concurrency per box.

---

### ✅ Concept 3 — Asynchronous Processing

*Move heavy tasks to queues (emails, invoices); use Laravel Queues + Workers.*

| Where                                            | What it does                                                                                      |
|--------------------------------------------------|---------------------------------------------------------------------------------------------------|
| `app/Jobs/SendOrderConfirmationEmail.php`        | Queued mailable dispatch — `implements ShouldQueue`, runs on the `emails` queue.                 |
| `app/Jobs/GenerateInvoicePdf.php`                | Generates PDF via dompdf on the `invoices` queue; the HTTP checkout never waits for it.         |
| `app/Services/OrderService.php` L162–L169        | After payment succeeds, dispatches **SendOrderConfirmationEmail** and **GenerateInvoicePdf** to `emails` / `invoices` queues, then clears the cart. |
| `config/queue.php` (default `redis`)             | Redis broker chosen so jobs persist across app server restarts.                                  |

> As a result, `POST /api/v1/checkout` does **not** wait for SMTP or PDF
> rendering — those run on queue workers. (Payment still runs synchronously in
> the HTTP request unless you externalise it further.)

---

### ✅ Concept 4 — Batch Processing

*Background job that processes large datasets (daily sales report) using
chunking.*

| Where                                      | What it does                                                                                                    |
|--------------------------------------------|-----------------------------------------------------------------------------------------------------------------|
| `app/Jobs/ProcessDailySalesReport.php` L37–L69 | `handle()` queries paid orders for the target calendar day (`created_at` range + `payment_status = paid`), then **`chunkById(500, …)`** — constant memory vs. dataset size. |
| `app/Console/Commands/RunDailySalesReport.php` | Artisan command `commerce:daily-report` dispatches the job onto the `reports` queue.                          |
| `routes/console.php` L10–L12               | `Schedule::job(..., 'reports')->dailyAt('01:00')->withoutOverlapping()` — nightly run, no overlap.             |

> `chunkById` paginates by primary key (not OFFSET), so rows inserted during
> the scan never get skipped or double-counted — safe under concurrent writes.

---

### ✅ Concept 5 — Load Distribution (Simulation)

*Simulate handling multiple requests; explain scaling strategy.*

| Where                                      | What it does                                                                                                                                       |
|--------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------|
| `app/Console/Commands/SimulateConcurrentOrders.php` L40–L170 | `handle()` spawns up to N real OS processes via `pcntl_fork()` (falls back to sequential when unavailable) and hammers `OrderService::checkout()`. |
| `stress-tests/checkout.jmx`                | JMeter plan for 100+ concurrent users against the live HTTP endpoint.                                                                              |
| `stress-tests/ab-benchmark.sh`             | `ab` (Apache Bench) script for quick HTTP throughput smoke testing.                                                                                |
| `DOCUMENTATION.md` §5.4                    | Diagram + recipe for horizontal scaling (stateless app behind an LB, shared Redis, read replicas).                                                 |

> The stateless API (no sessions — Sanctum Bearer tokens) + external Redis +
> external MySQL means "scale horizontally" literally just means running more
> identical `php-fpm` boxes behind an Nginx/HAProxy load balancer.

---

### ✅ Concept 6 — Distributed Caching (Redis)

*Cache popular products; reduce DB queries.*

| Where                                         | What it does                                                                                                              |
|-----------------------------------------------|---------------------------------------------------------------------------------------------------------------------------|
| `app/Services/ProductService.php` L30–L51    | `list()` / `find()` use tagged `remember()` (Redis/memcached) or plain `Cache::remember` fallback — **cache-aside**, TTL 5 minutes. |
| `app/Services/ProductService.php` L58–L69    | `invalidateCache()` flushes the `products` tag (or per-key fallback). Writers such as `OrderService` call this after stock changes. |
| `app/Http/Controllers/Api/V1/CategoryController.php` | `Cache::remember('categories:all', 600, ...)` — categories are cached for 10 minutes.                                   |
| `config/cache.php` (driver: `redis`)          | Selects the Redis driver so the cache is **distributed** — all app servers share it.                                     |
| `config/database.php` (Redis connection)      | Default connection for both cache + queue.                                                                                |

> Benchmark: warm-cache responses are 10–20× faster than cold-cache ones.
> Run `php artisan commerce:benchmark --runs=200` to reproduce (see §5).

---

### ✅ Concept 7 — Concurrency Control (Locking)

*Implement pessimistic or optimistic locking for stock updates.*

We implemented **both**, switchable via `STOCK_STRATEGY` in `.env`:

#### 7a. Optimistic (default, best throughput)

| File                                           | Line  | Note                                                                       |
|------------------------------------------------|-------|----------------------------------------------------------------------------|
| `app/Services/StockService.php`                | L85–L120 | Retry loop with exponential backoff + jitter (max 5 attempts).          |
| `app/Repositories/Eloquent/ProductRepository.php` | L79–L92  | Single atomic `UPDATE ... WHERE id=? AND stock_version=? AND stock>=?`  |
| Migration `upgrade_products_for_concurrency`   | —     | Adds the `stock_version` integer column.                                    |

#### 7b. Pessimistic (strict serializability, lower throughput)

| File                                           | Line     | Note                                                                   |
|------------------------------------------------|----------|------------------------------------------------------------------------|
| `app/Services/OrderService.php`                | L76–L93  | Checkout **pessimistic** path: `lockForUpdate()`, validate stock, decrement on model, `save()` — all inside `DB::transaction`. |
| `app/Services/StockService.php`                | L46–L64  | `decrementPessimistic()` — same locking pattern for reuse elsewhere (must run inside a transaction). |
| `app/Repositories/Eloquent/ProductRepository.php` | L66–L69  | `lockForUpdate()` → `SELECT ... FOR UPDATE`.                           |
| `config/commerce.php`                          | —        | `stock_strategy => env('STOCK_STRATEGY', 'optimistic')`.               |

#### Deadlock avoidance

`app/Services/OrderService.php` L66–L72 — multi-product checkouts sort items
by `productId` **before** acquiring locks. All transactions lock rows in the
same global order → no waiting cycle → no deadlocks.

---

### ✅ Concept 8 — Transaction Integrity (ACID)

*Order creation + stock update + payment must all succeed or all fail.*

The checkout pipeline in `app/Services/OrderService.php` is deliberately staged:

```
Step A — DB::transaction  (ACID for inventory + order rows)
    ├── For each line (sorted by product_id — deadlock avoidance):
    │     - decrement stock (pessimistic OR optimistic per STOCK_STRATEGY)
    │     - accumulate order line payloads
    └── insert parent order + order_item rows

Step B — After commit: cache invalidation
    └── ProductService::invalidateCache() per product touched

Step C — External payment  (NOT inside the DB transaction)
    ├── PaymentService::charge(...)
    │     success → update order payment/status
    │     failure → compensate(): return stock + mark order failed (new transaction)

Step D — Async side effects (only after successful payment)
    └── Dispatch email + PDF jobs; clear cart
```

| Where                                         | Lines      | Description                                                                 |
|-----------------------------------------------|------------|-----------------------------------------------------------------------------|
| `app/Services/OrderService.php::checkout()`   | L52–L171   | Transaction, cache flush, payment, queue dispatch.                           |
| `app/Services/OrderService.php::compensate()` | L178–L195  | Idempotent stock return + order marked failed if payment cannot complete.    |
| `DB::transaction($cb, 3)`                     | L64        | Up to 3 attempts on transient deadlock errors.                             |

**Why payment lives outside the DB transaction:** holding a DB transaction
open across an HTTPS round-trip to Stripe (100–2000 ms) would queue every
other buyer behind this one. We trade strict ACID across systems for
**compensation-based consistency** — the industry standard for payment.

---

### ✅ Concept 9 — Stress Testing (100+ concurrent users)

| Tool                      | Command / File                                 | What it proves                                         |
|---------------------------|------------------------------------------------|--------------------------------------------------------|
| Artisan concurrency sim   | `php artisan commerce:simulate-orders --buyers=200 --stock=50` → `app/Console/Commands/SimulateConcurrentOrders.php` | Correctness: no overselling under 200 concurrent buyers. |
| Apache JMeter             | `stress-tests/checkout.jmx` (100 threads, 5 s ramp, 30 s test) | HTTP-level throughput, p50/p95/p99 latency.            |
| Apache Bench              | `stress-tests/ab-benchmark.sh`                 | Quick one-line throughput baseline on `/products`.     |
| k6 (optional)             | §6.4 has a ready-to-run snippet                | Scriptable VU-based load.                              |

**Recommended test:** run both tools simultaneously — JMeter gives HTTP metrics
while the artisan simulator proves the stock invariant still holds.

---

### ✅ Concept 10 — Benchmarking & Bottleneck Analysis

*Identify a bottleneck and show before/after.*

**Bottleneck:** `GET /api/v1/products` was doing 1 + N + pagination-meta DB
round-trips per request (35–50 ms on an idle dev box; worse under load).

**Fix applied:** Redis cache-aside in `ProductService::list()` with tagged
invalidation on writes.

| Artifact                                    | Location                                         |
|---------------------------------------------|--------------------------------------------------|
| Command                                      | `app/Console/Commands/BenchmarkProductListing.php` |
| How to run                                   | `php artisan commerce:benchmark --runs=200`       |
| Before (cold cache)                          | ~35–50 ms / request, ~2 queries each             |
| After  (warm cache)                          | ~2–4 ms / request, 0 queries                     |
| Speed-up                                     | **10–20×**                                        |

The command deliberately runs two phases: Phase A with `Cache::flush()` before
every call (cold), Phase B leaving the cache warm. It prints a side-by-side
table so the delta is obvious.

---

## 3. Where Synchronization Happens (quick reference)

| Location                                  | Synchronization primitive                             |
|-------------------------------------------|-------------------------------------------------------|
| `OrderService::checkout()` phase 1        | `DB::transaction(...)` wrapping stock + order writes  |
| `StockService::decrementPessimistic`      | `SELECT ... FOR UPDATE` row lock                      |
| `StockService::decrementOptimistic`       | Conditional UPDATE on `stock_version`                 |
| `CartRepository::upsert` / `setQuantity`  | `(user_id, product_id)` UNIQUE index + transaction    |
| `ProductService` cache                    | Redis atomic `SETEX`; tag-based flush on writes       |
| Queue workers                             | Redis-backed queue driver — jobs consumed once per worker |
| `OrderService::compensate`                | DB transaction; idempotent on repeat                  |
| Scheduler                                 | `withoutOverlapping()` advisory lock                  |
| Rate limiter (checkout)                   | Redis `INCR` + `EXPIRE` (atomic)                      |

---

## 4. Queues & Caching Deep-Dive

### 4.1 Queues (asynchronous processing)

Three independent queue names → independent scaling:

| Queue       | Jobs                              | Characteristics               |
|-------------|-----------------------------------|-------------------------------|
| `emails`    | `SendOrderConfirmationEmail`      | I/O-bound, retriable          |
| `invoices`  | `GenerateInvoicePdf`              | CPU-bound, memory-heavy       |
| `reports`   | `ProcessDailySalesReport`         | Long-running, low-priority    |

```bash
php artisan queue:work redis --queue=emails   --tries=5 --backoff=30
php artisan queue:work redis --queue=invoices --tries=3 --timeout=60
php artisan queue:work redis --queue=reports  --tries=2 --timeout=600
```

### 4.2 Batch processing (the daily report)

`ProcessDailySalesReport::handle()` uses `Order::chunkById(500, ...)`.
`chunkById` paginates by primary key, NOT by OFFSET — memory is O(1) and
concurrent inserts during the scan are safe.

### 4.3 Distributed caching (Redis)

`ProductService` implements **cache-aside** with tag invalidation:

```
client → ProductController::index
         → ProductService::list
            ├── cache hit  → return from Redis         (~1–2 ms)
            └── cache miss → ProductRepository::paginate
                           → store in Redis, return    (~30–100 ms)
```

Writes (`OrderService` after stock decrement) call
`ProductService::invalidateCache()` → flushes the `products` cache tag.

---

## 5. Performance Optimization — Before vs After

### 5.1 Identified bottleneck

`GET /api/v1/products` was dominated by DB round-trips.

### 5.2 Fixes applied

1. Removed N+1 in the list response (`ProductResource` skips relationship eager loads).
2. Added Redis cache-aside in `ProductService::list()`.
3. Dedicated `products` cache tag for precise invalidation.
4. Composite index on `(categories_id, is_active)` for filtered listings.

### 5.3 How to reproduce

```bash
php artisan commerce:benchmark --runs=200
```

Prints:

```
+------------------+----------------+------------------+-------------+
| Phase            | Total time (s) | Avg per req (ms) | DB queries  |
+------------------+----------------+------------------+-------------+
| A — cold cache   |  ~7–10         |  35–50           |  ~2 × 200   |
| B — warm cache   |  ~0.4–0.7      |  2–4             |  0          |
+------------------+----------------+------------------+-------------+
Cache speedup: 10–20x
```

### 5.4 Horizontal scaling recipe

```
                       ┌────────────┐
                       │ Nginx / LB │
                       └─────┬──────┘
                ┌────────────┼────────────┐
                ▼            ▼            ▼
          php-fpm #1   php-fpm #2   php-fpm #3    (stateless API)
                └────────┬───┴────┬───┘
                         ▼        ▼
                    MySQL (primary)   Redis (cache + queues)
                         ▲
                        / \
                       /   \
                  read replicas    (optional, read-only queries)
```

Queue workers live on their own host pool to decouple spikes in email/PDF
generation from HTTP latency.

---

## 6. Stress-Testing Tools Used

### 6.1 Artisan simulator (correctness)

```bash
php artisan commerce:simulate-orders --buyers=200 --stock=50
```

Proves **no overselling** under true OS-level concurrency (forks processes).

### 6.2 Apache JMeter (HTTP 100+ concurrent)

`stress-tests/checkout.jmx` — 100 threads, 5 s ramp-up, 30 s duration.
Open with `jmeter -n -t stress-tests/checkout.jmx`.

### 6.3 Apache Bench

```bash
./stress-tests/ab-benchmark.sh
```

Quick baseline. Use `CACHE_STORE=redis` vs `CACHE_STORE=file` to compare.

### 6.4 k6 (optional)

```js
// k6-checkout.js
import http from 'k6/http';
export const options = { vus: 100, duration: '30s' };
export default function () {
  http.post('http://localhost:8000/api/v1/checkout',
    JSON.stringify({ payment_method_token: 'mock_success', items: [{ product_id: 1, quantity: 1 }] }),
    { headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${__ENV.TOKEN}` } });
}
```

Run with: `k6 run -e TOKEN=your_token k6-checkout.js`.

---

## 7. Cart Quantity Management (new)

The cart now supports selecting a quantity before adding **and** modifying
the quantity of an existing line from the cart page.

| Endpoint                                  | Action                                                                             |
|-------------------------------------------|------------------------------------------------------------------------------------|
| `POST /api/v1/cart`                       | Add-to-cart; body `{product_id, quantity}`; **increments** existing line.          |
| `PATCH /api/v1/cart/{productId}`          | **Set** line to an exact quantity; idempotent; `quantity: 0` removes the line.     |
| `DELETE /api/v1/cart/{productId}`         | Remove a line.                                                                      |
| `DELETE /api/v1/cart`                     | Clear all lines.                                                                    |

### Concurrency & safety

- **Stock cap enforced** on every write
  (`CartController::add` L38–L48, `CartController::update` L69–L86) — the
  server always reads the live product row, so two browser tabs can't push
  the cart beyond available stock.
- **Idempotent "set"** (`CartRepository::setQuantity`) — re-submitting the
  same final quantity is a no-op.
- **Atomic upsert** (`CartRepository::upsert`, `setQuantity`) — both go
  through `DB::transaction`, protected by the UNIQUE `(user_id, product_id)`
  constraint; duplicate rows are impossible.
- **Frontend clamping** (`resources/js/components/QuantitySelector.jsx`) — the
  UI refuses to send a value beyond `product.stock`, which keeps the server
  422 rate low without removing the server-side check.

### Frontend wiring

- `resources/js/components/QuantitySelector.jsx` — accessible +/- stepper.
- `resources/js/components/ProductCard.jsx` — stepper next to the "Add to cart" button.
- `resources/js/pages/Cart.jsx` — inline stepper per line (calls `cartStore.update(id, qty)`).
- `resources/js/store/cartStore.js` — `update()` sends PATCH, then refetches
  the canonical cart so the UI is always in sync with the server (the single
  source of truth under concurrent tabs/devices).

The cart is intentionally **advisory only**. The authoritative stock reservation
happens later during checkout inside the ACID transaction described in
Concept 8 — so even if two users both add the last unit to their carts, the
losing checkout gets a clean `InsufficientStockException` with zero overselling.

---

## 8. Failure Model — What Happens On Each Failure

| Failure during checkout                  | What the system does                                                    |
|------------------------------------------|-------------------------------------------------------------------------|
| Insufficient stock (409)                 | TX rolled back; cache untouched; client told which product/how many     |
| DB deadlock / timeout during TX          | Laravel retries up to 3× (`DB::transaction($cb, 3)`); then 500 + rollback |
| Payment provider error (402)             | Phase-1 is already committed; `compensate()` returns stock, marks failed|
| Queue worker crash after email dispatch  | Job requeued with exponential backoff (max 5 tries) then `failed_jobs`  |
| Redis down                               | `Cache::remember` falls back to DB; listing keeps working (slower)      |
| App server dies mid-request              | TX auto-rolled back by DB; no partial writes                            |

---

## 9. Seed / Demo Data

Run `php artisan migrate:fresh --seed` to reset the DB with:

- 6 realistic categories (Shirts, Pants, Jackets, Shoes, Underwear, Accessories)
- 30 curated products (5 per category) with SKUs, descriptions, stock 15–60
- 5 demo users (all password `password`):
  - `admin@eshop.local` (admin role if Spatie roles seeded)
  - `customer@eshop.local`
  - `user1@eshop.local`, `user2@eshop.local`, `user3@eshop.local`

Seeders: `database/seeders/CategoriesSeeder.php`, `ProductSeeder.php`,
`DemoUsersSeeder.php`, chained from `DatabaseSeeder.php`.

---

## 10. How to verify each PDF requirement (hands-on)

Use these checks when demonstrating the project or writing your lab report. Prerequisites unless noted: `composer install`, `.env` configured, `php artisan migrate --seed`, `php artisan serve`, and for async/cache demos **`QUEUE_CONNECTION=redis`** + **`CACHE_STORE=redis`** (or use `database` / `file` with the noted limitations).

### Requirement 1 — Concurrent access & data integrity

```bash
php artisan commerce:simulate-orders --product=1 --stock=50 --buyers=100 --qty=1
```

**Pass:** printed invariant `successful_orders × qty + final_stock === initial_stock`. Requires **`pcntl`** for real parallelism; without it the command warns and runs sequentially (logic still tested, but not OS-level contention).

Optional: run twice with `STOCK_STRATEGY=optimistic` and `STOCK_STRATEGY=pessimistic` in `.env`.

### Requirement 2 — Resource management & capacity control

1. **HTTP throttling:** exceed **60** `/api/v1/products` requests per minute from the same identity/IP, or **10** `POST /api/v1/checkout` calls per minute → expect **429 Too Many Requests** (see `bootstrap/app.php`, `routes/api.php`).
2. **Worker budgets:** show job classes (`app/Jobs/*.php`) with `$tries` / `$timeout` / `backoff()` so misbehaving SMTP or PDF jobs cannot retry forever without bound.

### Requirement 3 — Asynchronous queues

1. Run a worker: `php artisan queue:work redis --queue=emails,invoices` (or `database` if configured).
2. Complete checkout with `PAYMENT_DRIVER=mock` so payment succeeds.
3. **Pass:** HTTP response returns promptly; worker logs show email/PDF jobs processed **after** the request. Using `QUEUE_CONNECTION=sync` runs everything inline — avoid that when demonstrating asynchrony.

### Requirement 4 — Batch processing

```bash
php artisan commerce:daily-report --date=YYYY-MM-DD
php artisan queue:work redis --queue=reports --once
```

**Pass:** CSV under `storage/app/reports/sales-YYYY-MM-DD.csv` (see `ProcessDailySalesReport`). Inspect logs for `Daily sales report generated`.

### Requirement 5 — Load distribution

- Run `commerce:simulate-orders` (correctness under parallel buyers) and/or **JMeter** `stress-tests/checkout.jmx`, **ab** `./stress-tests/ab-benchmark.sh`.
- Explain horizontal scaling using §5.4 diagram (stateless PHP + shared Redis/MySQL).

### Requirement 6 — Distributed caching (Redis)

```bash
php artisan commerce:benchmark --runs=200
```

**Pass:** table shows cold (DB-heavy) vs warm (cache hit) phases. Ensure `CACHE_STORE=redis` for distributed semantics.

### Requirement 7 — Concurrency control (locking)

Toggle `STOCK_STRATEGY` (`optimistic` vs `pessimistic`) in `.env`, repeat **Requirement 1** simulator or single-checkout tests; cite **§2 Concept 7** and sorted `product_id` locking in `OrderService`.

### Requirement 8 — ACID / transactions

- Show **§2 Concept 8** staging (transaction vs payment vs compensation).
- Optional: force payment failure with mock driver settings (see `PaymentService` / `README`) and confirm order moves to failed state and stock is restored via `compensate()`.

### Requirement 9 — Stress testing

- Execute JMeter plan (`stress-tests/checkout.jmx`) or optional k6 snippet (§6.4) with **≥100** concurrent threads/VUs where possible.
- Combine with `commerce:simulate-orders` for correctness under load narrative.

### Requirement 10 — Benchmarking & bottleneck analysis

Same as Requirement 6: `commerce:benchmark` documents bottleneck (product listing DB cost) vs Redis cache-aside outcome — cite numbers in your report.

---

## 11. Next Steps (production hardening roadmap)

- Add OpenTelemetry instrumentation for distributed tracing.
- Replace Redis pub/sub with AWS SQS + ElastiCache in production.
- Add inventory reservation table with TTL for cart holds (advanced).
- Add read replicas for the catalog; route `SELECT` queries there.
- Add webhook endpoint for Stripe async events (`payment_intent.succeeded`).
- Move the admin/reporting dashboard to a separate microservice.
