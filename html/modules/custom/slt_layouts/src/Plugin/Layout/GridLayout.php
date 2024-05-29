<?php

namespace Drupal\slt_layouts\Plugin\Layout;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutDefault;

/**
 * GridLayout class implementation.
 */
class GridLayout extends LayoutDefault {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->getPluginDefinition()->setRegions($this->generateRegions());
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'grid_size' => 1,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $regions) {
    $build = parent::build($regions);
    $build['#attributes'] = NestedArray::mergeDeep(
      $build['#attributes'] ?? [],
      $this->getPluginDefinition()->get('attributes') ?? []
    );
    return $build;
  }

  /**
   * Generate the list of regions based on the grid size.
   *
   * @return array
   *   List of regions with each region being an associative array with at
   *   least a label key.
   *
   * @see \Drupal\Core\Layout\LayoutDefinition::getRegions()
   */
  public function generateRegions() {
    $count = (int) $this->configuration['grid_size'];
    $regions = [];
    for ($i = 1; $i <= $count; $i++) {
      $regions['region-' . $i] = [
        'label' => $this->t('Region %i', ['%i' => $i]),
      ];
    }
    return $regions;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['grid_size'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Grid size'),
      '#description' => $this->t('Maximum number of elements in the grid'),
      '#default_value' => $this->configuration['grid_size'],
      '#element_validate' => ['\Drupal\Core\Render\Element\Number::validateNumber'],
      '#min' => 1,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['grid_size'] = $form_state->getValue('grid_size');
    $this->getPluginDefinition()->setRegions($this->generateRegions());
  }

}
