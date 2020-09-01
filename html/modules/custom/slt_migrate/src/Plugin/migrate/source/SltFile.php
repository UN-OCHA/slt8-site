<?php

namespace Drupal\slt_migrate\Plugin\migrate\source;

use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;

/**
 * Drupal 7 file entities source from database.
 *
 * @MigrateSource(
 *   id = "slt_file",
 *   source_provider = "file"
 * )
 */
class SltFile extends FieldableEntity {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('file_managed', 'fm')
      ->fields('fm')
      ->orderBy('fm.fid');

    if (isset($this->configuration['type'])) {
      $query->condition('fm.type', $this->configuration['type']);
    }

    // Skip the user pictures coming from the HID login via hybridauth.
    $query->leftJoin('file_usage', 'fu', 'fu.fid = fm.fid');
    $query->condition('fu.type', 'user', '<>');

    return $query->distinct();
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $fid = $row->getSourceProperty('fid');
    // Get Field API field values.
    foreach (array_keys($this->getFields('file', $row->getSourceProperty('type'))) as $field) {
      $row->setSourceProperty($field, $this->getFieldValues('file', $field, $fid));
    }
    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return [
      'fid' => $this->t('File ID'),
      'uid' => $this->t('The {users}.uid who added the file. If set to 0, this file was added by an anonymous user.'),
      'filename' => $this->t('File name'),
      'uri' => $this->t('The URI to access the file'),
      'filemime' => $this->t('File MIME Type'),
      'status' => $this->t('The published status of a file.'),
      'timestamp' => $this->t('The time that the file was added.'),
      'type' => $this->t('The type of this file.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'fid' => [
        'type' => 'integer',
        'alias' => 'fm',
      ],
    ];
  }

}
