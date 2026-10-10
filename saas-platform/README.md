# wechevo e-shop — multi-tenant e-shop rental platform

This is a single PHP installation that serves many Greek e-shops. Each shop gets its own domain, its own SQLite database and one of 10 switchable templates. Everything is managed from a Super Admin panel.

- **Stack:** PHP 8.1+, SQLite (PDO), plain CSS/JS. There is no Composer, npm or build step.
- **Hosting:** runs on Hostinger shared hosting (Premium / Business / Cloud) or any Apache/LiteSpeed host.
- **Isolation:** the control plane lives in `data/platform.sqlite`. Each shop lives in `data/shops/shop_{id}.sqlite` and `uploads/{id}/`.

---

## 1. Requirements

| Item | Needed |
|---|---|
| PHP | 8.1 or newer (8.2/8.3 recommended) |
| Extensions | `pdo_sqlite`, `gd` (with WebP), `mbstring`, `zip`, `openssl`, `fileinfo` |
| Web server | Apache or LiteSpeed with `mod_rewrite` (both are standard on Hostinger) |
| PHP limits | `upload_max_filesize` and `post_max_size` ≥ 32M (needed for large CSV/XML imports), `memory_limit` ≥ 256M, `max_execution_time` ≥ 30 |

The setup wizard checks the extensions and tells you what is missing.

---

## 2. Fresh install on Hostinger

> **Never upload over a live site without a backup.** If the target `public_html` already contains a site, download a full backup first: hPanel → Files → Backups, or zip the folder in the File Manager.

1. **Create the platform domain.** Example: `shops.wechevo.com` (hPanel → Domains → Subdomains). This is where the Super Admin panel will live.
2. **Set the PHP version and limits.**
   - hPanel → Advanced → PHP Configuration: choose **PHP 8.2+**.
   - Enable the `pdo_sqlite`, `gd`, `zip` and `mbstring` extensions.
   - Set `upload_max_filesize = 64M`, `post_max_size = 64M`, `memory_limit = 256M`.
3. **Upload the files.** Open File Manager, go to the domain's document root (for example `public_html/` or `domains/shops.wechevo.com/public_html/`), upload `saas-platform-site-php.zip` and **Extract**.
   - Files must sit directly in the document root, so `index.php` and `.htaccess` are at the top level.
4. **Permissions.** The folders `data/` and `uploads/` must be writable (755 is enough on Hostinger). Do not use 777.
5. **Run the setup wizard.** Open `https://shops.wechevo.com/`. The wizard:
   - checks requirements
   - asks for the platform host(s), for example `shops.wechevo.com`
   - asks for the timezone (`Europe/Athens`)
   - creates the **first Super Admin**. There are no default credentials.

   It writes `data/config.php`, which holds your private `app_key` and `cron_key`. Keep that file private and back it up.
