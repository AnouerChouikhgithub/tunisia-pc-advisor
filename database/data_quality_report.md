# Data Quality Report — Tunisia PC Advisor

**Generated:** 10 November 2026, 16:42 (Africa/Tunis)  
**Database:** `tunisia_pc_advisor`  
**Primary snapshot:** 10 November 2026  
**Multi-retailer manual review:** 7 November 2026

## Executive summary

| Requirement | Target | Result | Status |
|---|---:|---:|---|
| Distinct verified catalogue records | 150 | 170 | Met |
| Laptop configurations | 120 | 127 | Met |
| Desktop configurations | 30 | 43 | Met |
| Retailers represented | Not fixed | 5 | Five Tunisian retailers |
| Duplicate product URLs | 0 | 0 | Pass |
| Duplicate retailer SKU groups | 0 | 0 | Pass |
| Records with missing critical fields | 0 | 0 | Pass |

The catalogue target was met without fabricating products. The 170 records are distinct product pages/configurations, not repeated rows created to reach the threshold. Prices are point-in-time retailer snapshots, not live feeds.

## Sources and method

- **Tunisia Net:** public product sitemap and individual product pages, collected 10 November 2026 with a single-worker, rate-limited collector. Structured page data was used for reference, price, stock label, image and specifications. The collector was extended into older catalogue pages to improve price-band coverage.
- **Mytek, SpaceNet, SBS Informatique, Wiki:** individual product pages reviewed manually. Only documented fields were entered. A separate HTTP check captured status, title and Open Graph image where accessible.
- A record was accepted only when processor, RAM, storage, price, brand, model and a direct product URL were available. Missing optional fields remain `NULL`.
- No access controls, authentication, paywalls or anti-bot challenges were bypassed. HTTP 403 responses are recorded as blocked/unconfirmed, not as a successful verification.

## Coverage

### By retailer

| Retailer | Records | Out of stock |
|---|---:|---:|
| Tunisia Net | 160 | 0 |
| SpaceNet | 4 | 0 |
| SBS Informatique | 4 | 4 |
| Mytek | 1 | 0 |
| Wiki | 1 | 0 |

### By brand

| Brand | Records |
|---|---:|
| ASUS | 46 |
| MSI | 32 |
| Lenovo | 25 |
| HP | 24 |
| Dell | 20 |
| Tunisia Net | 11 |
| Blackview | 5 |
| Bmax | 3 |
| Apple | 3 |
| PROBOX | 1 |

### By category and price band (TND)

| Category | Price band | Records |
|---|---|---:|
| Laptop | Under 1,200 | 3 |
| Laptop | 1,200–1,799 | 19 |
| Laptop | 1,800–2,499 | 26 |
| Laptop | 2,500–3,499 | 38 |
| Laptop | 3,500–4,999 | 26 |
| Laptop | 5,000–6,999 | 10 |
| Laptop | 7,000+ | 5 |
| Desktop | Under 1,200 | 1 |
| Desktop | 1,200–1,799 | 4 |
| Desktop | 1,800–2,499 | 7 |
| Desktop | 2,500–3,499 | 9 |
| Desktop | 3,500–4,999 | 6 |
| Desktop | 5,000–6,999 | 3 |
| Desktop | 7,000+ | 13 |

## Stock and freshness

| Metric | Count |
|---|---:|
| In stock at verification | 163 |
| Limited / arriving | 3 |
| Out of stock | 4 |
| Recently verified (0–7 days) | 170 |
| Needs review (8–30 days) | 0 |
| Outdated or never verified | 0 |
| Broken links marked by maintenance | 0 |
| Average listed price | 4 157,206 TND |

Out-of-stock records remain in the catalogue for auditability but are excluded from recommendation candidates. Limited/arriving records remain eligible and are labelled as limited in the UI.

## Integrity checks

- **Duplicate direct URLs:** 0 group(s).
- **Duplicate retailer SKU combinations:** 0 group(s).
- **Invalid URL format:** 0 record(s).
- **Records missing a critical field:** 0.
- **Price range:** 1 019,000 TND to 19 799,000 TND.
- **Categories:** Laptop = 127, Desktop = 43.
- **Link status:** unchecked = 2, ok = 168.

## Field completeness

Optional fields are deliberately allowed to be `NULL`. The following counts show documented coverage at the time of this report:

