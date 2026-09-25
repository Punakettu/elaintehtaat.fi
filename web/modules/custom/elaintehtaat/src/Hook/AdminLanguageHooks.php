<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\user\UserInterface;

/**
 * Forces English as the account language for every user.
 */
final class AdminLanguageHooks {

  public const string LANGCODE = 'en';

  /**
   * Implements hook_ENTITY_TYPE_presave() for user entities.
   */
  #[Hook('user_presave')]
  public function userPresave(UserInterface $account): void {
    $account->set('preferred_langcode', self::LANGCODE);
    $account->set('preferred_admin_langcode', self::LANGCODE);
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for the user form.
   */
  #[Hook('form_user_form_alter')]
  public function formUserFormAlter(array &$form, FormStateInterface $form_state): void {
    if (isset($form['language'])) {
      $form['language']['#access'] = FALSE;
    }
  }

}
