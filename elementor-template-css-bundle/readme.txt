=== Elementor Template CSS Bundle ===
Contributors: site-team
Tags: elementor, elementor-pro, css, performance
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Builds selected Elementor template CSS into one stable, content-hashed bundle.

== Description ==

This is a standalone Elementor template CSS bundle manager. It uses its own
settings, scheduled hooks, generated file names, and storage directory.

Generated CSS is stored in a persistent directory below Elementor uploads.

The status and manual rebuild screen is available at:

Tools > Template CSS

The same screen can add, remove, rename, and reorder template groups, and edit
their Elementor template IDs without changing PHP code.

== Installation ==

1. Deactivate the former stable Elementor CSS WPCode snippet or plugin.
2. Upload and activate this plugin.
3. Keep unrelated snippets, including the Rodest snippet, enabled.
4. Open Tools > Template CSS and trigger "Rebuild now" once.
5. Clear page/CDN cache if applicable.

== Frequently Asked Questions ==

= Does deactivation delete the generated CSS? =

No. Deactivation removes this plugin's scheduled events but preserves the
last-known-good bundle and manifest.

= Does this plugin change theme hooks? =

No. It does not add or remove Rodest theme callbacks. A separate snippet such
as remove_action( 'rodest_action_before_page_inner', ... ) remains independent.

== Changelog ==

= 1.0.0 =

* Initial standalone release.
* Builds a stable, content-hashed bundle from configurable Elementor templates.
* Includes group creation, deletion, renaming, ordering, and editable template IDs.
* Includes save-and-rebuild, manual rebuild, status, and restore-default controls.
