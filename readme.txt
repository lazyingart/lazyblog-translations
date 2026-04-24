=== LazyBlog Translations ===
Contributors: lazyingart
Tags: multilingual, translation, markdown, lazyblog
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.4.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress translation storage and language-switcher rendering for LazyBlog Markdown publishing workflows.

== Description ==

LazyBlog Translations stores post source language and per-language translations in WordPress post meta. It renders a lightweight floating language switcher and can request missing translations from a configured LazyBlog API service.

The plugin is deliberately decoupled from external AI vendors. The LazyBlog API performs translation, polishing, image handling, and Markdown synchronization. This plugin only stores, renders, and coordinates those results inside WordPress.

== Features ==

* Source-language metadata for each post.
* Maintained language list: Original, Simplified Chinese, Traditional Chinese, English, Japanese, Korean, Vietnamese, Arabic, French, Spanish, German, and Russian.
* On-demand missing-translation requests with duplicate-job protection.
* Signed frontend requests and server-side API token forwarding.
* MathJax rendering for Markdown-origin math content.
* Admin settings for LazyBlog API endpoint, token, model, and reasoning level.

== Installation ==

1. Upload the `lazyblog-translations` folder to `/wp-content/plugins/`.
2. Activate `LazyBlog Translations` from the WordPress Plugins screen.
3. Open Settings > LazyBlog Translations.
4. Configure the LazyBlog API endpoint and bearer token.
5. Save settings and purge caches if the site uses a page cache.

== Frequently Asked Questions ==

= Does this plugin translate posts by itself? =

No. It calls the configured LazyBlog API. The local LazyBlog service handles model calls, prompting, and Markdown workflow synchronization.

= Does this plugin replace TranslatePress, Polylang, or WPML? =

It is a narrower tool for LazyBlog-managed posts. It does not try to translate every string in a WordPress site.

= Where should secrets live? =

Use WordPress options for the plugin bearer token and local `.env` files for the LazyBlog API. Do not commit credentials to the plugin repository.

== Changelog ==

= 0.4.5 =
* Added LazyingArt LLC plugin metadata and fixed update identity with `Update URI`.
* Added a guarded self-migration endpoint for moving accidental installs from a conflicting plugin slug into the canonical plugin folder.

= 0.4.4 =
* Added configurable translation model and reasoning settings.
* Added live API support for on-demand translation generation.

