# Recommendation algorithm

Tunisia PC Advisor uses a deterministic PHP engine. It does not call an AI model, does not use a hosted recommendation service and does not generate free-form product claims. This document describes the actual rules used by `RecommendationEngine`, `HardwareEvaluator` and `RecommendationExplanationGenerator`.

## 1. Inputs and validation

The questionnaire submits:

- one activity (`activity_id`),
- one or more workloads (`workloads[]`),
- a device category: `laptop`, `desktop` or `both`,
- a budget range or a custom maximum in TND,
- optional minimum RAM and storage,
- an optional dedicated-GPU requirement (`yes`, `no`, `any`),
- optional preferred brands,
- optional portability, performance and battery importance.

`QuestionnaireValidator` verifies IDs against the database, rejects unknown/crafted values, accepts a custom budget between 300 and 20,000 TND and returns a typed `RecommendationRequest`. Errors redirect back to the correct questionnaire step. No client-supplied score or product ID is trusted.

## 2. Hard filters

These conditions remove a product before any scoring:

| Filter | Rule |
|---|---|
| Active | `products.is_active = 1` |
| Purchasable | `stock_status IN ('in_stock', 'limited')` |
| Budget ceiling | `price_tnd <= budget_max` |
| Category | `product_category` equals the requested laptop/desktop type; `both` does not filter |
| Minimum RAM | Applied only when the user explicitly chooses a minimum |
| Minimum storage | Applied only when the user explicitly chooses a minimum |
| Dedicated GPU | Applied only when the user explicitly requires one |
| Out-of-stock | Excluded from candidates, retained in the catalogue for audit |

**A product over the maximum budget is never loaded as a candidate.** There is no “slightly over budget” alternative path.

Activity minimum/preferred hardware targets are **soft constraints**. They affect the score but do not automatically remove a product. This distinction is what allows the engine to return a sensible budget option while still naming its trade-off.

If no products survive, the engine returns an empty result and a factual note. If fewer than five survive, it returns the available number and explains the shortfall. It never invents products.

## 3. 100-point score

Seven components are calculated on a 0–1 scale, multiplied by activity-specific percentage weights, then summed to 100.

| Component | What it measures |
|---|---|
| CPU suitability | Processor family tier against the activity's minimum and preferred tier |
| RAM suitability | Capacity against minimum/preferred targets, plus a small documented DDR5/LPDDR5 and upgradeability bonus |
| GPU suitability | Integrated/dedicated type and family tier against the activity's GPU requirement, with a VRAM adjustment when documented |
| Storage suitability | Capacity against minimum/preferred targets plus technology factor (NVMe > SSD > eMMC > HDD) |
| Budget and value | Affordability/headroom and relative performance per dinar |
| Activity and workload fit | Weighted fit across every selected workload |
| User preferences | Brand match, portability, performance, battery and screen preference |

### Activity weights

Weights are stored in `activity_requirements` and always sum to 100:

| Activity | CPU | RAM | GPU | Storage | Budget/value | Activity | Preferences |
|---|---:|---:|---:|---:|---:|---:|---:|
| Student | 20 | 17 | 8 | 12 | 25 | 11 | 7 |
| Software Developer | 24 | 22 | 8 | 12 | 18 | 11 | 5 |
| Web Developer | 22 | 20 | 8 | 12 | 22 | 11 | 5 |
| Mobile Application Developer | 22 | 22 | 8 | 12 | 20 | 11 | 5 |
| Graphic Designer | 18 | 18 | 20 | 12 | 18 | 8 | 6 |
| UI/UX Designer | 18 | 18 | 16 | 10 | 22 | 10 | 6 |
| Video Editor | 20 | 20 | 22 | 15 | 10 | 8 | 5 |
| 3D Designer / 3D Artist | 18 | 20 | 25 | 12 | 10 | 9 | 6 |
| Architect | 18 | 20 | 24 | 12 | 12 | 8 | 6 |
| Engineer | 20 | 18 | 18 | 12 | 18 | 8 | 6 |
| Data Scientist | 20 | 22 | 18 | 12 | 14 | 9 | 5 |
| Artificial Intelligence / Machine Learning | 18 | 22 | 24 | 12 | 12 | 7 | 5 |
| Cybersecurity Specialist | 24 | 20 | 6 | 12 | 20 | 12 | 6 |
| Office Worker | 22 | 18 | 5 | 12 | 25 | 11 | 7 |
| Business Manager | 20 | 18 | 5 | 12 | 27 | 11 | 7 |
| Gamer | 16 | 14 | 32 | 10 | 14 | 9 | 5 |
| Content Creator | 18 | 18 | 20 | 14 | 16 | 9 | 5 |
| General Personal Use | 18 | 16 | 10 | 12 | 26 | 11 | 7 |
| Other | 18 | 18 | 14 | 12 | 22 | 10 | 6 |

