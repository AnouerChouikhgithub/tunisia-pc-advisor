# Testing report

**Test date:** 10 November 2026 (Africa/Tunis)  
**Environment:** Windows, XAMPP PHP 8.3.31, MariaDB 10.4.32, Apache 2.4.58, Chrome headless for screenshots, and PHP's built-in server with `router.php` for the main HTTP checks.  
**Result:** **168 automated checks passed, 0 failed**, plus the HTTP, security, admin and responsive checks below.

## 1. Automated suite

Command:

```bash
C:\xampp\php\php.exe tools\run_tests.php
```

Output:

```text
168 passed, 0 failed.
```

Sections:

| Section | What it verifies |
|---|---|
| Database schema and integrity | Required tables, foreign keys, 150+ records, laptop/desktop targets, five retailers, URL/SKU uniqueness, missing critical fields, URL hashes, orphan records, positive prices, category values, duplicate configurations, initial price history, activity requirements, weight totals and workload mappings |
| Price history | Unchanged price/stock does not append; changed price appends a row; temporary test row cleaned up |
| Candidate filtering | Out-of-stock exclusion, budget ceiling, category, RAM, storage and dedicated-GPU filters, batch ordering |
| Questionnaire validation | Valid range/custom budget, invalid activity/workload/category, custom budget above 20,000, unknown workload IDs |
| Recommendation scenarios | Eight required scenarios, at most five results, at least one result where stock exists, no over-budget product, no out-of-stock product, active products, unique IDs, descending score, 0–100 range, breakdown arithmetic, weight total, category/min-RAM/GPU filters, HTTPS retailer-domain links, substantive and factual explanations, fewer-than-five note |
| Hard filter edge cases | 300 TND returns zero without inventing products; laptop-only and desktop-only scenarios |
| Security checks | SQL-injection payload treated as text, XSS escaping, CSRF generation/validation/rejection, password hashing, unauthenticated admin check, hash-only admin storage, HttpOnly/SameSite cookie settings |

### Recommendation scenarios

| Scenario | Budget | Results | Candidates | Top result | Top price |
|---|---:|---:|---:|---|---:|
| Student | 1,200 TND | 3 | 3 | BMAX B6 Turbo mini PC (88.74%) | 1,059.000 TND |
| Software developer | 2,500 TND | 5 | 26 | BMAX B9 Power mini PC (86.85%) | 1,699.000 TND |
| Graphic designer | 3,500 TND | 5 | 103 | Gamer LITE i5-14400F / RTX 3050 (81.67%) | 3,429.000 TND |
| Gamer | 4,000 TND | 5 | 16 | Gamer LITE i5-14400F / RTX 3050 (84.02%) | 3,429.000 TND |
| Video editor | 5,000 TND | 5 | 135 | Gamer LITE i7-14700F / RTX 3050 (83.46%) | 4,259.000 TND |
| AI developer | 6,000 TND | 5 | 101 | Gamer LITE i7-14700F / RTX 3050 (83.89%) | 4,259.000 TND |
| Office worker | 1,500 TND | 5 | 6 | BMAX B6 Turbo mini PC (89.79%) | 1,059.000 TND |
| Architect | 4,500 TND | 5 | 128 | Gamer LITE i7-14700F / RTX 3050 (84.09%) | 4,259.000 TND |

Every scenario passed the no-over-budget, no-out-of-stock, ranking, link-domain and explanation-fact assertions. The Student scenario correctly reports only three qualifying results instead of filling two slots.

## 2. HTTP and session tests

