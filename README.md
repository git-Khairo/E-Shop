# E-Shop: High-Performance E-Commerce Backend Engine

**Parallel Programming Course Project -- Semester 2026**

A full e-commerce system built with Laravel 11 and React 18. The focus is not on having many pages or features, but on applying parallel programming concepts to handle thousands of concurrent requests without data corruption, resource exhaustion, or system crashes.

**Tech Stack:** PHP 8.2+, Laravel 11, MySQL, Redis, React 18, Zustand, Tailwind CSS, Laravel Sanctum

---

## Table of Contents

1. [Requirement 1 -- Concurrent Access and Data Integrity](#requirement-1----concurrent-access-and-data-integrity)
2. [Requirement 2 -- Resource Management and Capacity Control](#requirement-2----resource-management-and-capacity-control)
3. [Requirement 3 -- Asynchronous Queues](#requirement-3----asynchronous-queues)
4. [Requirement 4 -- Batch Processing](#requirement-4----batch-processing)
5. [Requirement 5 -- Load Distribution](#requirement-5----load-distribution)
6. [Architecture and Code Structure](#architecture-and-code-structure)
7. [How to Run the Project](#how-to-run-the-project)
8. [API Reference](#api-reference)
9. [Stress Testing and Proof](#stress-testing-and-proof)
10. [Troubleshooting](#troubleshooting)

---

## Requirement 1 -- Concurrent Access and Data Integrity

**Goal:** Allow multiple users to buy the same product at the same time without overselling or corrupting the stock count.

### The Problem (Race Condition)

Imagine a product has 5 units in stock. Two users try to buy it at the exact same moment. Without protection, both users read stock = 5, both subtract 1, both write stock = 4. The store sold 2 units but stock only went down by 1. This is called a **lost update** -- a classic race condition.

### The Solution

We implemented **two locking strategies**, and the system can switch between them with a single environment variable:

```env
STOCK_STRATEGY=optimistic    # default
STOCK_STRATEGY=pessimistic   # alternative
```

#### Optimistic Locking (Default)

Every product row has a `stock_version` column. When we want to decrease stock, we run an UPDATE query that says: "decrease stock by X, but only if the version is still what I read a moment ago." If someone else changed the stock between our read and our write, the version won't match and the UPDATE affects zero rows. We detect that and retry.

How it works in code (`app/Services/StockService.php`):

1. Read the product and its current `stock_version`
2. Run: `UPDATE products SET stock = stock - qty, stock_version = stock_version + 1 WHERE id = ? AND stock_version = ?`
3. If the update affected 0 rows, it means someone else got there first. Wait a short random time (exponential backoff) and retry
4. Maximum 5 retries before giving up

This approach is fast because it doesn't lock the database row. Most of the time, there's no conflict and the first attempt succeeds. Under heavy load, some requests will need to retry, but they all get a fair chance.

#### Pessimistic Locking (Alternative)

This approach locks the database row before reading it. It uses `SELECT ... FOR UPDATE`, which tells MySQL: "lock this row, nobody else can read or write it until my transaction finishes."

How it works (`app/Services/StockService.php`):

1. Inside a database transaction, run `SELECT * FROM products WHERE id = ? FOR UPDATE`
2. MySQL locks the row. Any other request trying to read the same product will wait
3. Check if stock is enough, decrease it, save
4. Commit the transaction, which releases the lock

This is simpler but slower under high concurrency because requests queue up waiting for the lock. It's better suited for low-traffic, high-value operations where you want absolute safety with no retries.

### Proving it Works

The test file `tests/Feature/Req1_RaceConditionTest.php` demonstrates the race condition bug and proves the fix:

- **Before test:** Simulates two concurrent updates without locking. Shows that stock goes from 10 to 9 instead of 8 (lost update)
- **After test (optimistic):** Runs the same scenario with optimistic locking. Both updates succeed and stock correctly goes to 8
- **After test (pessimistic):** Same result using pessimistic locking

There's also a command-line simulator (`php artisan commerce:simulate-orders`) that forks 200 real OS processes, all trying to buy the same product at the same time. After all processes finish, it checks the invariant: `(successful_orders * quantity) + remaining_stock == original_stock`. If this equation holds, no stock was lost or created out of thin air.

### Wallet System (Bonus)

The digital wallet (`app/Services/WalletService.php`) also uses optimistic locking to prevent double-spending. If two requests try to spend the last $10 in a wallet at the same time, only one will succeed. The other will detect the version mismatch and fail gracefully.

---

## Requirement 2 -- Resource Management and Capacity Control

**Goal:** Prevent the system from crashing under heavy load by limiting how many expensive operations run at the same time.

### The Problem

If 1000 users all hit the checkout button at the same moment, the server would try to process all 1000 simultaneously. Each checkout involves database transactions, payment API calls, and email dispatching. This can exhaust MySQL connections, run out of memory, or crash the server entirely.

### The Solution (Counting Semaphore)

We built a **counting semaphore** using Redis (or any Laravel cache driver). A semaphore is like a room with a limited number of chairs. When all chairs are taken, new people have to wait outside.

The middleware `app/Http/Middleware/ConcurrencyLimiter.php` works like this:

1. When a checkout request arrives, we call `Cache::increment('semaphore:checkout')`. This atomically adds 1 to the counter and returns the new value
2. If the value is 10 or less (our max), the request proceeds normally
3. If the value is over 10, we immediately call `Cache::decrement` to give back the slot and return HTTP 429 ("Server is busy, please retry in a few seconds")
4. After the request finishes (whether it succeeded or failed), we always decrement the counter in a `finally` block, so the slot is always released

```
Request arrives → increment counter → counter ≤ 10? → process request → decrement counter
                                     → counter > 10? → decrement counter → return 429
```

The checkout route is configured with this middleware in `routes/api.php`:

```php
Route::post('/checkout', [CheckoutController::class, 'store'])
    ->middleware('concurrency:checkout,10');
```

The `10` means at most 10 simultaneous checkout operations. This number is tunable per route.

### Additional Rate Limiting

On top of the semaphore, we also have standard rate limiting:
- **Global API:** 60 requests per minute per user/IP
- **Checkout:** 10 requests per minute per user

The semaphore limits simultaneous requests (concurrency), while rate limiting limits total requests over time (throughput). They solve different problems and work together.

### Proving it Works

The test `tests/Feature/Req2_ResourceManagementTest.php`:

- Manually fills up 3 semaphore slots (max = 3 for the test)
- Sends a 4th request and confirms it gets rejected with HTTP 429
- Sends a request with empty slots and confirms it succeeds and the counter returns to 0 after completion

---

## Requirement 3 -- Asynchronous Queues

**Goal:** Move slow tasks out of the main request so the user doesn't have to wait for them.

### The Problem

When a user checks out, the system needs to send a confirmation email, generate a PDF invoice, and potentially trigger other tasks. If all of this happens during the HTTP request, the user might wait 5-10 seconds staring at a loading screen. That's bad user experience, and it also ties up a server process for no good reason.

### The Solution

We use **Laravel Queues** to push these tasks to a background worker. The checkout request finishes in under a second, and the slow work happens afterward.

Three separate queues handle different types of work:

| Queue      | Job Class                        | Purpose                      | Retries | Backoff                    |
|------------|----------------------------------|------------------------------|---------|----------------------------|
| `emails`   | `SendOrderConfirmationEmail`     | Send order email to customer | 5       | 10s, 30s, 60s, 2min, 5min |
| `invoices` | `GenerateInvoicePdf`             | Create PDF invoice via DomPDF| 3       | Default                    |
| `reports`  | `ProcessDailySalesReport`        | Generate daily sales CSV     | 2       | Default                    |

Each queue has its own dedicated worker process. This means a slow PDF generation won't block email sending, and a failing email won't hold up reports.

### How Jobs are Dispatched

Right after a successful checkout in `app/Services/OrderService.php`:

```php
SendOrderConfirmationEmail::dispatch($order->id)->onQueue('emails');
GenerateInvoicePdf::dispatch($order->id)->onQueue('invoices');
```

The `dispatch()` call puts the job on the queue and returns immediately. The actual work happens when a queue worker picks it up. The user's HTTP response is already sent by then.

### Retry and Fault Tolerance

The email job is configured with **exponential backoff**: if it fails the first time, it waits 10 seconds before retrying. If it fails again, it waits 30 seconds, then 60, then 120, then 300. This prevents hammering a failing mail server. After 5 total failures, the job moves to the `failed_jobs` table for manual inspection.

### Proving it Works

The test `tests/Feature/Req3_AsyncQueuesTest.php`:

- Dispatches a job and measures how long `dispatch()` takes vs how long the actual job would take if run synchronously
- Confirms that dispatching is near-instant (under 50ms) while the actual work would take much longer
- Verifies that each job goes to its correct queue (emails, invoices, reports)
- Checks retry and backoff configurations

---

## Requirement 4 -- Batch Processing

**Goal:** Process large amounts of data in chunks instead of loading everything into memory at once.

### The Problem

Imagine generating a sales report for a day with 100,000 orders. If you do `Order::all()`, PHP loads all 100,000 records into memory at once. With each record using maybe 2KB, that's 200MB of RAM just for this one query. If two reports run at the same time, that's 400MB. The server runs out of memory and crashes.

Even `Order::chunk(500)` has a subtle bug: if new orders are inserted while you're paginating through the table, you might skip records or process the same record twice, because `chunk()` uses OFFSET pagination.

### The Solution (chunkById)

We use `chunkById(500)` instead. This method paginates using `WHERE id > last_seen_id`, which is immune to new inserts because IDs only go up. It processes exactly 500 records at a time, keeping memory usage constant no matter how big the dataset is.

The job `app/Jobs/ProcessDailySalesReport.php`:

1. Queries all paid orders for a given day
2. Processes them in batches of 500 using `chunkById`
3. Writes each batch to a CSV stream (not building a huge array in memory)
4. Saves the final CSV to `storage/app/reports/sales-YYYY-MM-DD.csv`

```php
Order::query()
    ->where('created_at', '>=', $day)
    ->where('created_at', '<', $day->copy()->addDay())
    ->where('payment_status', 'paid')
    ->orderBy('id')
    ->chunkById(500, function ($orders) use (&$totalRows, &$grandTotal, $stream) {
        foreach ($orders as $order) {
            fputcsv($stream, [...]);
            $totalRows += 1;
            $grandTotal += (float) $order->total;
        }
    });
```

Memory stays constant at roughly 500 records worth regardless of whether there are 1,000 or 1,000,000 orders in the database.

### Proving it Works

The test `tests/Feature/Req4_BatchProcessingTest.php`:

- Creates a known number of orders and processes them with chunkById
- Verifies that chunk sizes are correct (500 per batch, remainder in the last batch)
- Confirms memory usage stays constant (doesn't grow with dataset size)
- Tests that chunkById is safe even when new records are inserted during processing

---

## Requirement 5 -- Load Distribution

**Goal:** Simulate distributing incoming requests across multiple servers, and explain why we chose specific strategies.

### The Implementation

The service `app/Services/LoadBalancerService.php` simulates 3 backend servers and supports 3 distribution strategies:

#### Strategy 1: Round Robin

Requests are distributed evenly in order: server 1, server 2, server 3, server 1, server 2, server 3, and so on.

An atomic counter in Redis tracks the current position. Each new request increments the counter and picks `counter % server_count`. Because Redis increment is atomic, even concurrent requests get unique sequence numbers.

**When to use:** When all servers have equal hardware and you want even distribution.

#### Strategy 2: Weighted Round Robin

Each server has a weight that reflects its capacity. In our setup:
- Server 1: weight 3 (powerful machine)
- Server 2: weight 1 (small machine)
- Server 3: weight 2 (medium machine)

We expand these into a pool: [1, 1, 1, 3, 3, 2], then round-robin through the pool. Server 1 gets 3 out of every 6 requests (50%), server 3 gets 2 (33%), and server 2 gets 1 (17%).

**When to use:** When servers have different hardware specs and you want to send more traffic to stronger machines.

#### Strategy 3: Least Connections

Each request goes to whichever server currently has the fewest active connections. We track active connections per server in Redis. When a request starts, we increment the server's counter. When it finishes, we decrement it.

**When to use:** When request durations vary a lot (some requests take 100ms, others take 5 seconds). Round robin would overload a server stuck with slow requests. Least connections naturally adapts.

### The Simulation Command

```bash
php artisan commerce:simulate-loadbalancer --requests=1000 --strategy=round_robin
```

This runs 1000 simulated requests and prints a distribution table showing how many requests each server handled.

### Proving it Works

The test `tests/Feature/Req5_LoadDistributionTest.php`:

- **Round robin:** Sends 60 requests and checks that each of 3 servers got exactly 20
- **Weighted:** Sends requests and verifies the distribution matches the weight ratios
- **Least connections:** Marks one server as busy (many active connections) and confirms new requests avoid it

---

## Architecture and Code Structure

### Clean / Onion Architecture

The codebase follows a layered architecture where dependencies only point inward:

```
HTTP Request
    ↓
[Controllers]        → Thin. Validate input, call service, return response.
    ↓
[Services]           → Business logic lives here. OrderService, StockService, etc.
    ↓
[Repositories]       → Database access through interfaces. Easy to swap implementations.
    ↓
[Models]             → Eloquent models. Represent database tables.
```

**Why this matters:** The service layer doesn't know about HTTP. It doesn't know about Eloquent either -- it talks to repository interfaces. This means you can test business logic without hitting the database, and you can swap MySQL for PostgreSQL by writing a new repository implementation without changing any service code.

### Key Files Map

```
app/
├── Console/Commands/
│   ├── SimulateConcurrentOrders.php    # Forks OS processes to prove no overselling
│   ├── BenchmarkProductListing.php     # Cold cache vs warm cache comparison
│   └── SimulateLoadBalancer.php        # Load distribution demo
├── Domain/DTOs/
│   ├── CheckoutDTO.php                 # Data transfer object for checkout input
│   └── CheckoutItemDTO.php
├── Exceptions/
│   ├── InsufficientStockException.php
│   └── PaymentFailedException.php
├── Http/
│   ├── Controllers/Api/V1/            # REST controllers
│   └── Middleware/
│       └── ConcurrencyLimiter.php      # Counting semaphore (Requirement 2)
├── Jobs/
│   ├── SendOrderConfirmationEmail.php  # Async email (Requirement 3)
│   ├── GenerateInvoicePdf.php          # Async PDF (Requirement 3)
│   └── ProcessDailySalesReport.php     # Batch processing (Requirement 4)
├── Repositories/
│   ├── Contracts/                      # Interfaces
│   └── Eloquent/                       # Implementations
└── Services/
    ├── OrderService.php                # Checkout orchestration + ACID + compensation
    ├── StockService.php                # Optimistic + Pessimistic locking (Req 1)
    ├── WalletService.php               # Digital wallet with concurrency control
    ├── PaymentService.php              # Stripe / wallet / mock payment drivers
    ├── ProductService.php              # Redis cache-aside pattern
    └── LoadBalancerService.php         # Load distribution simulation (Req 5)

config/commerce.php                     # STOCK_STRATEGY, PAYMENT_DRIVER, load balancer config
routes/api.php                          # All API routes under /api/v1
tests/Feature/                          # One test file per requirement
```

### The Checkout Flow (Tying it All Together)

The checkout is where most of the parallel programming concepts come together:

1. **Semaphore check** (Req 2): The `ConcurrencyLimiter` middleware checks if there's a free slot. If not, return 429.
2. **Database transaction** (ACID): Open a transaction. Sort items by product ID to prevent deadlocks.
3. **Stock reservation** (Req 1): For each item, decrement stock using the configured locking strategy.
4. **Create order and items** inside the transaction.
5. **Commit transaction**. Stock is now reserved.
6. **Charge payment** outside the transaction. If payment fails, run a **compensating transaction** that restores stock and marks the order as cancelled.
7. **Dispatch async jobs** (Req 3): Push email and invoice generation to their queues.
8. **Release semaphore slot** (Req 2): The `finally` block in the middleware decrements the counter.

Payment is deliberately outside the database transaction because payment APIs can take seconds. Holding a database transaction open for that long would block other checkouts. Instead, we use a compensating transaction pattern: if payment fails, we undo the stock changes.

### Frontend

The frontend is a React 18 single-page application served through Laravel's Vite plugin. Key pieces:

- **State management:** Zustand stores (`authStore.js`, `cartStore.js`) with simple async actions
- **API client:** Axios instance (`lib/api.js`) with automatic Bearer token injection and 401 handling
- **Styling:** Tailwind CSS with custom brand colors
- **Routing:** React Router. Laravel's `web.php` catches all non-API routes and serves the SPA shell

---

## How to Run the Project

### Prerequisites

| Tool       | Version | Notes                                          |
|------------|---------|------------------------------------------------|
| PHP        | >= 8.2  | With `pdo_mysql`, `pcntl`, `redis` extensions  |
| Composer   | >= 2.5  |                                                |
| MySQL      | >= 5.7  | XAMPP's MySQL works fine                       |
| Redis      | >= 5    | Optional but recommended for caching and queues|
| Node.js    | >= 18   | For the React frontend                         |

### Setup Steps

```bash
# 1. Install PHP and JS dependencies
composer install
npm install

# 2. Create your environment file
cp .env.example .env
php artisan key:generate

# 3. Configure .env
#    DB_CONNECTION=mysql
#    DB_DATABASE=eshop
#    CACHE_STORE=redis          (or "file" if no Redis)
#    QUEUE_CONNECTION=redis     (or "database")
#    STOCK_STRATEGY=optimistic  (or "pessimistic")
#    PAYMENT_DRIVER=mock        (or "stripe" or "wallet")

# 4. Run migrations
php artisan migrate --force

# 5. Start the server
php artisan serve

# 6. Start queue workers (each in its own terminal)
php artisan queue:work --queue=emails   --tries=5
php artisan queue:work --queue=invoices --tries=3
php artisan queue:work --queue=reports  --tries=2

# 7. Build or dev-serve the frontend
npm run dev       # hot-reload development
npm run build     # production bundle
```

Open http://localhost:8000, register an account, browse products, and checkout.

### Seeding Test Data

```bash
php artisan tinker --execute="
  App\Models\categories::firstOrCreate(['name' => 'Electronics']);
  for (\$i = 1; \$i <= 20; \$i++) {
    App\Models\Product::create([
      'name' => 'Product '.\$i,
      'slug' => 'product-'.\$i,
      'description' => 'Test product #'.\$i,
      'price' => random_int(10, 500) + 0.99,
      'image' => '',
      'stock' => random_int(5, 100),
      'stock_version' => 0,
      'is_active' => true,
      'categories_id' => 1,
      'amount' => 0,
    ]);
  }
"
```

### Switching Strategies

```env
STOCK_STRATEGY=optimistic     # Versioned UPDATE + retries. Higher throughput.
STOCK_STRATEGY=pessimistic    # SELECT FOR UPDATE. Simpler but serializes writers.

PAYMENT_DRIVER=mock           # For development and testing
PAYMENT_DRIVER=stripe         # Real Stripe (set STRIPE_SECRET=sk_test_...)
PAYMENT_DRIVER=wallet         # Use the built-in digital wallet
```

---

## API Reference

| Method | Path                        | Auth | Purpose                             |
|--------|-----------------------------|------|-------------------------------------|
| POST   | `/api/v1/auth/register`     | No   | Register and get a token            |
| POST   | `/api/v1/auth/login`        | No   | Login and get a token               |
| POST   | `/api/v1/auth/logout`       | Yes  | Revoke current token                |
| GET    | `/api/v1/auth/me`           | Yes  | Get current user info               |
| GET    | `/api/v1/products`          | No   | List products (paginated, cached)   |
| GET    | `/api/v1/products/{id}`     | No   | Get single product (cached)         |
| GET    | `/api/v1/categories`        | No   | List categories                     |
| POST   | `/api/v1/contact`           | No   | Submit contact form                 |
| GET    | `/api/v1/cart`              | Yes  | View current cart                   |
| POST   | `/api/v1/cart`              | Yes  | Add item to cart                    |
| PATCH  | `/api/v1/cart/{productId}`  | Yes  | Update item quantity                |
| DELETE | `/api/v1/cart/{productId}`  | Yes  | Remove item from cart               |
| DELETE | `/api/v1/cart`              | Yes  | Clear entire cart                   |
| POST   | `/api/v1/checkout`          | Yes  | Place order (semaphore-limited)     |
| GET    | `/api/v1/orders`            | Yes  | List user's orders                  |
| GET    | `/api/v1/orders/{id}`       | Yes  | View order details                  |

**Rate limits:** 60 requests/minute globally, 10 requests/minute on checkout.

---

## Stress Testing and Proof

### Concurrency Simulator (Best for Proving Correctness)

```bash
php artisan commerce:simulate-orders --product=1 --stock=50 --buyers=200
```

This forks 200 real OS processes using `pcntl_fork`. Each process attempts to buy the same product. After all processes finish, the command prints:

- How many orders succeeded
- How many failed (stock ran out)
- Final stock count
- Invariant check: `(successful * qty) + remaining_stock == original_stock`

If the invariant holds, no data was lost or duplicated under concurrent access.

### Cache Benchmark

```bash
php artisan commerce:benchmark --runs=200
```

Compares response time with cold cache (first run, hits database) vs warm cache (subsequent runs, hits Redis). Shows the speed improvement from the caching layer.

### Load Balancer Simulation

```bash
php artisan commerce:simulate-loadbalancer --requests=1000 --strategy=weighted_round_robin
```

Shows a table of how requests were distributed across servers.

### Running All Tests

```bash
php artisan test
```

This runs all feature tests including one test file per requirement (Req1 through Req5) plus wallet concurrency tests.

---

## Troubleshooting

| Problem                                     | Solution                                              |
|---------------------------------------------|-------------------------------------------------------|
| `CACHE tags not supported on file driver`   | Set `CACHE_STORE=redis` in `.env`                     |
| `pcntl not available` in simulator          | Enable `pcntl` PHP extension                          |
| CORS errors in browser                      | Check `config/cors.php`, add your frontend origin     |
| "Database is locked" on SQLite              | Use MySQL instead. SQLite can't handle concurrency    |
| Queues not processing                       | Run `php artisan queue:work`                          |
| Stripe errors                               | Switch to `PAYMENT_DRIVER=mock` for development       |

---

MIT License. Built with Laravel 11 and React 18.
