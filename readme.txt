=== RebelBoost ===
Contributors: rebelmouse
Tags: performance, cache, optimization, speed, core-web-vitals
Requires at least: 5.6
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatic cache invalidation and optimization integration for the RebelBoost page optimizer.

== Description ==

RebelBoost is a high-performance page caching and optimization proxy. This plugin connects your WordPress site to RebelBoost, providing:

* **Automatic cache invalidation** — Cache is automatically purged when you publish, update, or delete posts, pages, and comments.
* **Surrogate key injection** — Adds metadata headers so RebelBoost can do granular CDN cache invalidation.
* **Category tagging** — Passes WordPress taxonomy information to RebelBoost for category-based cache management.
* **Admin bar integration** — Quick-access purge buttons in the WordPress admin bar.
* **Post editor meta box** — Purge individual page cache directly from the post editor.
* **WP-CLI support** — Manage cache from the command line.
* **WooCommerce compatible** — Automatically purges product and shop pages on changes.

== Installation ==

1. Upload the `rebelboost` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to Settings > RebelBoost and enter your RebelBoost host URL and API key.
4. Click "Test Connection" to verify the setup.

Your site must be registered with RebelBoost and DNS configured to route through the RebelBoost proxy. Contact your account manager for setup assistance.

== Frequently Asked Questions ==

= Do I need a RebelBoost account? =

Yes. This plugin requires an active RebelBoost account with your site registered in the system.

= Does this plugin change my site's DNS? =

No. DNS changes must be made manually by pointing your domain to the RebelBoost proxy. The plugin provides setup instructions on the settings page.

= What happens if RebelBoost is unreachable? =

The plugin degrades gracefully. Cache purge requests will fail silently without affecting your site's functionality.

== Changelog ==

= 0.6.0 =
* Initial release.
* Automatic cache invalidation on content changes.
* Surrogate-Key header injection.
* Category tagging via response headers.
* Admin bar and post editor purge controls.
* WP-CLI commands for cache management.
* WooCommerce integration.
