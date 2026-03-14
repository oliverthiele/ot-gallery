# OT Gallery — TYPO3 Gallery Extension

A gallery extension for TYPO3 v13 built around one core idea: **image sizes are calculated mathematically from your Bootstrap grid configuration**, and all variants are pre-generated via CLI — so the first page load after a cache clear is just as fast as every subsequent one.

[![TYPO3](https://img.shields.io/badge/TYPO3-13.4-orange.svg)](https://typo3.org/)
[![Packagist Version](https://img.shields.io/packagist/v/oliverthiele/ot-gallery.svg)](https://packagist.org/packages/oliverthiele/ot-gallery)
[![PHP](https://img.shields.io/packagist/dependency-v/oliverthiele/ot-gallery/php.svg)](https://php.net/)
[![License](https://img.shields.io/packagist/l/oliverthiele/ot-gallery.svg)](LICENSE)
[![Changelog](https://img.shields.io/badge/Changelog-CHANGELOG.md-blue.svg)](CHANGELOG.md)

---

## Why another gallery extension?

Most TYPO3 gallery extensions generate processed images on the first frontend request — which means a slow, blocking page load whenever the TYPO3 cache is cleared. They also require you to manually specify image widths that match your grid, or they produce srcset values that don't match the actual rendered size.

OT Gallery takes a different approach:

- **Sizes are derived from your grid, not guessed.** You configure your Bootstrap 5 container widths, gutters and column counts once in the SiteSet. The extension calculates exact pixel widths for every breakpoint — including HiDPI variants — and generates a mathematically correct `sizes` attribute.

- **A CLI command pre-generates every variant before deployment.** `gallery:process` uses the same internal processing pipeline as the frontend renderer, so the browser's first request hits the file system cache directly. No cold-start penalty, no server spike on cache clear.

- **Zero TypoScript.** The entire configuration lives in TYPO3 v13 SiteSets. No setup.typoscript, no constants, no conditions.

- **Minimal JavaScript.** The gallery itself requires only Bootstrap 5 (which you likely already have) and optionally Fancybox 5 for the lightbox. No custom gallery framework, no jQuery.

- **Server-side pagination** means the gallery works correctly with hundreds of images and remains SEO-friendly — no JavaScript rendering required for content that search engines need to index.

---

## Features

- **Responsive images** — `<img srcset>` with WebP format, calculated from container widths, gutter and column configuration
- **Lightbox** — Fancybox, enable/disable per content element via checkbox; caption fields (title/description/copyright) configurable via SiteSet
- **Two image sources** — FAL file selection or folder (including recursive subfolders, multiple folders)
- **Three layout modes** — Grid, Masonry (CSS columns, no CLS), Justified (planned)
- **Aspect ratio + rendering** — Free, 1:1, 4:3, 3:2, 16:9 with cover/contain/fill per content element
- **Server-side pagination** — Cached, SEO-friendly URLs via TYPO3 Route Enhancer
- **FAL metadata** — Figcaption (title/description/copyright, optional `visually-hidden`) and three-dot context menu (title/description/copyright) — each field toggled independently per content element
- **Per-image download** — Optional download button in three-dot context menu
- **CLI pre-processing** — Pre-generate all image variants to avoid server load on cache clear; uses `ImageService` to guarantee identical cache keys with the frontend
- **SiteSet configuration** — All defaults configurable via TYPO3 v13 SiteSets (no TypoScript)
- **Bootstrap 5** — Grid, pagination, dropdown components
- **Accessible** — ARIA labels, keyboard navigation, `visually-hidden` screen reader text

---

## Requirements

| Requirement | Version |
|---|---|
| TYPO3 | 13.4+ |
| PHP | 8.3+ |
| Bootstrap | 5.x |
| Fancybox | 5.x (if lightbox enabled) |

---

## Installation

```bash
composer require oliverthiele/ot-gallery
```

Then run the TYPO3 setup:

```bash
vendor/bin/typo3 extension:setup -e ot_gallery
# or via DDEV:
ddev typo3 extension:setup -e ot_gallery
```

---

## Configuration

### 1. Add SiteSet

Include the SiteSet in your site configuration (`config/sites/yoursite/config.yaml`):

```yaml
dependencies:
  - oliverthiele/ot-gallery
```

### 2. Add Routing

Include the pagination route enhancer to get SEO-friendly pagination URLs (`?tx_otgallery_page=2` → `/page-2`):

```yaml
imports:
  - { resource: 'EXT:ot_gallery/Configuration/Routes/Default.yaml' }
```

### 3. Add SCSS

Copy or import the example stylesheet into your project build:

```scss
@use 'path/to/EXT:ot_gallery/Resources/Private/Scss/Example' as gallery;
```

Or copy `Resources/Private/Scss/Example.scss` into your project and adapt as needed.

### 4. Initialize Bootstrap JavaScript

The three-dot context menu (description, copyright, download) uses Bootstrap Dropdown. Ensure Bootstrap JS is initialized on your page:

```js
// If not using bootstrap.bundle.js (which auto-initializes):
document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(el => {
    new bootstrap.Dropdown(el);
});
```

### 5. Include Fancybox

If you use the lightbox feature, include Fancybox 5 in your project:

```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5/dist/fancybox/fancybox.css">
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5/dist/fancybox/fancybox.umd.js"></script>
```

Then initialize:

```js
Fancybox.bind('[data-fancybox]');
```

---

## SiteSet Settings

All settings can be configured in your site's SiteSet or overridden per content element via FlexForm.

### Grid & Breakpoints

| Setting | Default | Description |
|---|---|---|
| `otGallery.grid.columns.xs` | `1` | Columns on xs screens |
| `otGallery.grid.columns.sm` | `2` | Columns on sm screens |
| `otGallery.grid.columns.md` | `3` | Columns on md screens |
| `otGallery.grid.columns.lg` | `4` | Columns on lg screens |
| `otGallery.grid.columns.xl` | `5` | Columns on xl screens |
| `otGallery.grid.columns.xxl` | `6` | Columns on xxl screens |
| `otGallery.grid.gutter` | `24` | Gutter width in px |
| `otGallery.grid.container.padding` | `12` | Container padding in px (one side) |
| `otGallery.grid.container.sm.maxWidth` | `540` | Container max-width at sm in px |
| `otGallery.grid.container.md.maxWidth` | `720` | Container max-width at md in px |
| `otGallery.grid.container.lg.maxWidth` | `960` | Container max-width at lg in px |
| `otGallery.grid.container.xl.maxWidth` | `1140` | Container max-width at xl in px |
| `otGallery.grid.container.xxl.maxWidth` | `1320` | Container max-width at xxl in px |

### Thumbnail Defaults

| Setting | Default | Description |
|---|---|---|
| `otGallery.thumbnail.aspectRatio` | `free` | Default aspect ratio (`free`, `1:1`, `4:3`, `3:2`, `16:9`) |
| `otGallery.thumbnail.rendering` | `cover` | Object-fit mode (`cover`, `contain`, `fill`) |

### Lightbox

| Setting | Default | Description |
|---|---|---|
| `otGallery.lightbox.captionFields` | `title,description,copyright` | Comma-separated fields shown in lightbox caption |
| `otGallery.lightbox.dataAttribute` | `data-fancybox` | HTML data attribute used to trigger the lightbox |

### Pagination

| Setting | Default | Description |
|---|---|---|
| `otGallery.pagination.itemsPerPage` | `48` | Images per page (0 = unlimited) |

### Processing (CLI)

| Setting | Default | Description |
|---|---|---|
| `otGallery.processing.hiDpi` | `true` | Generate 2x variants for retina displays |
| `otGallery.processing.webp.quality` | `82` | WebP quality (1–100) |
| `otGallery.processing.jpeg.quality` | `85` | JPEG quality (1–100) |

---

## FlexForm Options (per content element)

### Layout tab
- **Layout** — Grid / Masonry
- **Aspect ratio** — Free / 1:1 / 4:3 / 3:2 / 16:9
- **Rendering** — Cover / Contain / Fill (visible only when aspect ratio is not free)
- **Column override** — Override default columns per breakpoint

### Sort tab
- **Sort field** — By filename / date / custom (FAL sort order)
- **Sort direction** — Ascending / Descending
- **Items per page** — Override SiteSet default (0 = use SiteSet default)

### Features tab
- **Enable lightbox** — Checkbox; activates Fancybox for all images in this element
- **Figcaption group** — Show title / description / copyright below each image; optionally `visually-hidden` (screen readers only)
- **Menu group** — Show title / description / copyright in the three-dot context menu per image
- **Enable download** — Per-image download button in the three-dot context menu

---

## CLI: Image Pre-Processing

Pre-process all gallery images to avoid server load when caches are cleared:

```bash
# Process all galleries
vendor/bin/typo3 gallery:process

# Process a specific content element
vendor/bin/typo3 gallery:process --content-uid=42

# Only process galleries where configuration has changed
vendor/bin/typo3 gallery:process --unprocessed-only

# Dry run (show what would be processed)
vendor/bin/typo3 gallery:process --dry-run

# Verbose output (show widths and column info per breakpoint)
vendor/bin/typo3 gallery:process -v
```

The command uses a configuration hash (`MD5`) stored per content element to detect when image dimensions need to be recalculated (e.g. after changing column counts or SiteSet grid settings).

The CLI uses `ImageService::applyProcessingInstructions()` — identical to the frontend ViewHelpers — so the pre-generated files produce exact cache hits on the first page load. No images are re-processed by the frontend.

---

## Template Customization

The extension follows TYPO3's template override convention. Override paths in your TypoScript:

```typoscript
tt_content.ot_gallery {
    templateRootPaths.20 = EXT:your_sitepackage/Resources/Private/Templates/
    partialRootPaths.20 = EXT:your_sitepackage/Resources/Private/Partials/
    layoutRootPaths.20 = EXT:your_sitepackage/Resources/Private/Layouts/
}
```

### Available template variables

| Variable | Type | Description |
|---|---|---|
| `{gallery.images}` | `FileInterface[]` | Images on current page |
| `{gallery.allImages}` | `FileInterface[]` | All images (all pages) |
| `{gallery.totalCount}` | `int` | Total image count |
| `{gallery.paginator}` | `ArrayPaginator` | TYPO3 paginator object |
| `{gallery.pagination}` | `SimplePagination` | TYPO3 pagination object |
| `{gallery.sizesAttribute}` | `string` | Calculated `sizes` attribute string |
| `{gallery.imageWidths}` | `array` | Calculated widths per breakpoint (incl. `@2x` keys) |
| `{gallery.rowColsClasses}` | `string` | Bootstrap `row-cols-*` class string |
| `{gallery.aspectRatioCss}` | `string` | CSS-formatted ratio (`4/3`) or empty string |
| `{gallery.effectiveLayout}` | `string` | Active layout name (`grid`, `masonry`, …) |
| `{gallery.flex}` | `array` | All FlexForm settings |
| `{gallery.lightboxCaptionFields}` | `array` | Map of `title/description/copyright => bool` for lightbox captions |
| `{gallery.source}` | `string` | `files` or `folder` |

### ViewHelpers

| ViewHelper | Description |
|---|---|
| `ot:gallerySrcset` | Generates `srcset` attribute string for all unique widths |
| `ot:galleryImageSrc` | Returns processed image URL for a single width (use for `src` fallback) |
| `ot:lightboxCaption` | Assembles lightbox caption string from image metadata fields |

---

## SiteKit Integration (optional)

If your project uses [OT SiteKit Base](https://github.com/oliverthiele/ot-sitekit-base), the extension ships with a `Configuration/SiteKit.yaml` that registers `ot_gallery` with the SiteKit grid system:

```yaml
elements:
  - ctype: ot_gallery
    groups: [group_content_wide]
    grid: { minCols: 6, requiresFullWidth: false }
```

This tells SiteKit that the gallery requires at least 6 grid columns and does not need to span the full width. Without SiteKit installed, this file is simply ignored.

---

## Planned Features

- **Fancybox across pagination pages** — Show all images in lightbox slideshow regardless of current page
- **Subfolder galleries** — Subfolders as nested gallery entries with cover image
- **Folder/image sidecar files** — `gallery.json` per folder, `image.jpg.json` per image for metadata without EXIF
- **ZIP download** — Pre-generated ZIP via CLI, served by middleware
- **EXIF display** — Camera, aperture, shutter speed, ISO, date in metadata overlay
- **Search / Filter** — Server-side via middleware or Extbase plugin (client-side not feasible with server-side pagination)

---

## License

GPL-2.0-or-later — see [LICENSE](LICENSE)

## Author

Oliver Thiele — [oliver-thiele.de](https://www.oliver-thiele.de)