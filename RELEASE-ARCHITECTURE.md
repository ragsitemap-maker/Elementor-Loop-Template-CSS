# Elementor Loop Template CSS Release Architecture

**English** | [繁體中文](RELEASE-ARCHITECTURE.zh-TW.md)

## 1. Purpose

This document defines the functional scope, runtime architecture, GitHub
repository structure, upload/update file scope, release process, and validation
standards for the **Elementor Loop Template CSS** plugin.

The plugin is displayed in WordPress as **Elementor Template CSS Bundle**.

## 2. Publication Scope

### 2.1 Included

- WordPress plugin bootstrap
- Elementor template CSS bundling core
- Editable template-group administration interface
- WordPress plugin `readme.txt`
- Automated smoke test
- GitHub project documentation
- Release architecture documentation
- Version-specific release notes
- Installable GitHub Release ZIP

### 2.2 Explicitly Excluded

- `Ref. Plugin/`
- Elementor or Elementor Pro source code
- Local reference plugins
- WordPress core
- Website databases, uploads, credentials, or environment configuration
- Obsolete development ZIP files

`Ref. Plugin/` exists only for consulting Elementor APIs, classes, and hooks
during development. It is not source code, a dependency, or a distribution
component of this plugin. The repository `.gitignore` must continue to exclude it.

## 3. Core Functionality

### 3.1 Template Group Management

Administrators can use **Tools → Template CSS** to:

- Add, remove, and rename groups
- Reorder groups
- Edit each group's Elementor template IDs
- Separate IDs with newlines, spaces, or commas
- View the number of valid IDs in each group
- Restore the plugin's built-in default list

Group and ID order defines CSS concatenation order. If the same ID appears in
multiple groups, only its first occurrence is retained. Individual groups may
be empty, but the complete configuration must contain at least one valid ID.

### 3.2 CSS Bundle Build

The plugin reads CSS for each configured Elementor template, safely minifies it,
and concatenates it into one bundle. The filename includes the first 16
characters of a SHA-256 content hash and is written below:

`wp-content/uploads/elementor/template-css-bundle/`

Filename format:

`elementor-template-css-{hash}.css`

### 3.3 Last-Known-Good Publication

A new bundle becomes active only after all of these steps succeed:

1. Read CSS from every configured template.
2. Concatenate and minify the CSS.
3. Write a temporary file.
4. Verify the written byte count.
5. Atomically rename it to the final content-hashed filename.
6. Switch the manifest to the new file.

If a build fails, the plugin does not overwrite the existing valid file.

### 3.4 Front-End Enqueue and Fallback

- Valid manifest: enqueue the single stable bundle.
- Invalid manifest or missing file: mark the response as uncacheable, queue a
  rebuild, and temporarily enqueue the original Elementor CSS.
- Visitor requests never build files directly; they schedule the rebuild.

### 3.5 Automatic Rebuild and Audit

Build triggers include:

- Saving a configured template in the Elementor editor
- Clearing Elementor's CSS cache
- Saving the admin template-group configuration
- A manual admin rebuild
- The plugin's custom rebuild action
- Daily template fingerprint auditing

Each request queues at most one rebuild. An option-based lock prevents concurrent
processes from writing the same bundle.

## 4. Runtime Architecture

```text
Admin template-group configuration
    ↓
Configuration normalization and ID deduplication
    ↓
Elementor template CSS loading
    ↓
Token-safe CSS minification
    ↓
Ordered group/template concatenation
    ↓
Temporary-file write and verification
    ↓
Content-hashed final file
    ↓
Manifest switch
    ↓
Single front-end bundle enqueue
```

## 5. GitHub Repository Structure

```text
Elementor-Loop-Template-CSS/
├─ .gitignore
├─ README.md
├─ README.zh-TW.md
├─ RELEASE-ARCHITECTURE.md
├─ RELEASE-ARCHITECTURE.zh-TW.md
├─ RELEASE-NOTES-v1.0.0.md
├─ RELEASE-NOTES-v1.0.0.zh-TW.md
├─ elementor-template-css-bundle/
│  ├─ elementor-template-css-bundle.php
│  ├─ readme.txt
│  └─ includes/
│     └─ class-elementor-template-css-bundle.php
└─ tests/
   └─ plugin-smoke.php
```

Installable ZIP files are not committed to Git history. They are published as
GitHub Release assets.

## 6. Files Uploaded and Updated

| File | Purpose | Update timing |
|---|---|---|
| `.gitignore` | Excludes reference plugins and ZIP files | When exclusion rules change |
| `README.md` | Primary English GitHub landing page | When features or usage change |
| `README.zh-TW.md` | Traditional Chinese landing page | Alongside English README updates |
| `RELEASE-ARCHITECTURE.md` | Primary release structure and process | When architecture or release scope changes |
| `RELEASE-ARCHITECTURE.zh-TW.md` | Traditional Chinese release architecture | Alongside English architecture updates |
| `RELEASE-NOTES-vX.Y.Z.md` | Primary English version notes | Every release |
| `RELEASE-NOTES-vX.Y.Z.zh-TW.md` | Traditional Chinese version notes | Every release |
| `elementor-template-css-bundle.php` | Plugin header, version, and loader | Every version update |
| `includes/class-elementor-template-css-bundle.php` | Core behavior | When features or fixes change |
| `readme.txt` | WordPress plugin documentation and changelog | Every version update |
| `tests/plugin-smoke.php` | Basic loading and isolation validation | When hooks or behavior change |
| Release ZIP | Installable WordPress package | Every release |

## 7. Release Process

1. Update plugin behavior and tests.
2. Synchronize the plugin header `Version`, version constant, `readme.txt`
   Stable tag, and changelog.
3. Update English documentation first, then synchronize Traditional Chinese
   documentation.
4. Run PHP syntax checks.
5. Run the smoke test.
6. Build a ZIP containing only `elementor-template-css-bundle/`.
7. Verify the ZIP root, path separators, and required files.
8. Commit and push the source and documentation.
9. Create the `vX.Y.Z` tag and GitHub Release.
10. Upload the ZIP as a Release asset.

## 8. Release Validation Standard

Every release must verify:

- No PHP syntax errors in the bootstrap or core class
- Successful plugin boot
- Registered group-settings endpoint
- Readable built-in defaults
- Correct normalization of order, empty groups, and duplicate IDs
- Required admin fields and ordering controls are rendered
- Repeated boot does not register duplicate hooks
- Independent code such as the Rodest `remove_action()` snippet still runs
- The ZIP contains only the plugin directory and its three distribution files
- `Ref. Plugin/` is absent from commits, tags, ZIP files, and releases
- English documentation is primary and Traditional Chinese documentation is synchronized

## 9. Rollback Strategy

- GitHub retains version tags and Release ZIP files.
- A previous ZIP can be reinstalled if a release fails.
- A failed bundle build uses the last-known-good file or original Elementor CSS fallback.
- Deactivation removes scheduled hooks but does not delete generated CSS.

## 10. v1.0.0 Scope

- New standalone Elementor Template CSS Bundle plugin
- New option, hook, class, bundle directory, and filename namespace
- Editable Elementor template-group configuration
- Stable content-hashed bundle
- Admin-bar status and WordPress administration page
- Automated audit, scheduled rebuild, and fallback
- No legacy migration or compatibility layer
