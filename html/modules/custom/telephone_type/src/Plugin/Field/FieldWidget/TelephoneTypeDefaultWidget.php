<?php

namespace Drupal\telephone_type\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'telephone_type_default' widget.
 *
 * @FieldWidget(
 *   id = "telephone_type_default",
 *   label = @Translation("Telephone number with type"),
 *   field_types = {
 *     "telephone_type"
 *   }
 * )
 */
class TelephoneTypeDefaultWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'placeholder_type' => '',
      'placeholder_number' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element['placeholder_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Telephone type placeholder'),
      '#default_value' => $this->getSetting('placeholder_type'),
      '#description' => $this->t('Text that will be shown inside the "telephone type" field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
    ];
    $element['placeholder_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Telephone number placeholder'),
      '#default_value' => $this->getSetting('placeholder_number'),
      '#description' => $this->t('Text that will be shown inside the "telephone number" field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $placeholder_type = $this->getSetting('placeholder_type');
    if (!empty($placeholder_type)) {
      $summary[] = $this->t('Telephone type placeholder: @placeholder_type', [
        '@placeholder_type' => $placeholder_type,
      ]);
    }

    $placeholder_number = $this->getSetting('placeholder_number');
    if (!empty($placeholder_number)) {
      $summary[] = $this->t('Telephone number placeholder: @placeholder_number', [
        '@placeholder_number' => $placeholder_number,
      ]);
    }

    if (empty($summary)) {
      $summary[] = $this->t('No placeholders');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Type'),
      '#default_value' => $items[$delta]->type ?? NULL,
      '#placeholder' => $this->getSetting('placeholder_type'),
    ];
    $element['number'] = [
      '#type' => 'tel',
      '#title' => $this->t('Number'),
      '#default_value' => $items[$delta]->number ?? NULL,
      '#placeholder' => $this->getSetting('placeholder_number'),
    ];
    return $element;
  }

}
