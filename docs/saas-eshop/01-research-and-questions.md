# SaaS E-Shop Rental Platform: Stage 1 research & Stage 2 questions

Status: **Waiting on answers from the client.** No design or production code has been written yet.

---

## Stage 1: Research findings

### 1.1 Repository
- This repository (`Mage-UI`) is a **Next.js 14 / React 18 / Tailwind / Storybook component library**. It has about 23 component groups: hero, bento-grid, card, carousel, cursor-effects, text animations, overlay/drawer, preloader, graphs, and others.
- It has **no PHP code, no storefront, and no SaaS code**. Nothing here can be reused directly, because production must run without npm or a build step.
- What I can use: Mage-UI is a library of **motion and component patterns** (drawers, bento grids, text reveals, carousels). I can port these ideas to vanilla CSS/JS for the templates, so there is no React runtime.
- Local tooling: PHP 8.3 CLI is available, so `php -S localhost:8000 index.php` and Playwright QA are possible.

### 1.2 Existing site
- The brief gives no existing website, sitemap, logo, brand colors, statistics, clients, or reviews.
- So there are **no URLs to preserve**, **no brand assets to inherit**, and **no real data**. Every piece of copy, every product, and every image will be flagged as *"Template text — verify before going live."*

### 1.3 Market signals in the brief
- `.gr` domains, Skroutz and BestPrice feeds, and EUR prices point to the **Greek market**. That brings three requirements:
  - Greek-language storefronts.
  - VAT-inclusive pricing (24% standard rate).
  - A Skroutz-compliant XML feed with fields such as `availability`, `mpn`/`ean`, and `manufacturer`.

### 1.4 Spec conflicts and risks that need a decision
| # | Issue | Why it matters |
|---|---|---|
| R1 | **"Each shop on its own independent hosting"** vs. a central Super Admin that controls quotas, locks, and templates, all in PHP/SQLite on shared hosting | This choice decides the whole architecture. See Q1. |
| R2 | **10 templates in V1** | Ten full storefront journeys (home → category → product → cart → checkout → account) is the biggest single cost driver. See Q4. |
| R3 | **Background jobs** (10,000-row imports, expiry checks at 10/3/1 days, auto-lock) | Shared hosting has no queue workers. We need cron (available on most Hostinger plans) or a fallback of browser-driven batches plus a check on each request. |
| R4 | **SQLite at scale** | 10,000 products per shop is fine with WAL mode and indexes. One shared SQLite file for *all* shops would be a write bottleneck and an isolation risk. One file per shop is the safer design. |
| R5 | **End-customer checkout payment** is not specified | Orders exist, but the brief names no payment method for shoppers. |
| R6 | **What the "3 mockups" cover** | The platform has four separate UIs: (a) the storefront templates, (b) the Shop Admin panel, (c) the Super Admin panel, and (d) possibly a public marketing site that sells the packages. |
| R7 | **AVIF generation** | It depends on GD/Imagick support on the specific Hostinger server. WebP is the guaranteed fallback. |

---

## Stage 2: Questions

Answers marked **(Recommended)** are the defaults I will use unless you say otherwise. Questions marked ★ affect the architecture or the brand, so I can't decide them for you.

### A. Architecture and hosting
1. ★ **Deployment model**
   - (a) **One installation serving many domains.** Each client domain is pointed or parked at the same Hostinger account. There is one SQLite database file per shop plus a central platform database. Isolation is enforced both by separate files and by `shop_id` scoping. **(Recommended)**: simplest operation, instant lock/renew, real tenant isolation.
   - (b) **Distributed.** Each shop is its own install on its own hosting and contacts a central control plane through a signed API (license/heartbeat). It is much more complex, and locking depends on the remote calls arriving.
   - (c) Build (a) now and keep the code ready for (b) later.
2. ★ **Hostinger plan** (Premium, Business, Cloud, or other)? This affects cron, the number of addon domains, and SSL automation.
3. **Cron jobs** for expiry checks and the import queue: is cron available? If it isn't, I fall back to batch processing in the browser plus a check on each request. **(Recommended: cron, with the fallback built in.)**
4. ★ **Templates in V1**: all 10 at full quality, or **3 at full quality in V1 and 7 more in a V1.1 phase** **(Recommended)**? All templates share the same components and data either way.
5. **Where the code lives**: a new folder `saas-platform/` in this repo **(Recommended)**, or a separate repository?
6. **Super Admin location**: a separate platform domain, such as `panel.yourbrand.gr/superadmin/` **(Recommended)**, or a path on any shop domain?

