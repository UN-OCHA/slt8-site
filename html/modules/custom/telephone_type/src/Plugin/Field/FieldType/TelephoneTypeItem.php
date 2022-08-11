<?php

namespace Drupal\telephone_type\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'telephone' field type with a "type" sub-field.
 *
 * @FieldType(
 *   id = "telephone_type",
 *   label = @Translation("Telephone number with type"),
 *   description = @Translation("A field to store a phone number with an optional type (ex: work cell phone)."),
 *   category = @Translation("Number"),
 *   default_widget = "telephone_type_default",
 *   default_formatter = "telephone_type_default"
 * )
 */
class TelephoneTypeItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'type' => [
          'type' => 'varchar',
          'length' => 256,
        ],
        'number' => [
          'type' => 'varchar',
          'length' => 256,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['type'] = DataDefinition::create('string')
      ->setLabel(t('Telephone type'))
      ->setRequired(TRUE);

    $properties['number'] = DataDefinition::create('string')
      ->setLabel(t('Telephone number'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('number')->getValue();
    return $value === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
    $constraints = parent::getConstraints();

    $max_length = 256;
    $constraints[] = $constraint_manager->create('ComplexData', [
      'type' => [
        'Length' => [
          'max' => $max_length,
          'maxMessage' => $this->t('%name: the telephone type may not be longer than @max characters.', [
            '%name' => $this->getFieldDefinition()->getLabel(),
            '@max' => $max_length,
          ]),
        ],
      ],
    ]);
    $constraints[] = $constraint_manager->create('ComplexData', [
      'number' => [
        'Length' => [
          'max' => $max_length,
          'maxMessage' => $this->t('%name: the telephone number may not be longer than @max characters.', [
            '%name' => $this->getFieldDefinition()->getLabel(),
            '@max' => $max_length,
          ]),
        ],
      ],
    ]);

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $values['type'] = substr(md5(rand()), 0, 7);
    $values['number'] = rand(pow(10, 8), pow(10, 9) - 1);
    return $values;
  }

}
