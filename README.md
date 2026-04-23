# E-Shop — High-Performance E-Commerce (Laravel + React)

A production-grade e-commerce system that showcases modern backend
architecture AND real parallel-programming practices:

- **Clean / Onion architecture** (Controllers → Services → Repositories → Models)
- **Optimistic *and* pessimistic stock locking** (switch per workload)
- **ACID checkout** (DB transaction + compensating rollback on payment failure)
- **Redis distributed cache** with tag-based invalidation
- **Laravel Queues** for async emails, PDF invoices, daily sales reports
- **Batch processing** with `chunkById` (safe under concurrent inserts)
- **Sanctum Bearer-token API** consumed by a modern React frontend
- **Stress-testing artefacts** (JMeter, Apache Bench, custom artisan simulator)

For the deep technical write-up see **[DOCUMENTATION.md](./DOCUMENTATION.md)**.

---

## 1. Project layout

```
E-Shop/
├── app/                     # Laravel backend (API v1 + services + jobs)
├── config/commerce.php      # STOCK_STRATEGY + PAYMENT_DRIVER knobs
├── database/migrations/     # Schema (with stock_version, order_items, payments)
├── resources/js/            # React 18 SPA (served via Laravel Vite plugin)
│   ├── spa.jsx              #   entry point (mounted at /)
│   ├── App.jsx, components/, pages/, store/, lib/
├── resources/css/spa.css    # Tailwind entry for the SPA
├── routes/api.php           # API endpoints under /api/v1/*
├── stress-tests/            # JMeter plan + ab script
├── DOCUMENTATION.md         # Architecture, concurrency, benchmarks
└── README.md                # This file
```

---

## 2. Prerequisites

| Tool       | Version | Notes                                        |
|------------|---------|----------------------------------------------|
| PHP        | ≥ 8.2   | With `pdo_mysql`, `pcntl`, `redis` or `predis` |
| Composer   | ≥ 2.5   |                                              |
| MySQL      | ≥ 5.7   | XAMPP's MySQL works out of the box           |
| Redis      | ≥ 5     | Optional but recommended                     |
| Node.js    | ≥ 18    | For the React frontend                       |
| JMeter     | any     | Optional, for stress tests                   |

---

## 3. Quick start (5 minutes)

```bash
# 1. Clone / cd into the project
cd /Applications/XAMPP/xamppfiles/htdocs/E-Shop

# 2. Install PHP deps
composer install

# 3. Copy env + generate key
cp .env.example .env
php artisan key:generate

# 4. Configure database + cache in .env
#    DB_CONNECTION=mysql / DB_DATABASE=eshop etc.
#    CACHE_STORE=redis   (or "file" if no Redis)
#    QUEUE_CONNECTION=redis   (or "database")
#    STOCK_STRATEGY=optimistic   (or "pessimistic")
#    PAYMENT_DRIVER=mock   (or "stripe" with STRIPE_SECRET=...)

# 5. Migrate
php artisan migrate --force

# 6. Seed a user + some products (see "seeders" section below — optional)

# 7. Start the API
php artisan serve           # http://localhost:8000

# 8. Start queue workers (in 3 separate terminals ideally)
php artisan queue:work --queue=emails   --tries=5
php artisan queue:work --queue=invoices --tries=3
php artisan queue:work --queue=reports  --tries=2

# 9. Build or dev-serve the React SPA (served by Laravel Vite plugin)
npm install
npm run dev                 # hot-reload dev server
# or, for production bundle:
npm run build
```

Open **http://localhost:8000**, register, browse products, add to cart, checkout.

> The React SPA is the ONLY frontend — Blade views have been removed.
> Laravel's `web.php` has a single catch-all route (`/{any?}` excluding `/api/*`)
> that returns `resources/views/spa.blade.php`; React Router takes over from there.

---

## 4. Seeding test data (tinker one-liner)

```bash
php artisan tinker --execute="
  App\Models\categories::firstOrCreate(['name' => 'Electronics']);
  for (\$i = 1; \$i <= 20; \$i++) {
    App\Models\Product::create([
      'name' => 'Product '.\$i,
      'slug' => 'product-'.\$i,
      'description' => 'Auto-seeded item #'.\$i,
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

Register a user via the frontend or with:

```bash
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H 'Content-Type: application/json' \
  -d '{"username":"alice","email":"alice@test.com","password":"secret123","password_confirmation":"secret123"}'