A gamer therefore gets 32% of the score from graphics, while an office worker gets 5%; a software developer gets more weight on CPU and RAM. These weights are visible in every result's “Why this score?” panel.

## 4. Component formulas

### CPU

`HardwareEvaluator::cpuTier()` maps a processor family to a transparent 0–10 tier: Apple M-series, Intel Core/Core Ultra, AMD Ryzen/AI, Celeron/N-series, Pentium, Athlon and Xeon. Generation/series adjustments are applied for Intel `i3/i5/i7/i9-xxxx` and Ryzen `3/5/7/9-xxxx`, and H/HX suffixes. This is a rule-based family classification, **not a benchmark**.

Given activity minimum `minCpu` and preferred `prefCpu`:

- tier >= preferred → 1.0
- tier between minimum and preferred → `0.68 + 0.32 × (tier − min) / (pref − min)`
- tier below minimum → `max(0.10, 0.50 × tier / min)`
- unclassified → 0.25 and an explicit “unclassified” detail

### RAM

Given `ram`, `min`, `pref`:

- `ram >= pref` → 1.0
- `ram >= min` → `0.70 + 0.30 × (ram − min) / (pref − min)`
- below minimum → `max(0.10, 0.45 × ram / min)`

A documented DDR5/LPDDR5 type adds 0.02 and a documented upgradeable slot adds 0.01, capped at 1.0. Unknown type or upgradeability adds nothing.

### Storage

Capacity follows the same minimum/preferred pattern, then is blended with technology:

`0.80 × capacity + 0.20 × typeFactor`

where NVMe = 1.0, SSD = 0.90, eMMC = 0.60, HDD = 0.35 and unknown = 0.55. The result is capped at 1.0.

### GPU

`HardwareEvaluator::gpuTier()` has explicit family tiers for RTX 50/40/30/20, GTX 16/10, Radeon RX, Radeon integrated, Arc, Iris Xe, Intel UHD and Apple GPUs. It is a rule classification, not a frame-rate claim.

The activity GPU requirement changes the logic:

- `none` / `integrated_ok`: integrated graphics score 0.95/0.96; dedicated scores 1.0.
- `dedicated_preferred`: dedicated scores `0.68 + 0.32 × tier/10`; integrated scores lower; Apple integrated gets a documented exception at 0.72.
- `dedicated_required`: dedicated is required for a high score; integrated scores 0.10 (or 0.35 for a capable Apple integrated GPU).
- When a required/preferred VRAM figure exists and the product is dedicated, missing VRAM applies a 0.92 multiplier and insufficient VRAM applies 0.85.

### Budget and value

For each candidate with price `p` and maximum budget `B`:

- headroom ratio = `clamp((B − p) / B, 0, 1)`
- affordability = `0.60 + 0.40 × headroomRatio`
- value ratio = `(performanceComposite × 1000) / max(0.001, p)`
- valueNorm = `0.15 + 0.85 × (ratio − minRatio) / (maxRatio − minRatio)` across the current candidate set (0.65 if all ratios are equal)
- budget score = `0.55 × affordability + 0.45 × valueNorm`

