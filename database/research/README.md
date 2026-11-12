# Research evidence

This folder contains the data-collection artifacts used to build the catalogue. They are kept so the 170 records can be audited or regenerated; they are not served by the application.

| File | Contents |
|---|---|
| `products.json` | 160 selected Tunisia Net records, including direct URL, structured specifications, price, stock label, image URL and raw feature payload |
| `products_first_pass.json` | Earlier 160-record pass kept for comparison |
| `tunisianet_candidates.json` | Valid candidate pool from the extended scan |
| `tunisianet_sitemap_urls.txt` | Product sitemap URLs used by the collector |
| `manual_products.json` | 10 manually reviewed Mytek, SpaceNet, SBS Informatique and Wiki records |
| `manual_products_verified.json` | The same manual records with image URLs where the page exposed them |
| `manual_verification.json` | HTTP status, page title and Open Graph image check for each manual record |
| `dataset_summary.json` | Generated counts by retailer, brand, category, price band, stock and missing fields |
| `scrape.log` | Collector run log; local only and ignored by Git |

## Method

- Tunisia Net was read from its public product sitemap and individual product pages with a single-worker, rate-limited collector on **10 November 2026**.
- Structured page data was used for the reference, price, stock label, image and specification fields.
- Only records with a direct product URL, brand, model, CPU, RAM, storage and TND price were selected.
- Manual records came from individual retailer product pages, not search pages or homepages.
- Missing optional fields were left empty. Prices and stock are point-in-time snapshots.

## Verification status

- 8 of the 10 manual product pages returned **HTTP 200** with an Open Graph image.
- Mytek and Wiki returned **HTTP 403** (`Just a moment...` Cloudflare challenge) and no image. Their records remain manually reviewed, with `link_status='unchecked'`, because the automated check was blocked rather than proving the page missing.
- The two missing image URLs are exactly those two blocked records.

## Regeneration

```bash
C:\xampp\php\php.exe tools/scrape_tunisianet.php
C:\xampp\php\php.exe tools/verify_manual.php
C:\xampp\php\php.exe tools/enrich_images.php
C:\xampp\php\php.exe tools/build_seed.php
C:\xampp\php\php.exe tools/data_quality.php
```

Review `dataset_summary.json` before importing the regenerated `database/seed.sql`. Research collection is a maintenance action and must respect each retailer's current terms, rate limits and `robots.txt`. Do not bypass access controls.

## Notes about specific records

- **Mytek ASUS TUF A14:** the listing price came from Mytek's own product listing because the page price block did not render in the text extraction. Recheck it in a browser before relying on the price.
- **Wiki MSI Thin 15:** the page title/description said 20 GB while the specification table said 16 GB. The conservative 16 GB table value is stored; the inconsistency is retained here.
- **BMAX/PROBOX:** brands were taken from the product naming, not inferred from a marketplace category.
- **Images:** the enrichment script reads only the Open Graph image of the exact product page; it does not download or re-host the retailer's image.

The generated, user-facing summary is [../data_quality_report.md](../data_quality_report.md).
