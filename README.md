# Loop and Template CSS Recovery for Elementor

**English** | [Traditional Chinese](README.zh-TW.md)

Loop and Template CSS Recovery for Elementor is a **selective CSS recovery layer**
for Elementor Loop Items, Theme Builder templates, and other templates that render
without styles, with incomplete responsive rules, or with broken front-end CSS.

This independent third-party plugin is not affiliated with or endorsed by Elementor.

This repository solely publishes the **Loop and Template CSS Recovery for Elementor**
WordPress plugin. Local Elementor Pro reference files are used only during
development and are never committed, packaged, or distributed.

## Why This Plugin Exists

Elementor normally generates and enqueues CSS per document. In real-world edge
cases, a template may look correct in the editor but reach the front end without
its expected stylesheet or with only part of its responsive CSS.

Examples reported in Elementor's official issue tracker include:

- a Loop Item page requesting a missing `post-{id}.css` file and returning 404
  while `loop-{id}.css` loads correctly
  ([elementor/elementor#24959](https://github.com/elementor/elementor/issues/24959));
- responsive tablet/mobile rules not being generated after multiple templates
  are rendered with `get_builder_content_for_display()`
  ([elementor/elementor#20555](https://github.com/elementor/elementor/issues/20555));
- a missing generated post stylesheet not being recreated on page load
  ([elementor/elementor#7237](https://github.com/elementor/elementor/issues/7237));
- cached pages continuing to reference generated CSS files after those files
  have been removed, resulting in broken layouts
  ([elementor/elementor#33057](https://github.com/elementor/elementor/issues/33057)).

This plugin provides an explicit recovery path for those **specific affected
templates**. An administrator adds only the template IDs known to be unreliable.
The plugin asks Elementor for their generated CSS, combines that selected CSS
in a controlled order, writes it to a stable content-hashed file, and queues
the recovery bundle immediately before WordPress prints late front-end styles.

## Selective by Design

This is **not** a whole-site CSS combiner, a general minifier, or a replacement
for Elementor's normal asset pipeline.

- It does not scan and import every Elementor template.
- It does not require healthy templates to be added.
- The bundle contains only administrator-selected template IDs.
- Templates that already load correctly should remain outside the recovery list.
- Adding unnecessary templates can duplicate CSS and introduce avoidable cascade
  or page-weight costs.

Elementor's own asset guidance notes that each stylesheet increases page size
and recommends loading assets only when they are needed
([Elementor Scripts & Styles](https://developers.elementor.com/docs/scripts-styles/)).
That is why this plugin deliberately uses an allowlist instead of collecting all
template CSS.

## When to Add a Template

Add a template ID only after confirming one or more of these symptoms:

- the template is styled in the editor but unstyled or partially styled on the front end;
- DevTools shows a missing or 404 `loop-{id}.css` / `post-{id}.css` request;
- desktop CSS is present but responsive rules are missing;
- regenerating Elementor CSS or clearing caches fixes the template only temporarily;
- a dynamically inserted, nested, Loop, Archive, or Theme Builder template does
  not receive its expected document CSS.

## What the Plugin Does

1. Stores an ordered allowlist of affected Elementor template IDs.
2. Generates CSS only for that selected list.
3. Deduplicates IDs across administrator-defined groups.
4. Minifies and combines the selected CSS in the configured order.
5. Publishes a stable, content-hashed recovery bundle.
6. Preserves the last-known-good bundle if a rebuild fails.
7. Rebuilds after relevant Elementor saves, cache clearing, manual requests, or audits.
8. Falls back to the original Elementor CSS files if no valid bundle exists.
9. Queues the recovery bundle after Elementor styles discovered while rendering
   nested and Loop templates, preserving the intended CSS cascade order.

## WordPress Admin

After activation, open **Tools → Template CSS** to:

- Review bundle status, size, build time, template count, and content hash
- Manage the allowlisted problem-template groups and Elementor template IDs
- Change CSS concatenation order with move-up and move-down controls
- Save the list and rebuild immediately
- Restore the plugin's built-in defaults
- Manually rebuild or inspect the current bundle

## Installation

1. Download the latest ZIP from [GitHub Releases](https://github.com/ragsitemap-maker/Elementor-Loop-Template-CSS/releases).
2. If upgrading from version 1.0.x, deactivate and delete the old **Elementor Template CSS Bundle** plugin folder. Existing settings and generated bundles remain available.
3. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
4. Deactivate any previous Elementor CSS recovery snippet or plugin that this plugin replaces.
5. Upload and activate **Loop and Template CSS Recovery for Elementor**.
6. Open **Tools → Template CSS**.
7. Remove templates that do not need recovery and add only confirmed affected IDs.
8. Save the list and run one rebuild.

## Project Documentation

- [Traditional Chinese README](README.zh-TW.md)

## Development Validation

Run with the WordPress PHP Docker image:

```bash
php -l loop-and-template-css-recovery-for-elementor/loop-and-template-css-recovery-for-elementor.php
php -l loop-and-template-css-recovery-for-elementor/includes/class-elementor-template-css-bundle.php
php tests/plugin-smoke.php
```

## License

GPL-2.0-or-later
