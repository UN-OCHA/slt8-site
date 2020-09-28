<?php

namespace Drupal\slt_migrate\Plugin\migrate\source;

use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;

/**
 * Drupal 7 menu source from database.
 *
 * @MigrateSource(
 *   id = "slt_menu",
 *   source_module = "menu",
 *   source_provider = "menu"
 * )
 */
class SltMenu extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this
      ->select('menu_custom', 'm')
      ->fields('m')
      ->condition('m.menu_name', [
        // SLT drupal 7, only has custom links in the main menu and the footer.
        'main-menu',
        'menu-footer',
      ], 'IN')
      ->distinct();
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'menu_name' => $this->t('The menu name. Primary key.'),
      'title' => $this->t('The human-readable name of the menu.'),
      'description' => $this->t('A description of the menu'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'menu_name' => [
        'type' => 'string',
        'alias' => 'm',
      ],
    ];
  }

}
