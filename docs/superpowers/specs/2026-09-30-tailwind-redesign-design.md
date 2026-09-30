# Storefront redesign with Tailwind CSS and Twig Components

- **Date:** 2026-09-30
- **Branch:** `feat/storefront-design`
- **Status:** approved design, awaiting implementation plan

## 1. Goal

Give the whole store a professional, modern look, consistently across every page.

The first iteration (`theme.css`, "MiniStore" palette graphite / teal / coral) redesigned the
header, footer, home, shop, product and cart pages on top of Bootstrap and the original template
CSS. Two things still felt unprofessional: the **palette** and the **component detail** (buttons,
cards, inputs, badges looked basic). The remaining pages were never redesigned.

This iteration replaces the Bootstrap-based styling with **Tailwind CSS** and reusable
**Twig Components**, using the "Ink & indigo" palette, and brings every page onto it.

### Success criteria

- Every customer-facing and back-office page uses the new design system. No Bootstrap, jQuery or
  template CSS/JS is left.
- Pages work from 360px wide upward.
- Text contrast meets WCAG AA (4.5:1). Every interactive element has a visible focus ring and is
  keyboard-usable.
- The full PHPUnit suite passes after every step, and new component tests are added.
- Behaviour is unchanged: routes, controllers, forms, security rules and business logic are not
  modified.

### Non-goals

- New features, new pages, or copy rewrites beyond moving the remaining English text to French.
- Turbo Drive / SPA-style navigation (it is explicitly disabled, see §3.5).
- Dark mode.
- Restyling the HTML password-reset email (`reset_password/email.html.twig`). Emails need inline
  styles and are a separate topic.
- A JavaScript test runner. The project has no `package.json`, and the Stimulus controllers are
  verified in the browser instead (§5.2).

## 2. Decisions and alternatives considered

| Decision | Chosen | Rejected, and why |
|---|---|---|
| Direction | Refine the existing design, then finish every page | Starting a new visual identity was not needed: the problems were the palette and component detail |
| Palette | B · Ink & indigo (chosen in the visual companion) | A · Refined teal and C · Monochrome + orange |
| Styling approach | Tailwind CSS through `symfonycasts/tailwind-bundle` (AssetMapper, standalone binary, no Node) | Keeping `theme.css` with role-named tokens: cheaper, but the user preferred a full modern toolchain. Webpack Encore: needs Node and adds nothing here |
| Reusable UI | `symfony/ux-twig-component` | Twig includes/macros: clunky props and defaults. Tailwind `@apply` classes: bring back the naming layer Tailwind removes |
| Form styling | A global Symfony **form theme** | A `Field` Twig Component: it would duplicate what form themes already do |
| JavaScript | Stimulus controllers (Stimulus bundle already installed) | Keeping jQuery 1.11 (known XSS CVEs) and the Bootstrap JS bundle |

## 3. Architecture

### 3.1 Tooling

- **Add:** `symfonycasts/tailwind-bundle` (Tailwind v4) and `symfony/ux-twig-component`.
- **Wire AssetMapper**, which is already installed but not loaded. `base.html.twig` renders
  `{{ importmap('app') }}`. `assets/app.js` imports `./styles/app.css` (the Tailwind entry) and
  `./bootstrap.js` (Stimulus). The `console.log` welcome line is removed.
- **Development:** run `php bin/console tailwind:build --watch` next to the web server.
  **Before tests and deployments:** run `php bin/console tailwind:build` (with `--minify` for
  production), because a page that loads `app.css` fails when the built file is missing. This is
  documented in the README and added to the test setup.
- **Font:** Manrope stays (Google Fonts, weights 400–800).

### 3.2 Design tokens (`assets/styles/app.css`, `@theme`)

Tokens are named by role, so utilities read as intent (`bg-accent`, `text-muted`).

| Token | Value | Use |
|---|---|---|
| `ink` | `#0f172a` | main text, headings |
| `muted` | `#5b6577` | secondary text (5.88:1 on white, 5.37:1 on `subtle`) |
| `line` | `#e2e8f0` | borders, dividers |
| `canvas` | `#f8fafc` | page background |
| `surface` | `#ffffff` | cards, inputs |
| `subtle` | `#f1f5f9` | product photo tiles, summary panels, table headers |
| `accent` | `#4f46e5` | primary buttons and action links (6.29:1 with white) |
| `accent-hover` | `#4338ca` | hover / active state of accent |
| `accent-soft` | `#e0e7ff` | soft badges and selected filters (text `#3730a3`, 8.06:1) |
| `signal` | `#dc2626` | sale, out of stock, errors (4.83:1 with white) |
| `success` | `#16a34a` | success alerts, "in stock" |

