# wechevo e-shop platform: final report

- **Branch:** `claude/website-design-process-he2shz`
- **Code:** `saas-platform/` (153 PHP files, about 14k lines of PHP/CSS/JS, with no dependencies or build step)
- **Fresh-install package:** `dist/saas-platform-site-php.zip`. It contains no data, uploads, secrets, tests or tools.
- **Deployment guide:** `saas-platform/README.md` (Hostinger)

---

## 1. Implemented

### Platform and architecture

- **One installation serves many domains.** Requests are resolved by host name: the setup wizard (before install), the platform host (Super Admin), or a shop domain (storefront and `/admin`).
- **Tenant isolation:**
  - The control plane lives in `data/platform.sqlite`; each shop has its own `data/shops/shop_{id}.sqlite` and `uploads/{id}/`.
  - Sessions are scoped per host; a session presented to another shop is destroyed.
- **Database:**
  - Migrations are tracked with `PRAGMA user_version`; WAL mode is on.
  - Accent-insensitive Greek search uses a `FOLD()` SQL function.
  - Transactions can nest, using savepoints.
- **One-domain mode:** the platform domain can also be a shop's domain, giving the storefront at `/`, the shop admin at `/admin` and the Super Admin at `/superadmin`. The two logins use separate sessions, `/superadmin` keeps working while the shop is locked, robots.txt hides `/superadmin/`, and the `www.` variant is handled (README §2b).
- **Setup wizard:** checks requirements, sets the platform host and creates the first Super Admin. There are no default credentials.
- **Cron:** runs from the CLI or from an HTTP URL with a key. It handles subscription checks, the background import queue and feed refresh. Status is also refreshed on every request.

### Super Admin

- **Dashboard:** MRR, expiring shops, locked shops, network-wide product totals and notifications.
- **Shop provisioning wizard:** plan, duration, template, domain, owner, demo content and setup-fee/payment records.
- **Per-shop page:**
  - details, domains (primary, SSL/DNS status, DNS check), subscription and plan
  - template (switching it keeps all data) and the templates the shop may choose from itself
  - per-feature access overrides and an admin-login switch, plus an active-limit override
  - owner password reset, backups, payments, audit trail, and a danger zone (typed-domain confirmation, with an automatic backup before deletion)
- **Plans are data, not code:** price, active-product limit (20/50/100), stored-product limit (10,000), staff limit, durations and feature flags.
- **Templates:** enable or disable each one per plan, and preview it with an HMAC-signed link.
- **Other areas:** payments, audit log, notifications, platform settings (SMTP, grace days, lock message, server IP, run cron), backups and Super Admin users.
- **Impersonation:** a one-time token valid for 120s and bound to one shop. Every use is audited and an "impersonating" bar stays visible.

### Subscriptions and limits

- **Status flow:** ACTIVE → EXPIRING → EXPIRED (optional grace days) → LOCKED.
- **Warnings at 10, 3 and 1 days:** each one is a Super Admin notification plus email, an **email to the shop owner**, and a dashboard banner.
- **Renew** continues from the current end date; **reset** starts from today. Neither ever touches shop data.
- **Downgrade** offers two modes, "deactivate excess" or "let the shop choose". Products are never deleted, and the storefront is always capped at the plan limit (oldest activations win).
- **Quota enforcement:** every activation path (toggle, bulk, import, product save) runs inside `BEGIN IMMEDIATE`. A quota pill in the admin top bar shows usage. Denials are written to the audit log.
- **Lock page:** branded, served as 503 with Retry-After. It shows no products, prices, forms or admin links. Feeds, sitemap, search, the APIs and robots.txt are also closed.

### Shop Admin (39 sections)

- **Dashboard:** onboarding checklist, stats, low stock and expiry banner.
- **Catalog:**
  - **Products:** variants, attributes, gallery, FAQ, SEO fields, duplicate, bulk actions.
  - **Categories:** tree structure.
  - **Media library:** GD re-encoding, WebP at 400/800/1200, max 2000px, max 10 MB.
  - **CSV/XML import:**
    - field mapping and preview
    - batches of 200 rows within about 6 seconds per request, with a progress bar; works with or without cron
    - Greek Windows-1253 fallback and auto-detected delimiters
  - **CSV export.**
- **Content and marketing:**
  - Pages and blog, with a Markdown helper and FAQ extraction.
  - Homepage CMS (sections, order), menu builder, appearance (logo, colours, fonts within template limits).
  - Pop-ups with GDPR-compliant email capture and a subscriber list with export.
  - Skroutz, BestPrice and custom XML feeds.
