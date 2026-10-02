# Technical Blueprint — Cinora → KooheFilm Integration

> Generated: 2026-10-01
> Status: **Analysis & Planning only — no production code changed.**

---

## 1. Current Architecture Summary

### Theme: `koohe-film` (Block Theme, FSE)

| Layer | Files | Notes |
|-------|-------|-------|
| theme.json v3 | `theme.json` | Color palette (10 tokens), gradients (3), typography (7 sizes, fluid), spacing (6 steps), shadows (2), radius (4), layout widths (820/1360), custom scheme (light/dark) |
| Templates (21) | `templates/*.html` | front-page, home, index, archive, search, 404, single-*, archive-*, taxonomy, page variants |
| Template Parts (6) | `parts/*.html` | header, header-minimal, footer, sidebar, post-meta, title-header |
| Patterns (10) | `patterns/*.php` | hero-featured, latest-movies, latest-series, top-rated, trending-row, genre-cloud, single-title-body, plans-grid, subscribe-cta, hidden-no-results |
| Style Variations (4) | `styles/*.json` | dark (default), light, midnight, cinema |
| CSS | `assets/css/theme.css`, `editor.css`, `woocommerce.css` | Main theme styles, editor overrides, Woo compat |
| JS | `assets/js/theme.js`, `editor.js`, `blocks.js`, `customize-preview.js` | Dark mode toggle, editor presets, block styles, customizer live preview |
| PHP inc/ (8 files) | `inc/*.php` | setup, assets, patterns, block-styles, template-tags, blocks, compat, customize |

### Core Plugin: `manacore-core`

| Layer | Details |
|-------|---------|
| PHP Classes (21) | Post_Types, Taxonomies, Meta, Metaboxes, Links, Query, Ratings, Watchlist, Player, Templates, Blocks, Block_Data, Block_Query, Block_Visibility, Block_Support, Rest_Api, Seo, Settings, Assets, Install + trait-singleton |
| CPTs (6) | movie, series, anime, episode, person, collection |
| Taxonomies (7) | genre, country, release_year, network, studio, quality, language |
| Meta Fields (49) | manacore_* prefix |
| Blocks (10) | titles-grid, hero-slider, episodes-list, cast-list, download-links, filter-bar, search-box, rating-box, title-meta, trailer |
| REST Endpoints (6) | /search, /titles, /links/{id}, /watchlist, /rate, /track-download |
| Front CSS | `assets/css/front.css` (2908 lines) — uses `--mc-*` tokens bridged to `--wp--preset--color-*` |
| Front JS | `assets/js/front.js` (569 lines) — watchlist, rating, copy, player, search, slider |
| Admin JS | `assets/js/admin-links.js` — link metabox management |
| Editor CSS | `assets/css/editor.css`, `editor-canvas.css` |

### Other Plugins

- `manacore-sources`: TMDB/Wikidata/TVMaze/Jikan/OMDb importer
- `manacore-subscriptions`: Plans, Access control, WooCommerce integration

### Conventions

