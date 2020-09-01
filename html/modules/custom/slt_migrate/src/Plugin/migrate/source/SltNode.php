<?php

namespace Drupal\slt_migrate\Plugin\migrate\source;

use Drupal\node\Plugin\migrate\source\d7\Node;

/**
 * Drupal 7 nodes source from database.
 *
 * Extends the base d7_node migration plugin with a condition to determine
 * if a page is public or private.
 *
 * @MigrateSource(
 *   id = "slt_node",
 *   source_provider = "node"
 * )
 */
class SltNode extends Node {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();

    // Use the node_access realm to distinguish between private and public
    // pages. Pages accessible to anonymous users (with realm set to 'all') are
    // public pages.
    if (isset($this->configuration['node_type']) && $this->configuration['node_type'] === 'basic_page') {
      $operator = $this->configuration['bundle'] === 'private_page' ? '<>' : '=';
      $query->innerJoin('node_access', 'na', 'na.nid = n.nid');
      $query->condition('na.realm', 'all', $operator);
    }

    return $query->distinct();
  }

}
