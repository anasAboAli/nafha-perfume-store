# Nafha | نفحة — perfume decant store

WooCommerce store for perfume decants (5/10/30 ml + full bottles + gift boxes), Arabic RTL, mobile-first.
Requirements: [docs/requirements.pdf](docs/requirements.pdf) · Plan: [docs/PLAN.md](docs/PLAN.md)

## Local environment (XAMPP)
| | |
|---|---|
| Site | http://localhost:8080 (Apache vhost `C:\xampp\apache\conf\extra\perfume-store.conf`) |
| Admin | http://localhost:8080/wp-admin — credentials in `.local-credentials.txt` (git-ignored) |
| DB | MariaDB `perfume_store`, user `root`, no password |
| WP-CLI | `tools/wp.sh <command>` (from Git Bash) |

Start services: run XAMPP Control Panel → start **Apache** and **MySQL**.

## Code layout
- `public/` — WordPress install (core is not committed)
- `public/wp-content/themes/nafha-child/` — child theme of Blocksy (presentation only)
- `public/wp-content/plugins/nafha-core/` — store features (custom fields, WhatsApp order, quiz, side cart, tracking…)
- `docs/` — requirements, plan, plugin list, [bulk import guide](docs/IMPORT-GUIDE.md) + sample CSV

## Common commands (Git Bash, from the project root)
| Task | Command |
|---|---|
| Apply store configuration | `tools/wp.sh eval-file tools/setup/store-setup.php` |
| Regenerate demo images | `(cd tools && npm install && npm run images)` |
| Import / update demo products | `tools/wp.sh eval-file tools/setup/demo-products.php` |
| Write home page + static pages (OVERWRITES page content — run once) | `(cd tools && node images/hero.js)` then `tools/wp.sh eval-file tools/setup/content.php` |
| Rebuild the product search index | `tools/wp.sh nafha reindex` |
| Check Arabic translations are complete | `/c/xampp/php/php.exe tools/check-translations.php` |

Local e-mails are not sent: `mu-plugins/nafha-local-mail.php` saves them to `wp-content/uploads/nafha-mail/` (only when `WP_ENVIRONMENT_TYPE` is `local`).

Demo products are flagged with `_nafha_demo` — see the header of `tools/setup/demo-products.php` for the one-line removal before launch.

## Custom features (nafha-core)
| Module | What it does |
|---|---|
| `class-qatar-areas.php` | Qatar municipalities as checkout areas (drive shipping zones) |
| `class-shipping.php` | Hides paid rates once free shipping applies |
| `class-settings.php` | WooCommerce → Settings → Nafha (WhatsApp number) |
| `class-fragrance-profile.php` | "Fragrance profile" product tab: notes, longevity, sillage, English name, video |
| `class-variation-buttons.php` | Size buttons with per-size price (replaces the dropdown) |
| `class-whatsapp.php` | "Order via WhatsApp" button with product/size/qty/price message |
| `class-product-page.php` | Stock indicator, "From $X" prices, similar / you-may-also-like logic |
| `class-review-photos.php` | Optional photo on reviews (validated, held for moderation) |
| `class-shop-filters.php` | Off-canvas filters with live counts, active-filter chips, sorting options |
| `class-cart.php` | Side cart (opens on add-to-cart / cart icons), free-shipping progress bar, cart WhatsApp button |
| `class-checkout.php` | Short checkout (name, mobile, area, address, notes), phone validation + normalisation to +974…, no terms checkbox |
| `class-whatsapp-gateway.php` | "Confirm via WhatsApp" checkout method: order saved on-hold, then WhatsApp opens with the full summary |
| `class-quiz.php` | Fragrance quiz `[nafha_quiz]`: 6 questions, server-side scoring, match % on product cards |
| `class-offers.php` | Discovery box builder `[nafha_discovery_box]` (discounted box of 3/5 decants) and sample quantity discount |
| `class-reviews-showcase.php` | `[nafha_reviews]` — latest real 4–5★ reviews for the home page |
| `class-import-export.php` | "Brands" column for the product CSV importer/exporter |
| `class-seo.php` | Product schema name/alternateName fix on top of Rank Math |
| `class-tracking.php` | GTM container, GA4 e-commerce dataLayer events, Consent Mode v2 + consent bar ([docs/TRACKING.md](docs/TRACKING.md)) |
| `class-product-feed.php` | Google Merchant / Meta Catalog feed at `/feed/nafha-products/` |
| `class-search.php` | Normalised product search index (Arabic variants, English names, brands + aliases, notes); `wp nafha reindex` |