- No build step — vanilla JS, no JSX, no npm deps for WP code
- Tab indentation in PHP/JS/CSS
- All output escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`)
- All inputs sanitized; nonce + capability checks on writes
- RTL-first with logical CSS properties
- Vazirmatn font
- Test suite: 12 sections via `wp/tests/run.sh`

---

## 2. Reference Design Summary (Cinora)

| Aspect | Details |
|--------|---------|
| Pages (18) | index, browse, detail, player, account, subscription, magazine, article, schedule, live, cast, person, 404, help, about, privacy |
| CSS | Single file `style.css` (~72 lines minified, ~118KB) |
| JS (18 files) | main.js (shared API), per-page modules, data.js (static catalog) |
| Font | Vazirmatn (CDN + local option) |
| Direction | RTL |
| Theme mode | Dark default, light via `[data-theme=light]` |
| State | localStorage for watchlist, no server-side auth in reference |

### Key Design Characteristics

- **Dark-first cinema aesthetic** with muted backgrounds (`#0e1218`, `#161c24`)
- **Green accent** (`#c6ed7b`) — very different from KooheFilm's amber (`#f59e0b`)
- **Flat, minimal borders** (1px `#293039`), small radius (4–14px)
- **Dense information layout** — small font sizes (8–14px body)
- **Glassmorphism** on buttons/badges (`backdrop-filter: blur`)
- **Sticky header** with blur backdrop (84px)
- **Mega menu** for genre browsing
- **⌘K search overlay** (modal)
- **Hero slider** with cinematic gradient + grain texture
- **Horizontal scroll rows** for trending
- **Card hover: scale + play overlay + save button reveal**
- **Detail page: sticky tab bar + sidebar recommendations**
- **Download table** with quality/format/size columns
- **Episode accordion** with expandable download rows
- **Comments with spoiler system**
- **Pricing cards** with featured highlight
- **Account dashboard** with stats, analytics charts, settings
- **Toast notifications**
- **Mobile drawer** navigation

---

## 3. Design System Extraction

### Color Tokens

| Cinora Token | Value (Dark) | Value (Light) | → theme.json mapping |
|---|---|---|---|
| `--bg` | `#0e1218` | `#f5f6f2` | `surface` |
| `--panel` | `#161c24` | `#fff` | `surface-2` |
| `--panel-2` | `#1c242d` | `#eef1e8` | `surface-3` |
| `--border` | `#293039` | `#dce2d6` | `border` |
| `--text` | `#eef1f3` | `#202a22` | `foreground` |
| `--muted` | `#8b949f` | `#778172` | `muted` |
| `--accent` | `#c6ed7b` | `#9bc84f` | `accent` |
| `--accent-text` | `#1b2712` | `#18230d` | `accent-contrast` |
| `--gold` | `#efd18a` | `#a98225` | New: `rating` |
| `--danger` | `#ed9c92` | `#bb6357` | `danger` |
| — | — | — | New: `success` (keep existing) |

### Typography Tokens

| Use | Cinora | → theme.json |
|-----|--------|---|
| Body base | 14px | `medium` → 0.875rem |
| Small/caption | 9–11px | `x-small` / `small` |
| Section heading | 19px | `large` |
| Page title | 29–37px | `xx-large` |
| Hero title | 41–46px | `display` |
| Eyebrow | 9–10px, tracking 1.2px | New utility class |
| Font | Vazirmatn | Already used ✓ |

### Spacing Tokens

| Cinora | Value | → theme.json |
|--------|-------|---|
| Container padding | 56px (desktop), 35/28/20/16px (responsive) | Keep `padding` in theme.json styles |
| Section gap | 38px | `spacing-40` |
| Card gap | 20px | `spacing-20` |
| Inner card padding | 17–24px | Component-level |

### Radius Tokens

| Cinora | Value | → theme.json custom |
|--------|-------|---|
| Small (badges, inputs) | 4–7px | `radius.sm` → 6px |
| Base (cards, panels) | 9–11px | `radius.base` → 10px |
| Large (modals, hero) | 14–16px | `radius.lg` → 14px |
| Pill (buttons) | — | Keep `radius.pill` → 999px |

### Shadow Tokens

| Cinora | Value | → theme.json |
|--------|-------|---|
| Card hover | `0 4px 22px rgba(...)` | `soft` |
| Modal | `0 30px 100px #0008` | `raised` |
| Hero | `0 15px 35px #0001` | Component-level |

### Container Widths

| Cinora | KooheFilm current | Decision |
|--------|-------------------|----------|
| 1440px max | wideSize: 1360px | Update to 1440px |
| Content: full-width | contentSize: 820px | Keep 820px for text |

### Grid Rules

- Media grid: 6 cols (home), 4 cols (browse/account), 3 cols (cast)
- Landscape grid: 4 cols
- Collections: 3 cols
- Footer: 5 cols → 4 → 2
- Detail layout: content + 282px sidebar

