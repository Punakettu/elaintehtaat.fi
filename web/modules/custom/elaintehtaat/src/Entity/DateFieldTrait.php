<?php

declare(strict_types=1);

namespace Drupal\elaintehtaat\Entity;

use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Reads the field_date of the entities that carry one.
 */
trait DateFieldTrait {

  /**
   * The name of the date field.
   */
  private const string DATE_FIELD = 'field_date';

  /**
   * The date the entity is dated to, if any.
   */
  public function getDate(): ?DrupalDateTime {
    if (!$this->hasField(self::DATE_FIELD)) {
      return NULL;
    }
    $date = $this->get(self::DATE_FIELD)->date;

    return $date instanceof DrupalDateTime ? $date : NULL;
  }

  /**
   * The year the entity is dated to, if any.
   */
  public function getYear(): ?string {
    return $this->getDate()?->format('Y');
  }

}
