<?php

/**
 * @file
 * Reverts exported Canvas block components that only switched versions.
 *
 * Canvas stores block labels in the interface language active when it
 * regenerates components. Without Finnish translations, such as on a fresh CI
 * install, it re-saves canvas.component.block.* with English labels and a
 * different active_version. Both versions are already listed in
 * versioned_properties, so such an export is noise. A file whose new
 * active_version is not in the committed file is a real change and is kept.
 *
 * Run from the project root after drush config:export.
 */

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

require __DIR__ . '/../../vendor/autoload.php';

$modified = [];
exec('git diff --name-only --diff-filter=M HEAD -- "config/sync/canvas.component.block.*.yml"', $modified, $status);
if ($status !== 0) {
  fwrite(STDERR, "git diff failed\n");
  exit(1);
}

foreach ($modified as $path) {
  $committed = shell_exec('git show ' . escapeshellarg("HEAD:$path"));
  if (!is_string($committed)) {
    continue;
  }
  $old = Yaml::parse($committed);
  $new = Yaml::parseFile($path);
  $version = $new['active_version'] ?? NULL;
  // The committed active version is stored under the "active" key, older
  // versions under their hash.
  $isFlip = $version !== ($old['active_version'] ?? NULL)
    && array_key_exists($version, $old['versioned_properties'] ?? []);

  if ($isFlip) {
    file_put_contents($path, $committed);
    echo "Reverted version flip: $path\n";
  }
}