Changes from the mockup, made for WCAG AA: `signal` `#ef4444` → `#dc2626` (was 3.76:1),
`muted` `#64748b` → `#5b6577` (was 4.34:1 on `subtle`).

Also defined:

- **Shadows:** `shadow-card` (a thin sharp layer plus a wide soft layer) and `shadow-card-hover`.
- **Radius:** 10px for controls, 14px for cards.
- **Focus:** `ring-4 ring-accent/20` plus `border-accent` on every interactive element.

Motion (such as the card lift on hover) uses `motion-safe:` so it respects
`prefers-reduced-motion`.

### 3.3 Twig Components (`src/Twig/Components/`, `templates/components/`)

| Component | Props | Notes |
|---|---|---|
| `Button` | `variant` (primary, secondary, ghost, danger), `size`, `href`, `type` | Renders `<a>` when `href` is set, `<button>` otherwise. Extra attributes pass through `{{ attributes }}` |
| `Badge` | `tone` (accent, signal, success, neutral) | Pill shape |
| `Alert` | `tone`, `dismissible` | Uses the `dismiss` controller. Content is always escaped |
| `Card` | — | Surface, border, radius, shadow. Content goes in the block |
| `ProductCard` | `product` | Replaces `partials/product_card.html.twig`. Handles the sale and sold-out badges and the hover lift |
| `PriceTag` | `price`, `compareAt` | Uses the existing French price formatting and crosses out the compare-at price only on a discount |
| `EmptyState` | `title`, `icon` | Empty cart, no results, no orders |
| `PageHeader` | `title`, `breadcrumb` | Replaces `partials/breadcrumb.html.twig` |
| `Pagination` | `page`, `pages`, `route`, `params` | Replaces `partials/pagination.html.twig` and keeps `rel="next"`/`rel="prev"` |

Rule: never use `|raw` in a component template.

### 3.4 Form theme

`templates/form/theme.html.twig` extends `form_div_layout.html.twig` and overrides `form_row`,
`form_label`, `form_widget_simple`, `choice_widget`, `checkbox_widget` and `form_errors` with the
new styles. It is registered globally in `config/packages/twig.yaml` (`form_themes`). Every
existing `form_row()` and `form_widget()` call picks it up without template changes.

### 3.5 JavaScript (`assets/controllers/`)

| Controller | Replaces |
|---|---|
| `menu` | Bootstrap offcanvas mobile menu (focus trap, Escape to close) |
| `search` | jQuery search popup (open, close on Escape and backdrop click, autofocus) |
| `dropdown` | Bootstrap dropdown (account menu, keyboard support) |
| `dismiss` | Bootstrap `data-bs-dismiss="alert"` |
| `quantity` | jQuery `.product-qty` stepper |
| `gallery` | Swiper on the product page (thumbnails switch the main image) |

Home-page carousels (`main-swiper`, `product-swiper`, `testimonial-swiper`) are replaced by static
grids or a CSS scroll-snap row. No carousel library is kept.

**Turbo:** `@symfony/ux-turbo` `turbo-core` is set to `enabled: false` in
`assets/controllers.json`. Loading the importmap would otherwise enable Turbo Drive, which changes
form-submission behaviour (it requires 422 responses for invalid forms). That is out of scope for
a visual redesign.

**Removed files:** `public/css/bootstrap.min.css`, `public/css/vendor.css`,
`public/css/theme.css`, `public/style.css`,
`public/js/jquery-1.11.0.min.js`, `public/js/plugins.js`, `public/js/script.js`,
`public/js/modernizr.js`, `public/js/bootstrap.bundle.min.js`, and both jsdelivr Swiper tags.
Image and icon assets still referenced by templates are kept. The final step removes
`public/css/ajax-loader.gif` only if nothing references it any more.

### 3.6 Layouts

- **Storefront** (`base.html.twig`):
  - sticky header: logo, navigation, search, account dropdown, cart with item count
  - mobile menu
  - `<main>` on `canvas`
  - footer
