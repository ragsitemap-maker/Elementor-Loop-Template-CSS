# Elementor Template CSS Bundle

**English** | [Traditional Chinese](README.zh-TW.md)

Elementor Template CSS Bundle prebuilds CSS from configurable Elementor templates
into a single, content-hashed, stable bundle. It also provides an editable
WordPress admin interface, build status, scheduled audits, and manual rebuilds.

This repository is solely for publishing the **Elementor Loop Template CSS**
WordPress plugin. Local Elementor Pro reference files are used only during
development and are never committed, packaged, or distributed.

## Features

- Add, remove, rename, and reorder template groups
- Edit Elementor template IDs using lines, spaces, or commas
- Automatically remove duplicate IDs across groups
- Save the configuration and rebuild CSS immediately
- Combine multiple Elementor Loop/Template CSS files into one bundle
- Generate content-hashed filenames for browser and CDN caching
- Preserve a last-known-good bundle
- Run scheduled audits, manual rebuilds, and original-CSS fallback
- Remain isolated from unrelated WPCode and theme-hook snippets

## WordPress Admin

After activation, open **Tools → Template CSS** to:

- Review bundle status, size, build time, template count, and content hash
- Manage group names and Elementor template IDs
- Change CSS concatenation order with move-up and move-down controls
- Save the list and rebuild immediately
- Restore the plugin's built-in defaults
- Manually rebuild or inspect the current bundle

## Installation

1. Download the latest ZIP from [GitHub Releases](https://github.com/ragsitemap-maker/Elementor-Loop-Template-CSS/releases).
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Deactivate any previous stable Elementor CSS WPCode snippet or plugin.
4. Upload and activate **Elementor Template CSS Bundle**.
5. Open **Tools → Template CSS**, review the list, and run one rebuild.

## Project Documentation

- [v1.0.0 Release Notes](RELEASE-NOTES-v1.0.0.md)
- [Traditional Chinese README](README.zh-TW.md)

## Development Validation

Run with the WordPress PHP Docker image:

```bash
php -l elementor-template-css-bundle/elementor-template-css-bundle.php
php -l elementor-template-css-bundle/includes/class-elementor-template-css-bundle.php
php tests/plugin-smoke.php
```

## License

GPL-2.0-or-later
