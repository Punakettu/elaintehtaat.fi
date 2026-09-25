<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Entity;

use Drupal\node\Entity\Node;

/**
 * Bundle class for project nodes.
 *
 * @see \Drupal\elaintehtaat\Hook\BundleClassHooks
 * @see \Drupal\elaintehtaat\Hook\ProjectSummaryHooks
 */
class Project extends Node {

  /**
   * The node type this class is the bundle class of.
   */
  public const string BUNDLE = 'project';

}
