# Eläintehtaat example content

Default content for local development, imported with Drupal core's default
content system. Applying the recipe creates:

- the **About us / Tietoa meistä** page (`/about-us`, `/tietoa-meista`),
- **Species**, **Use** and **Licence** taxonomy terms in English and Finnish,
- the **main** menu link to the About page and the grouped **footer** menu.

The recipe only adds content; all configuration comes from `config/sync`.
Content that already exists (matched by UUID) is skipped, so the recipe is safe
to apply repeatedly.

## Apply

```sh
make example-content
# or
docker compose exec app drush recipe:apply /var/www/html/recipes/elaintehtaat_example_content
```

`make install` applies it automatically after `site:install`.

## Update the content

Edit the entities on a local site, then re-export them into `content/`:

```sh
docker compose exec app vendor/bin/dr content:export taxonomy_term --dir=/var/www/html/recipes/elaintehtaat_example_content/content
docker compose exec app vendor/bin/dr content:export menu_link_content --dir=/var/www/html/recipes/elaintehtaat_example_content/content
docker compose exec app vendor/bin/dr content:export node <nid> --dir=/var/www/html/recipes/elaintehtaat_example_content/content
```

After re-exporting the About page, keep `pathauto: 0` on both `path` items in
the node YAML. The exporter drops it, and without it Pathauto regenerates the
alias from the `[node:menu-link]` pattern during import, before the menu link
exists, so the page ends up with no alias at all.
