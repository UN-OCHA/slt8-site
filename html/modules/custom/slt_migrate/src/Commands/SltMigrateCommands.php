<?php

namespace Drupal\slt_migrate\Commands;

use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Config\ConfigManager;
use Drupal\Core\Database\Database;
use Drush\Commands\DrushCommands;
use Symfony\Component\Yaml\Yaml;

/**
 * SLT Drush commandfile.
 */
class SltMigrateCommands extends DrushCommands implements SiteAliasManagerAwareInterface {

  use SiteAliasManagerAwareTrait;

  /**
   * Config Installer.
   *
   * @var Drupal\Core\Config\ConfigInstallerInterface
   */
  protected $configInstaller;

  /**
   * Config Manager.
   *
   * @var Drupal\Core\Config\ConfigManager
   */
  protected $configManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigInstallerInterface $config_installer, ConfigManager $config_manager) {
    $this->configInstaller = $config_installer;
    $this->configManager = $config_manager;
  }

  /**
   * Reload SLT migration configurations.
   *
   * @command slt-migrate:reload-configuration
   * @aliases slt-mrc,slt-migrate-reload-configuration
   * @usage slt-migrate:reload-configuration
   *   Reload all SLT migration configurations.
   * @validate-module-enabled slt_migrate
   */
  public function reloadConfiguration() {
    // Uninstall and reinstall all configuration.
    $this->configManager->uninstall('module', 'slt_migrate');
    $this->configInstaller->installDefaultConfig('module', 'slt_migrate');

    // Rebuild cache.
    $process = $this->processManager()->drush($this->siteAliasManager()->getSelf(), 'cache-rebuild');
    $process->mustrun();

    $this->logger()->success(dt('Config reload complete.'));
    return TRUE;
  }

  /**
   * Export custom layouts so they can be added during the migration.
   *
   * This is to be used during development to export the individual page layouts
   * built with the Drupal 8 built-in layout builder module so that they can
   * be added during the migration of the Drupal 7 data.
   *
   * @command slt-migrate:export-layouts
   * @aliases slt-mel,slt-migrate-export-layouts
   * @usage slt-migrate:export-layouts
   *   Export the layout field to files.
   * @validate-module-enabled slt_migrate
   */
  public function exportLayouts() {
    $directory = drupal_get_path('module', 'slt_migrate') . '/data/layouts';

    // Empty the layouts directory.
    file_unmanaged_delete_recursive($directory);

    // Prepare the layouts directory.
    if (!file_prepare_directory($directory, FILE_CREATE_DIRECTORY)) {
      $this->logger()->error(dt('Unable to create the layouts directory.'));
      return FALSE;
    }

    // Get the layout data.
    $records = Database::getConnection()
      ->select('node__layout_builder__layout', 'l')
      ->fields('l')
      // Skip deleted layout sections.
      ->condition('l.deleted', 0)
      ->execute();

    // Group layout sections for each entity.
    $layouts = [];
    foreach ($records as $record) {
      $key = $record->bundle . '-' . $record->entity_id;
      // We simply store the the serialized data for the section. It will
      // be unserialized by the SltImportLayout process plugin.
      $layouts[$key][$record->delta] = $record->layout_builder__layout_section;
    }

    $total = count($layouts);
    $count = 0;
    foreach ($layouts as $key => $data) {
      // Sort the sections by delta.
      ksort($data);

      // Save as yaml and reset the keys as some deltas may have been skipped.
      $data = Yaml::dump(array_values($data));

      // Save the layouts.
      if (file_unmanaged_save_data($data, $directory . '/' . $key . '.yml')) {
        $count++;
      }
      else {
        $this->logger()->warning(dt('Unable to export @file.', [
          '@file' => $key . '.yml',
        ]));
      }
    }

    $this->logger()->success(dt('Successfully exported @count/@total layouts.', [
      '@count' => $count,
      '@total' => $total,
    ]));
    return TRUE;
  }

}
