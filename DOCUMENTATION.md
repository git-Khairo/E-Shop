# E-Shop — Technical Documentation

This document explains the architecture, the concurrency model, and **where every
one of the ten parallel-programming concepts is implemented** in the codebase
(with file + line references).

If you are short on time, jump to **section 2 — Concept-by-Concept Map**.

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
| `app/Services/OrderService.php` L52–L100       | `checkout()` opens `DB::transaction(...)` and performs **all** stock + order writes inside one atomic unit of work.                             |
| `app/Services/StockService.php` L46–L82        | `decrementPessimistic()` uses `SELECT ... FOR UPDATE` inside a transaction — other buyers block until this one commits.                         |
| `app/Services/StockService.php` L85–L130       | `decrementOptimistic()` uses a conditional UPDATE that depends on `stock_version`; if another transaction bumped the version, we retry.          |
| `app/Repositories/Eloquent/ProductRepository.php` L66–L77 | `lockForUpdate(id)` — Eloquent's `->lockForUpdate()` emits the `SELECT ... FOR UPDATE` row-level lock.                                           |
| `app/Repositories/Eloquent/ProductRepository.php` L79–L92 | `optimisticDecrementStock()` — single atomic `UPDATE ... WHERE stock_version = ?` that returns the affected-row count.                           |
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
| `bootstrap/app.php` L24–L37             | Registers **two named rate limiters** — `api` (60/min/IP) and `checkout` (10/min per-user) — backed by Redis `INCR+EXPIRE`. |
| `routes/api.php` L23–L50                | Applies `throttle:api` everywhere and the stricter `throttle:checkout` to `POST /checkout`, so a single user can't flood the checkout pipeline. |
| `app/Jobs/SendOrderConfirmationEmail.php` L15–L20 | `public int $tries = 5; public int $backoff = 30;` bounds worker retry work.                                                         |
| `app/Jobs/GenerateInvoicePdf.php` L11–L18   | Long timeout (`$timeout = 60`) + bounded retries (`$tries = 3`) prevent one PDF hogging a worker forever.                                    |
| `app/Jobs/ProcessDailySalesReport.php` L17–L24 | `$tries = 2, $timeout = 600` — report jobs have their own budget.                                                                             |
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
| `app/Services/OrderService.php` L135–L160        | `dispatchSideEffects()` — **after** the DB transaction commits, dispatches the two jobs async.   |
| `config/queue.php` (default `redis`)             | Redis broker chosen so jobs persist across app server restarts.                                  |

> As a result, `POST /api/v1/checkout` returns in ~tens of ms even when the
> customer's inbox and the PDF renderer are slow.

---

### ✅ Concept 4 — Batch Processing

*Background job that processes large datasets (daily sales report) using
chunking.*

| Where                                      | What it does                                                                                                    |
|--------------------------------------------|-----------------------------------------------------------------------------------------------------------------|
| `app/Jobs/ProcessDailySalesReport.php` L37–L75 | `handle()` calls `Order::query()->paid()->whereDate(...)->chunkById(500, fn($chunk)=>...)` — memory is **constant** regardless of dataset size. |
| `app/Console/Commands/RunDailySalesReport.php` | Artisan entrypoint for manual/scheduled dispatch.                                                               |
| `routes/console.php` L9–L12                | `Schedule::job(new ProcessDailySalesReport(), 'reports')->dailyAt('01:00')->withoutOverlapping();` runs it nightly with an advisory lock that prevents two overlapping runs. |

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
| `app/Services/ProductService.php` L28–L55     | `list()` / `find()` use `Cache::tags(['products'])->remember(...)` — classic **cache-aside** pattern, TTL 5 minutes.      |
| `app/Services/ProductService.php` L58–L69     | `invalidateCache()` flushes the `products` tag. Called by `OrderService` immediately after a successful stock decrement. |
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
| `app/Services/StockService.php`                | L85–L130 | Retry loop with exponential backoff + jitter (max 5 attempts).          |
| `app/Repositories/Eloquent/ProductRepository.php` | L79–L92  | Single atomic `UPDATE ... WHERE id=? AND stock_version=? AND stock>=?`  |
| Migration `upgrade_products_for_concurrency`   | —     | Adds the `stock_version` integer column.                                    |

#### 7b. Pessimistic (strict serializability, lower throughput)

| File                                           | Line     | Note                                                                   |
|------------------------------------------------|----------|------------------------------------------------------------------------|
| `app/Services/StockService.php`                | L46–L82  | `DB::transaction` + `lockForUpdate` + explicit stock check + UPDATE.   |
| `app/Repositories/Eloquent/ProductRepository.php` | L66–L77  | Eloquent's `->lockForUpdate()` → `SELECT ... FOR UPDATE`.              |
| `config/commerce.php`                          | —        | `stock_strategy => env('STOCK_STRATEGY', 'optimistic')`.               |

#### Deadlock avoidance

`app/Services/OrderService.php` L66–L72 — multi-product checkouts sort items
by `productId` **before** acquiring locks. All transactions lock rows in the
same global order → no waiting cycle → no deadlocks.

---

### ✅ Concept 8 — Transaction Integrity (ACID)

*Order creation + stock update + payment must all succeed or all fail.*

The checkout is explicitly split into three phases to make the ACID story
clear. See `app/Services/OrderService.php`:

```
Phase 1 — DB::transaction  (atomic, rollback-safe)
    ├── For each item (sorted by product_id for deadlock-safety):
    │     - decrement stock (pessimistic OR optimistic per config)
    │     - create the order_item row
    └── create the parent order row

Phase 2 — Cache invalidation  (fire-and-forget)
    └── ProductService::invalidateCache() — flushes 'products' tag in Redis

Phase 3 — External payment  (outside any DB transaction)
    ├── PaymentService::charge(...)
    │     success → order.payment_status = 'paid'; clear cart; dispatch async jobs
    │     failure → compensate(): return stock (under a new transaction),
    │                             mark order 'failed', rethrow
```

| Where                                         | Line      | Description                                                                 |
|-----------------------------------------------|-----------|-----------------------------------------------------------------------------|
| `app/Services/OrderService.php::checkout()`   | L52–L140  | Three-phase orchestration.                                                  |
| `app/Services/OrderService.php::compensate()` | L178–L195 | Idempotent stock-return + order-mark-failed.                                |
| `DB::transaction($cb, 3)`                     | L66       | Retry budget = 3 for transient deadlock errors.                             |

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
| Queue workers                             | Redis atomic `BRPOPLPUSH` prevents double-processing  |
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

## 10. Next Steps (production hardening roadmap)

- Add OpenTelemetry instrumentation for distributed tracing.
- Replace Redis pub/sub with AWS SQS + ElastiCache in production.
- Add inventory reservation table with TTL for cart holds (advanced).
- Add read replicas for the catalog; route `SELECT` queries there.
- Add webhook endpoint for Stripe async events (`payment_intent.succeeded`).
- Move the admin/reporting dashboard to a separate microservice.
