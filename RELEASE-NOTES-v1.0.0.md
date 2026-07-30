# Elementor Template CSS Bundle v1.0.0

Initial standalone release of a selective CSS recovery layer for Elementor
templates whose styles are missing, incomplete, or broken on the front end.

Add only administrator-confirmed affected template IDs. This release is not a
whole-site CSS combiner and does not bundle every Elementor template by default.

## Features

- Manage recovery groups, names, order, and affected Elementor template IDs
- Combine CSS only from the configured allowlist into one content-hashed bundle
- Leave healthy and unlisted Elementor templates outside the recovery bundle
- Token-safe CSS minification
- Last-known-good manifest switching
- Stable front-end enqueue with original-CSS fallback
- Automatic rebuild after Elementor saves or CSS cache clearing
- Daily template fingerprint auditing
- Admin-bar bundle status
- **Tools → Template CSS** management page
- Manual rebuild and restore-default controls

## Published Files

- GitHub source: plugin source code, tests, and project documentation
- Release asset: `elementor-template-css-bundle-1.0.0.zip`

## Not Included

- Elementor or Elementor Pro
- `Ref. Plugin/`
- Previous plugins, obsolete ZIP files, or migration layers
- Website data, credentials, or environment configuration

## Validation

- PHP 8.4 syntax checks: passed
- Plugin boot: passed
- Configurable group normalization: passed
- Admin fields and ordering-control rendering: passed
- Repeated-boot protection: passed
- Rodest hook isolation: passed