### B. Brand and design scope
7. ★ **Platform brand name**: is there a name, logo, or brand colors for the SaaS itself?
8. ★ **What should the 3 design concepts (A/B/C) cover?**
   - (a) **Storefront templates.** The chosen concept becomes Template 01 and sets the design system. The other two become Templates 02 and 03. **(Recommended)**
   - (b) A public marketing site that sells the packages.
   - (c) Both.
9. **Does the platform need its own public marketing or sales site** (pricing, packages, contact form)? Or are shops sold offline?
10. **Target industries for the templates**: fashion, electronics, cosmetics, food, furniture, or general retail? This shapes the 3 concepts.
11. **Admin panels**: should they share one clean, neutral admin design system across Super Admin and Shop Admin **(Recommended)**, or should each have its own look?
12. **Lock page branding**: the shop's logo and colors **(Recommended)**, or the platform brand?

### C. Commerce behavior (storefronts)
13. ★ **Checkout payment methods for shoppers in V1**: Cash on Delivery plus bank transfer **(Recommended)**, with Stripe and Viva Wallet prepared for later? Or do you need online card payments now?
14. **Shipping model**: flat rate, free above a threshold, and per-zone rates (Greece mainland, islands, Cyprus), all configured per shop **(Recommended)**?
15. **Customer accounts**: guest checkout plus optional account (orders, addresses) **(Recommended)**?
16. **Wishlist**: browser-based for guests, saved to the account when logged in **(Recommended)**?
17. **Product reviews in V1**? (I will never seed fake reviews.) **(Recommended: off in V1, schema ready.)**
18. **Coupons**: in V1, or later as the spec says? **(Recommended: later.)**
19. **Prices**: VAT-inclusive, EUR, one VAT rate per shop, with an optional per-product override **(Recommended)**?
20. **Variants**: size/color style attributes with per-variant SKU, price, and stock **(Recommended)**?
21. **Blog per shop**: needed in V1? The spec doesn't mention it. **(Recommended: simple per-shop blog or articles for SEO/AEO.)**

### D. Language and content
22. ★ **Languages**: Greek-only storefronts in V1 with i18n-ready code **(Recommended)**, or Greek plus English? And should the admin panels be in Greek, English, or both?
23. Legal pages (terms, privacy, cookies, shipping, returns): I will ship them as clearly marked template text. Agreed?
24. Demo/seed data: neutral placeholder products and images, all flagged **(Recommended)**, or do you have a real pilot client's catalog?

### E. Subscriptions and operations
25. **Grace period**: when a subscription expires, lock immediately, or after N days (EXPIRED shown only to the admin)? **(Recommended: setting stored in the database, default 0 days.)**
26. **Downgrade with too many active products**: block the downgrade until the Shop Admin chooses which products to keep active, with a forced "deactivate newest first" fallback by the Super Admin **(Recommended)**?
27. **Feature flags per plan** (feeds, imports, popups, staff limit): should every plan get everything at launch, with flags only controlling access **(Recommended)**, or should features differ by plan from day one?
28. **Notifications**: Super Admin by email plus in the panel **(Recommended)**. Which email address?
29. **Email sending**: PHP `mail()`, or Hostinger SMTP through a built-in lightweight SMTP client (no Composer) **(Recommended for deliverability)**?
30. **Backups**: on-demand ZIP (database plus uploads) per shop, plus automatic backup before destructive actions **(Recommended)**. Should there also be scheduled backups via cron?

### F. SEO and analytics
31. **GA4 / Search Console**: does each Shop Admin enter their own GA4 ID and GSC verification tag in settings **(Recommended)**? GA4 loads only after cookie consent.
32. **Feeds in V1**: Skroutz, BestPrice, and a custom XML builder. Do you also want a Google Merchant Center feed **(Recommended, low cost)**?
33. **AI crawlers**: allow GPTBot, OAI-SearchBot, ClaudeBot, PerplexityBot, and Google-Extended in each shop's robots.txt by default **(Recommended)**, with an option per shop to turn it off?

---

## What happens next
1. You answer the questions above. "Defaults" is fine for anything marked (Recommended). The ★ items need explicit answers.
2. **Stage 3–4:** I study the reference repositories for patterns only and produce **3 genuinely different clickable HTML mockups**. Each covers home, category, product, and cart drawer, and they are checked against the distinctness matrix from the brief.
3. **Stage 5:** I stop and wait for your choice of A, B, or C (or a mix).