### Breakpoints

| Cinora | KooheFilm current | Decision |
|--------|-------------------|----------|
| 1600px | — | Add for hero scaling |
| 1300px | — | Add for padding reduction |
| 1100px | — | Add for layout shifts |
| 980px | 782px (WP core) | **Adopt 980px** for nav collapse |
| 768px | 600px (WP core) | **Adopt 768px** for major layout |
| 480px | — | Add for small mobile |
| 360px | — | Add for ultra-small |

> **Risk:** Changing breakpoints from WP core boundaries (600/782) to Cinora's (768/980) may conflict with core navigation block behavior. Mitigation: test thoroughly; the theme already uses its own breakpoints in theme.css.

### Motion Rules

| Interaction | Cinora | Notes |
|---|---|---|
| Card hover | `translateY(-4px)`, image `scale(1.045)` | 0.25s |
| Button hover | `translateY(-2px)` | 0.2s |
| Hero slide | fade 0.7s, copy slide-up 0.6s | |
| Drawer | slide-in 0.25s | |
| Toast | copy-in 0.2s | |
| Transitions | 0.2s default | |
| Reduced motion | All disabled | ✓ already in KooheFilm |

### Icon Sizes

- Nav/header: 18–24px
- Card actions: 17px
- Section icons: 19–23px
- Small inline: 12–14px

### Artwork Ratios

| Context | Ratio |
|---------|-------|
| Poster (portrait) | 2:3 |
| Backdrop (landscape) | 16:9 |
| Cast portrait | 3:4 |
| Article cover | 1.95:1 |
| Collection | free height (204px) |

---

## 4. Component Inventory

### Cinora Components

| # | Component | Page(s) |
|---|-----------|---------|
| 1 | Site Header (sticky, blur) | All |
| 2 | Mega Menu | All (desktop) |
| 3 | Mobile Drawer | All (mobile) |
| 4 | Search Overlay (⌘K) | All |
| 5 | Theme Toggle | All |
| 6 | Auth Modal (login/register) | All |
| 7 | Toast Notification | All |
| 8 | Hero Slider | Home |
| 9 | Discovery Shortcuts (4-col) | Home |
| 10 | Media Card (poster) | Home, Browse, Account |
| 11 | Media Card (landscape) | Home, Browse |
| 12 | Ranked Card (number overlay) | Home |
| 13 | Section Heading + Tabs | Home, Browse |
| 14 | Schedule Panel (week tabs) | Home, Schedule |
| 15 | Taste Banner (CTA) | Home |
| 16 | Collection Card | Home |
| 17 | Article Card | Home, Magazine |
| 18 | Subscription Banner | Home |
| 19 | Footer (5-col) | All |
| 20 | Browse Toolbar (search, type, sort, view) | Browse |
| 21 | Filter Sidebar (genre, year, rating, toggles) | Browse |
| 22 | Active Filter Chips | Browse |
| 23 | List Layout (horizontal card) | Browse |
| 24 | Load More / Infinite Scroll | Browse |
| 25 | Empty State | Browse, Search, Account |
| 26 | Detail Hero (backdrop + poster + info) | Detail |
| 27 | Detail Tab Bar (sticky) | Detail |
| 28 | Download Table | Detail |
| 29 | Season Tabs + Episode Accordion | Detail |
| 30 | Cast Grid | Detail, Cast |
| 31 | Rating Box (sidebar) | Detail |
| 32 | Sidebar Recommendations | Detail |
| 33 | Comments (compose, list, spoiler) | Detail |
| 34 | Trailer Embed | Detail |
| 35 | Video Player + Controls | Player |
| 36 | Episode Pills Navigation | Player |
| 37 | Cinema Mode | Player |
| 38 | Pricing Cards | Subscription |
| 39 | FAQ Accordion | Subscription, Help |
| 40 | Account Sidebar + Tabs | Account |
| 41 | Stat Cards | Account |
| 42 | Analytics (donut, bars, progress) | Account |
| 43 | Watchlist Grid | Account |
| 44 | History List | Account |
| 45 | Settings Form | Account |
| 46 | Invoice Table | Account |
| 47 | Person Hero | Person |
| 48 | People Grid | Cast |
| 49 | Magazine Feature + Side Features | Magazine |
| 50 | Article Layout + TOC | Article |
| 51 | Schedule Page (full) | Schedule |
| 52 | Live Player + Channels | Live |
| 53 | Breadcrumb | Detail, Article |
| 54 | 404 Page | 404 |
| 55 | Profile Dropdown | Header |