- **Back office**: the admin, user and order-management pages extend a lighter layout
  (`admin/layout.html.twig`) with a sidebar navigation and the same components, form theme and
  tokens.

## 4. Rollout

One commit per step. The full test suite passes after each commit.

| # | Commit | Scope |
|---|---|---|
| 0 | `feat(design): redesign the cart page` | Pre-existing cart work, committed as-is (`d0e3c23`) |
| 1 | `feat(design): Tailwind foundation, components and layout` | Packages, tokens, base layout, header, footer, Stimulus controllers, Turbo disabled, form theme, `Button`/`Badge`/`Alert`/`Card` |
| 2 | `feat(design): catalogue on Tailwind` | Home, shop (filters, sort, pagination), product page, search, `ProductCard`, `PriceTag`, `Pagination`, `PageHeader` |
| 3 | `feat(design): cart and checkout on Tailwind` | Cart, checkout, order success, `EmptyState` |
| 4 | `feat(design): authentication pages on Tailwind` | Login, register, verify phone, verify code, reset-password request/reset, `api_register` |
| 5 | `feat(design): customer account on Tailwind` | Account, edit, change password, customer order list/detail |
| 6 | `feat(design): content pages on Tailwind` | Blog, single post, blog sidebar, about, contact, contact confirmation |
| 7 | `feat(design): back office on Tailwind` | `admin/*`, `user/*`, order new/edit/show/delete, status badge, back-office layout |
| 8 | `chore(design): remove legacy CSS and JS` | Delete legacy files, update `.gitignore` (`.superpowers/`, Tailwind build output), README instructions |

**Intermediate state:** between steps 1 and 7, pages not yet migrated render without styles. They
remain functional, but the branch must not be merged into `main` before step 8.

**Language:** all visible text is in French (`<html lang="fr">`), following the cart page.

## 5. Testing and verification

### 5.1 PHPUnit

- **Existing tests stay unchanged.** The selectors they use are kept as unstyled hook classes in
  the new markup: `.ms-grid`, `.ms-tile__name`, `.ms-hero__feature`, `.ms-pagination`,
  `.product-price`, `.product-actions`, `.product-title`, `.related-products`, `.cart-summary`,
  `.quantity-input`, `.add-to-cart-form`, `.card-body`, `#checkout-form`, `header form[role="search"]`,
  and the form `name` and `action` attributes.
- **New tests in `tests/Twig/Components/`** (`InteractsWithTwigComponents`):
  - `Button`: `<a>` versus `<button>`; variant classes.
  - `PriceTag`: compare-at price shown only on a discount; French formatting.
  - `ProductCard`: "Épuisé" badge when stock is 0; percentage badge on a discount.
  - `Alert`: content containing `<script>` is rendered escaped (XSS guard).
- **Regression test in `HomePageTest`:** the page loads the importmap and no longer references
  `jquery` or `cdn.jsdelivr.net`.

### 5.2 Browser checks (Playwright)

The app runs with `php -S 127.0.0.1:8000 -t public`. After each step:

- Screenshots at 375px and 1280px of the pages in that step, shared with the user.
- Interactive checks for the controllers introduced or used in that step: menu, search, dropdown,
  dismiss, quantity, gallery.
- Keyboard-only pass: tab order, visible focus, Escape closes overlays.

### 5.3 Security checks

- `config/packages/csrf.yaml` enables **stateless CSRF** (`submit`, `authenticate`, `logout`).
  Loading the importmap activates `csrf_protection_controller.js` (double-submit cookie and
  header) on top of the `Origin`/`Referer` check. After step 1, verify in the browser that
  **login, logout, registration, add to cart, cart update and checkout** still submit
  successfully. `CsrfProtectionTest` must still pass.
- No `|raw` in new templates. All user data goes through Twig auto-escaping.
- After step 8, no third-party script CDN remains. Fonts are the only external resource.

## 6. Risks

| Risk | Mitigation |
|---|---|
| Tests fail because the Tailwind build is missing | Run `tailwind:build` before `bin/phpunit`; documented in the README |
| The CSRF JavaScript changes login behaviour | Browser verification after step 1 (§5.3) |
| A hook class used by a test is lost in a rewrite | The list in §5.1 is checked at each step; the full suite runs per commit |
| Unmigrated pages look broken mid-branch | No merge before step 8 |
| Removing a carousel loses content | Every slide's content is kept as a static grid or scroll-snap row |
