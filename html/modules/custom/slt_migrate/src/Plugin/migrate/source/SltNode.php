<?php

namespace Drupal\slt_migrate\Plugin\migrate\source;

use Drupal\Component\Utility\Html;
use Drupal\Core\Database\Database;
use Drupal\migrate\Row;
use Drupal\node\Plugin\migrate\source\d7\Node;
use Drupal\slt_migrate\Helpers\HtmlSanitizer;

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

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    if ($this->configuration['node_type'] !== 'basic_page') {
      return parent::prepareRow($row);
    }

    if (!parent::prepareRow($row)) {
      return FALSE;
    }

    $paragraphs = [];

    // Create a paragraph for the hero image.
    $main_image = $row->getSourceProperty('field_basic_page_main_image');
    if (!empty($main_image[0]['fid'])) {
      // The media associated to the image as the same ID.
      $paragraphs[] = static::createImageParagraph($main_image[0]['fid']);
    }

    // Create a paragraph for the title.
    $paragraphs[] = static::createPageTitleParagraph();

    // Create a paragraph for the body.
    // @todo pass the body value through the HTML cleaner.
    // @todo extract grids.
    $body = $row->getSourceProperty('body');
    if (!empty($body[0]['value'])) {
      $text = $body[0]['value'];
      $paragraphs[] = static::createTextParagraph($text);

      // Convert any grid content to paragraphs.
      $paragraphs = array_merge($paragraphs, static::convertGridContent($text));
    }

    // Special case for the homepage: convert the custome menu links (nodequeue)
    // to a list of paragraphs in a 5 columns layout.
    if ($row->getSourceProperty('nid') == 943943) {
      $paragraphs = array_merge($paragraphs, static::convertNodequeue());
    }

    // Prepare the field_paragraphs with the IDs of the paragraphs so we can
    // do a direct simple mapping in the migration.
    $field_paragraphs = [];
    foreach ($paragraphs as $paragraph) {
      if (!empty($paragraph)) {
        $field_paragraphs[] = [
          'target_id' => $paragraph->id(),
          'target_revision_id' => $paragraph->getRevisionId(),
        ];
      }
    }

    // Store the paragraphs.
    $row->setSourceProperty('field_paragraphs', $field_paragraphs);

    return TRUE;
  }

  /**
   * Generate paragraphs replacing grid content in the html text.
   *
   * This assumes a flat structure like:
   *
   * <div class="col-sm-6 col-md-3">...</div>
   * <div class="col-sm-6 col-md-3">...</div>
   * <div class="col-sm-6 col-md-3">...</div>
   * <div class="col-sm-6 col-md-3">...</div>
   *
   * @param string $html
   *   HTML text.
   *
   * @return array
   *   List of paragraphs corresponding to the grid content.
   */
  public static function convertGridContent($html) {
    $dom = Html::load($html);
    $xpath = new \DomXPath($dom);
    $nodes = $xpath->query("//*[@class='col-sm-6 col-md-3']");
    $count = $nodes->count();

    if ($nodes === FALSE || $count === 0) {
      return [];
    }

    $layouts = [
      'layout_onecol',
      'layout_twocol_section',
      'layout_threecol_section',
      'layout_fourcol_section',
    ];

    $layout = 'layout_image_grid_four_columns';
    $config = ['grid_size' => $count];
    $regions = [];

    if ($count <= 4) {
      // Get a sample of the content to determine what layout to use.
      // If we don't have a list of links, we use a column based layout instead
      // of the grid.
      $child = static::getFirstChildElement($nodes->item(0));
      if ($child->tagName !== 'a') {
        $layout = $layouts[$count - 1];
        $config = [];
        $regions = ['first', 'second', 'third', 'fourth'];
      }
    }

    // Create the layout paragraph.
    $paragraphs = [static::createLayoutParagraph($layout, $config)];
    $parent_uuid = $paragraphs[0]->uuid();

    // Parse the grid elements and generate the appropriate paragraphs.
    $index = 1;
    foreach ($nodes as $node) {
      $child = static::getFirstChildElement($node);

      if (!isset($child)) {
        continue;
      }

      $behavior_settings = [
        'layout_paragraphs' => [
          'region' => $regions[$index - 1] ?? 'region-' . $index,
          'parent_uuid' => $parent_uuid,
          'layout' => '',
          'config' => [],
        ],
      ];

      if ($child->tagName === 'a') {
        $image_link = static::extractImageLink($child);
        if (empty($image_link)) {
          continue;
        }
        list($url, $title, $media_link) = $image_link;
        $paragraphs[] = static::createImageLinkParagraph($url, $title, $media_link, $behavior_settings + [
          'paragraphs_viewmode_behavior' => [
            'view_mode' => 'linked_image_medium',
          ],
        ]);
      }
      else {
        $text = static::getInnerHtml($node);
        $paragraphs[] = static::createTextParagraph($text, 2, $behavior_settings);
      }

      $index++;
    }

    return $paragraphs;
  }

  /**
   * Extra an image link paragraph's data from a node.
   *
   * @param \DOMElement $node
   *   The anchor dom element.
   *
   * @return array|null
   *   Array containing the link url, title and the media id.
   */
  public static function extractImageLink(\DOMElement $node) {
    $url = $node->getAttribute('href');
    $title = '';
    $media_id = '';

    // Extract the media id and link title from the first image.
    foreach ($node->getElementsByTagName('img') as $image) {
      $title = $image->getAttribute('alt');
      // The media as the same ID as its file due to the migration.
      $media_id = static::getFileIdFromUri(basename($image->getAttribute('src')));
      break;
    }

    return empty($media_id) ? NULL : [$url, $title, $media_id];
  }

  /**
   * Create a layout paragraph.
   *
   * @param string $layout
   *   The layout machine name. Default to the 4 columns layout.
   * @param array $config
   *   Lyout paragraph configuration (associative array).
   *
   * @return \Drupal\paragraphs\Entity\Paragraph
   *   The paragraph object.
   */
  public static function createLayoutParagraph($layout, array $config = []) {
    $behavior_settings = [
      'layout_paragraphs' => [
        'region' => '',
        'parent_uuid' => '',
        'layout' => $layout,
        'config' => $config + [
          'label' => '',
        ],
      ],
    ];
    return static::createParagraph([
      'type' => 'layout',
    ], $behavior_settings);
  }

  /**
   * Create an image paragraph.
   *
   * @param int $id
   *   ID of the media.
   * @param array $behavior_settings
   *   Behavior settings like the layout_paragraphs settings.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph
   *   The paragraph object.
   */
  public static function createImageParagraph($id, array $behavior_settings = []) {
    return static::createParagraph([
      'type' => 'image',
      'field_media_content_image' => [
        [
          'target_id' => $id,
        ],
      ],
    ], $behavior_settings);
  }

  /**
   * Create a page title paragraph.
   *
   * @param array $behavior_settings
   *   Behavior settings like the layout_paragraphs settings.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph
   *   The paragraph object.
   */
  public static function createPageTitleParagraph(array $behavior_settings = []) {
    return static::createParagraph([
      'type' => 'page_title',
    ], $behavior_settings);
  }

  /**
   * Create a text paragraph.
   *
   * @param int $text
   *   HTML text.
   * @param int $heading_offset
   *   Heading offset from H1: `1` means headings start at h2.
   * @param array $behavior_settings
   *   Behavior settings like the layout_paragraphs settings.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph
   *   The paragraph object.
   */
  public static function createTextParagraph($text, $heading_offset = 1, array $behavior_settings = []) {
    return static::createParagraph([
      'type' => 'text',
      'field_media_content_text' => [
        [
          'value' => HtmlSanitizer::sanitize($text, $heading_offset),
          'format' => 'filtered_html',
        ],
      ],
    ], $behavior_settings);
  }

  /**
   * Create an image link paragraph.
   *
   * @param string $url
   *   The link URL.
   * @param int $title
   *   The link title.
   * @param int $media_id
   *   ID of the media.
   * @param array $behavior_settings
   *   Behavior settings like the layout_paragraphs settings.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph
   *   The paragraph object.
   */
  public static function createImageLinkParagraph($url, $title, $media_id, array $behavior_settings = []) {
    return static::createParagraph([
      'type' => 'image_link',
      'field_media_content_link' => [
        [
          'uri' => $url,
          'title' => $title,
        ],
      ],
      'field_media_content_image' => [
        [
          'target_id' => $media_id,
        ],
      ],
    ], $behavior_settings);
  }

  /**
   * Create a paragraph.
   *
   * @param array $data
   *   The paragraph data. It must be an associative array with a least the
   *   "type" key with the paragraph type as value.
   * @param array $behavior_settings
   *   Behavior settings like the layout_paragraphs settings.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph
   *   The paragraph object.
   */
  public static function createParagraph(array $data, array $behavior_settings = []) {
    $paragraph = \Drupal::entityTypeManager()
      ->getStorage('paragraph')
      ->create($data);
    foreach ($behavior_settings as $plugin_id => $settings) {
      $paragraph->setBehaviorSettings($plugin_id, $settings);
    }
    $paragraph->save();
    return $paragraph;
  }

  /**
   * Get a file ID from a filename.
   *
   * We assume the file is private and not in a subdirectory.
   *
   * @param string $filename
   *   The file name.
   *
   * @return int|null
   *   The file ID or NULL if not found.
   */
  public static function getFileIdFromUri($filename) {
    /** @var \Drupal\file\FileInterface[] $files */
    $files = \Drupal::entityTypeManager()
      ->getStorage('file')
      ->loadByProperties(['uri' => 'private://' . $filename]);
    return !empty($files) ? reset($files)->id() : NULL;
  }

  /**
   * Get the first child element from a dom element.
   *
   * @param \DOMElement $node
   *   Parent element.
   *
   * @return \DOMElement|null
   *   The first child element or null if none was found.
   */
  public static function getFirstChildElement(\DOMElement $node) {
    $child = $node->firstChild;
    while ($child) {
      if ($child->nodeType === XML_ELEMENT_NODE) {
        return $child;
      }
      $child = $child->nextSibling;
    }
    return NULL;
  }

  /**
   * Get the inner HTML of a dom node as a string.
   *
   * @aram \DOMElement $node
   *   The DOM element.
   *
   * @return string
   *   The inner HTML.
   */
  public static function getInnerHtml(\DOMElement $node) {
    $html = '';
    foreach ($node->childNodes as $child) {
      $html .= $child->ownerDocument->saveHtml($child);
    }
    return trim($html);
  }

  /**
   * Convert nodequeue to paragraphs.
   *
   * @return array
   *   List of paragraphs representing the link grid.
   */
  public static function convertNodequeue() {
    $connection = Database::getConnection('default', 'slt7');
    $query = $connection->select('nodequeue_nodes', 'nn');
    $query->innerJoin('node', 'n', 'n.nid = nn.nid');
    $query->innerJoin('field_data_field_basic_page_icon', 'fi', 'fi.entity_id = nn.nid');
    $query->addExpression("CONCAT('entity:node/', n.nid)", 'url');
    $query->addField('n', 'title', 'title');
    // The media ID is the same as the file ID due to the migration.
    $query->addField('fi', 'field_basic_page_icon_fid', 'media_id');
    $query->orderBy('position', 'ASC');

    $result = $query->execute();
    if (empty($result)) {
      return [];
    }
    $records = $result->fetchAll();

    // Create the layout paragraph.
    $paragraphs = [
      static::createLayoutParagraph('layout_image_grid_five_columns', [
        'grid_size' => count($records),
      ]),
    ];

    $parent_uuid = $paragraphs[0]->uuid();

    // Create a paragraph for each node in the nodequeue.
    $index = 1;
    foreach ($records as $record) {
      $paragraphs[] = static::createImageLinkParagraph($record->url, $record->title, $record->media_id, [
        'layout_paragraphs' => [
          'region' => 'region-' . $index++,
          'parent_uuid' => $parent_uuid,
          'layout' => '',
          'config' => [],
        ],
        'paragraphs_viewmode_behavior' => [
          'view_mode' => 'link_with_background_image_small',
        ],
      ]);
    }

    return $paragraphs;
  }

}