| Check | Observed result |
|---|---|
| Homepage | HTTP 200, 170 listings and 5 retailers rendered |
| Questionnaire | HTTP 200, five steps, CSRF field present |
| Submit valid questionnaire | HTTP 302 to a 64-hex-token result URL |
| Results | HTTP 200, five cards for the software-developer scenario |
| Invalid result token | HTTP 404 |
| Invalid product ID | HTTP 404 |
| Missing public-form CSRF | HTTP 419 with “Your form session expired” |
| Student 1,200 TND | HTTP 200, 3 cards, “Only 3 qualifying matches found” warning |
| 300 TND request | HTTP 200, zero cards, “No product satisfied every hard filter” |
| Comparison with two IDs | HTTP 200, mixed-category price winner suppressed, category badges shown |
| Unauthenticated `/admin/index.php` | HTTP 302 to `/admin/login.php` |
| Admin login page | HTTP 200, `Set-Cookie: tpa_session=...; path=/; HttpOnly; SameSite=Lax` |
| Wrong admin password | HTTP 200 with “Invalid username or password”; no session established |
| Correct admin login | HTTP 302 to dashboard |
| Admin dashboard | HTTP 200, 170 total products, 127 laptops, 43 desktops |
| Admin products/retailers/quality/account | HTTP 200 |
| Admin logout | HTTP 302 to login; dashboard redirects again |
| Admin POST without CSRF | HTTP 419 |
| Invalid admin product submission | HTTP 200 with URL validation error; zero rows inserted |
| Valid temporary admin product | HTTP 302 to edit page, row created and initial history row written |
| Admin price edit | HTTP 302; product price changed and a second append-only history row created |
| Admin deactivation | HTTP 302; `is_active` changed to 0; temporary row and history removed after the test |
| Final clean state | 170 products, 0 admins, 0 anonymous sessions, 170 initial history rows |

### Apache runtime check

A temporary Apache 2.4.58 instance was started on `127.0.0.1:8081` with the project as `DocumentRoot`, `AllowOverride All` and the XAMPP PHP 8.3 module. Results:

| Request | Result |
|---|---|
| `/` | 200 |
| `/questionnaire.php` | 200 |
| `/assets/css/style.css` | 200 |
| `/admin/index.php` | 302 to login |
| `/.env` | 403 |
| `/database/seed.sql` | 403 |
| `/docs/INSTALLATION.md` | 403 |
| `/config/database.php` | 403 |
| `/README.md` | 403 |
| Real questionnaire POST with `_csrf` | 302 to a tokenised result URL |
| That result URL | 200, 5 results, maximum price 2,469 TND against a 2,500 TND ceiling |

The Apache instance was stopped after the check. This confirms the included `.htaccess` rules are effective under Apache, not only under `router.php`.

On this particular Windows host, the machine-wide `PHP_INI_SCAN_DIR` pointed at a separate Scoop PHP installation, which made the temporary Apache module look for extensions in the wrong directory. The check was run with `PHP_INI_SCAN_DIR` cleared and `PHPRC` set to `C:/xampp/php`. That is a host-environment issue; normal XAMPP Apache does not set those variables to another PHP installation.

## 3. Purchase-link checks

Five purchase links from the sample result set were requested with a browser user agent:

```text
200 https://spacenet.tn/...bmax-b6...
200 https://spacenet.tn/...bmax-b9...
200 https://www.tunisianet.com.tn/...hp-laptop-15...
200 https://www.tunisianet.com.tn/...msi-modern-a14...
200 https://www.tunisianet.com.tn/...dell-16...
```

All were HTTPS and on the selected retailer's domain. The result page uses `target="_blank"` and `rel="noopener noreferrer"` on every purchase link.

Stored-session budget audit:

| Session | Budget | Max result price | Results |
|---|---:|---:|---:|
| 1 | 2,500.000 | 2,359.000 | 5 |
| 2 | 1,200.000 | 1,169.000 | 3 |
| 3 | 300.000 | NULL | 0 |

No stored recommendation exceeded its budget ceiling.

## 4. Responsive layout checks

The page was rendered at 1440, 1280, 768 and 390 CSS pixels.

| Width | Result |
|---|---|
| 1440 / 1280 | Desktop navigation, two-column hero and result cards; no horizontal overflow |
| 768 | Navigation collapses to the hamburger; result cards become one column; questionnaire layout becomes one column; no horizontal overflow after the quiz glow width fix |
| 390 | Mobile navigation, single-column hero/result cards, two-column activity choice grid; no horizontal overflow |

