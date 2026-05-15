# Parallel Programming Project Report
## High-Performance E-Commerce Backend Engine

**Course:** Parallel Programming - Semester 2026
**Technology:** Laravel 11 (PHP 8.2) + MySQL 8 + Redis 7 + Docker + Nginx

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Requirement 1: Concurrent Access & Data Integrity](#requirement-1-concurrent-access--data-integrity)
3. [Requirement 2: Resource Management & Capacity Control](#requirement-2-resource-management--capacity-control)
4. [Requirement 3: Asynchronous Queues](#requirement-3-asynchronous-queues)
5. [Requirement 4: Batch Processing](#requirement-4-batch-processing)
6. [Requirement 5: Load Distribution](#requirement-5-load-distribution)
7. [Wallet System](#wallet-system)
8. [Monitoring Dashboard](#monitoring-dashboard)
9. [Docker Infrastructure](#docker-infrastructure)
10. [How to Run & Test](#how-to-run--test)

---

## Architecture Overview

```
                         ┌──────────────────────────────────────────────┐
                         │              Docker Compose                  │
                         ├──────────────────────────────────────────────┤
                         │                                              │
    Client ──────▶  ┌─────────┐     ┌──────────┐ ┌──────────┐ ┌──────────┐
    (Browser)       │  Nginx  │────▶│  App 1   │ │  App 2   │ │  App 3   │
                    │  (LB)   │────▶│ PHP-FPM  │ │ PHP-FPM  │ │ PHP-FPM  │
                    │  :8080  │────▶│  w=3     │ │  w=1     │ │  w=2     │
                    └─────────┘     └────┬─────┘ └────┬─────┘ └────┬─────┘
                         │               │            │            │
                    ┌────┴────┐    ┌─────┴────────────┴────────────┘
                    │  Redis  │    │
                    │  :6379  │    │  Shared State:
                    │ (Cache) │    │  - Semaphore counters (Req 2)
                    │ (Queue) │    │  - Product cache (Req 6)
                    │ (Sema.) │    │  - LB counters (Req 5)
                    └─────────┘    │
                                   │
                    ┌──────────┐   │
                    │  MySQL   │◀──┘
                    │  :3306   │
                    │ Products │  Locking:
                    │ Orders   │  - SELECT FOR UPDATE (pessimistic)
                    │ Wallets  │  - WHERE version=? (optimistic)
                    │ Payments │  - ACID transactions
                    └──────────┘
                         │
              ┌──────────┴──────────┐
              │    Queue Workers    │
              ├────────┬───────┬────┤
              │ Emails │Invoice│ Reports │
              │ Worker │Worker │ Worker  │
              └────────┴───────┴─────────┘
```

### Key Design Decisions

| Decision | Choice | Justification |
|----------|--------|---------------|
| Language | PHP 8.2 + Laravel 11 | Rich ecosystem, built-in queue/cache/DB support |
| Database | MySQL 8.0 | ACID transactions, row-level locking (InnoDB) |
| Cache/Queue | Redis 7 | Atomic operations for semaphores, sub-ms cache reads |
| Load Balancer | Nginx | Industry standard, weighted upstream, health checks |
| Containerization | Docker Compose | Reproducible multi-service deployment |

---

## Requirement 1: Concurrent Access & Data Integrity

**File:** `app/Services/StockService.php`, `app/Repositories/Eloquent/ProductRepository.php`
**Test:** `tests/Feature/Req1_RaceConditionTest.php`

### The Problem: Race Condition (Lost Update)

When two users buy the same product simultaneously WITHOUT protection:

```
Timeline:
  t0: Stock = 10
  t1: Thread A reads stock → sees 10
  t2: Thread B reads stock → sees 10
  t3: Thread A writes: 10 - 1 = 9
  t4: Thread B writes: 10 - 1 = 9  ← OVERWRITES A's update!

Result: Stock = 9, but 2 units were sold → 1 unit not deducted = OVERSELLING
```

### BEFORE: No Locking (Broken Code)

```php
// DANGEROUS: Classic race condition
$product = Product::find($id);           // Read
if ($product->stock >= $qty) {           // Check
    $product->stock -= $qty;             // Modify
    $product->save();                    // Write
}
// Between Read and Write, another thread can modify the same row!
```

### AFTER: Solution A — Optimistic Locking (Preferred)

```php
// StockService::decrementOptimistic()
$affected = DB::table('products')
    ->where('id', $productId)
    ->where('stock_version', $expectedVersion)  // ← version guard
    ->where('stock', '>=', $qty)                // ← prevent oversell
    ->update([
        'stock'         => DB::raw("stock - {$qty}"),
        'stock_version' => DB::raw('stock_version + 1'),
    ]);

if ($affected === 0) {
    // Version changed = someone else modified the row → RETRY
}
```

**Why Optimistic?** No locks held between read and write. Under low contention (typical e-commerce), we succeed on attempt #1. Under high contention (flash sale), we retry with exponential backoff + jitter to avoid thundering herd.

### AFTER: Solution B — Pessimistic Locking

```php
// StockService::decrementPessimistic()
DB::transaction(function () {
    $product = Product::where('id', $id)
        ->lockForUpdate()    // ← SELECT ... FOR UPDATE (row locked)
        ->first();

    if ($product->stock < $qty) throw new InsufficientStockException();

    $product->stock -= $qty;
    $product->save();
});  // ← Lock released on COMMIT
```

**Why Pessimistic?** Guarantees serial execution. Simpler but lower throughput — every concurrent buyer of the same SKU queues up at the DB level.

### Before vs After Comparison

| Metric | Before (No Lock) | After (Optimistic) | After (Pessimistic) |
|--------|-------------------|---------------------|---------------------|
| Data Integrity | BROKEN — lost updates | SAFE — version guard | SAFE — row lock |
| Overselling | YES — stock goes negative | NO — WHERE stock >= qty | NO — serial execution |
| Throughput | High (but incorrect) | High (non-blocking) | Medium (blocking) |
| Deadlock Risk | None | None | Possible (mitigated by lock ordering) |
| Retry Needed | No | Yes (up to 5 retries) | No |

### Deadlock Prevention

In `OrderService::checkout()`, items are sorted by `product_id` before locking:

```php
$items = collect($dto->items)->sortBy('productId')->values();
```

This is the classic deadlock-avoidance technique: if every transaction acquires locks in the same order, no cycle can form in the wait-for graph.

### Test Evidence

```bash
php artisan commerce:simulate-orders --product=1 --stock=50 --buyers=200 --qty=1
```

Output: `INVARIANT HELD: successful * qty + final_stock === initial_stock`

---

## Requirement 2: Resource Management & Capacity Control

**File:** `app/Http/Middleware/ConcurrencyLimiter.php`
**Test:** `tests/Feature/Req2_ResourceManagementTest.php`

### The Problem: Unbounded Concurrency

Without resource limits, a flash sale spawns unlimited concurrent processes:
- 1000 simultaneous checkouts → 1000 PHP-FPM workers
- Each worker holds a MySQL connection (pool default: ~150)
- Connection 151+ fails → "Too many connections" → CASCADE FAILURE
- Or: memory exhaustion → OOM killer → CRASH

### BEFORE: No Limiter

```php
// All requests accepted unconditionally
Route::post('checkout', [OrderController::class, 'checkout']);
// Under 1000 concurrent requests: server crashes
```

### AFTER: Counting Semaphore Middleware

```php
// ConcurrencyLimiter middleware (counting semaphore pattern)
$current = Cache::increment("semaphore:{$key}");  // Atomic in Redis

if ($current > $maxSlots) {
    Cache::decrement("semaphore:{$key}");          // Release slot
    return response()->json([
        'message' => 'Server busy, retry in 2s'
    ], 429);
}

try {
    $response = $next($request);                   // Process request
} finally {
    Cache::decrement("semaphore:{$key}");          // Always release
}
```

This is analogous to Java's `Semaphore(10)`:

```java
// Java equivalent
Semaphore sem = new Semaphore(10);
sem.acquire();  // blocks if all 10 permits taken
try { processCheckout(); }
finally { sem.release(); }
```

**Synchronization point:** `Cache::increment()` is atomic in Redis (single-threaded event loop) — no race condition between checking the counter and incrementing it.

### Multi-Layer Resource Control

| Layer | Mechanism | Config | Purpose |
|-------|-----------|--------|---------|
| Nginx | `worker_connections` | 1024 | Max simultaneous connections |
| Nginx | `upstream` weights | 3:1:2 | Distribute load by capacity |
| PHP-FPM | `pm.max_children` | 10/container | Limit PHP worker processes |
| Laravel | `ConcurrencyLimiter` | 10 slots | Limit concurrent checkouts |
| Laravel | `throttle:api` | 60 req/min | Rate limit per IP |
| MySQL | `max_connections` | 150 | DB connection pool |
| Queue | `--max-jobs` | per queue | Limit jobs per worker lifecycle |

### Before vs After

| Metric | Before | After |
|--------|--------|-------|
| Max concurrent checkouts | Unlimited | 10 (configurable) |
| Under 1000 req burst | CRASH | 10 proceed, 990 get 429 |
| Server stability | Unpredictable | Guaranteed stable |
| User experience | Random errors/timeouts | Clear "retry" message |
| Recovery time | Manual restart needed | Automatic (self-healing) |

---

## Requirement 3: Asynchronous Queues

**Files:** `app/Jobs/SendOrderConfirmationEmail.php`, `app/Jobs/GenerateInvoicePdf.php`
**Test:** `tests/Feature/Req3_AsyncQueuesTest.php`

### The Problem: Blocking I/O on the HTTP Thread

In synchronous processing, the user waits for EVERYTHING:

```
Client → [Create Order: 50ms] → [Send Email: 800ms] → [Generate PDF: 1200ms] → Response
Total: ~2,050ms (user waits 2+ seconds!)
```

- Email sending: 200-800ms (SMTP network I/O)
- PDF generation: 200-2000ms (CPU-bound DOM rendering)
- Both BLOCK the PHP worker → fewer workers available for other requests

Under 100 concurrent checkouts: ALL 100 workers stuck on email/PDF → no workers left for browsing → ENTIRE SITE FREEZES.

### BEFORE: Synchronous Processing

```php
// Everything inline — user waits for email + PDF
public function checkout(Request $request) {
    $order = $this->createOrder($request);      // 50ms
    Mail::send(new OrderConfirmation($order));   // 800ms (BLOCKS!)
    $pdf = PDF::loadView('invoice', $order);     // 1200ms (BLOCKS!)
    Storage::put("invoices/{$order->id}.pdf", $pdf->output());
    return response()->json($order);             // Total: 2050ms
}
```

### AFTER: Queue-Based Async Processing

```php
// Only the critical path is synchronous — side effects are queued
public function checkout(Request $request) {
    $order = $this->createOrder($request);                            // 50ms

    SendOrderConfirmationEmail::dispatch($order->id)->onQueue('emails');    // <1ms
    GenerateInvoicePdf::dispatch($order->id)->onQueue('invoices');          // <1ms

    return response()->json($order);                                  // Total: ~52ms
}
```

### Queue Architecture

```
                   ┌──────────────┐
  HTTP Request ──▶ │ Controller   │ ──▶ Response (52ms)
                   │ dispatch()   │
                   └──────┬───────┘
                          │ (serialized job → Redis/DB)
                   ┌──────▼───────┐
                   │  Job Queue   │
                   ├──────────────┤
                   │ emails       │ ──▶ Worker 1: SendOrderConfirmationEmail
                   │ invoices     │ ──▶ Worker 2: GenerateInvoicePdf
                   │ reports      │ ──▶ Worker 3: ProcessDailySalesReport
                   └──────────────┘
```

### Why Separate Queues?

If all jobs shared one queue:
- A 10-minute batch report would block email delivery for 10 minutes
- Users wouldn't receive confirmation emails until the report finishes

Separate queues = separate worker pools = **no interference between job types**.

### Retry & Backoff Strategy

```php
// SendOrderConfirmationEmail
public int $tries = 5;
public function backoff(): array {
    return [10, 30, 60, 120, 300]; // Exponential backoff
}
```

- Attempt 1: immediate
- Attempt 2: wait 10s
- Attempt 3: wait 30s
- Attempt 4: wait 60s
- Attempt 5: wait 300s
- If all fail → moved to `failed_jobs` table for manual review

### Before vs After

| Metric | Before (Sync) | After (Async) |
|--------|--------------|---------------|
| Checkout response time | ~2,050ms | ~52ms |
| Speedup | 1x | **~40x faster** |
| PHP worker blocked | 2+ seconds | 52ms |
| Email failure impact | User sees error | Silent retry, user unaffected |
| PDF generation | Inline, blocks response | Background, separate worker |
| Scalability | Limited by slowest side-effect | Scales workers independently |

---

## Requirement 4: Batch Processing

**File:** `app/Jobs/ProcessDailySalesReport.php`
**Test:** `tests/Feature/Req4_BatchProcessingTest.php`

### The Problem: Memory Exhaustion on Large Datasets

Loading all records into memory at once:

```php
$orders = Order::all();  // Loads EVERY row into PHP array
// 1,000 orders: ~2MB RAM → OK
// 10,000 orders: ~20MB → slow
// 100,000 orders: ~200MB → PHP memory_limit hit → FATAL ERROR
// 1,000,000 orders: impossible
```

### BEFORE: Naive Load-All Approach

```php
// DANGEROUS: loads entire table into memory
$orders = Order::where('payment_status', 'paid')->get();  // O(N) memory

$total = 0;
foreach ($orders as $order) {
    $total += $order->total;
    fputcsv($stream, [$order->id, $order->total]);
}
// At 100K rows: PHP memory exhausted → process killed
```

### AFTER: chunkById — Fixed-Size Streaming Windows

```php
// SAFE: processes 500 rows at a time — O(1) memory
Order::query()
    ->where('payment_status', 'paid')
    ->orderBy('id')
    ->chunkById(500, function ($orders) use (&$total, $stream) {
        foreach ($orders as $order) {
            $total += $order->total;
            fputcsv($stream, [$order->id, $order->total]);
        }
    });
// At 1M rows: same ~1MB memory as 100 rows
```

### Why chunkById Instead of chunk?

| Method | SQL | Problem |
|--------|-----|---------|
| `chunk(500)` | `LIMIT 500 OFFSET 0`, `LIMIT 500 OFFSET 500` | If a row is INSERTED between page 1 and page 2, one row gets skipped or counted twice |
| `chunkById(500)` | `WHERE id > 0 LIMIT 500`, `WHERE id > 500 LIMIT 500` | Immune to concurrent inserts — always pagination by primary key |

### Before vs After

| Metric | Before (all()) | After (chunkById) |
|--------|----------------|---------------------|
| Memory usage | O(N) — grows with data | O(1) — constant ~1MB |
| 100K rows | CRASH (memory exhausted) | Works fine |
| 1M rows | Impossible | Works fine |
| Concurrent inserts | N/A (crashes first) | Safe (id-based pagination) |
| Processing speed | Fast for small data | Slightly slower per-row, but doesn't crash |

---

## Requirement 5: Load Distribution

**File:** `app/Services/LoadBalancerService.php`, `docker/nginx/default.conf`
**Test:** `tests/Feature/Req5_LoadDistributionTest.php`

### The Problem: Single Point of Failure

One server handles all traffic:
- CPU bottleneck: 100% utilization under load
- No redundancy: server failure = total downtime
- Cannot scale: adding more requests requires bigger hardware (vertical scaling)

### BEFORE: Single Server

```
Client ──▶ [ Single PHP Server ] ──▶ MySQL
              CPU: 100%
              Response: 500ms
              Failure = DOWNTIME
```

### AFTER: Load-Balanced Architecture

```
              ┌─── App 1 (w=3, 50%) ──┐
Client ──▶ Nginx ─── App 2 (w=1, 17%) ──┼──▶ MySQL
              └─── App 3 (w=2, 33%) ──┘
              Each CPU: ~33%
              Response: 50ms
              1 failure = 2 others absorb
```

### Three Strategies Implemented

#### 1. Round Robin
```
Request 1 → Server 1
Request 2 → Server 2
Request 3 → Server 3
Request 4 → Server 1 (cycle repeats)
```
- **Pro:** Perfectly even distribution
- **Con:** Ignores server capacity — a weak server gets the same share as a powerful one

#### 2. Weighted Round Robin
```
Weights: S1=3, S2=1, S3=2 (total=6)
Request 1-3 → Server 1 (weight 3)
Request 4   → Server 2 (weight 1)
Request 5-6 → Server 3 (weight 2)
```
- **Pro:** Accounts for heterogeneous hardware
- **Con:** Weights are static — doesn't adapt to runtime conditions

#### 3. Least Connections
```
Active connections: S1=5, S2=2, S3=3
Next request → Server 2 (fewest active)
```
- **Pro:** Adapts to real-time load
- **Con:** Requires tracking active connections (overhead)

### Nginx Configuration (Production)

```nginx
upstream eshop_backends {
    server app1:9000 weight=3;
    server app2:9000 weight=1;
    server app3:9000 weight=2;
}
```

### Test Evidence

```bash
php artisan commerce:simulate-lb --requests=1000 --strategy=all
```

Output shows distribution table + standard deviation comparison.

### Before vs After

| Metric | Before (1 server) | After (3 servers) |
|--------|-------------------|-------------------|
| Max throughput | ~500 req/s | ~1500 req/s |
| CPU per server | 100% | ~33% |
| Server failure | TOTAL DOWNTIME | 2 servers absorb |
| Scaling | Vertical only ($$) | Horizontal (add servers) |
| Response time under load | Degrades linearly | Stays consistent |

---

## Wallet System

**Files:** `app/Services/WalletService.php`, `app/Models/Wallet.php`, `app/Models/WalletTransaction.php`
**Test:** `tests/Feature/WalletConcurrencyTest.php`

### Architecture

```
┌──────────┐     ┌──────────────────┐     ┌─────────────────────┐
│  Wallet  │     │ WalletTransaction│     │   Checkout Flow     │
├──────────┤     ├──────────────────┤     ├─────────────────────┤
│ user_id  │ 1:N │ wallet_id        │     │ 1. Reserve stock    │
│ balance  │────▶│ type (credit/    │     │ 2. Create order     │
│ version  │     │       debit)     │     │ 3. Wallet debit     │
│          │     │ amount           │     │ 4. Dispatch emails  │
└──────────┘     │ balance_before   │     └─────────────────────┘
                 │ balance_after    │
                 │ reference        │
                 └──────────────────┘
```

### Double-Spend Prevention

Uses the exact same optimistic locking pattern as stock management:

```php
$affected = DB::table('wallets')
    ->where('id', $wallet->id)
    ->where('version', $wallet->version)    // Optimistic lock
    ->where('balance', '>=', $amount)       // Prevent negative balance
    ->update([
        'balance' => $balanceAfter,
        'version' => DB::raw('version + 1'),
    ]);
```

### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/wallet` | View balance + recent transactions |
| POST | `/api/v1/wallet/credit` | Add funds (top-up) |
| POST | `/api/v1/wallet/debit` | Spend funds |
| POST | `/api/v1/wallet/transfer` | Transfer to another user |
| GET | `/api/v1/wallet/transactions` | Full transaction history |

### Audit Trail

Every operation creates an immutable ledger entry with `balance_before` and `balance_after`. The chain must satisfy:
```
sum(credits) - sum(debits) = current_balance
```

---

## Monitoring Dashboard

**File:** `app/Http/Controllers/Api/V1/MonitorController.php`
**Endpoint:** `GET /api/v1/monitor/dashboard`

### Why Not Grafana?

| Criteria | Grafana + Prometheus | Built-in Dashboard |
|----------|---------------------|-------------------|
| Setup complexity | 3 extra Docker services | Zero — just a Laravel route |
| What it monitors | Generic server metrics (CPU/RAM) | **Exactly** our 5 requirements |
| Configuration | PromQL queries, Grafana panels | Pre-built, requirement-aligned |
| For this course | Overkill | Purpose-built for grading |

### Dashboard Sections

The `/api/v1/monitor/dashboard` endpoint returns JSON with sections mapped to each requirement:

```json
{
  "req1_concurrency": {
    "status": "HEALTHY",
    "negative_stock_items": 0,
    "hot_products": [...],
    "stock_strategy": "optimistic"
  },
  "req2_resources": {
    "checkout_semaphore": { "current_active": 3, "max_slots": 10 },
    "rate_limits": { "api_per_minute": 60 }
  },
  "req3_queues": {
    "queues": { "emails": {"pending": 5}, "invoices": {"pending": 2} },
    "total_failed": 0
  },
  "req4_batch": {
    "recent_reports": [...],
    "chunk_size": 500
  },
  "req5_load": {
    "servers": [
      {"id": "server-1", "total_requests": 500, "active_connections": 3},
      ...
    ]
  },
  "wallet": {
    "total_wallets": 150,
    "total_balance": 45230.00
  },
  "health": {
    "req1_data_integrity": "PASS",
    "req2_resource_control": "PASS",
    "req3_async_queues": "PASS",
    "req4_batch_processing": "PASS",
    "req5_load_distribution": "PASS",
    "wallet_system": "PASS"
  }
}
```

---

## Docker Infrastructure

### Services

| Service | Image | Purpose | Requirement |
|---------|-------|---------|-------------|
| nginx | nginx:1.25 | Load balancer (weighted round-robin) | Req 5 |
| app1, app2, app3 | PHP 8.2 FPM | Application servers (3 instances) | Req 5 |
| mysql | MySQL 8.0 | Database with InnoDB row-level locking | Req 1, 7, 8 |
| redis | Redis 7 | Cache + Queue + Semaphore | Req 2, 3, 6 |
| queue-emails | PHP 8.2 | Email queue worker | Req 3 |
| queue-invoices | PHP 8.2 | Invoice PDF queue worker | Req 3 |
| queue-reports | PHP 8.2 | Batch report queue worker | Req 4 |

### Quick Start

```bash
# Copy Docker environment
cp .env.docker .env

# Start all services
docker compose up -d

# Run migrations
docker compose exec app1 php artisan migrate --seed

# Verify
curl http://localhost:8080/api/v1/products
curl http://localhost:8080/api/v1/monitor/dashboard
```

---

## How to Run & Test

### Without Docker (XAMPP)

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/E-Shop

# Run migrations (includes wallet tables)
php artisan migrate

# Start development server
php artisan serve

# Start queue worker (in separate terminal)
php artisan queue:work --queue=emails,invoices,reports

# Run tests
php artisan test --filter=Req1
php artisan test --filter=Req2
php artisan test --filter=Req3
php artisan test --filter=Req4
php artisan test --filter=Req5
php artisan test --filter=WalletConcurrency

# Run all tests
php artisan test
```

### Concurrency Simulation

```bash
# Simulate 200 concurrent buyers against a product with 50 units
php artisan commerce:simulate-orders --product=1 --stock=50 --buyers=200 --qty=1

# Expected output: INVARIANT HELD
```

### Load Balancer Simulation

```bash
# Compare all three strategies with 1000 requests
php artisan commerce:simulate-lb --requests=1000 --strategy=all
```

### Batch Processing

```bash
# Generate daily sales report
php artisan commerce:daily-report --date=2026-05-15
```

### Benchmarking

```bash
# Cache vs no-cache benchmark
php artisan commerce:benchmark --runs=100

# Stress test with Apache Bench
./stress-tests/ab-benchmark.sh
```

### Monitoring Dashboard

```bash
# View full system status
curl http://localhost:8000/api/v1/monitor/dashboard | jq .
```

---

## Synchronization Points Summary

| Location | Mechanism | Purpose |
|----------|-----------|---------|
| `StockService::decrementOptimistic()` | `WHERE stock_version = ?` (CAS) | Prevent lost updates on stock |
| `StockService::decrementPessimistic()` | `SELECT ... FOR UPDATE` | Serialize stock writes |
| `WalletService::debit()` | `WHERE version = ?` (CAS) | Prevent double-spend |
| `ConcurrencyLimiter` | `Cache::increment()` (atomic) | Counting semaphore |
| `LoadBalancerService::roundRobin()` | `Cache::increment()` (atomic) | Atomic counter for RR |
| `OrderService::checkout()` | `DB::transaction()` + lock ordering | ACID + deadlock prevention |
| `ProcessDailySalesReport` | `chunkById()` | Safe pagination under inserts |
| Nginx upstream | Weighted round-robin | Network-level load distribution |

---

## AOP (Aspect-Oriented Programming) for Monitoring

The monitoring dashboard implements AOP concepts through:

1. **Cross-cutting concern separation:** Metrics collection is separate from business logic
2. **Middleware as aspects:** `ConcurrencyLimiter` wraps the checkout without modifying it
3. **Cache instrumentation:** Every cache/semaphore operation records metrics as a side-effect
4. **Queue instrumentation:** Job retries, failures, and backoff are transparent to the business logic

This follows the principle: **business logic doesn't know it's being monitored**.
