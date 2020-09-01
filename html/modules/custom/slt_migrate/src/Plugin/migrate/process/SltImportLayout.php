<?php

namespace Drupal\slt_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\Yaml\Yaml;

/**
 * Import a node layout, returning a list of sections.
 *
 * This should be used in conjunction with the `sub_process` plugin as it
 * returns an array of layout sections.
 *
 * @MigrateProcessPlugin(
 *   id = "slt_import_layout"
 * )
 *
 * To use the plugin:
 *
 * @code
 * layout_builder__layout:
 *  -
 *     plugin: slt_import_layout
 *     source:
 *       - node_type
 *       - node_id
 *   -
 *     plugin: skip_on_empty
 *     method: process
 *   -
 *     plugin: sub_process
 *     process:
 *       section: section
 * @endcode
 *
 * The plugin itself returns a list of section in the form:
 *
 * @code
 * Array
 * (
 *    [0] => array(
 *      [section] => 'first section layout serialized string'
 *    ),
 *    [1] => array(
 *      [section] => 'second section layout serialized string'
 *    )
 * )
 * @endcode
 */
class SltImportLayout extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_array($value)) {
      throw new MigrateException(sprintf('%s is not an array', var_export($value, TRUE)));
    }

    // Validate the bundle.
    if (empty($value[0])) {
      throw new MigrateException('Missing node bundle');
    }
    $bundle = $value[0];
    if (!is_string($bundle)) {
      throw new MigrateException(sprintf('%s is not a valid bundle', var_export($bundle, TRUE)));
    }

    // Validate the node id.
    if (empty($value[1])) {
      throw new MigrateException('Missing node id');
    }
    $id = $value[1];
    if (!is_scalar($id)) {
      throw new MigrateException(sprintf('%s is not a valid id', var_export($id, TRUE)));
    }

    // Load any saved layout for the entity.
    $directory = drupal_get_path('module', 'slt_migrate') . '/data/layouts';
    $file = $directory . '/' . $bundle . '-' . $id . '.yml';
    if (file_exists($file)) {
      $layout = file_get_contents($file);
      // Parse the yaml content.
      if (!empty($layout)) {
        $layout = Yaml::parse($layout);
      }
      // Unserialize the layout sections.
      if (!empty($layout) && is_array($layout)) {
        foreach ($layout as $key => $section) {
          $layout[$key] = ['section' => \unserialize($section)];
        }
        return $layout;
      }
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function multiple() {
    return TRUE;
  }

}
