<?php

/**
 * @file
 * Contains \DrupalProject\composer\ScriptHandler.
 */

namespace DrupalProject\composer;

use Composer\Script\Event;
use Composer\Semver\Comparator;
use DrupalFinder\DrupalFinder;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;

class ScriptHandler {

  public static function createRequiredFiles(Event $event) {
    $fs = new Filesystem();
    $drupalFinder = new DrupalFinder();
    $drupalFinder->locateRoot(getcwd());
    $drupalRoot = $drupalFinder->getDrupalRoot();

    $dirs = [
      'modules',
      'profiles',
      'themes',
    ];

    // Required for unit testing
    foreach ($dirs as $dir) {
      if (!$fs->exists($drupalRoot . '/'. $dir)) {
        $fs->mkdir($drupalRoot . '/'. $dir);
        $fs->touch($drupalRoot . '/'. $dir . '/.gitkeep');
      }
    }

    // Create the files directory with chmod 0777
    if (!$fs->exists($drupalRoot . '/sites/default/files')) {
      $oldmask = umask(0);
      $fs->mkdir($drupalRoot . '/sites/default/files', 0777);
      umask($oldmask);
      $event->getIO()->write("Create a sites/default/files directory with chmod 0777");
    }
  }

  /**
   * Remove unnecessary files added by symfony/flex.
   *
   * @param \Composer\Script\Event $event
   *   Composer script event.
   */
  public static function removeUnnecessaryFiles(Event $event) {
    $fs = new Filesystem();
    $root = getcwd();

    $files = [
      '.env',
      '.env.test',
      'config/bootstrap.php',
      'config/packages/',
      'config/routes.yaml',
      'config/routes/',
      'phpcs.xml.dist',
      'phpunit.xml.dist',
      'tests/bootstrap.php',
      'translations/.gitignore',
    ];

    // Remove the files/directories.
    foreach ($files as $file) {
      if ($fs->exists($root . '/'. $file)) {
        try {
          $fs->remove($file);
          $event->getIO()->write("Removed '$file'");
        }
        catch (IOException $exception) {
          $event->getIO()->write("Unable to remove '$file'");
        }
      }
    }
  }

}
