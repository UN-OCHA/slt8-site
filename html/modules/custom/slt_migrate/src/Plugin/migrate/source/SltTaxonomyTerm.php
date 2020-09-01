<?php

namespace Drupal\slt_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Drupal 7 taxonomy terms source from database.
 *
 * @todo Support term_relation, term_synonym table if possible.
 *
 * @MigrateSource(
 *   id = "slt_taxonomy_term",
 *   source_provider = "taxonomy"
 * )
 */
class SltTaxonomyTerm extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('taxonomy_term_data', 'td')
      ->fields('td', [
        'tid',
        'vid',
        'name',
        'description',
        'weight',
        'format',
      ])
      // Skip the unused Active and Role taxonomy vocabularies.
      ->condition('vid', [6, 7], 'NOT IN')
      ->distinct();
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'tid' => $this->t('The term ID.'),
      'vid' => $this->t('Existing term VID'),
      'name' => $this->t('The name of the term.'),
      'description' => $this->t('The term description.'),
      'weight' => $this->t('Weight'),
      'parent' => $this->t("The Drupal term IDs of the term's parents."),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // Find parents for this term.
    $query = $this->select('taxonomy_term_hierarchy', 'th')
      ->fields('th', ['parent'])
      ->condition('tid', $row->getSourceProperty('tid'));
    $row->setSourceProperty('parent', $query->execute()->fetchCol());

    // Order the `year` terms by most recent first.
    if ($row->getSourceProperty('vid') == 2) {
      $weight = -intval($row->getSourceProperty('name'), 10);
      $row->setSourceProperty('weight', $weight);
    }
    // Order the `order` terms by lowest order first.
    elseif ($row->getSourceProperty('vid') == 10) {
      $weight = intval($row->getSourceProperty('name'), 10);
      $row->setSourceProperty('weight', $weight);
    }

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'tid' => [
        'type' => 'integer',
        'alias' => 'td',
      ],
    ];
  }

}
