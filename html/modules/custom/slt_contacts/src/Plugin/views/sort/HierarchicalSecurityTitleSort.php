<?php

namespace Drupal\slt_contacts\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\SortPluginBase;

/**
 * Sort contacts by security title hierarchically.
 *
 * @ViewsSort("hierarchical_security_title_sort")
 */
class HierarchicalSecurityTitleSort extends SortPluginBase {

  /**
   * Called to add the sort to a query.
   */
  public function query() {
    $this->ensureMyTable();

    $field = $this->tableAlias . '.' . $this->realField;

    // The security title are sometimes followed by an abbreviation in
    // parentheses (ex: (DSA)) so we do a prefix comparison instead of equality.
    $formula = "
      CASE
        WHEN $field LIKE 'Chief Security Adviser%' THEN 4
        WHEN $field LIKE 'Deputy Security Adviser%' THEN 3
        WHEN $field LIKE 'Security Adviser%' THEN 2
        WHEN $field LIKE 'Field Security Coordination Officer%' THEN 1
        ELSE 0
      END
    ";

    $this->query->addOrderBy(
      NULL,
      $formula,
      $this->options['order'],
      'hierarchical_security_title_sort'
    );
  }

}
