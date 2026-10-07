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
