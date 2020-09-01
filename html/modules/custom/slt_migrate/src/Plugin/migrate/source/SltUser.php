<?php

namespace Drupal\slt_migrate\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Extract users from Drupal 7 database.
 *
 * @MigrateSource(
 *   id = "slt_user",
 *   source_module = "slt_migrate"
 * )
 */
class SltUser extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    return $this->select('users', 'u')
      ->fields('u', [
        'uid',
        'name',
        'pass',
        'mail',
        'signature',
        'signature_format',
        'created',
        'access',
        'login',
        'status',
        'timezone',
        'language',
        'picture',
        'init',
      ])
      // Skip the anonymous and admin users.
      ->condition('u.uid', 1, '>');
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      // Base `users` table fields.
      'uid' => $this->t('User ID'),
      'name' => $this->t('Username'),
      'pass' => $this->t('Password'),
      'mail' => $this->t('Email address'),
      'signature' => $this->t('Signature'),
      'signature_format' => $this->t('Signature format'),
      'created' => $this->t('Registered timestamp'),
      'access' => $this->t('Last access timestamp'),
      'login' => $this->t('Last login timestamp'),
      'status' => $this->t('Status'),
      'timezone' => $this->t('Timezone'),
      'language' => $this->t('Language'),
      'picture' => $this->t('Picture'),
      'init' => $this->t('Init'),
      // List of user roles.
      'roles' => $this->t('Roles'),
      // Custom fields.
      'first_name' => $this->t('First Name'),
      'last_name' => $this->t('Last Name'),
      'title' => $this->t('Title'),
      'member_state' => $this->t('SLT Member State you represent'),
      'duty_station' => $this->t('Current Duty Station'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $uid = $row->getSourceProperty('uid');

    // Roles.
    $query = $this->select('users_roles', 'ur')
      ->fields('ur', ['rid'])
      ->condition('ur.uid', $uid);
    $row->setSourceProperty('roles', $query->execute()->fetchCol());

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'uid' => [
        'type' => 'integer',
        'alias' => 'u',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function bundleMigrationRequired() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function entityTypeId() {
    return 'user';
  }

}
