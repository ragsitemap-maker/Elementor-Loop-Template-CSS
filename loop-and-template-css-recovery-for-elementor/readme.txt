=== Loop and Template CSS Recovery for Elementor ===
Contributors: site-team
Tags: elementor, loop, loop-builder, template-css, css
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

Recovers missing or broken front-end CSS for selected Elementor Loop Items, Theme Builder templates, and other templates.

== Description ==

Loop and Template CSS Recovery for Elementor is a selective CSS recovery layer. It is intended
for Elementor Loop Items, Theme Builder templates, and other templates that
look correct in the editor but render with missing, incomplete, or broken CSS
on the front end.

This independent third-party plugin is not affiliated with or endorsed by Elementor.

Add only template IDs that have a confirmed styling problem. The plugin reads
CSS for those selected templates, removes duplicate IDs, combines the CSS in
the configured order, and publishes one stable, content-hashed recovery bundle.

This plugin is not a whole-site CSS combiner, general minifier, or replacement
for Elementor's normal CSS generation. It does not scan or import every
Elementor template automatically. Healthy templates should remain outside the
configured list to avoid unnecessary CSS, duplicate rules, and cascade
conflicts.

Generated recovery CSS is stored in a persistent directory below Elementor
uploads. A last-known-good bundle remains available if a rebuild fails.

The status and manual rebuild screen is available at:

Tools > Template CSS

The same screen can add, remove, rename, and reorder template groups and edit
their Elementor template IDs without changing PHP code. Remove a template from
the list when it no longer needs recovery.

Known Elementor reports that informed this scope include missing Loop Item
stylesheets, missing generated post CSS files, missing responsive styles in
rendered templates, and cached pages referencing deleted generated CSS files:

* https://github.com/elementor/elementor/issues/24959
* https://github.com/elementor/elementor/issues/20555
* https://github.com/elementor/elementor/issues/7237
* https://github.com/elementor/elementor/issues/33057

== Installation ==

1. If upgrading from version 1.0.x, deactivate and delete the old Elementor Template CSS Bundle plugin folder. Existing settings and generated bundles remain available.
2. Deactivate any previous Elementor CSS recovery snippet or plugin that this plugin replaces.
3. Upload and activate Loop and Template CSS Recovery for Elementor.
4. Open Tools > Template CSS.
5. Add only the IDs of templates with a confirmed front-end CSS problem.
6. Save the list and trigger "Rebuild now" once.
7. Clear page/CDN cache if applicable.

== Frequently Asked Questions ==

= Should I add every Elementor template? =

No. Add only templates whose CSS is missing, incomplete, or broken on the front
end. Including healthy templates can add unnecessary or duplicate CSS.

= Does this replace Elementor's normal CSS generation? =

No. It adds a recovery bundle for explicitly selected templates. Elementor
continues to generate and load its normal styles.

= Does the plugin detect affected templates automatically? =

No. An administrator confirms the affected template and adds its ID to the
allowlist. This keeps the scope deliberate and prevents all template CSS from
being bundled by default.

= Does deactivation delete the generated CSS? =

No. Deactivation removes this plugin's scheduled events but preserves the
last-known-good bundle and manifest.

== Changelog ==

= 1.1.0 =

* Renames the plugin to Loop and Template CSS Recovery for Elementor.
* Changes the public package slug, plugin directory, main file, and text domain to match the new name while preserving existing internal settings and hooks.
* Clarifies recovery coverage for Elementor Loop Items, Theme Builder templates, and other administrator-selected templates.

= 1.0.1 =

* Queues the recovery bundle after late-discovered Elementor widget styles so template-authored rules keep their intended cascade order.
* Preserves Elementor-only admission and the existing manifest fallback behavior.

= 1.0.0 =

* Initial standalone release.
* Builds a selective recovery bundle from administrator-confirmed template IDs.
* Leaves healthy and unlisted templates outside the bundle.
* Includes group creation, deletion, renaming, ordering, and editable template IDs.
* Includes save-and-rebuild, manual rebuild, status, and restore-default controls.