---

## 5. Feature Inventory

### Cinora Features (Functional)

| Feature | Type | Implementation |
|---------|------|----------------|
| Search (⌘K overlay, live results) | Interactive | JS filters static catalog |
| Watchlist (add/remove) | Interactive | localStorage |
| Theme toggle (dark/light) | Interactive | `data-theme` attr + localStorage |
| Hero slider (auto, arrows, dots) | Interactive | JS interval |
| Browse filtering (genre, year, rating, type, sort) | Interactive | JS filters static data |
| View toggle (grid/list) | Interactive | JS class swap |
| Load more pagination | Interactive | JS slice |
| Episode accordion expand/collapse | Interactive | JS toggle |
| Season tabs | Interactive | JS tab switch |
| Detail tabs (story, downloads, cast, comments) | Interactive | JS tab switch |
| Comments (compose, reply, spoiler toggle) | Interactive | JS local state |
| Rating picker (1-5 stars) | Interactive | JS local state |
| Auth modal (login/register tabs) | Interactive | JS form UI |
| Toast notifications | Feedback | JS DOM create |
| Mobile drawer open/close | Interactive | JS class toggle |
| Mega menu open/close | Interactive | JS toggle |
| Profile dropdown | Interactive | JS toggle |
| Cinema mode (player) | Interactive | JS class toggle |
| Episode navigation (prev/next) | Interactive | JS |
| Quality selector (player) | Interactive | JS select |
| FAQ accordion | Interactive | JS toggle |
| Account tabs | Interactive | JS tab switch |
| Reading progress (article) | Interactive | JS scroll |
| TOC active tracking | Interactive | JS IntersectionObserver |

### KooheFilm Existing Features

| Feature | Implementation |
|---------|----------------|
| Watchlist (server-side) | REST API + user meta |
| Rating (server-side) | REST API + post meta |
| Search (live) | REST API + search-box block |
| Hero slider | hero-slider block (server-rendered) |
| Filter bar | filter-bar block (server-rendered) |
| Download links | download-links block |
| Episodes list | episodes-list block |
| Cast list | cast-list block |
| Player | Player class + template |
| Dark/light mode | theme.js + localStorage + prefers-color-scheme |
| Mobile action bar | customize option + PHP render |
| Block visibility (12 rules) | Block_Visibility class |
| Subscription gating | manacore-subscriptions plugin |

---

## 6. Reference → KooheFilm Mapping

