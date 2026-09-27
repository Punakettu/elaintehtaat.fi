<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Entity;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\file\FileInterface;
use Drupal\media\Entity\Media;

/**
 * Base of the bundle classes of the media that albums hold.
 *
 * Images and videos carry the same credit fields: a caption, a date, an author
 * and a licence.
 *
 * @see \Drupal\elaintehtaat\Hook\BundleClassHooks
 */
abstract class AlbumMedia extends Media {

  use DateFieldTrait;

  /**
   * The media source field, if the media has one.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>|null
   *   The source field, or NULL when the media type has none.
   */
  public function getSourceField(): ?FieldItemListInterface {
    $source_field = $this->getSource()->getConfiguration()['source_field'] ?? NULL;
    if ($source_field === NULL || !$this->hasField($source_field)) {
      return NULL;
    }

    return $this->get($source_field);
  }

  /**
   * The item of the media source field, if the media has one.
   *
   * For an image the item carries the alt text and the dimensions along with
   * the file.
   */
  public function getSourceItem(): ?FieldItemInterface {
    return $this->getSourceField()?->first();
  }

  /**
   * The item of the generated thumbnail, if any.
   *
   * The item carries the alt text and the dimensions along with the file.
   */
  public function getThumbnailItem(): ?FieldItemInterface {
    return $this->get('thumbnail')->first();
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
   * Who made the media, or an empty string when we do not know.
   */
  public function getAuthor(): string {
    return $this->hasField('field_author') ? (string) ($this->get('field_author')->value ?? '') : '';
  }

  /**
   * The licence the media is published under, if any.
   */
  public function getLicence(): ?Licence {
    if (!$this->hasField('field_licence')) {
      return NULL;
    }
    $licence = $this->get('field_licence')->entity;

    return $licence instanceof Licence ? $licence : NULL;
  }

}