| Field | Records with a value |
|---|---:|
| Weight | 0 |
| Battery detail | 0 |
| Screen panel type | 53 |
| Operating system | 169 |
| Warranty text | 160 |
| GPU VRAM | 47 |
| RAM upgradeable = yes | 0 |
| RAM upgradeable = no | 1 |
| RAM upgradeability unknown | 169 |

## Exceptions and limitations

- **Mytek and Wiki automated HTTP checks returned 403** (Cloudflare challenge) on 10 November 2026. Their records are included from the manual page review with product URL, title, specifications and price evidence, but the automated recheck is blocked. They are flagged `unchecked`, not `ok` or `broken`, in the link-status field.
- **Mytek price extraction caveat:** the product page price block did not render in the text extraction; the listed price came from Mytek’s own product listing. This is recorded in `manual_products.json` and should be rechecked in a browser before relying on it.
- **Wiki RAM inconsistency:** the page title/description said 20 GB, while the specification table said 16 GB. The 16 GB specification-table value is stored and the inconsistency is retained in the research notes rather than silently resolved.
- **Low-budget supply:** the selected catalogue contains only one laptop under 1,200 TND. The investigated current listings did not supply a deep sub-1,200 TND segment. The engine returns fewer than five matches rather than relaxing the budget.
- **Brand coverage:** ASUS, HP, Lenovo, Dell, MSI, Apple, Blackview and Tunisia Net house configurations are represented. No Acer, Gigabyte or Samsung listing survived the selected verified set. They were not invented to fill brand quotas.
- **Point-in-time prices:** every record is a snapshot from its verification date. The application marks freshness but does not claim real-time prices or stock.
- **Heuristic hardware tiers:** the recommendation engine uses family-level tiers, not measured benchmarks. Explanations describe the stored specifications and rule classification, not benchmark superiority.
- **Four out-of-stock and three limited-stock records** are included in the 170-record catalogue. They are excluded or labelled appropriately in the recommendation interface.

## Manual URL verification snapshot

| Retailer | Product | HTTP | Page title captured |
|---|---|---:|---|
| mytek | ASUS TUF Gaming A14 (FA401KH-RG005W) | 403 | Just a moment... |
| spacenet | Dell Vostro 3530 | 200 | Pc Portable Dell Vostro 3530 i3 13Gén Au Meilleur Prix En Tunisie |
| spacenet | Dell Latitude 5420 | 200 | Pc Portable Dell Latitude 5420 i7 11Gén 8 Go 256 Go SSD - SpaceNet |
| spacenet | BMAX B6 Turbo mini PC | 200 | Mini Pc Bmax B6 Intel I5 16Go 512Go SSD W11 Pro Au Meilleur Prix En Tunisie |
| spacenet | BMAX B9 Power mini PC | 200 | Mini Pc Bmax B9 Power i9 12Gén 24Go 1To SSD Au Meilleur Prix En Tunisie |
| sbs-informatique | Lenovo IdeaPad Slim 3 15IRU8 | 200 | Pc Portable Lenovo IdeaPad Slim 3 15IRU8 - Intel® Core™ i3-1315U - 8 Go - 512 Go / Tunisie |
| sbs-informatique | Lenovo LOQ 15IAX9e | 200 | PC Portable - LENOVO LOQ 15IAX9e i5-12450HX 16Go 512Go SSD RTX3050 / Tunisie |
| sbs-informatique | Lenovo LOQ 15IRX9 | 200 | PC Portable - LENOVO LOQ 15IRX9 i7 13ème Gén 16G RTX3050 / Tunisie |
| sbs-informatique | PC PROBOX NEXT desktop | 200 | PC PROBOX NEXT - Intel Core i5-12400F - 8Go - 256Go-GT 730 4GB / Tunisie |
| wiki | MSI Thin 15 B13UC | 403 | Just a moment... |

## Reproducing and maintaining

```bash
/c/xampp/php/php.exe tools/scrape_tunisianet.php   # optional, rate-limited research refresh
/c/xampp/php/php.exe tools/verify_manual.php       # recheck multi-retailer pages
/c/xampp/php/php.exe tools/build_seed.php          # regenerate database/seed.sql
/c/xampp/php/php.exe tools/check_links.php --limit=50 --delay=1200
/c/xampp/php/php.exe tools/data_quality.php        # regenerate this report
```

A successful structural check is not proof that a retailer’s current price or stock is unchanged. Freshness must be reviewed manually in the admin dashboard.