- **SEO:** settings, analyzer, redirects, 404 monitor with one-click 301.
- **Sales:** orders (status, notes, CSV export), customers, contact messages.
- **Settings:** store, shipping zones and pickup, payments (cash on delivery, bank transfer; Stripe and Viva prepared but not active), checkout options.
- **Staff:** owner, administrator, product manager and editor, plus per-user custom modules. Escalation is prevented (users can only manage lower ranks), and the plan's staff limit applies.
- **Account and activity log.**

### Storefront (10 templates, all switchable from the Super Admin)

- **Templates:** Atelier (fashion), Agora (electronics), Kipos (food/gifts), Lumière (beauty), Oikos (home), Karat (jewellery, dark), Stadio (sports), Paidi (kids), Ergo (tools/B2B), Iasis (pharmacy).
  - Each has its own design tokens and self-hosted Greek+Latin fonts.
  - Layout options per template: 10 header styles, 10 hero styles, 10 card styles, 4 product-page layouts.
- **Pages:** home, listing with filters and sorting, product, search with live suggestions, cart page and AJAX drawer, wishlist (local, synced to the account), checkout (cash on delivery, bank transfer, pickup, invoice fields, PRG, duplicate-submit protection), order page, customer accounts, blog, CMS pages, contact form (honeypot, timing check, rate limit).
- **SEO/AEO/GEO:**
  - JSON-LD (OnlineStore, WebSite + SearchAction, WebPage, Product with Offer, BreadcrumbList, FAQPage, Article)
  - canonical URLs, OG tags
  - sitemap.xml, robots.txt (AI crawlers allowed), llms.txt, RSS
  - automatic 301 on slug change, 404 log
- **Cookie consent:** GA4 loads only after consent.
- **Accessibility:** skip link, focus states, ARIA on dialogs and the drawer, reduced-motion support, 16px inputs on mobile.

---

## 2. Tested (all passing)

| Suite | Result |
|---|---|
| `php -l` on every PHP file | 0 errors |
| **Acceptance suite** `tests/acceptance.py --big` (on the dev install and on a **fresh install from the ZIP**) | **55/55**, now **65/65** with the one-domain checks |
| Crawl `tests/sweep.py`: every Super Admin link (43), Shop Admin link (56) and about 32 storefront URLs × 10 template shops | no non-200/302, no PHP warnings in the log, 404 page returns 404 |
| JSON-LD on home, listing and product pages of all 10 template shops | valid JSON, expected types |
| Playwright `tests/shots.js` (storefront home/category/product/cart) and `tests/admin_shots.js` at 1440 and 390 | no JS errors, **no horizontal overflow at 390px** |
| Fresh install from the ZIP | setup wizard → Super Admin → shop provisioning → storefront → cron → internals return 404 |

The acceptance suite covers:

- **Quota:** limit 20, importing 100 rows gives 20 active; the 21st activation (single and bulk) is blocked; the storefront shows at most 20.
- **CSRF:** rejected without a token.
- **Tenant isolation:**
  - a session from shop 1 is rejected on shop 2, and the session is terminated
  - a product ID from shop 1 is not reachable from shop 2
- **Impersonation:** bound to its shop, single-use, works on the right shop.
- **Template switch:** applied, and products are unchanged.
- **Cart and checkout:** AJAX add to cart, checkout creates exactly one order, resubmitting creates no duplicate, the order appears in the admin.
- **SEO:** slug change gives a 301 to the new URL; the 404 monitor logs the missing URL.
- **Staff permissions:**
  - an editor can open pages but gets 403 on products and users, and cannot create an owner
  - the Starter plan cannot add staff
- **Downgrade:** Business → Starter keeps all 60 products, the storefront is capped at 20, and the admin shows a warning.
- **Expiry:**
  - warnings at 10/3/1 days, once each, status changes to EXPIRING, and the owner gets 3 emails
  - expiry leads to LOCKED (503), with **no data exposed** on 9 public routes and a temporary 503 for robots.txt
  - renew unlocks the shop with all data intact
- **Suspend:** closes both the storefront and the admin; reactivating restores them.
- **10,000-product XML import:** finishes in about 19 s (batches of 200, each request bounded at about 6 s). The stored cap stops at exactly 10,000, the active quota is respected, search works across 10k products, and a page of the product list loads in under 2 s.

### Bugs found and fixed by QA in this phase