```

The response contains a `token` — use it as `Authorization: Bearer <token>` for
protected endpoints.

---

## 5. Key commands cheat-sheet

```bash
# Boot up everything (Laravel dev helper already present in composer.json):
composer dev       # runs server + queue + logs + vite concurrently

# Concurrency proof — prevents overselling under 200 concurrent buyers
php artisan commerce:simulate-orders --product=1 --stock=50 --buyers=200

# Benchmark the catalog with/without Redis
php artisan commerce:benchmark --runs=200

# Dispatch today's sales report (batch job)
php artisan commerce:daily-report --date=2026-04-22

# Inspect failed jobs
php artisan queue:failed

# Tail logs
php artisan pail
```

---

## 6. Stress testing

### Option A — Artisan (best for correctness proof)

```bash
php artisan commerce:simulate-orders --buyers=200 --stock=50
```

Forks 200 OS processes that each hit `OrderService::checkout()` against the
same product. Prints an invariant check proving no overselling occurred.

### Option B — Apache Bench (smoke test)

```bash
./stress-tests/ab-benchmark.sh
```

### Option C — JMeter (100+ concurrent HTTP users)

```bash
# 1. Get a Sanctum token via /api/v1/auth/login
# 2. Open stress-tests/checkout.jmx in JMeter and edit the TOKEN user variable
# 3. Run: hits POST /api/v1/checkout at 100 threads for 30 seconds
```

See `DOCUMENTATION.md §6` for interpretation.

---

## 7. Switching strategies

The system is designed so the two biggest operational decisions are just
env-var toggles:

```env
# Stock concurrency
STOCK_STRATEGY=optimistic     # (default) versioned UPDATE + retries — high throughput
STOCK_STRATEGY=pessimistic    # SELECT FOR UPDATE — serializes writers per row

# Payment provider
PAYMENT_DRIVER=mock           # deterministic, for tests/load
PAYMENT_DRIVER=stripe         # real Stripe (set STRIPE_SECRET=sk_test_...)
```

---

## 8. API reference (summary)

| Method | Path                        | Auth | Purpose                               |
|--------|-----------------------------|------|---------------------------------------|
| POST   | `/api/v1/auth/register`     | ❌   | Register user + return token          |
| POST   | `/api/v1/auth/login`        | ❌   | Login + return token                  |
| POST   | `/api/v1/auth/logout`       | ✅   | Revoke current token                  |
| GET    | `/api/v1/auth/me`           | ✅   | Current user                          |
| GET    | `/api/v1/products`          | ❌   | List (filter/paginate, cached)        |
| GET    | `/api/v1/products/{id}`     | ❌   | Single product (cached)               |
| GET    | `/api/v1/categories`        | ❌   | Category list with product counts     |
| POST   | `/api/v1/contact`           | ❌   | Contact form submission               |
| GET    | `/api/v1/cart`              | ✅   | Current cart                          |
| POST   | `/api/v1/cart`              | ✅   | Add item (atomic upsert)              |
| DELETE | `/api/v1/cart`              | ✅   | Clear cart                            |
| DELETE | `/api/v1/cart/{productId}`  | ✅   | Remove one line                       |
| POST   | `/api/v1/checkout`          | ✅   | ACID checkout (rate-limited 10/min)   |
| GET    | `/api/v1/orders`            | ✅   | User's orders                         |
| GET    | `/api/v1/orders/{id}`       | ✅   | One order with items + payment        |

Rate limits live in `bootstrap/app.php`:
- global API: **60 req/min** per user/IP
- checkout:  **10 req/min** per user

---

## 9. Troubleshooting

| Symptom                                       | Fix                                                           |
|-----------------------------------------------|---------------------------------------------------------------|
| `CACHE tags not supported on file driver`     | Set `CACHE_STORE=redis` (or just don't use tag flushing)      |
| `pcntl not available` in simulator            | Enable `pcntl` PHP extension, or re-run it serially           |
| CORS blocked in browser                       | Check `config/cors.php` — add your frontend origin            |
| "Database is locked" on SQLite                | Don't use SQLite for concurrency tests; use MySQL             |
| Queues aren't processing                      | Start `php artisan queue:work`                                |
| Stripe errors                                 | Use `PAYMENT_DRIVER=mock` while developing                    |

---

## 10. Credits / License

MIT. Built on top of Laravel 11 and React 18.
