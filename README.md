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
- `docs/` — requirements, plan, plugin list

## Common commands (Git Bash, from the project root)
| Task | Command |
|---|---|
| Apply store configuration | `tools/wp.sh eval-file tools/setup/store-setup.php` |
| Regenerate demo images | `(cd tools && npm install && npm run images)` |
| Import / update demo products | `tools/wp.sh eval-file tools/setup/demo-products.php` |
| Check Arabic translations are complete | `/c/xampp/php/php.exe tools/check-translations.php` |

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
