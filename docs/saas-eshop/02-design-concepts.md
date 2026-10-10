# Stage 3–5: Design concepts

Status: **STOPPED. Waiting for the client to choose A, B or C (or a mix).**

## Decisions confirmed by the client
| Topic | Decision |
|---|---|
| Deployment | **One installation serving many domains.** There is one platform database plus one SQLite file per shop. Isolation is enforced by both the separate files and `shop_id` scoping. |
| Hosting | Hostinger **shared hosting**. Client domains are attached as parked/alias domains pointing at the same installation. This must be verified on the actual plan (domain limits, automatic SSL per domain). Cron is used where available, with a fallback that processes work in browser-driven batches. |
| Templates | **All 10 in V1.** The template engine is built so more templates can be added later. |
| Design scope | The build is the product: the storefront templates plus the admin panels. A promotional page already exists at wechevo.com. |
| Platform brand | A sub-product of **wechevo.com**. Storefront footers show "Powered by wechevo e-shop". The wechevo site could not be reached from the build environment because of the network policy, so its colors and logo have not been checked yet. |
| Checkout | Cash on delivery and bank transfer. Stripe and Viva Wallet are prepared but switched off. |
| Language | **Greek only** (`lang="el"`; Greek uppercase drops accents automatically). |
| Industries | Cover as many as possible, with the option to add templates later. |
| Everything else | The (Recommended) defaults from `01-research-and-questions.md`. |

## The three mockups
Each mockup is one self-contained HTML file with four views (home, category, product, checkout) plus a working cart, wishlist toggle and variant selection. All names, prices, images and copy are **samples**.

| | File | Live preview (private) |
|---|---|---|
| A · Atelier | `mockups/concept-a-atelier.html` | https://claude.ai/artifact/RPKnsvQLJJ1xVrXrcgGJv6 |
| B · Agora | `mockups/concept-b-agora.html` | https://claude.ai/artifact/HcykpGoogjTnxyUzbKVkua |
| C · Kipos | `mockups/concept-c-kipos.html` | https://claude.ai/artifact/1MgxcvXs4hQMxLxcQp69pc |

### How the three differ
| Dimension | A · Atelier | B · Agora | C · Kipos |
|---|---|---|---|
| Visual style | Luxury editorial | Marketplace / tech, utilitarian | Playful colour-block lifestyle |
| Industries | Fashion, jewelry, premium cosmetics, furniture | Electronics, tools, auto parts, general retail | Food/deli, natural cosmetics, gifts, kids |
| Layout | Asymmetric 12-column editorial grid, oversized type, staggered cards | Dense modular grid, bento promos, filter sidebar | Rounded colour panels, horizontal snap shelves, floating panels |
| Hero | Image-led, with a serif headline overlapping the image | Bento promo grid (search is the real hero) | Split panel with a fanned stack of product cards and a rotating sticker |
| Navigation | Minimal centered logo, full-screen overlay menu | Utility strip, large search with live suggestions, mega menu, category bar | Floating pill header; bottom tab bar on mobile |
| Typography | Noto Serif Display (high-contrast, italics) + Commissioner | Sofia Sans Condensed (dense, numeric) + IBM Plex Sans | M PLUS Rounded 1c (soft, chunky) + Manrope |
| Palette | Stone paper, ink, oxblood | White/cool grey, navy, signal orange | Chalk, forest green, mint/butter/lilac/coral blocks |
| Motion | Slow: image settle, hover image swap, category preview that follows the cursor, clip-path menu | Micro only: fly-to-cart, toast, instant feedback | Spring: quick-add that turns into a quantity stepper, card fan, marquee, cart sliding in at an angle |
| Product card | Large luxury card, hover reveal, quick add | Compact: badges, spec chips, stock level, compare, quick add | Rounded image card, stepper that morphs in place |
| Product page | Split screen, sticky info, stacked gallery with zoom, accordions | Three columns: thumbnails with zoom lens, variants, buy box; tabs; "bought together" bundle | Large rounded gallery, floating info card, pill variants, FAQ |
| Cart | Side drawer | Toast, then side drawer with free-shipping progress bar | Floating rounded panel (bottom sheet on mobile) with progress and cross-sell |
| Checkout | One editorial page | Numbered accordion steps (1–3) | One friendly page with gift-wrap option |
| Conversion lever | Desire and story | Speed, comparison, bundles | Bundle builder, popup capture, cross-sell |

Shown in all three: Popup Builder output (C), Menu Builder (A overlay, B mega menu), Homepage CMS sections, category SEO/AEO text, breadcrumbs, FAQ blocks.

## Proposed set of 10 templates
Every template shares one data model and one component and behaviour core. Each template is a presentation layer of tokens, layouts and section variants.

| # | Template | Industry family | Starts from |
|---|---|---|---|
| 01 | Atelier | Fashion & apparel | Concept A |
| 02 | Agora | Electronics & tech | Concept B |
| 03 | Kipos | Food, deli & gifts | Concept C |
| 04 | Lumière | Cosmetics & beauty (clean, ingredient-led) | A + C |
| 05 | Oikos | Furniture & home décor (architectural, spacious, room hotspots) | A |
| 06 | Karat | Jewelry & watches (dark luxe, macro zoom) | A |
| 07 | Stadio | Sports & outdoor (bold, dynamic, size-heavy) | B + C |
| 08 | Paidi | Kids & toys (illustrated, age/category filters) | C |
| 09 | Ergo | Hardware, auto parts, B2B-style (SKU-first, compatibility search) | B |
| 10 | Iasis | Pharmacy & wellness (clinical, trust-led, strong search) | B |

## What the choice decides
The concept you pick becomes the **reference implementation**. It is built first, and its cart, checkout and interaction patterns become the platform defaults that the other 9 templates inherit and restyle. The two concepts you don't pick still become Templates 01–03 as shown above.

**Which design would you like to implement? A / B / C**, or a mix such as "B + the cart from C".
