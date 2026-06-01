=== Citatly - Daily Quote ===
Contributors: dieter93
Tags: quote, quotes, quote of the day, daily quote, block
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.3.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display a fresh quote every day from your own collection — cache-safe, no external services, no API key required.

== Description ==

Citatly lets you manage your own quote collection and automatically displays a fresh one each day on your website — cache-safe, without any external services or API keys.

The daily quote is selected based on the current date, so every visitor sees the same quote throughout the day regardless of caching. It is delivered via a REST API endpoint with proper HTTP caching headers, making it fully compatible with full-page caches and CDNs like WP Rocket, LiteSpeed Cache, or Cloudflare.

**Live demo & documentation:** [citatly.com](https://citatly.com)

**Für deutschsprachige Nutzer:**

Das Plugin „Zitat des Tages" ist vollständig auf Deutsch übersetzt (de_DE). Es zeigt täglich ein neues Zitat aus deiner eigenen Sammlung — cache-sicher, ohne externe Abhängigkeiten und ohne API-Schlüssel. Live-Demo & Dokumentation auf Deutsch: [citatly.com/de/](https://citatly.com/de/)

**Features:**

* Manage your quotes via a dedicated custom post type in the WordPress admin
* Fields for quote text, author, and an optional extra field (e.g. source, year, or context)
* Daily quote rotation — same quote for all visitors throughout the day
* Embed anywhere with the `[citatly]` shortcode or the Gutenberg block
* Optional `class` parameter for custom styling: `[citatly class="my-style"]`
* REST API endpoint `/wp-json/citatly/v1/today` with HTTP caching headers
* Import and export your quotes as JSON
* Plain text only — no HTML stored or output, safe by design
* Clean uninstall — removes all plugin data when deleted
* Fully translated into German (de_DE)

== Installation ==

1. Upload the `citatly-daily-quote` folder to `/wp-content/plugins/`.
2. Activate the plugin via the "Plugins" menu in the WordPress admin.
3. Go to **Quotes → Add New** and add one or more quotes.
4. Insert the shortcode `[citatly]` on any page, post, or widget area — or use the Gutenberg block.

The quote changes automatically at midnight (site timezone).

== Frequently Asked Questions ==

= Does the quote change for every page load? =

No. The quote is selected once per day based on the current date. All visitors see the same quote throughout the day, regardless of caching.

= Why might the displayed quote unexpectedly change? =

The daily quote is selected based on the current date and the total number of published quotes. Adding, deleting, or unpublishing a quote may cause today's quote to change. From the next day on, everything works as normal again.

= Is the plugin compatible with caching plugins and CDNs? =

Yes. The REST endpoint returns proper `Cache-Control` and `Expires` headers that expire at midnight. It works correctly with WP Rocket, W3 Total Cache, LiteSpeed Cache, Cloudflare, and similar solutions.

= Does the plugin work with LiteSpeed Cache? =

Yes. However, if REST API caching is enabled in LiteSpeed Cache, the quote may not change daily as expected. To fix this, set "Default REST TTL" to 0 under LiteSpeed Cache → Cache → TTL.

= Does the plugin work when the REST API is restricted? =

The daily quote is loaded via a REST API request in the visitor's browser. If the REST API is restricted or disabled for unauthenticated visitors, the quote will not be displayed.

Most performance and security plugins that restrict the REST API also allow whitelisting specific endpoints. Add `citatly/v1/today` as an exception to restore functionality.

Note: If you are using Perfmatters, the exception is registered automatically — no manual configuration needed.

= Does the Gutenberg block work? =

Yes. The block is available in the editor right away after activation. The shortcode `[citatly]` works independently of the block and is always available.

= Can I style the output? =

Yes. The plugin outputs a simple HTML structure with BEM-style CSS classes that you can target in your theme's stylesheet:

`.citatly` — outer wrapper
`.citatly__text` — the quote text
`.citatly__meta` — wraps author and source
`.citatly__separator` — dash before author (default: "— ")
`.citatly__author` — author name
`.citatly__divider` — dot between author and source (default: " · ")
`.citatly__source` — optional extra field

Interactive styling examples are available at [citatly.com/docs/css-styling](https://citatly.com/docs/css-styling)

= How many quotes can I add? =

There is no hard limit. The plugin handles up to 5,000 published quotes without any issues.

= Can I import existing quotes? =

Yes. Go to **Quotes → Import / Export** in the admin. Upload a JSON file containing an array of objects with the fields `text`, `author`, and `extra`. Duplicate quotes (same text) are automatically skipped.

= Does the plugin store HTML in quotes? =

No. All fields are stored and output as plain text only. Line breaks entered in the text field are preserved in the frontend output.

= What happens when the plugin is deleted? =

All plugin data is permanently removed: all quote posts, their meta fields, and the transient cache. Use **Quotes → Import / Export** to export your quotes before deleting the plugin.

= Is the plugin available in German? =

Yes. The plugin is fully translated into German (de_DE). If you installed it from wordpress.org, the translation is downloaded automatically by WordPress.

== Screenshots ==

1. Frontend output — the daily quote displayed on a page, styled with a custom theme.
2. Quote list in the WordPress admin — an overview of all your quotes.
3. Add or edit a quote — plain text fields for quote text, author, and an optional extra field.
4. Import / Export — bulk manage your quotes via JSON file upload and download.
5. Built-in help page — quick reference for shortcode, block, CSS classes, and the REST API endpoint.

== Source Code ==

The full source code, including all build tools and configuration, is publicly available at:
[github.com/dieterDG/citatly-daily-quote](https://github.com/dieterDG/citatly-daily-quote)

== Changelog ==

= 1.3.5 =
* Separators (dash and dot) are now wrapped in their own BEM elements (`.citatly__separator`, `.citatly__divider`) and can be hidden or replaced via CSS
* All meta elements (separator, author, divider, source) are now built dynamically by JavaScript — only elements with content are rendered

= 1.3.4 =
* Fix: Transient cache for quote IDs is now invalidated when a quote is trashed or deleted. Previously, deleting a quote could result in no quote being displayed until the cache expired on its own

= 1.3.3 =
* Block: Title and block name changed to English ("Daily Quote") for consistency with the plugin name on wordpress.org. The editor preview text remains translated

= 1.3.2 =
* Improved daily quote selection: a fallback mechanism now ensures that the same quote never appears on two consecutive days

= 1.3.1 =
* REST endpoint automatically registered as exception when Perfmatters restricts the REST API

= 1.3.0 =
* Added CLS-optimized skeleton loader with shimmer animation
* Dynamic min-height calculation prevents layout shift during quote loading
* New citatly.css for skeleton loader styling
* Responsive min-height adjustment for mobile and desktop viewports
* CSS custom properties for easy skeleton customization (--citatly-skeleton-base, --citatly-skeleton-shine)

= 1.2.0 =
* Gutenberg block support
* JSON import and export via admin submenu
* Admin help / documentation page
* Auto-generated post title from quote text
* Clean uninstall routine
* Full German translation (de_DE)

= 1.1.0 =
* Shortcode with optional class parameter
* REST API endpoint with HTTP caching headers
* Deterministic daily selection
* Custom post type with plain text meta fields
* Initial release

== Upgrade Notice ==

= 1.3.5 =
New CSS classes for separator elements. Existing custom CSS continues to work. No manual steps required.

= 1.3.4 =
Fixes a bug where deleting a quote could leave the frontend empty until the cache expired.

= 1.3.3 =
Block name updated to English. No manual steps required.

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