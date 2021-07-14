<?php

namespace Drupal\common_design_subtheme;

use Drupal\Component\Utility\Html;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Provides trusted callback to add components.
 *
 * @see common_design_subtheme_process_field().
 */
class CommonDesignSubthemeCallbacks implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['postRender'];
  }

  /**
   * Sets post_render callback.
   */
  public static function postRender($html, array $element) {
    $components = common_design_subtheme_get_components();
    if (empty($components)) {
      return $html;
    }

    $dom = Html::load($html);

    // Add the classes to the HTML tags for each component.
    foreach ($components as $tags) {
      foreach ($tags as $tag => $classes) {
        $nodes = $dom->getElementsByTagName($tag);
        foreach ($nodes as $node) {
          $existing = $node->getAttribute('class') ?? '';
          $classes = array_merge(preg_split("/\s+/", $existing), $classes);
          $node->setAttribute('class', trim(implode(' ', array_unique($classes))));
        }
      }
    }

    $html = Html::serialize($dom);
    return trim($html);
  }

}
