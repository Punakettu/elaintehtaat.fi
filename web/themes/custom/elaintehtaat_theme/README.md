# Eläintehtaat theme

Front-end theme for elaintehtaat.fi, generated from Drupal core's
`starterkit_theme` (see the `generator` key in `elaintehtaat_theme.info.yml`).
It has no base theme; every template and stylesheet is owned by this theme.

Reference design: https://punakettu.github.io/elaintehtaat.fi/

## Structure

- `css/base/` – design tokens (`variables.css`) and element defaults.
- `css/layout/` – page shell and content/sidebar grid.
- `css/components/` – masthead, hero, footer, buttons, plus the Starterkit
  component styles for core markup (forms, pager, tabs, messages, ...).
- `templates/` – Twig overrides. `layout/page.html.twig` defines the masthead,
  hero, content, sidebar and footer structure.
- `src/Hook/` – hook implementations (OOP hooks with `#[Hook]` attributes).

There is no build step: plain CSS is attached through
`elaintehtaat_theme.libraries.yml`. Fonts are loaded from Google Fonts by the
`elaintehtaat_theme/fonts` library.

## Regions

header, primary_menu, secondary_menu, hero, breadcrumb, highlighted, help,
content, sidebar, footer_top, footer.
