<?php

namespace Drupal\telephone_type\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'telephone_link' formatter.
 *
 * @FieldFormatter(
 *   id = "telephone_type_default",
 *   label = @Translation("Telephone number prefixed with type"),
 *   field_types = {
 *     "telephone_type"
 *   }
 * )
 */
class TelephoneTypeDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      // The text value has no text format assigned to it, so the user input
      // should equal the output, including newlines.
      $elements[$delta] = [
        '#type' => 'inline_template',
        '#template' => '{{ type ? type ~ ": " }}{{ number|nl2br }}',
        '#context' => [
          'type' => trim($item->type),
          'number' => trim($item->number),
        ],
      ];
    }

    return $elements;
  }

}
