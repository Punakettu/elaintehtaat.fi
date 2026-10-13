<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\MediaInterface;

/**
 * Links each item in the media library widget to its edit form.
 *
 * The link opens in a new tab so that unsaved changes on the referencing
 * form, such as an album, are kept. The admin theme already styles the
 * media-library-item__edit class as a pencil button next to the remove button.
 */
final class MediaLibraryEditHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_field_widget_single_element_WIDGET_TYPE_form_alter().
   */
  #[Hook('field_widget_single_element_media_library_widget_form_alter')]
  public function mediaLibraryWidgetFormAlter(array &$element, FormStateInterface $form_state, array $context): void {
    foreach ($context['items']->referencedEntities() as $delta => $media) {
      if (!$media instanceof MediaInterface || !isset($element['selection'][$delta])) {
        continue;
      }
      $access = $media->access('update', return_as_object: TRUE);
      $element['selection'][$delta]['edit_link'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit @label', ['@label' => $media->label()]),
        '#url' => $media->toUrl('edit-form'),
        '#access' => $access->isAllowed(),
        '#attributes' => [
          'class' => ['media-library-item__edit'],
          'target' => '_blank',
        ],
      ];
    }
  }

}
