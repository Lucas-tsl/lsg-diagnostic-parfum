=== LSG Diagnostic Parfum ===
Contributors: lucastsl
Donate link: https://github.com/Lucas-tsl
Tags: woocommerce, perfume, product finder, wpml, quiz
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
WC requires at least: 6.0
WC tested up to: 9.0
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A perfume-finder (quiz) block, shortcode and dedicated page for WooCommerce stores, filtering products by scent family and note, with WPML support.

== Description ==

**LSG Diagnostic Parfum** lets a WooCommerce store visitor pick a perfume "family" (e.g. Warm, Floral, Woody) and, optionally, a scent "note", to instantly see the matching products.

It ships with three ways to use it:

* A **Gutenberg block** (`custom/diag`), meant to be dropped into a WooCommerce product category template (Site Editor). It reads `?diag=1&diag_parfum=...&diag_note=...` from the URL and only appears once the diagnostic has been triggered, filtering the existing category product loop via `pre_get_posts`.
* A **shortcode** (`[lsg_diag_parfum]`) for use on any regular WordPress page. Unlike the block, it always renders, ships its own responsive 2-column layout (filters + result count on the left, a loading indicator and the matching products grid on the right), and runs its own WooCommerce product query since a plain page has no product loop of its own.
* Full **WPML** compatibility: the page title (native WordPress title, Yoast SEO and RankMath), the canonical URL, and the default pre-selected perfume family all adapt to the active language.

= Key features =

* Responsive 2-column layout (stacked on mobile, side-by-side from tablet upward, wider gutters on laptop/large screens).
* A sensible default selection out of the box (configurable per language) so the page is never empty on first load.
* A "Reset" button, shown only once a filter is active.
* A loading indicator while the page reloads after a selection change.
* A live product counter ("X perfumes found") above the results.
* Dynamic `<title>` and canonical URL, with dedicated hooks for Yoast SEO (`wpseo_title`) and RankMath (`rank_math/frontend/title`) since both plugins bypass WordPress' native title filter.
* Language-aware default selection and a small compatibility helper for WPML's `get_terms()` language-filtering quirk (`suppress_filters` must be explicitly set to `false`, otherwise terms from every language are returned together).

= Requirements =

This plugin expects two existing WooCommerce product attribute taxonomies on the site: `pa_mini_diag_parfum` (perfume family) and `pa_mini_diag_note` (scent note), each product being tagged with the relevant terms. It also expects the theme to provide an `lsg_t( $fr, $en )` translation helper (a simple two-argument bilingual helper); if your theme doesn't have one, add a small one or swap those calls for `__()` calls of your own.

== Installation ==

1. Upload the `lsg-diagnostic-parfum` folder to `/wp-content/plugins/`.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Make sure your products are tagged with terms from the `pa_mini_diag_parfum` and `pa_mini_diag_note` attribute taxonomies.
4. To use it on a category page: insert the `custom/diag` block in your category template via the Site Editor.
5. To use it on a standalone page: create a new page and add the shortcode `[lsg_diag_parfum]`.
6. Open `lsg-diagnostic-parfum.php` and adjust the `LSG_DIAG_DEFAULT_PARFUM` constant and the `lsg_diag_default_parfum_by_lang()` mapping to match the actual slug(s) of your default term in each active language.

== Frequently Asked Questions ==

= Why do I see terms from two languages mixed together in the dropdown? =

This is a known WPML behaviour: `get_terms()` defaults to `suppress_filters => true`, which switches off WPML's language filter. This plugin already works around it via the `lsg_diag_get_terms()` helper, which explicitly passes `suppress_filters => false`. If you still see duplicates, check that the taxonomy itself is registered as translatable under WPML → Languages → Taxonomy translation, and that the mixed terms are actually proper WPML translations of one another rather than unrelated terms.

= The wrong language's terms show as "disabled" in the note dropdown =

The plugin disables a note option when no product links that specific term (by its exact term ID) to the currently selected perfume family. If your products' attribute relationships are not fully synced across languages in WPML/WooCommerce Multilingual, some translated note terms may have no direct product relationship of their own, even though the equivalent term in the default language does. Re-saving the affected products (or running a WPML sync) usually fixes this.

= Can I change the default pre-selected perfume? =

Yes. Edit `LSG_DIAG_DEFAULT_PARFUM` (the base/original-language slug) and, if the term's slug differs by language, the `lsg_diag_default_parfum_by_lang()` array, at the top of `lsg-diagnostic-parfum.php`.

== Changelog ==

= 1.1.0 =
* Two-column responsive layout for the `[lsg_diag_parfum]` shortcode (filters + count on the left, loading indicator + results on the right).
* H2 subtitle under the main heading.
* Modernised select styling (no border-radius, custom SVG chevron).
* The perfume family select no longer offers an empty placeholder option — a real selection is always active.
* Per-language default perfume selection.
* Fixed a WPML `get_terms()` language-filtering bug causing duplicate terms across languages.
* French translations for "Reset" and the results counter via a lightweight bilingual helper, independent of WPML String Translation registration.

= 1.0.0 =
* Initial extraction from the theme's `functions.php`: Gutenberg block, product filtering, and the `[lsg_diag_parfum]` shortcode.

== Upgrade Notice ==

= 1.1.0 =
Review the `LSG_DIAG_DEFAULT_PARFUM` and `lsg_diag_default_parfum_by_lang()` values after upgrading to make sure they match your actual term slugs in each language.
