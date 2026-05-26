=== Citatly - Daily Quote ===
Contributors: dieter93
Tags: quote, quotes, quote of the day, shortcode, daily quote
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 1.3.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display a new quote every day — delivered cache-safely via the WordPress REST API.

== Description ==

Citatly (Daily Quote) adds a dedicated custom post type for quotes and automatically displays one of them each day on your website.

The displayed quote changes once per day and is selected deterministically based on the current date — no randomness involved, so every visitor sees the same quote on the same day. Delivery is cache-safe via a REST API endpoint that sends appropriate HTTP caching headers, making it fully compatible with full-page caches and CDNs.

**Live demo & documentation:** See the plugin in action at https://citatly.com

**Für deutschsprachige Nutzer:**

Das Plugin „Zitat des Tages" ist vollständig auf Deutsch übersetzt (de_DE). Es zeigt täglich ein neues Zitat aus deiner eigenen Sammlung — cache-sicher, ohne externe Abhängigkeiten und ohne API-Schlüssel. Live-Demo & Dokumentation auf Deutsch: https://citatly.com/de/

**Features:**

* Custom post type "Quotes" in the WordPress admin
* Fields for quote text, author, and an optional extra field (e.g. source, year, or context)
* Daily quote rotation — consistent for all visitors
* Embed anywhere with the `[citatly]` shortcode
* Optional `class` parameter for individual styling: `[citatly class="my-style"]`
* Gutenberg block support (requires compiled `/build` directory)
* REST API endpoint `/wp-json/citatly/v1/today` with HTTP caching headers
* Plain text only — no HTML stored or output, XSS-safe by design
* Auto-generated post title from quote text
* Import and export quotes as JSON (via admin menu)
* Clean uninstall — removes all plugin data when deleted
* Translation-ready (`.pot` file included)

**How the daily quote is selected:**

The plugin uses a deterministic algorithm: `crc32(date + site_url)` maps today's date to a fixed quote from your collection. A fallback mechanism ensures that the same quote never appears on two consecutive days. This means the quote is stable throughout the day, works correctly even with full-page caching, and does not require any session or cookie.

**CSS structure:**

The plugin does not style the quote output — all visual styling is left to your theme. The only included CSS handles the skeleton loader during page load.

`.citatly` — outer wrapper
`.citatly__text` — the quote text
`.citatly__meta` — wraps author and source
`.citatly__separator` — dash before author (default: "— ")
`.citatly__author` — author name
`.citatly__divider` — dot between author and source (default: " · ")
`.citatly__source` — optional extra field

== Installation ==

1. Upload the `citatly-daily-quote` folder to `/wp-content/plugins/`.
2. Activate the plugin via the "Plugins" menu in the WordPress admin.
3. Go to the new "Quotes" menu item and add one or more quotes.
4. Insert the shortcode `[citatly]` on any page, post, or widget area.

The shortcode accepts an optional `class` parameter to add a custom CSS class:

`[citatly class="my-style"]`

All styling of the output (`.citatly`, `.citatly__text`, `.citatly__separator`, `.citatly__author`, `.citatly__divider`, `.citatly__source`) is handled entirely by your theme.

== Frequently Asked Questions ==

= Does the quote change for every page load? =

No. The quote is selected once per day based on the current date. All visitors see the same quote throughout the day, regardless of caching.

= Why might the current quote change? =

The quote of the day is selected based on the date and the total number of published quotes. Adding, deleting, or unpublishing a quote may cause today's displayed quote to change. From the next day on, everything works as normal again.

= Is the plugin compatible with caching plugins and CDNs? =

Yes. The REST endpoint returns proper `Cache-Control` and `Expires` headers that expire at midnight. It works correctly with WP Rocket, W3 Total Cache, LiteSpeed Cache, Cloudflare, and similar solutions.

= How many quotes can I add? =

There is no hard limit. The plugin loads up to 5,000 published quote IDs into a transient cache (refreshed daily), which is sufficient for any practical use case.

= Can I import existing quotes? =

Yes. Go to "Quotes → Import / Export" in the admin. Upload a JSON file containing an array of objects with the fields `text`, `author`, and `extra`. Duplicate quotes (matching text) are automatically skipped.

= Does the plugin store HTML in quotes? =

No. All fields (text, author, extra) are stored and output as plain text only. This prevents XSS issues and keeps quotes portable. Line breaks entered in the text field are preserved in the frontend output.

= Can I style the output? =

Yes. The plugin outputs a simple HTML structure with BEM-style CSS classes. Separators between author and source (dash and dot) have their own classes and can be hidden or replaced via CSS. Add your own styles in your theme's stylesheet or via the WordPress Customizer. Interactive examples are available at https://citatly.com/docs/css-styling

= Does the plugin work with the block editor? =

Yes, if the `/build` directory with the compiled block files is present. The shortcode works independently of the block and is always available.

= What happens when the plugin is deleted? =

All plugin data is permanently removed: all quote posts, their meta fields, and the transient cache. Use the export function (Quotes → Import / Export) before deleting the plugin if you want to keep your quotes.

= Is the plugin available in German? =

Yes. The plugin is fully translated into German (de_DE). The text domain is `citatly-daily-quote`.

= Does the plugin work when the REST API is disabled? =

The plugin loads the daily quote via a REST API request in the visitor's browser. If the REST API is restricted or disabled for unauthenticated visitors, the quote will not be displayed.

Most performance and security plugins that restrict the REST API also provide a way to whitelist specific endpoints. Add `citatly/v1/today` as an exception to restore functionality. The exact method depends on the plugin or server configuration used — please refer to its documentation for details.

Note: If you are using Perfmatters, the plugin registers the exception automatically — no manual configuration needed.

= Does the plugin work with LiteSpeed Cache? =

Yes. However, if the REST API cache is enabled, the quote may not change daily as expected. To fix this, set "Default REST TTL" to 0 under LiteSpeed Cache → Cache settings. A fix for automatic compatibility is included in an upcoming LiteSpeed Cache release.

== Screenshots ==

1. The quote output on the frontend — styled with a custom theme.
2. The quote list in the WordPress admin.
3. Adding or editing a quote — plain text fields for text, author, and extra.
4. Import / Export page for bulk management of quotes via JSON.
5. The help / documentation page inside the admin.

== Source Code ==

The compiled file `build/index.js` is generated from the human-readable source files in the `src/` directory using `@wordpress/scripts`.

The full source code, including all build tools and configuration, is publicly available at:
https://github.com/dieterDG/citatly-daily-quote

To regenerate the build files:

1. Clone the repository
2. Run `npm install`
3. Run `npm run build`

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
