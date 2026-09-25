<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Entity;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\file\FileInterface;
use Drupal\media\Entity\Media;

/**
 * Bundle class for image media.
 *
 * @see \Drupal\elaintehtaat\Hook\BundleClassHooks
 */
class Image extends Media {

  use DateFieldTrait;

  /**
   * The media type this class is the bundle class of.
   */
  public const string BUNDLE = 'image';

  /**
   * The item of the media source field, if the media has one.
   *
   * The item carries the alt text and the dimensions along with the file.
   */
  public function getSourceItem(): ?FieldItemInterface {
    $source_field = $this->getSource()->getConfiguration()['source_field'] ?? NULL;
    if ($source_field === NULL || !$this->hasField($source_field)) {
      return NULL;
    }

    return $this->get($source_field)->first();
  }

  /**
   * The file behind the media source field, if any.
   */
  public function getSourceFile(): ?FileInterface {
    $file = $this->getSourceItem()?->get('entity')->getValue();

    return $file instanceof FileInterface ? $file : NULL;
  }

  /**
   * The generated thumbnail file, if any.
   */
  public function getThumbnailFile(): ?FileInterface {
    $file = $this->get('thumbnail')->entity;

    return $file instanceof FileInterface ? $file : NULL;
  }

  /**
   * The caption field, if it is filled in.
   *
   * The field is returned rather than its text so that callers render it
   * through the field formatters.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>|null
   *   The caption field, or NULL when there is no caption.
   */
  public function getCaption(): ?FieldItemListInterface {
    if (!$this->hasField('field_caption') || $this->get('field_caption')->isEmpty()) {
      return NULL;
    }

    return $this->get('field_caption');
  }

  /**
   * Who made the image, or an empty string when we do not know.
   */
  public function getAuthor(): string {
    return $this->hasField('field_author') ? (string) ($this->get('field_author')->value ?? '') : '';
  }

  /**
   * The licence the image is published under, if any.
   */
  public function getLicence(): ?Licence {
    if (!$this->hasField('field_licence')) {
      return NULL;
    }
    $licence = $this->get('field_licence')->entity;

    return $licence instanceof Licence ? $licence : NULL;
  }

}
