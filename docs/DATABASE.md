# Database design and maintenance

The application uses a single MySQL/MariaDB database named `tunisia_pc_advisor`. The schema is in `database/schema.sql`; the generated catalogue data is in `database/seed.sql`.

## 1. Design principles

- **One row per real retailer listing.** Product URLs are unique through a SHA-256 hash. Retailer SKUs are unique per retailer.
- **The database is the source of truth.** Recommendation scoring and explanations read the stored specifications rather than parsing product names at request time.
- **Unknown stays unknown.** Optional fields are nullable. A missing weight, battery, panel type or VRAM is not guessed.
- **Prices are snapshots.** `products.price_tnd` is the latest recorded listing price. `product_price_history` is append-only and is never updated in place.
- **Money uses `DECIMAL`, never `FLOAT`.** Prices are `DECIMAL(10,3)` because the Tunisian dinar uses three decimal places (millimes).
- **Recommendation results are reproducible.** The submitted profile, result count and score breakdown are stored with an anonymous token.

## 2. Relationship overview

```text
brands 1 ─────< products >───── 1 retailers
                    │
                    ├──────< product_price_history
                    │
                    └──────< recommendation_results >────── 1 recommendation_sessions
                                                                   │
activities 1 ──── 1 activity_requirements                          │
     │                                                             │
     └──────< activity_workloads >────── 1 workloads               │
                                                                   │
admin_users (independent authentication table) ────────────────────┘
```

`recommendation_results` stores a historical score and explanation, so deleting a product is restricted while a result references it. Deactivating a product is the intended way to remove it from public recommendations. `product_price_history` and `recommendation_results` cascade from their parent rows.

## 3. Tables

### `brands`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | Auto increment |
| `name` | VARCHAR(80) | Unique |
| `logo_url` | VARCHAR(500) NULL | Optional |
| `created_at` | DATETIME | Defaults to current time |

### `retailers`

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED PK | Auto increment |
| `name` | VARCHAR(120) | Display name |
| `slug` | VARCHAR(120) | Unique stable key |
| `website_url` | VARCHAR(500) | Used to validate product-link domains |
| `logo_url` | VARCHAR(500) NULL | Optional |
| `is_active` | TINYINT(1) | Inactive retailers are excluded from public retailer lists |
| `created_at`, `updated_at` | DATETIME | Audit timestamps |

### `products`

The central table. Important fields:

| Group | Columns |
|---|---|
| Identity | `id`, `brand_id`, `retailer_id`, `model`, `product_name`, `product_category` (`laptop`/`desktop`) |
| Processor | `processor_brand`, `processor_model`, `processor_generation`, `processor_cores` |
| Memory | `ram_gb`, `ram_type`, `ram_upgradeable` (`yes`/`no`/`unknown`) |
| Storage | `storage_type`, `storage_gb` |
| Graphics | `gpu_brand`, `gpu_model`, `gpu_type` (`dedicated`/`integrated`/`unknown`), `gpu_vram_gb` |
| Display | `screen_size`, `screen_resolution`, `screen_panel`, `refresh_rate` |
| System | `operating_system`, `weight_kg`, `battery` |
| Listing | `price_tnd`, `retailer_sku`, `product_url`, `product_url_hash`, `image_url`, `stock_status`, `warranty_information`, `source_url` |
| Verification | `link_status`, `link_checked_at`, `verified_at`, `raw_specs_json`, `is_active`, timestamps |

Indexes cover brand, retailer, category/price, active/stock, RAM, storage, GPU type and verification date. Foreign keys restrict deletion of referenced brands/retailers and cascade history/results appropriately. `CHECK` constraints enforce positive price, RAM and storage.

`stock_status` values:

- `in_stock` — retailer says in stock.
- `limited` — retailer says limited/arriving; eligible but labelled.
- `out_of_stock` — retained for transparency, excluded from recommendations.
- `preorder` — retained, currently not treated as purchasable by the engine.
- `unknown` — retained, not treated as purchasable.

### `activities` and `activity_requirements`

Nineteen activity profiles. Each requirement row stores minimum/preferred CPU tier, minimum/preferred RAM, minimum/preferred storage, GPU requirement, portability importance and seven scoring weights.

A database `CHECK` enforces that the seven weights total exactly 100. The engine also normalises weights defensively if an older database lacks the check.

### `workloads` and `activity_workloads`

Eighteen workloads, each with its own CPU/RAM/GPU/storage minimums. `activity_workloads` maps activities to workloads with a relevance value from 1 to 3, allowing central workloads to carry more weight in the fit calculation.

### `product_price_history`

Append-only rows of `product_id`, `price_tnd`, `stock_status` and `recorded_at`. The admin edit path calls `recordChange()` and inserts a new row only when price or stock actually changes. Deleting a product cascades its history.

### `recommendation_sessions` and `recommendation_results`