| Cinora Component | KooheFilm Equivalent | Classification |
|---|---|---|
| Site Header | `parts/header.html` + `theme.css` | **A** — Visual redesign |
| Mega Menu | ❌ None | **D** — New valuable feature |
| Mobile Drawer | Core navigation block (hamburger) | **B** — Structural adaptation |
| Search Overlay (⌘K) | `manacore/search-box` block | **B** — Structural adaptation |
| Theme Toggle | `theme.js` dark mode | **A** — Visual redesign |
| Auth Modal | WP login page / WooCommerce account | **E** — Reference-only (WP handles auth) |
| Toast | `front.js` toast | **A** — Visual redesign |
| Hero Slider | `manacore/hero-slider` block | **B** — Structural adaptation |
| Discovery Shortcuts | ❌ None | **D** — New valuable feature |
| Media Card (poster) | `manacore/titles-grid` card render | **A** — Visual redesign |
| Media Card (landscape) | `manacore/titles-grid` (wide style) | **A** — Visual redesign |
| Ranked Card | ❌ None | **C** — Extend titles-grid |
| Section Heading + Tabs | Pattern headings | **A** — Visual redesign |
| Schedule Panel | ❌ None | **D** — New (needs CPT/meta support) |
| Taste Banner | `subscribe-cta` pattern | **A** — Visual redesign |
| Collection Card | `collection` CPT archive | **B** — Structural adaptation |
| Article Card | Core post loop | **A** — Visual redesign |
| Subscription Banner | `subscribe-cta` pattern | **A** — Visual redesign |
| Footer | `parts/footer.html` | **A** — Visual redesign |
| Browse Toolbar | `manacore/filter-bar` block | **B** — Structural adaptation |
| Filter Sidebar | `manacore/filter-bar` (inline) | **B** — Structural adaptation |
| Active Filter Chips | ❌ None | **C** — Extend filter-bar |
| List Layout | titles-grid `list` layout | **A** — Visual redesign |
| Load More | ❌ None (pagination) | **D** — New valuable feature |
| Empty State | `hidden-no-results` pattern | **A** — Visual redesign |
| Detail Hero | `parts/title-header.html` + single templates | **B** — Structural adaptation |
| Detail Tab Bar | ❌ None (single-page layout) | **D** — New valuable feature |
| Download Table | `manacore/download-links` block | **B** — Structural adaptation |
| Season Tabs + Episode Accordion | `manacore/episodes-list` block | **B** — Structural adaptation |
| Cast Grid | `manacore/cast-list` block | **A** — Visual redesign |
| Rating Box | `manacore/rating-box` block | **A** — Visual redesign |
| Sidebar Recommendations | ❌ None | **C** — Extend with titles-grid (related source) |
| Comments | WP core comments | **B** — Structural adaptation |
| Trailer | `manacore/trailer` block | **A** — Visual redesign |
| Video Player | `Player` class | **B** — Structural adaptation |
| Episode Pills | ❌ None | **C** — Extend episodes |
| Cinema Mode | ❌ None | **D** — New (low priority) |
| Pricing Cards | `manacore-subscriptions` plans block | **A** — Visual redesign |
| FAQ Accordion | ❌ None | **E** — Reference-only (static content) |
| Account Dashboard | WooCommerce My Account | **E** — Reference-only |
| Analytics | ❌ None | **E** — Reference-only (no data source) |
| Watchlist Grid | Watchlist feature exists | **B** — Structural adaptation |
| History List | ❌ None | **E** — Reference-only (no tracking) |
| Settings Form | WP/Woo account | **E** — Reference-only |
| Person Hero | `single-person.html` | **A** — Visual redesign |
| People Grid | `person` archive | **A** — Visual redesign |
| Magazine Feature | Blog/archive | **A** — Visual redesign |
| Article Layout + TOC | `single.html` | **B** — Structural adaptation |
| Schedule Page | ❌ None | **D** — New (needs data model) |
| Live Player + Channels | ❌ None | **E** — Reference-only (no infra) |
| Breadcrumb | ❌ None explicit | **D** — New (small, valuable) |
| 404 Page | `404.html` | **A** — Visual redesign |
| Profile Dropdown | ❌ None (WP admin bar) | **E** — Reference-only |

### Classification Legend

- **A** = Existing feature, visual redesign only
- **B** = Existing feature, structural adaptation needed
- **C** = Partial feature, must be extended
- **D** = New valuable feature worth integrating
- **E** = Reference-only, should NOT enter the project

---

## 7. Existing Feature Dependencies

### CSS Selectors Used by JS

