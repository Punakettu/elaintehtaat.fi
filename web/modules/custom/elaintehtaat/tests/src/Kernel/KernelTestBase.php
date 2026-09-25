<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\KernelTests\KernelTestBase as CoreKernelTestBase;

/**
 * Base class for kernel tests.
 */
abstract class KernelTestBase extends CoreKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path_alias',
    'token',
    'pathauto',
    // A dependency of the module: it provides the album data processor and the
    // index the album hooks mark items as changed on.
    'search_api',
    'elaintehtaat',
  ];

}
