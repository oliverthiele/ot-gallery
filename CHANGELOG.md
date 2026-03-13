# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] — 2026-03-13

### Initial release

#### Pagination URLs
- SEO-friendly, cHash-free pagination URLs via TYPO3 Route Enhancer (`Simple` type)
- `StaticRangeMapper` aspect (pages 1–100) eliminates cHash requirement
- `LocaleModifier` aspect for locale-aware URL segment: `page` (default/en), `seite` (de), `pagina` (nl/es/it), `strona` (pl)
- Page 1 renders as clean base URL (no `/page-1` suffix); pages 2+ render as `/{slug}-{n}`

#### Image rendering
- Responsive `<img srcset sizes>` with WebP format
- Widths calculated from SiteSet container widths, gutters and column counts per breakpoint
- HiDPI / Retina support via `@2x` variants (configurable)
- Near-duplicate width deduplication (within 5%) to avoid redundant processed files
- Bootstrap 5 `row-cols-*` classes generated from SiteSet + optional per-content-element override
- Aspect ratio modes: free, 1:1, 4:3, 3:2, 16:9 with cover / contain / fill rendering
- CSS custom properties (`--ot-gallery-ratio`, `--ot-gallery-rendering`) for pure-CSS aspect ratio without layout shift

#### Image sources
- FAL file selection (`sys_file_reference`) — per-reference title, description, copyright from `sys_file_reference`
- Folder source — single or multiple folders, optional recursive subfolder inclusion
- Source toggle with `onChange: reload` in TYPO3 backend

#### Lightbox
- Fancybox 5 integration via `data-fancybox` grouping per content element
- Enable / disable per content element via checkbox
- Lightbox caption assembled from configurable fields (title, description, copyright) via SiteSet `otGallery.lightbox.captionFields`
- Custom `LightboxCaptionViewHelper` for flexible caption rendering

#### Metadata display
- **Figcaption**: title, description, copyright — each independently toggled per content element; optional Bootstrap `visually-hidden` for screen-reader-only captions
- **Three-dot context menu** (Bootstrap Dropdown): title, description, copyright — each independently toggled
- **Per-image download** button in context menu (optional, downloads original file)

#### Layout modes
- **Grid** — Bootstrap `row-cols-*` responsive grid
- **Masonry** — CSS `columns` based, no cumulative layout shift; activated via `data-variant="masonry"`

#### Sorting & pagination
- Sort by filename, date, or custom FAL order — ascending or descending
- Server-side pagination via TYPO3 `ArrayPaginator` + `SimplePagination`
- Items per page: SiteSet default, overridable per content element

#### CLI pre-processing (`gallery:process`)
- Pre-generates all required image variants before first page load
- Uses `ImageService::applyProcessingInstructions()` — identical to frontend ViewHelpers — guaranteeing exact `sys_file_processedfile` cache hits with no redundant re-processing on first frontend request
- FAL source: processes `FileReference` objects (not underlying `File`) so per-reference crop context is included in the cache key
- Options: `--content-uid`, `--unprocessed-only`, `--dry-run`, `-v` (verbose breakpoint info)
- Configuration hash (MD5) stored per content element to detect grid/column changes

#### SiteSet configuration (TYPO3 v13, no TypoScript required)
- Grid: columns per breakpoint (xs–xxl), gutter, container padding and max-widths
- Thumbnail defaults: aspect ratio, rendering mode
- Lightbox: caption fields, data attribute
- Pagination: items per page
- Processing: HiDPI on/off, WebP quality, JPEG quality

#### ViewHelpers
- `GallerySrcsetViewHelper` — generates `srcset` attribute string for all unique widths
- `GalleryImageSrcViewHelper` — generates single processed image URL for `src` fallback; uses identical `ImageService::applyProcessingInstructions()` call as `GallerySrcsetViewHelper` and CLI, guaranteeing exact `sys_file_processedfile` cache hits
- `LightboxCaptionViewHelper` — assembles lightbox caption from image metadata fields

#### Code quality
- Unit tests: 27 tests for `ImageSizeCalculatorService` via `typo3/testing-framework` (PHPUnit 11)
- PHPStan configuration included (`phpstan.neon.dist`)
- All classes `final` where not designed for inheritance
- Zero TYPO3 deprecated APIs (TYPO3 v13 conformant throughout)