| Selector | Used by | Purpose |
|----------|---------|---------|
| `[data-manacore-copy]` | front.js | Copy link button |
| `[data-manacore-watchlist]` | front.js | Watchlist toggle |
| `[data-manacore-rate]` | front.js | Rating stars |
| `[data-manacore-player]` | front.js | Player init |
| `[data-manacore-search]` | front.js | Live search |
| `[data-manacore-slider]` | front.js | Hero slider |
| `.manacore-toast` | front.js | Toast container |
| `#koohe-main` | theme.js | Skip link target |
| `[data-color-mode]` | theme.js | Dark mode attribute |

### REST/AJAX Dependencies

| Endpoint | Consumer |
|----------|----------|
| `/manacore/v1/search` | search-box block JS |
| `/manacore/v1/watchlist` | Watchlist buttons |
| `/manacore/v1/rate` | Rating box |
| `/manacore/v1/titles` | Filter bar (AJAX load) |
| `/manacore/v1/links/{id}` | Download links |
| `/manacore/v1/track-download` | Download tracking |

### Block Dependencies

- Templates reference blocks by name (`manacore/titles-grid`, etc.)
- Block visibility rules evaluated server-side
- Block attributes defined in PHP (`Blocks::definitions()`) and shared to JS via `window.manaCoreBlockRegistry`

### Template Dependencies

- `single-movie.html` → `parts/title-header.html`, `patterns/single-title-body.php`
- `front-page.html` → `patterns/hero-featured.php`, `patterns/latest-movies.php`, etc.
- All templates → `parts/header.html`, `parts/footer.html`

### Security Dependencies

- Nonce in `front.js` via `wp_localize_script`
- `current_user_can()` checks in REST callbacks
- `sanitize_*` on all inputs
- `esc_*` on all outputs

---

## 8. New Features Worth Integrating

| Feature | Value | Effort | Priority |
|---------|-------|--------|----------|
| Mega Menu (genre grid + featured) | High — discovery UX | Medium | P1 |
| Detail Tab Bar (sticky) | High — content organization | Medium | P1 |
| Load More / Infinite Scroll | High — browse UX | Low | P1 |
| Active Filter Chips | Medium — browse clarity | Low | P2 |
| Discovery Shortcuts row | Medium — quick nav | Low | P2 |
| Breadcrumb | Medium — SEO + nav | Low | P2 |
| Ranked Card (number overlay) | Low — visual flair | Low | P3 |
| Cinema Mode (player) | Low — nice-to-have | Low | P3 |
| Schedule Panel | High — but needs data model | High | P3 (future) |
| Sidebar Recommendations | Medium — engagement | Low (use existing `related` source) | P2 |

### Features Explicitly NOT Integrating

- Auth modal (WP handles auth)
- Account dashboard/analytics (no data source, Woo handles account)
- Live TV (no infrastructure)
- History tracking (privacy concern, no existing model)
- Profile dropdown (WP admin bar exists)
- FAQ accordion (static content, not dynamic)

---

## 9. Recommended WordPress Architecture

### File Organization

```
koohe-film/
├── theme.json          ← Updated tokens (colors, spacing, radius, breakpoints)
├── assets/css/
│   ├── theme.css       ← REWRITE: Cinora design system applied
│   ├── editor.css      ← Updated to match
│   └── woocommerce.css ← Minor updates
├── assets/js/
│   ├── theme.js        ← Extended: mega menu, drawer, search overlay, tabs
│   └── ...
├── parts/
│   ├── header.html     ← REWRITE: Cinora header structure
│   ├── footer.html     ← REWRITE: Cinora footer
│   └── ...
├── patterns/
│   ├── hero-featured.php   ← Updated markup
│   └── ... (new patterns for discovery shortcuts, etc.)
├── templates/          ← Updated to use new parts/patterns
└── inc/
    ├── assets.php      ← Updated enqueue
    └── ...

manacore-core/
├── assets/css/front.css ← REWRITE: Cinora card styles, download table, etc.
├── assets/js/front.js   ← Extended: tabs, accordion, load-more, chips
├── includes/
│   ├── class-templates.php ← Updated card render HTML
│   ├── class-blocks.php    ← Block render updates
│   └── ...
└── ...
```