6. **Enable SSL** for the platform domain: hPanel → Security → SSL → Install (free Let's Encrypt).
7. **Set up the cron job** (see §5).
8. **Set up email** (see §6).

### Verify that the internals are blocked

The `.htaccess` file denies web access to `app/`, `data/`, `views/`, template views and `theme.json`, `.sqlite` files and dotfiles. `uploads/` cannot execute PHP. Check these after installing:

```
https://shops.wechevo.com/data/platform.sqlite   → 403
https://shops.wechevo.com/app/bootstrap.php      → 403
https://shops.wechevo.com/data/config.php        → 403
```

---

## 3. Connecting a customer's domain to a shop

Every shop is served from the **same installation**. A request is routed by its host name, which is looked up in `shop_domains`.

1. **Super Admin → Shops → New shop.** The wizard asks for:
   - the shop's name
   - the domain, for example `myshop.gr`
   - the plan and duration
   - the template
   - the owner's email and password
   - whether to add demo content

   Domains can also be added later, in the shop's **Domains** tab.
2. **Add the domain to Hostinger** so the server answers for it.
   - **Same hPanel account:** hPanel → Domains → **Parked domains** (alias). Park `myshop.gr` (and `www.myshop.gr`) on the platform domain, so it serves the same `public_html`.
   - **Hostinger Cloud/VPS, or a plan with addon domains:** add the domain and point its document root at the platform's folder.
3. **DNS** at the customer's registrar: point an `A` record for `@` and `www` to the server IP shown in hPanel. Enter the same IP in Super Admin → Settings → *Server IP*, so the **DNS check** button on the shop's Domains tab can confirm it.
4. **SSL:** once DNS resolves, go to hPanel → SSL and install a certificate for the parked domain. Then set the domain's SSL status to *active* in the shop's Domains tab. Shops redirect HTTP→HTTPS and non-primary → primary domain automatically.
5. **Local testing:** `*.localhost` domains work without DNS. An example is `php -S 127.0.0.1:8099 index.php` and opening `http://myshop.localhost:8099`.

---

## 4. Daily operation (Super Admin)

| Task | Where |
|---|---|
| Create / edit shops, domains, owner password | Shops → shop |
| Plans (price, active-product limit, staff limit, features) | Plans. Limits are data, not code. |
| Enable or disable templates per plan, preview them | Templates |
| Switch a shop's template (data is untouched) | Shop → Template |
| Enable or disable individual features or the shop admin login | Shop → Access |
| Renew (extend from the current end, or reset from today) | Shop → Subscription |
| Upgrade / downgrade | Shop → Plan. Downgrades never delete products: choose *deactivate excess* or *let the shop choose*. The storefront always shows at most the plan limit. |
| Lock / suspend / reactivate | Shop → Status |
| Log in as the shop owner (audited, one-time 120s token) | Shop → Impersonate |
| Backups (ZIP of the tenant DB + uploads) | Shop → Backup, or Backups for the platform DB |
| Audit log, notifications, payments | sidebar |

**Subscription lifecycle:**

- `ACTIVE`
- `EXPIRING`: warnings at 10, 3 and 1 days, shown in Super Admin notifications and on the shop's dashboard banner
- `EXPIRED`: with the optional *grace days* setting
- `LOCKED`: a branded lock page with HTTP 503, revealing no products, prices or forms

Renewing restores the shop immediately, and no data is ever touched. Status is refreshed on every request and by cron.

---

## 5. Cron

Cron runs subscription checks, processes queued imports in the background and refreshes product feeds. Use hPanel → Advanced → **Cron Jobs**, every 10 minutes:

```
*/10 * * * *  /usr/bin/php /home/USER/domains/shops.wechevo.com/public_html/cron.php
```

If CLI PHP is not available, use the URL form instead (the key is in `data/config.php` → `cron_key`):

```
*/10 * * * *  curl -s "https://shops.wechevo.com/cron?key=YOUR_CRON_KEY" > /dev/null
```

Super Admin → Settings shows the last cron run and has a **Run now** button. Imports also run in the browser in small batches (200 rows, about 6 seconds per request), so the import screen works without cron and never hits PHP timeouts.

---

## 6. Email (SMTP)

1. Create a mailbox in hPanel → Emails, for example `no-reply@wechevo.com`.
2. Go to Super Admin → Settings → Email and set:
   - driver **SMTP**
   - host `smtp.hostinger.com`
   - port `465`
   - security `ssl`
   - the mailbox user and password
   - the From address (the same mailbox)
3. Press **Send test email**.

The `log` driver writes mail to `data/mail/` instead of sending it, which is useful for staging. Every shop sends through the platform's SMTP account, and the shop name is used as the sender name.

For deliverability, add SPF/DKIM for the sending domain (Hostinger shows the records under Emails → DNS).

---

## 7. Backups

- **Automatic:** a ZIP backup of a shop is created before destructive actions (permanent delete, restore).
- **Manual:** Shop → Backup (tenant DB + uploads), or Super Admin → Backups (platform DB). Files are stored in `data/backups/`.
- **Off-site:** download the ZIPs regularly. Also keep Hostinger's daily or weekly backups enabled. `data/` and `uploads/` are the only folders that contain state.
- **Restore a shop manually:** stop traffic (lock the shop), replace `data/shops/shop_{id}.sqlite` and `uploads/{id}/` with the files from the ZIP, then unlock.

---

## 8. Each shop: SEO checklist after launch

The shop owner works under `https://myshop.gr/admin/`.

1. **Settings:** store details, VAT, shipping zones, payment methods (cash on delivery, bank transfer; Stripe and Viva are prepared but not active), terms and privacy pages.
2. **Appearance and Homepage CMS:** logo, colours and fonts within the template's limits, homepage sections, menus.
3. **SEO → General:**
   - default titles and descriptions
   - Organization / LocalBusiness data
   - GA4 Measurement ID, which loads **only after cookie consent**
   - Search Console verification meta tag
4. **Google Search Console:**
   1. Add the property `https://myshop.gr/`.
   2. Verify it (meta tag field in SEO settings, or DNS TXT).
   3. Submit `https://myshop.gr/sitemap.xml`.
5. **robots.txt** is generated automatically, allows AI crawlers (GPTBot, ClaudeBot, PerplexityBot, …) and is editable in SEO settings. `/llms.txt` and the blog RSS feed (`/feed/blog.xml`) are generated too.
6. **404 monitor** (SEO → 404): review missing URLs weekly and turn them into 301 redirects with one click. Changing a product, category or page slug creates a 301 automatically.
7. **Product feeds** (Feeds): Skroutz XML, BestPrice XML and custom XML. Give the feed URL to the marketplace.
8. **Content:** replace every text marked *«Κείμενο δείγμα — επαληθεύστε πριν τη δημοσίευση»* (Greek for "Sample text — verify before publishing") and all demo products before going live. Demo content is clearly flagged; nothing in it is a real claim, review or statistic.

---

## 9. Templates

All 10 templates ship at once. The Super Admin decides which ones each plan or shop may use.

| Key | Name | Best for |
|---|---|---|
| `01-atelier` | Atelier | fashion, boutiques, editorial |
| `02-agora` | Agora | electronics, large catalogues, marketplaces |
| `03-kipos` | Kipos | food, deli, gifts, local products |
| `04-lumiere` | Lumière | beauty, cosmetics, wellness |
| `05-oikos` | Oikos | furniture, home, décor |
| `06-karat` | Karat | jewellery, watches, luxury (dark) |
| `07-stadio` | Stadio | sports, outdoor, streetwear |
| `08-paidi` | Paidi | kids, toys, baby |
| `09-ergo` | Ergo | tools, hardware, auto parts, B2B |
| `10-iasis` | Iasis | pharmacy, supplements, health |

A template is a folder `templates/{key}/` containing:

- `theme.json`: name, design tokens, fonts, layout options and the demo industry
- `assets/theme.css` and `assets/fonts.css` with self-hosted Greek+Latin `woff2` fonts
- an optional `assets/theme.js`
- optional view overrides in `views/`. Any view not overridden falls back to `views/storefront/`.

**To add a template:**

1. Copy an existing folder to `templates/11-name/` and change `theme.json`.
2. Open Super Admin → Templates. The new template is detected automatically.

`tools/fetch_fonts.py` downloads Greek-subset fonts for a new template.

---

## 10. Development and tests

```bash
php -d upload_max_filesize=64M -d post_max_size=64M -S 127.0.0.1:8099 index.php
python3 tests/acceptance.py          # end-to-end acceptance suite (creates and deletes shops qa1/qa2)
python3 tests/acceptance.py --big    # + 10,000-product XML import
NODE_PATH=$(npm root -g) node tests/shots.js myshop.localhost:8099 /tmp/shots / /products/   # screenshots, JS errors, 390px overflow
```

`tests/` and `tools/` are not part of the production ZIP.

---

## 11. Security notes

- **Passwords and sessions:**
  - bcrypt password hashing
  - login lockout after 5 failures for 15 minutes
  - session ID regeneration
  - sessions bound to their shop, so a session from one shop presented to another is destroyed
- **Request protection:**
  - CSRF on every POST
  - allow-list HTML sanitizer
  - prepared statements everywhere
- **Uploads:** re-encoded through GD (no polyglots), served as WebP variants; PHP execution is disabled in `uploads/`.
- **Role-based staff permissions:** owner, administrator, product manager, editor, plus per-user custom modules. Users can only manage strictly lower roles.
- **Audit log:** every Super Admin and shop admin action, including impersonation and quota denials.
