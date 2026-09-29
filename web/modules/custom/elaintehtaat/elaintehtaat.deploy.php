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
  return sprintf('Created %d view path aliases.', $created);
}