---

## 10. Theme vs Core Plugin Responsibilities

| Responsibility | Location | Rationale |
|---|---|---|
| Color tokens, typography, spacing | `theme.json` | Design system = theme |
| Header/footer layout | `parts/*.html` | Presentation = theme |
| Component styles (cards, buttons, panels) | `theme.css` | Presentation = theme |
| Block render markup | `class-templates.php`, `class-blocks.php` | Domain logic = plugin |
| Block-specific styles | `front.css` | Couples to plugin markup |
| REST API | Plugin | Business logic = plugin |
| Watchlist, rating logic | Plugin | Domain = plugin |
| Interactive JS (tabs, accordion) | `front.js` (plugin) if tied to block markup | Coupling |
| Interactive JS (nav, drawer, search overlay) | `theme.js` | Presentation = theme |
| Mega menu data (genres) | Plugin provides terms; theme renders | Separation |
| Load-more / infinite scroll | Plugin (query) + theme (UI trigger) | Shared |

---

## 11. Files / Modules Likely to Change

### Phase 1: Design Tokens & Base Styles
- `wp/themes/koohe-film/theme.json`
- `wp/themes/koohe-film/assets/css/theme.css`
- `wp/themes/koohe-film/styles/dark.json`, `light.json`, `midnight.json`, `cinema.json`

### Phase 2: Header & Navigation
- `wp/themes/koohe-film/parts/header.html`
- `wp/themes/koohe-film/parts/header-minimal.html`
- `wp/themes/koohe-film/assets/js/theme.js`
- `wp/themes/koohe-film/assets/css/theme.css`

### Phase 3: Cards & Grid
- `wp/plugins/manacore-core/includes/class-templates.php`
- `wp/plugins/manacore-core/assets/css/front.css`
- `wp/plugins/manacore-core/assets/js/front.js`

### Phase 4: Detail Page
- `wp/themes/koohe-film/parts/title-header.html`
- `wp/themes/koohe-film/templates/single-movie.html` (and series, anime)
- `wp/plugins/manacore-core/includes/class-links.php` (download table markup)
- `wp/plugins/manacore-core/assets/css/front.css`

### Phase 5: Browse & Filter
- `wp/plugins/manacore-core/includes/class-blocks.php` (filter-bar render)
- `wp/plugins/manacore-core/assets/js/front.js`
- `wp/themes/koohe-film/templates/archive-*.html`

### Phase 6: Footer & Misc
- `wp/themes/koohe-film/parts/footer.html`
- `wp/themes/koohe-film/templates/404.html`
- `wp/themes/koohe-film/patterns/*.php`

---

## 12. Risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Breakpoint change (600/782 → 768/980) conflicts with WP core nav | High | Test nav block at all breakpoints; may need to keep WP boundaries for nav and add Cinora breakpoints for layout |
| Card markup change breaks existing JS selectors | High | Maintain `data-manacore-*` attributes; update JS in same commit |
| theme.json color rename breaks existing patterns/templates | Medium | Keep slug names, only change values |
| front.css rewrite breaks admin/editor styles | Medium | Editor styles are separate files; test both |
| Mega menu requires dynamic term list | Low | Use `wp_get_terms` with cache; fallback to static |
| Load-more changes archive pagination | Medium | Keep pagination as fallback; load-more is progressive |
| Sticky tab bar conflicts with admin bar | Low | Account for `#wpadminbar` height |
| Font size reduction (14px base) affects readability | Medium | Test with actual Persian text; ensure WCAG AA contrast |

---

## 13. Migration Concerns

1. **No database migration needed** — all changes are presentation layer.
2. **Block attribute schema unchanged** — no block deprecations.
3. **Template part references** — if header.html structure changes, all templates referencing it auto-update (FSE).
4. **Pattern content** — patterns use block markup; updating pattern PHP updates new inserts but NOT existing saved content. Existing pages using old patterns will retain old markup until re-edited.
5. **Style variations** — updating `styles/*.json` is safe; they override theme.json at runtime.
6. **User content** — no user-generated content is affected.
7. **WooCommerce templates** — `woocommerce.css` needs alignment but Woo templates are separate.