`performanceComposite = 0.40 × CPU + 0.25 × RAM + 0.25 × GPU + 0.10 × storage`.

This makes affordability important without letting a cheap but unsuitable product win. The final result is capped at 100.

### Activity/workload fit

For each selected workload, the engine calculates:

`0.35 × CPU fit + 0.30 × RAM fit + 0.20 × GPU fit + 0.15 × storage fit`

Each workload has a relevance weight from `activity_workloads` (1–3). The final activity score is the relevance-weighted average. Selecting more workloads broadens the requirements; it does not dilute the hard budget filter.

### Preferences

The optional preference component is assembled from whatever the user selected:

- preferred brand match → 1.0 vs 0.35 for outside the brand list, weight 2
- portability → weight-based score for laptops, 0.20 for desktops, weight 2
- performance importance → the performance composite, weight 2
- battery importance → documented battery capacity, or a neutral value when unknown, weight 1
- screen quality for graphic/UI/video profiles → resolution and documented IPS/OLED, weight 1

The weighted average is normalized to 0–1. When no optional preference is active, the component is 0.70 with a “No optional preference was active” detail.

## 5. Ranking and diversity

After scoring:

1. Sort by compatibility score descending.
2. Use performance-per-dinar as the first tie-breaker.
3. Use lower price as the final tie-breaker.
4. Apply a diversity pass:
   - at most one product per model family,
   - at most three products per brand,
   - if fewer than five remain, relax family uniqueness first, then brand limits,
   - if still fewer than five, append the next ranked products.
5. Re-sort the selected five by the same score/value/price ordering.

The diversity pass avoids five RAM variants of one laptop when there are equally suitable alternatives. It never adds a product that failed a hard filter.

## 6. Explanations

`RecommendationExplanationGenerator` builds a paragraph from conditional rules:

- profile and compatibility percentage,
- actual processor model, mapped rule class and whether it meets minimum/preferred,
- actual RAM and whether it meets preferred/minimum; mentions upgradeability only when the database says `yes`/`no`,
- actual storage capacity/type and target comparison,
- actual GPU model and integrated/dedicated status; VRAM only when stored,
- laptop screen size/resolution/panel only when stored,
- price, retailer and verification date,
- explicit trade-offs (CPU class, RAM, storage, missing dedicated GPU, full-budget use),
- the selected workloads.

There is no template that inserts a field the product does not have. This is why the interface can say “Weight not stated” while the explanation simply omits weight rather than inventing it.

## 7. Worked example

Scenario: Student, 1,200 TND, laptop or desktop, Programming + Virtual machines.

- Candidate set: 3 purchasable listings at or below 1,200 TND (two desktops and one laptop).
- Top result: BMAX B6 Turbo mini PC, 1,059 TND, 16 GB RAM, 512 GB SSD, Intel Core i5-8257U, integrated Intel Iris Plus 645.
- The CPU meets the Student preferred class in the family-tier rule set; RAM and storage meet the preferred targets; integrated graphics are appropriate for the profile; the price leaves 141 TND of headroom.
- The result page says “Only 3 qualifying matches found” and does not add two unsuitable products.
- Every purchase link points to the SpaceNet product page; both remaining results are also under 1,200 TND.

A 300 TND request returns zero results and a factual “No product satisfied every hard filter” state. A 2,500 TND software-developer request returns five results, all within budget.

## 8. What the engine does not claim

- It does not claim live stock or live prices.
- It does not claim benchmark superiority from a model number.
- It does not claim that a product with missing data has a particular weight, battery life or colour gamut.
- It does not use affiliate/sponsor signals.
- It does not use an LLM to rewrite or embellish the explanation.

The rule set and weights are inspectable in `database/seed_base.sql`, `classes/HardwareEvaluator.php` and `classes/RecommendationEngine.php`.