A session is created only when the public questionnaire is submitted. It stores:

- a 64-character random token (not a sequential public ID),
- activity name and selected workload JSON,
- category, budget, explicit minimums, dedicated-GPU preference,
- preferred brands and importance answers,
- result and candidate counts.

Results store the product, score, explanation, rank and score-breakdown JSON. `UNIQUE(session_id, product_id)` prevents duplicate products in one result set. Rank is constrained to 1–5 and score to 0–100.

No email, account, IP address or user agent is stored with a recommendation session.

### `admin_users`

Usernames and PHP `password_hash()` hashes. There is no seeded account. The application never stores or logs a plaintext password. `last_login_at` supports simple operational visibility.

## 4. Seed generation

`database/seed_base.sql` holds the stable reference data (activities, requirements, workloads, mappings, brands, retailers).

`database/seed.sql` is generated from:

1. `database/research/products.json` — 160 Tunisia Net records.
2. `database/research/manual_products_verified.json` — 10 manually reviewed cross-retailer records.

Run:

```bash
C:\xampp\php\php.exe tools\build_seed.php
```

The builder:

- deduplicates exact URLs and same-retailer configurations,
- assigns deterministic IDs in category/price/name order,
- normalises trailing retailer specification text from CPU/GPU model fields,
- maps brands/retailers,
- writes one initial price-history row per product,
- sets link status from the research evidence (`unchecked` for blocked manual pages),
- writes `database/research/dataset_summary.json`.

The seed is **not** imported automatically. Import it separately after reviewing the summary.

## 5. Useful queries

Catalogue counts:

```sql
SELECT product_category, COUNT(*) AS n
FROM products
WHERE is_active = 1
GROUP BY product_category;
```

Freshness buckets:

```sql
SELECT
  SUM(verified_at >= NOW() - INTERVAL 7 DAY) AS recent,
  SUM(verified_at < NOW() - INTERVAL 7 DAY
      AND verified_at >= NOW() - INTERVAL 30 DAY) AS needs_review,
  SUM(verified_at IS NULL OR verified_at < NOW() - INTERVAL 30 DAY) AS outdated
FROM products;
```

Duplicate URLs:

```sql
SELECT product_url, COUNT(*) AS n
FROM products
GROUP BY product_url
HAVING n > 1;
```

Products excluded from recommendations because they are not purchasable:

```sql
SELECT id, product_name, stock_status
FROM products
WHERE stock_status NOT IN ('in_stock', 'limited');
```

Budget integrity for the latest sessions:

```sql
SELECT s.id, s.budget_max, MAX(p.price_tnd) AS max_result_price
FROM recommendation_sessions s
JOIN recommendation_results r ON r.session_id = s.id
JOIN products p ON p.id = r.product_id
GROUP BY s.id, s.budget_max
HAVING max_result_price > s.budget_max;
```

An empty result means no stored recommendation exceeded its ceiling.

Price history for one product:

```sql
SELECT price_tnd, stock_status, recorded_at
FROM product_price_history
WHERE product_id = ?
ORDER BY recorded_at DESC, id DESC;
```

## 6. Data quality and maintenance

Generate the Markdown report:

```bash
C:\xampp\php\php.exe tools\data_quality.php
```

Check product links politely:

```bash
C:\xampp\php\php.exe tools\check_links.php --limit=50 --delay=1200
```

Only 404/410 is marked `broken`. 403/429 and network errors stay `unchecked`. The checker never changes price or stock.

Prune old anonymous sessions:

```bash
C:\xampp\php\php.exe tools\prune_sessions.php --days=180 --yes
```

Deleting sessions cascades their results. It does not touch products or price history.

## 7. Backup, restore and reset

Backup:

```bash
C:\xampp\mysql\bin\mysqldump.exe -u root --databases tunisia_pc_advisor > backup.sql
```

Restore:

```bash
C:\xampp\mysql\bin\mysql.exe -u root < backup.sql
```

Reset to the research snapshot:

```bash
C:\xampp\mysql\bin\mysql.exe -u root < database/schema.sql
C:\xampp\mysql\bin\mysql.exe -u root < database/seed.sql
C:\xampp\php\php.exe tools\create_admin.php --username=admin
```

The reset drops all application tables, including admin accounts and recommendation sessions. Back up first.

## 8. Security notes

- All application queries use PDO prepared statements. The only interpolated SQL values are hard-limited integers (page sizes, offsets and similar).
- Admin product URLs are hashed for uniqueness and validated against the selected retailer's host and path depth.
- Session tokens are random 256-bit hex strings; they are not derived from personal data.
- The database user should be restricted to this schema on anything other than a local teaching machine.
- `database/`, `config/`, `classes/`, `includes/`, `tools/` and `.env` are blocked by `.htaccess` under Apache and by `router.php` under the built-in server.