---

## 14. Implementation Order

| Phase | Scope | Depends on |
|-------|-------|------------|
| **1** | Design tokens (theme.json, CSS variables, style variations) | Nothing |
| **2** | Base component styles (buttons, cards, panels, badges) | Phase 1 |
| **3** | Header + Navigation + Mega Menu + Mobile Drawer | Phase 1, 2 |
| **4** | Footer | Phase 1, 2 |
| **5** | Hero Slider visual redesign | Phase 2 |
| **6** | Media Card + Grid + Ranked card | Phase 2 |
| **7** | Detail page (hero, tabs, download table, episodes, cast, rating) | Phase 2, 6 |
| **8** | Browse page (toolbar, filter sidebar, chips, load-more) | Phase 2, 6 |
| **9** | Player page | Phase 2 |
| **10** | Subscription/Pricing page | Phase 2 |
| **11** | Search overlay (⌘K) | Phase 3 |
| **12** | Toast, empty states, breadcrumb | Phase 2 |
| **13** | 404, magazine, person pages | Phase 2, 6 |
| **14** | Responsive QA + accessibility pass | All |
| **15** | Test suite updates + regression | All |

---

## 15. Testing Strategy

| Layer | Method |
|-------|--------|
| PHP syntax | `php -l` on all files (existing test §1) |
| JS syntax | `node --check` (existing test §2) |
| Block editor simulation | Existing test §5 — update expected markup |
| Dark/light mode | Existing test §6 — update expected CSS vars |
| Responsive | Existing test §12 — update breakpoints |
| Visual regression | Manual browser testing at 360/480/768/980/1100/1300/1600px |
| Feature preservation | Verify all REST endpoints still work |
| Accessibility | Keyboard nav, screen reader, contrast, focus rings |
| RTL | All layouts are RTL; verify no LTR assumptions |
| WooCommerce | Subscription flow still works |

### New Tests to Add

- Mega menu renders correct taxonomy terms
- Detail tab bar sticky behavior
- Load-more returns correct page
- Filter chips reflect active filters
- Search overlay opens/closes, returns results
- Episode accordion expands/collapses

---

## 16. Regression Risks

| Area | Risk | Prevention |
|------|------|------------|
| Watchlist buttons | Card markup change may break `[data-manacore-watchlist]` | Keep attribute in new markup |
| Rating stars | Same as above | Keep attribute |
| Copy link | Same | Keep attribute |
| Player init | `[data-manacore-player]` must exist | Keep attribute |
| Search input | Block render change may break JS binding | Keep `data-manacore-search` |
| Slider | `data-manacore-slider` must persist | Keep attribute |
| Admin metabox | No change — separate CSS/JS | No risk |
| Block editor | ServerSideRender uses same PHP render | Test in editor |
| Existing saved pages | Patterns changed → old pages keep old markup | Acceptable; document in changelog |
| Style variations | Color slug names unchanged | No risk |
| WooCommerce compat | `woocommerce.css` updated separately | Test checkout flow |

---

## Appendix: Token Mapping Quick Reference

```
Cinora --bg         → theme.json "surface"
Cinora --panel      → theme.json "surface-2"
Cinora --panel-2    → theme.json "surface-3"
Cinora --border     → theme.json "border"
Cinora --text       → theme.json "foreground"
Cinora --muted      → theme.json "muted"
Cinora --accent     → theme.json "accent"
Cinora --accent-text→ theme.json "accent-contrast"
Cinora --gold       → NEW theme.json "rating"
Cinora --danger     → theme.json "danger"

Cinora --header-height → theme.json custom.header.height (84px)
Cinora container 1440px → theme.json layout.wideSize
```

---

*End of Blueprint. No production code has been modified.*