# Image styles

Large images are rendered as a **responsive image**: one `<img>` with a
`srcset` of several sizes. The browser downloads the smallest file that is
still sharp on the viewer's screen. Small, fixed-size images use a plain image
style.

Every style ends with the core *Convert to AVIF* effect (`image_convert_avif`).
Derivatives are AVIF, and WebP is used only if the server cannot encode AVIF.

## Which style to use

| Where | View mode / code | Style |
| --- | --- | --- |
| Album page grid, recent media (featured), browse results | media `tile` display | responsive `media_masonry` |
| Album cards (album teaser) | media `card` display | responsive `media_tile` |
| Project card mosaic, first (large) cover | `ProjectSummaryHooks::MODES['card']` | responsive `media_tile` |
| Project card mosaic, the smaller covers | `ProjectSummaryHooks::MODES['card']` | `large` |
| Project rows on the front page (40×30 covers) | `ProjectSummaryHooks::MODES['teaser']` | `thumbnail` |
| Media page stage image | `MediaPageController::STAGE_STYLE` | responsive `media_stage` |
| Media page album thumbnail (48×48) | `MediaPageController::THUMBNAIL_STYLE` | `thumbnail` |
| Media default display, admin media views, media library | media `default` / `media_library` | `large`, `thumbnail`, `medium`, `media_library` |
| Canvas image components | Canvas | `canvas_parametrized_width` (Canvas builds its own `srcset`) |

Rules of thumb:

- **An image wider than about 200 CSS px, or one whose width follows the viewport, gets a responsive
  style.** In a display, use the *Responsive image* formatter. In code, use
  `'#theme' => 'responsive_image'` with `#responsive_image_style_id`. Pass the source `#width` and
  `#height` so the `<img>` gets real dimensions and doesn't shift the layout. Put `alt` and `title` in
  `#attributes`, because the responsive image theme reads them from there.
- **Small fixed-size images use a plain image style** (`'#theme' => 'image_style'`). A `srcset`
  gains nothing when the box is always 40 px.
- A new layout that shows images at about the same widths as the media tiles can reuse
  `media_tile` (3:2 crop) or `media_masonry` (uncropped). If the widths are clearly different, add a new responsive style rather than
  stretching the `sizes` of an existing one.

## Responsive styles

Both styles use the `sizes` mapping on core's *Viewport Sizing* breakpoint, so they print a plain
`<img srcset sizes>`, never a `<picture>`. Theme CSS such as `.project-card__mosaic > img` relies on
that. The breakpoint **group** is `responsive_image`, and the breakpoint **id** is
`responsive_image.viewport_sizing`. If the group is set to the id instead, no source matches and
Drupal silently prints an empty `<picture>` with only the fallback image.

### `media_masonry`: masonry tiles, scaled to width, never cropped

| Image style | Width | Note |
| --- | --- | --- |
| `masonry_480` | 480 | 1× desktop |
| `masonry_720` | 720 | 1× tablet |
| `masonry_900` | 900 | 2× desktop; fallback `src` |
| `masonry_1200` | 1200 | phones at 3× |

`sizes`: `(max-width: 600px) 100vw, (max-width: 1080px) 50vw, min(33vw, 440px)`

The album media, featured and browse listings are CSS `columns` masonry (`album-media.css`,
`featured.css`, `browse.css`), so each tile takes the height of its image (`media-tile.css`).
Landscape and portrait images both show in full. The column widths are the same as the `media_tile`
grids, so the `sizes` value is the same too. The styles scale by width only, without upscaling, so
the `<img>` dimensions follow the source's aspect ratio and lazy-loaded tiles don't shift the
columns.

### `media_tile`: cards, 3:2 crop

| Image style | Size | Note |
| --- | --- | --- |
| `tile_480` | 480×320 | 1× desktop |
| `tile_720` | 720×480 | 1× tablet |
| `card` | 900×600 | 2× desktop; fallback `src` |
| `tile_1200` | 1200×800 | phones at 3× |

`sizes`: `(max-width: 600px) 100vw, (max-width: 1080px) 50vw, min(33vw, 440px)`

The album cards and project mosaics are one column up to 600px, two up to 1080px, and three or four
above that, which puts an image at roughly 250–440 CSS px (`album-card.css`, `project-card.css`). The `sizes` value caps the tile at 440px rather than tracking each grid exactly,
so 4-column layouts overestimate slightly. That's a deliberate simplification.

`card` keeps its old name because derivatives already generated for it stay in use.

### `media_stage`: media page, scaled to fit a 10:7 box, never upscaled

| Image style | Box | Note |
| --- | --- | --- |
| `stage_800` | 800×560 | phones at 2× |
| `stage_1200` | 1200×840 | fallback `src` |
| `stage_1600` | 1600×1120 | |
| `media_page` | 2000×1400 | |
| `stage_2400` | 2400×1680 | 2× desktops, where the stage is 1100 CSS px wide |

`sizes`: `(max-width: 720px) 100vw, (max-width: 900px) calc(100vw - 320px), min(1100px, calc(100vw - 392px))`

This follows `components/media-page/media-page.css`: the frame is at most 1100px wide next to a
392px panel. The panel is 320px up to 900px, and below 720px it stacks under the image. Styles scale
without upscaling, so an original smaller than a box keeps its own size, and `srcset` reports the
real width (e.g. `2073w`). Portrait images are limited by the viewport height, so the width-based
`sizes` overestimates for them a little.

## Other image styles

| Style | Size | Used by |
| --- | --- | --- |
| `thumbnail` | 100×100 scale | Small covers and thumbnails, admin media view |
| `medium` | 220×220 scale | The `media_library` media display |
| `large` | 480×480 scale | Smaller project mosaic covers, media default display |
| `wide` | 1090 wide scale | Core default; not used by the theme |
| `media_library` | 220×220 scale | Media library widget |
| `canvas_avatar`, `canvas_parametrized_width` | | Provided by and used by Canvas |

## Changing styles

- Changing an image style's effects regenerates its derivatives on the next request. Every
  responsive style adds 4–5 derivatives per image, which affects storage and makes the
  first view of a new image slow.
