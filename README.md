# LSG Diagnostic Parfum

A WordPress/WooCommerce plugin that adds a perfume-finder (scent family + note) diagnostic tool: a Gutenberg block for product category pages, and a `[lsg_diag_parfum]` shortcode for a dedicated, fully responsive, WPML-aware page.

**Author:** [Lucas Troteseil](https://github.com/Lucas-tsl) — [WordPress.org profile](https://profiles.wordpress.org/lucastsl)

## Features

- **Gutenberg block** (`custom/diag`) for WooCommerce category templates — filters the existing product loop via `pre_get_posts` based on `?diag_parfum=` / `?diag_note=` query parameters.
- **Shortcode** `[lsg_diag_parfum]` for any standalone page — renders its own responsive 2-column layout:
  - Left column: title, subtitle, the two filters, a reset button, and a live result count.
  - Right column: a loading indicator and the matching WooCommerce products grid.
- **Responsive breakpoints**: stacked on mobile, 2 columns from tablet (≥768px), wider gutters on laptop (≥1024px) and large screens (≥1440px).
- **WPML-ready**:
  - Dynamic `<title>` and canonical URL, with dedicated hooks for Yoast SEO and RankMath (both plugins bypass WordPress' native title filter, so a generic `document_title_parts` hook alone isn't enough).
  - Per-language default pre-selected perfume family.
  - Works around a common WPML pitfall where `get_terms()` returns terms from every language at once unless `suppress_filters` is explicitly set to `false`.

## Requirements

- WordPress 5.8+
- PHP 7.4+
- WooCommerce 6.0+
- Two WooCommerce attribute taxonomies on your products: `pa_mini_diag_parfum` and `pa_mini_diag_note`
- (Optional) WPML, if you want multilingual support
- A theme-level `lsg_t( $fr, $en )` helper function (a minimal bilingual string helper) — or adapt the calls to your own i18n approach

## Installation

```bash
cd wp-content/plugins
git clone https://github.com/Lucas-tsl/lsg-diagnostic-parfum.git
```

Then activate it from **Plugins** in wp-admin.

## Configuration

Open `lsg-diagnostic-parfum.php` and adjust, near the top of the file:

```php
define( 'LSG_DIAG_DEFAULT_PARFUM', 'reconfortant' ); // base/default-language slug

function lsg_diag_default_parfum_by_lang() {
    return apply_filters( 'lsg_diag_default_parfum_by_lang', array(
        'fr' => 'reconfortant',
        'en' => 'comforting',
    ) );
}
```

## Usage

**On a category page:** insert the `custom/diag` block into your category template via the Site Editor.

**On a standalone page:** create a page and add:

```
[lsg_diag_parfum]
```

## License

GPL v2 or later — see [LICENSE](LICENSE).
