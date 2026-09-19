<?php

declare(strict_types=1);

namespace Drupal\Tests\elaintehtaat\Kernel;

use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that users always get English as their account language.
 */
#[Group('elaintehtaat')]
#[RunTestsInSeparateProcesses]
class AdminLanguageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['language']);
  }

  /**
   * Tests that both language preferences are forced to English on save.
   */
  public function testLanguagesAreForcedToEnglish(): void {
    $account = User::create([
      'name' => 'editor',
      'preferred_langcode' => 'fi',
      'preferred_admin_langcode' => 'fi',
    ]);
    $account->save();
    $this->assertSame('en', $account->getPreferredLangcode(FALSE));
    $this->assertSame('en', $account->getPreferredAdminLangcode(FALSE));

    $account->set('preferred_langcode', NULL);
    $account->set('preferred_admin_langcode', NULL);
    $account->save();
    $this->assertSame('en', $account->getPreferredLangcode(FALSE));
    $this->assertSame('en', $account->getPreferredAdminLangcode(FALSE));
  }

}
