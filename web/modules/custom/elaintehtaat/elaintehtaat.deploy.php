<?php

/**
 * @file
 * Deploy hooks for the Eläintehtaat module.
 */

declare(strict_types=1);

/**
 * Translate the browse and projects page paths to English and Swedish.
 */
function elaintehtaat_deploy_translate_view_paths(): string {
  // The view paths are Finnish; the other languages get an alias of their own.
  $aliases = [
    '/selaa' => ['en' => '/browse', 'sv' => '/bladdra'],
    '/projektit' => ['en' => '/projects', 'sv' => '/projekt'],
  ];

  $created = _elaintehtaat_create_path_aliases($aliases);
  return sprintf('Created %d view path aliases.', $created);
}

/**
 * Translate the albums page path and move "About us" after its menu link.
 */
function elaintehtaat_deploy_albums_page(): string {
  $created = _elaintehtaat_create_path_aliases([
    '/albumit' => ['en' => '/albums', 'sv' => '/album'],
  ]);

  $storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
  // Projects, albums, then "About us": the albums link, which the view
  // provides, has weight 0, which "About us" had too.
  foreach ($storage->loadByProperties(['uuid' => '67202b1d-287f-4c1f-8d6d-aca5ae089c3f']) as $about) {
    $about->set('weight', 1)->save();
  }

  return sprintf('Created %d albums page path aliases.', $created);
}

/**
 * Delete the hand-made browse menu link that the browse view now provides.
 */
function elaintehtaat_deploy_browse_menu_link(): string {
  $storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
  $ids = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('menu_name', 'main')
    ->condition('link.uri', [
      'internal:/selaa',
      'internal:/browse',
      'internal:/bladdra',
      'route:view.browse.page_1',
    ], 'IN')
    ->execute();
  $storage->delete($storage->loadMultiple($ids));

  return sprintf('Deleted %d browse menu links.', count($ids));
}

/**
 * Creates the path aliases that do not exist yet.
 *
 * @param array<string, array<string, string>> $aliases
 *   The aliases, keyed by system path and then by language code.
 *
 * @return int
 *   The number of aliases created.
 */
function _elaintehtaat_create_path_aliases(array $aliases): int {
  $storage = \Drupal::entityTypeManager()->getStorage('path_alias');
  $created = 0;
  foreach ($aliases as $path => $translations) {
    foreach ($translations as $langcode => $alias) {
      if ($storage->loadByProperties(['path' => $path, 'langcode' => $langcode])) {
        continue;
      }
      $storage->create([
        'path' => $path,
        'alias' => $alias,
        'langcode' => $langcode,
      ])->save();
      $created++;
    }
  }
  return $created;
}