Fixes made during visual testing:

1. Added a global `[hidden]` rule so the questionnaire's “Back” and submit buttons do not appear on the wrong steps.
2. Changed the questionnaire glow from a fixed 800 px width to a percentage-limited width, removing a 9 px horizontal scroll at tablet width.
3. Aligned result-card images to the top of the media panel, removing a large blank white area before the product image.
4. Suppressed the “lower price” winner when comparing a laptop with a desktop, and added explicit category badges in the comparison header.
5. Enriched 160 product image URLs from the exact retailer pages and normalized trailing spec text from CPU/GPU model fields.

Screenshots: [desktop](screenshots/) · [mobile](screenshots/12-mobile-home.png).

## 5. Security checks

| Test | Result |
|---|---|
| SQL injection string used as admin search text | Treated literally; zero rows returned |
| `<script>` output escaping | Produces `&lt;script&gt;...` |
| CSRF token generation/validation | 64-character token; wrong/missing tokens rejected |
| Public/admin POST without token | HTTP 419 |
| Password hashing | `password_hash`/`password_verify` round-trip verified; admin table stores hashes |
| Unauthenticated admin access | Redirect to login |
| Session cookie | HttpOnly, SameSite=Lax |
| Login throttling | Six failures per 10-minute session window (code path present; one failure exercised) |
| Sensitive paths | `.env`, SQL, config and docs return 404 under the built-in-server router and 403 under Apache via `.htaccess` |

## 6. Data-quality checks

- 170 distinct product records; 127 laptops, 43 desktops.
- 5 retailers; 168 records with a product-image URL; 2 without (Mytek/Wiki).
- 0 duplicate product URLs; 0 duplicate retailer-SKU groups.
- 0 missing critical fields (CPU, RAM, storage, GPU, laptop screen, source URL, verification date).
- 0 invalid URL formats; 0 products with a URL on the wrong retailer domain.
- 163 in stock, 3 limited/arriving, 4 out of stock.
- 170 recently verified; 0 older than 30 days at the time of the report.
- 170 initial price-history rows.

The full generated report is [database/data_quality_report.md](../database/data_quality_report.md).

## 7. Known limitations and untested items

- **Mytek and Wiki** return HTTP 403 Cloudflare challenges to automated checks. Their records are manually reviewed and marked `unchecked`; no challenge was bypassed.
- **Two records have no image URL** because the blocked pages did not expose usable Open Graph metadata to the checker.
- **Low-budget depth is limited.** Only three laptop records are under 1,200 TND and one of those is out of stock. The engine returns fewer results rather than relaxing the budget.
- **Apache was validated with a temporary host config, not the XAMPP Control Panel virtual-host setup.** The dynamic flow and `.htaccess` denials passed; a deployment should still confirm its own `DocumentRoot` and `AllowOverride All`.
- **No full screen-reader audit or cross-browser matrix** was performed. The responsive checks used Chromium. Semantic landmarks, labels, a skip link, focus styles and reduced-motion CSS are present.
- **Prices and stock are not live.** They are verification-time snapshots. The app does not run a real-time polling service and does not claim to.
- **Hardware tiers are heuristics**, not measured benchmarks. The UI explicitly says so.
- The local `qa_admin` account and temporary test product/session rows used during testing were removed. There are currently no seeded admin credentials.

## 8. Reproduce

```bash
C:\xampp\php\php.exe tools/run_tests.php
C:\xampp\php\php.exe tools/data_quality.php
C:\xampp\php\php.exe tools/check_links.php --limit=10 --delay=1200
```

For the HTTP flow, run:

```bash
C:\xampp\php\php.exe -S 127.0.0.1:8080 router.php
```

Then use the questionnaire and admin login in a browser. The automated suite does not require an administrator account.