1. Batch import failed on every row because of nested transactions. Fixed with savepoint-based nesting, so one bad row rolls back on its own.
2. **The XML reader skipped every other product**, so half of any supplier feed was silently dropped. Fixed and covered by a test.
3. Uploads over `upload_max_filesize` or `post_max_size` showed a misleading "form expired" message. They now name the real limit.
4. JSON-LD and meta descriptions merged words at HTML block boundaries.
5. Template visual fixes: logos truncated in centred headers, a sale badge invisible on Kipos, a badge overlapping the button on Lumière, a 76px overflow on Iasis mobile, search fields wrapping badly on mobile, category names overflowing on Ergo.
6. Admin mobile overflows: grid min-content, screen-reader labels escaping table scrollers, inline grid styles. Text inputs without a `type` attribute were unstyled.
7. Links to deleted shops in the audit log returned a bare 404; they now explain that the shop was deleted.
8. Expiry warnings did not email the shop owner.
9. Remote image import followed redirects without checking them again. It now re-validates every hop, so a public URL cannot redirect to localhost, private, link-local or metadata addresses.

---

## 3. Not tested, or tested only partly

- **Real Apache/LiteSpeed:** testing used PHP's built-in server. The `.htaccess` rules (blocking internals, no PHP in `uploads/`, caching) have not run on Hostinger yet. `index.php` also refuses non-static paths when `.htaccess` is ignored, but verify on the server (see §4).
- **Real SMTP delivery:** the `log` driver was used. The Hostinger SMTP settings and the test-email button were not exercised against a real mailbox.
- **Real DNS/SSL:** parked domains, the DNS check against a real server IP, and Let's Encrypt.
- **Image import from remote URLs:** blocked by this sandbox's network policy. The code path exists (SSRF-guarded on every redirect hop, inside the time budget) but has not been exercised end to end.
- **Skroutz/BestPrice validation:** feeds are generated to the published field names but have not been validated by the marketplaces' own validators.
- **Email deliverability** (SPF/DKIM) and spam scoring.
- **Load and concurrency** beyond single-user tests: quota race safety relies on `BEGIN IMMEDIATE`, which is correct by design but was not load-tested.
- **Browsers:** visual checks used Chromium only (1440 and 390). Safari/iOS and Firefox were not checked.
- **Screen readers:** not tested with NVDA or VoiceOver; only structural ARIA checks were done.
- **Stripe and Viva Wallet:** prepared as disabled providers; not integrated, by design for V1.
- **wechevo.com:** unreachable from this sandbox because of the network policy, so its branding was not compared. Only a "Powered by wechevo e-shop" credit is used.

---

## 4. BEFORE GOING LIVE

1. **Backup first.** If the target folder holds anything, take a full Hostinger backup. Never upload over a live site without one.
2. Set PHP 8.2+ and enable `pdo_sqlite`, `gd`, `zip` and `mbstring`. Set `upload_max_filesize` and `post_max_size` to 64M and `memory_limit` to 256M.
3. Upload and extract `saas-platform-site-php.zip` into the platform domain's document root. Then run the setup wizard over **HTTPS** and create the Super Admin with a strong, unique password.
4. **Check that the internals are blocked on the real server.** These must return 403 or 404:
   - `/data/config.php`
   - `/data/platform.sqlite`
   - `/app/bootstrap.php`
   - `/templates/01-atelier/theme.json`
   - any `.php` file under `/uploads/`
5. Configure SMTP and send a test email. Set the *notify email* and the *server IP* in Super Admin → Settings.
6. Add the cron job (every 10 minutes) and confirm that "last cron run" updates.
7. Review the plans: prices (€20/€45/€80 + €150 setup are **sample values**), limits, durations and features. Set grace days.
8. Review which templates are enabled for which plans.
9. For each shop:
   1. Park the domain and set DNS → SSL.
   2. Set the primary domain.
   3. Replace **all demo products** and every text marked «Κείμενο δείγμα — επαληθεύστε πριν τη δημοσίευση» ("Sample text — verify before publishing"). This includes the terms, privacy, cookies, shipping, returns and about pages, which must be reviewed by the merchant or a lawyer.
   4. Enter real company details (name, VAT number, tax office, address) and real shipping prices.
   5. Bank details for bank-transfer payment.
10. Search Console: verify each shop and submit `/sitemap.xml`. Add the GA4 ID if wanted (it loads only after consent).
11. Hand the Skroutz/BestPrice feed URLs to the marketplaces and run them through their validators.
12. Set up off-site backups: download `data/backups/` regularly and keep Hostinger's automatic backups on.
13. Remove the demo shops and any test accounts from the Super Admin.
14. Keep `data/config.php` private and backed up. It contains the app key and cron key; losing it invalidates sessions and signed links.
