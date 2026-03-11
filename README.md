# OT Gallery — TYPO3 Gallery Extension

A modern, accessible gallery extension for TYPO3 v13 with responsive images, lightbox support, server-side pagination, and optional image pre-processing via CLI.

[![TYPO3](https://img.shields.io/badge/TYPO3-13.4-orange.svg)](https://typo3.org/)
[![PHP](https://img.shields.io/badge/PHP-8.3+-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](LICENSE)

---

## Features

- **Responsive images** — `<img srcset>` with WebP format, calculated from container widths, gutter and column configuration
- **Lightbox** — Fancybox (default), configurable or disabled per content element
- **Two image sources** — FAL file selection or folder (including recursive subfolders, multiple folders)
- **Three layout modes** — Grid, Masonry (CSS columns, no CLS), Justified (planned)
- **Aspect ratio + rendering** — Free, 1:1, 4:3, 3:2, 16:9 with cover/contain/fill per content element
- **Server-side pagination** — Cached, SEO-friendly URLs via TYPO3 Route Enhancer
- **FAL metadata** — Title (figcaption), description, copyright via three-dot menu
- **Per-image download** — Optional download button in three-dot context menu
- **CLI pre-processing** — Pre-generate all image variants to avoid server load on cache clear
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

### Images

| Setting | Default | Description |
|---|---|---|
| `otGallery.thumbnail.aspectRatio` | `free` | Default aspect ratio (`free`, `1:1`, `4:3`, `3:2`, `16:9`) |
| `otGallery.thumbnail.rendering` | `cover` | Object-fit mode (`cover`, `contain`, `fill`) |

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
- **Rendering** — Cover / Contain / Fill
- **Column override** — Override default columns per breakpoint

### Sort tab
- **Sort field** — By filename / date / custom (FAL sort order)
- **Sort direction** — Ascending / Descending
- **Items per page** — Override SiteSet default (0 = use default)

### Features tab
- **Lightbox** — Fancybox / None / Custom
- **Show title** — Display title as figcaption
- **Show description** — Show in three-dot menu
- **Show copyright** — Show in three-dot menu
- **Enable download** — Per-image download in three-dot menu

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
```

The command uses a configuration hash (`MD5`) stored per content element to detect when image dimensions need to be recalculated (e.g. after changing column counts).

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
| `{gallery.imageWidths}` | `array` | Calculated widths per breakpoint |
| `{gallery.rowColsClasses}` | `string` | Bootstrap `row-cols-*` class string |
| `{gallery.aspectRatioCss}` | `string` | CSS-formatted ratio (`4/3`) or empty |
| `{gallery.flex}` | `array` | All FlexForm settings |
| `{gallery.source}` | `string` | `files` or `folder` |

---

## Planned Features

- **Fancybox across pagination pages** — Show all images in lightbox slideshow regardless of current page
- **Subfolder galleries** — Subfolders as nested gallery entries with cover image
- **Folder/image sidecar files** — `gallery.json` per folder, `image.jpg.json` per image for metadata without EXIF
- **ZIP download** — Pre-generated ZIP via CLI, served by middleware
- **EXIF display** — Camera, aperture, shutter speed, ISO, date in metadata overlay

---

## License

GPL-2.0-or-later — see [LICENSE](LICENSE)

## Author

Oliver Thiele — [oliver-thiele.de](https://www.oliver-thiele.de)