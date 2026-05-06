=== QOTD – Quote of the Day ===
Contributors: dietergeiling
Tags: quote, quote of the day, shortcode, rest-api
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.3.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display a new quote every day — delivered cache-safely via the WordPress REST API.

== Description ==

QOTD (Quote of the Day) adds a dedicated custom post type for quotes and automatically displays one of them each day on your website.

The displayed quote changes once per day and is selected deterministically based on the current date — no randomness involved, so every visitor sees the same quote on the same day. Delivery is cache-safe via a REST API endpoint that sends appropriate HTTP caching headers, making it fully compatible with full-page caches and CDNs.

**Features:**

* Custom post type "Quotes" in the WordPress admin
* Fields for quote text, author, and an optional extra field (e.g. source, year, or context)
* Daily quote rotation — consistent for all visitors
* Embed anywhere with the `[qotd]` shortcode
* Optional `class` parameter for individual styling: `[qotd class="my-style"]`
* Gutenberg block support (requires compiled `/build` directory)
* REST API endpoint `/wp-json/qotd/v1/today` with HTTP caching headers
* Plain text only — no HTML stored or output, XSS-safe by design
* Auto-generated post title from quote text
* Import and export quotes as JSON (via admin menu)
* Clean uninstall — removes all plugin data when deleted
* Translation-ready (`.pot` file included)

**How the daily quote is selected:**

The plugin uses a deterministic algorithm: `crc32(date + site_url)` maps today's date to a fixed quote from your collection. This means the quote is stable throughout the day, works correctly even with full-page caching, and does not require any session or cookie.

**CSS structure:**

The plugin does not ship its own frontend CSS. Style the output using your theme:

`.qotd` — outer wrapper
`.qotd__text` — the quote text
`.qotd__meta` — wraps author and source
`.qotd__author` — author name
`.qotd__source` — optional extra field

== Installation ==

1. Upload the `qotd` folder to `/wp-content/plugins/`.
2. Activate the plugin via the "Plugins" menu in the WordPress admin.
3. Go to the new "Quotes" menu item and add one or more quotes.
4. Insert the shortcode `[qotd]` on any page, post, or widget area.

The shortcode accepts an optional `class` parameter to add a custom CSS class:

`[qotd class="my-style"]`

All styling of the output (`.qotd`, `.qotd__text`, `.qotd__author`, `.qotd__source`) is handled entirely by your theme.

== Frequently Asked Questions ==

= Does the quote change for every page load? =

No. The quote is selected once per day based on the current date. All visitors see the same quote throughout the day, regardless of caching.

= Is the plugin compatible with caching plugins and CDNs? =

Yes. The REST endpoint returns proper `Cache-Control` and `Expires` headers that expire at midnight. It works correctly with WP Rocket, W3 Total Cache, LiteSpeed Cache, Cloudflare, and similar solutions.

= How many quotes can I add? =

There is no hard limit. The plugin loads up to 5,000 published quote IDs into a transient cache (refreshed daily), which is sufficient for any practical use case.

= Can I import existing quotes? =

Yes. Go to "Quotes → Import / Export" in the admin. Upload a JSON file containing an array of objects with the fields `text`, `author`, and `extra`. Duplicate quotes (matching text) are automatically skipped.

= Does the plugin store HTML in quotes? =

No. All fields (text, author, extra) are stored and output as plain text only. This prevents XSS issues and keeps quotes portable.

= Can I style the output? =

Yes. The plugin outputs a simple HTML structure with BEM-style CSS classes. Add your own styles in your theme's stylesheet or via the WordPress Customizer.

= Does the plugin work with the block editor? =

Yes, if the `/build` directory with the compiled block files is present. The shortcode works independently of the block and is always available.

= What happens when the plugin is deleted? =

The uninstall routine removes all quote post meta entries (`_qotd_text`, `_qotd_author`, `_qotd_extra`) and the transient cache. The custom post type entries themselves follow standard WordPress behavior.

= Is the plugin available in German? =

Yes. The plugin is fully translated into German (de_DE). The text domain is `qotd`.

== Screenshots ==

1. The quote output on the frontend — styled with a custom theme.
2. The quote list in the WordPress admin.
3. Adding or editing a quote — plain text fields for text, author, and extra.
4. Import / Export page for bulk management of quotes via JSON.
5. The help / documentation page inside the admin.

== Changelog ==

= 1.3.2 =
* Improved daily quote selection: a fallback mechanism now ensures that the
  same quote never appears on two consecutive days.

= 1.3.1 =
* Compatibility: Automatically registers the REST endpoint as an exception
  when the REST API is restricted via Perfmatters.
  
= 1.3.0 =
* Added CLS-optimized skeleton loader with shimmer animation
* Dynamic min-height calculation prevents layout shift during quote loading
* Responsive min-height adjustment for mobile and desktop viewports
* Improved loading states with fade-in transition
* CSS custom properties for easy skeleton customization (--qotd-skeleton-base, --qotd-skeleton-shine)
* Enhanced frontend performance with optimized AJAX loading
* New qotd.css for skeleton loader styling

= 1.2.0 =
* Custom post type `qotd_quote` with plain text meta fields (text, author, extra)
* Deterministic daily selection via `crc32(date + site_url)`
* REST API endpoint `/wp-json/qotd/v1/today` with HTTP caching headers
* Shortcode `[qotd]` with optional `class` parameter
* Gutenberg block support
* JSON import and export via admin submenu
* Admin help / documentation page
* Auto-generated post title from quote text
* Transient cache for published quote IDs
* Full German translation (de_DE)
* Clean uninstall routine

= 1.1.0 =
* First public release

== Upgrade Notice ==

= 1.3.2 =
Improved quote rotation. No manual steps required.

= 1.3.1 =
Compatibility update for Perfmatters users. No manual steps required.

= 1.3.0 =
Performance update with CLS optimization. No manual steps required. The new skeleton loader improves loading experience and reduces layout shift.

= 1.2.0 =
Feature update — no manual steps required.

= 1.1.0 =
First public release. No upgrade steps required.