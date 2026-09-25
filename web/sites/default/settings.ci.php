<?php

/**
 * @file
 * Drupal settings for GitHub Actions, loaded when the CI variable is set.
 */

// Drupal chmods sites/default to 0555, which makes the workflow's git switch
// fail.
$settings['skip_permissions_hardening'] = TRUE;
// No S3 in CI.
$settings['s3fs.use_s3_for_public'] = FALSE;
// Skip localize.drupal.org so translation imports don't change the exported
// language/* config.
$config['locale.settings']['translation']['use_source'] = 'local';
