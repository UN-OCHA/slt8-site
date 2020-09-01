<?php

namespace Drupal\slt_migrate\Plugin\migrate\process;

use DOMDocument;
use DOMNode;
use DOMXPath;
use Drupal\Component\Utility\UrlHelper;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\slt_migrate\Helpers\Outliner;

/**
 * Clean HTML content.
 *
 * @MigrateProcessPlugin(
 *   id = "slt_clean_html"
 * )
 *
 * To use the plugin:
 *
 * @code
 * field_text:
 *   plugin: slt_clean_html
 *   source: text
 * @endcode
 */
class SltCleanHtml extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_string($value)) {
      throw new MigrateException(sprintf('%s is not a string', var_export($value, TRUE)));
    }

    // Skip if the html string is empty.
    $value = trim($value);
    if (empty($value)) {
      return '';
    }

    // Convert all '&nbsp;' to normal spaces.
    $value = str_replace('&nbsp;', ' ', $value);

    // Supported tags an whether they can be empty (no children) or not.
    $tags = [
      'html' => FALSE,
      'head' => FALSE,
      'meta' => TRUE,
      'body' => FALSE,
      'div' => FALSE,
      'article' => FALSE,
      'section' => FALSE,
      'header' => FALSE,
      'footer' => FALSE,
      'aside' => FALSE,
      'span' => FALSE,
      // No children.
      'br' => TRUE,
      'a' => FALSE,
      'em' => FALSE,
      'i' => FALSE,
      'strong' => FALSE,
      'b' => FALSE,
      'cite' => FALSE,
      'code' => FALSE,
      'strike' => FALSE,
      'ul' => FALSE,
      'ol' => FALSE,
      'li' => FALSE,
      'dl' => FALSE,
      'dt' => FALSE,
      'dd' => FALSE,
      'blockquote' => FALSE,
      'p' => FALSE,
      'pre' => FALSE,
      'h1' => FALSE,
      'h2' => FALSE,
      'h3' => FALSE,
      'h4' => FALSE,
      'h5' => FALSE,
      'h6' => FALSE,
      'table' => FALSE,
      'caption' => FALSE,
      'thead' => FALSE,
      'tbody' => FALSE,
      'th' => FALSE,
      'td' => FALSE,
      'tr' => FALSE,
      'sup' => FALSE,
      'sub' => FALSE,
      // No children.
      'img' => TRUE,
    ];

    $convert = [
      'i' => 'em',
      'b' => 'strong',
      'div' => 'p',
      'span' => '',
    ];

    $headings = [
      'h1' => TRUE,
      'h2' => TRUE,
      'h3' => TRUE,
      'h4' => TRUE,
      'h5' => TRUE,
      'h6' => TRUE,
    ];

    // Heading offset from H1: `1` means headings start at h2.
    $heading_offset = $this->configuration['heading_offset'] ?? 1;

    // Flags to load the HTML string.
    $flags = LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOERROR | LIBXML_NOWARNING;

    // Adding this meta tag is necessary to tell DOMDocument we are dealing
    // with UTF-8 encoded html.
    $meta = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    $prefix = '<!DOCTYPE html><html><head>' . $meta . '</head><body>';
    $suffix = '</body></html>';
    $dom = new DOMDocument();
    $dom->loadHTML($prefix . $value . $suffix, $flags);

    // Fix the heading hierarchy.
    Outliner::fixNodeHeadingHierarchy($dom, $heading_offset);

    // Parse all the dom nodes.
    foreach (static::getElementsByTagName($dom, '*') as $node) {
      // Skip orphan nodes (for example from manipulations below).
      if (empty($node) || empty($node->parentNode)) {
        continue;
      }

      $tag = $node->tagName;

      // Remove unrecognized/unallowed tags.
      if (!isset($tags[$tag])) {
        $node->parentNode->removeChild($node);
      }
      // Remove tags that should not be empty.
      elseif ($tags[$tag] === FALSE && static::isEmpty($node)) {
        $node->parentNode->removeChild($node);
      }
      // Process headings, keeping only ids.
      elseif (isset($headings[$tag])) {
        static::handleHeading($node);
      }
      // Process links, removing invalid ones.
      elseif ($tag === 'a') {
        static::handleLink($node);
      }
      // Process images.
      elseif ($tag === 'img') {
        static::handleImage($node);
      }
      // Process tables.
      elseif ($tag === 'table') {
        static::handleTable($node);
      }
      // Process table cells.
      elseif ($tag === 'td' || $tag === 'th') {
        static::handleTableCell($node);
      }
      // Process list items.
      elseif ($tag === 'li') {
        static::handleListItem($node);
      }
      // Process strong tags.
      elseif ($tag === 'strong') {
        static::handleStrong($node);
      }
      // Process Line Break tags.
      elseif ($tag === 'br') {
        static::handleLineBreak($node);
      }
      // Process the node, converting if necessary and removing attributes.
      else {
        if (isset($convert[$tag])) {
          static::changeTag($node, $convert[$tag]);
        }
        else {
          static::removeAttributes($node);
        }
      }
    }

    // Remove "ignorable" whitespaces. This is ok-ish for this migration and
    // allows to have a slightly better formatted and more consistent output
    // than without when combined with `formatOutput` below.
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query('//text()');
    for ($i = $nodes->length - 1; $i >= 0; $i--) {
      $node = $nodes->item($i);
      if ($node->isElementContentWhitespace()) {
        $node->parentNode->removeChild($node);
      }
    }

    $dom->formatOutput = TRUE;
    $html = $dom->saveHTML();

    // Search for the body tag and return its content.
    $start = mb_strpos($html, '<body>');
    $end = mb_strrpos($html, '</body>');
    if ($start !== FALSE && $end !== FALSE) {
      $start += 6;
      return mb_substr($html, $start, $end - $start);
    }

    return '';
  }

  /**
   * Check if a node is empty (empty or only whitespaces).
   *
   * @param \DOMNode $node
   *   Node to check.
   *
   * @return bool
   *   TRUE if the node is considered empty.
   */
  public static function isEmpty(DOMNode $node) {
    // Trim the content, including nbps.
    $content = preg_replace('/(?:^\s+)|(?:\s+$)/u', '', $node->textContent);
    return empty($content);
  }

  /**
   * Sanitize heading attributes.
   *
   * @param \DOMNode $node
   *   Heading node.
   */
  public static function handleHeading(DOMNode $node) {
    // Remove all the attributes except the 'id' that we keep to allow
    // internal links.
    static::removeAttributes($node, ['id']);
  }

  /**
   * Validate link url and sanitize attributes.
   *
   * @param \DOMNode $node
   *   Link node.
   */
  public static function handleLink(DOMNode $node) {
    $url = $node->getAttribute('href');

    // Remove links with an invalid url.
    // @todo replace 'isValid' with something more robust.
    // @todo check if anchors are preserved.
    if (!UrlHelper::isValid($url, UrlHelper::isExternal($url))) {
      // Replace the link with its content.
      if ($node->hasChildNodes()) {
        $fragment = $node->ownerDocument->createDocumentFragment();
        while ($node->firstChild !== NULL) {
          $fragment->appendChild($node->firstChild);
        }
        $node->parentNode->replaceChild($fragment, $node);
      }
      // Remove the link otherwise.
      else {
        $node->parentNode->removeChild($node);
      }
    }
    // Remove all the attributes except the 'href' and optional 'target'.
    else {
      $allowed_attributes = ['href'];

      // We preserve the target attribute to open in a new tab/window.
      $target = $node->getAttribute('target');
      if ($target === '_blank') {
        // Set the rel attribute to avoid exploitation of the window.opener
        // Api.
        // @see https://developer.mozilla.org/en-US/docs/Web/HTML/Element/a
        $node->setAttribute('rel', 'noreferrer noopener');
        $allowed_attributes[] = 'target';
        $allowed_attributes[] = 'rel';
      }

      static::removeAttributes($node, $allowed_attributes);
    }
  }

  /**
   * Validate image url and sanitize attributes.
   *
   * @param \DOMNode $node
   *   Image node.
   */
  public static function handleImage(DOMNode $node) {
    $url = $node->getAttribute('src');

    // Remove images with an invalid url.
    // @todo replace 'isValid' with something more robust.
    // @todo check if anchors are preserved.
    if (!UrlHelper::isValid($url, UrlHelper::isExternal($url))) {
      // Remove the node.
      $node->parentNode->removeChild($node);
    }
    // Remove all the attributes except the 'src', 'alt' and 'title' ones.
    else {
      static::removeAttributes($node, ['src', 'alt', 'title']);
    }
  }

  /**
   * Fix a table, splitting it if it contains several "header" rows.
   *
   * Note: this the part specific to ODSG.
   *
   * @param \DOMNode $node
   *   Table node.
   */
  public static function handleTable(DOMNode $node) {
    $dom = $node->ownerDocument;
    $fragment = $dom->createDocumentFragment();

    // Parse the cells, converting header ones to TH.
    foreach (static::getElementsByTagName($node, 'td') as $td) {
      // If the cell is marked as "header".
      if ($td->getAttribute('class') === 'docheaderbkg') {
        // Replace the TD by a TH.
        $td = static::changeTag($td, 'th', ['colspan']);
        // Mark the parent row as being a header row.
        $td->parentNode->setAttribute('data-header', '');
      }
    }

    // Parse the rows, create a new table when encountering a header row.
    $table = NULL;
    foreach (static::getElementsByTagName($node, 'tr') as $index => $tr) {
      if ($index === 0 || $tr->hasAttribute('data-header')) {
        if (isset($table)) {
          $fragment->appendChild($table);
        }
        // Create a new table.
        $table = $dom->createElement('table');
        $thead = $dom->createElement('thead');
        $tbody = $dom->createElement('tbody');
        $table->appendChild($thead);
        $table->appendChild($tbody);
      }

      if ($tr->hasAttribute('data-header') || $tr->parentNode->tagName === 'thead') {
        $thead->appendChild($tr);
      }
      else {
        $tbody->appendChild($tr);
      }
    }
    if (isset($table)) {
      $fragment->appendChild($table);
    }

    // Replace the table with the list of tables.
    $node->parentNode->replaceChild($fragment, $node);
  }

  /**
   * Remove table cell attributes, except colspan.
   *
   * @param \DOMNode $node
   *   Table cell node.
   */
  public static function handleTableCell(DOMNode $node) {
    static::removeAttributes($node, ['colspan']);
  }

  /**
   * Ensure list items have a proper UL or OL parent.
   *
   * @param \DOMNode $node
   *   List item node.
   */
  public static function handleListItem(DOMNode $node) {
    // Add a list parent to orphan list items.
    if ($node->parentNode->tagName !== 'ul' && $node->parentNode->tagName !== 'ol') {
      $listElement = $node->ownerDocument->createElement('ul');
      $node->parentNode->insertBefore($listElement, $node);
      $sibling = $node;
      while ($sibling !== NULL && ($sibling->nodeType !== 1 || $sibling->tagName === 'li')) {
        $next = $sibling->nextSibling;
        $listElement->appendChild($sibling);
        $sibling = $next;
      }
    }
  }

  /**
   * Remove strong tags when only children of headings or table headers.
   *
   * @param \DOMNode $node
   *   Strong node.
   */
  public static function handleStrong(DOMNode $node) {
    // Tags for which there is no need to have a strong element.
    static $tags = [
      'h1' => TRUE,
      'h2' => TRUE,
      'h3' => TRUE,
      'h4' => TRUE,
      'h5' => TRUE,
      'h6' => TRUE,
      'th' => TRUE,
    ];

    $parent = $node->parentNode;

    // Replace the node with its content or remove it if empty.
    if (isset($parent->tagName, $tags[$parent->tagName])) {
      $fragment = $node->ownerDocument->createDocumentFragment();
      while ($node->firstChild !== NULL) {
        $fragment->appendChild($node->firstChild);
      }
      $parent->replaceChild($fragment, $node);
    }
  }

  /**
   * Remove line break tags except when in a paragraph.
   *
   * @param \DOMNode $node
   *   BR node.
   */
  public static function handleLineBreak(DOMNode $node) {
    // Tags for which there is no need to have line breaks.
    static $tags = [
      'h1' => TRUE,
      'h2' => TRUE,
      'h3' => TRUE,
      'h4' => TRUE,
      'h5' => TRUE,
      'h6' => TRUE,
      'th' => TRUE,
    ];

    $parent = $node->parentNode;

    if (isset($parent->tagName, $tags[$parent->tagName])) {
      // Often the <br> is followed by a line break character, we replace it
      // with a space if there is anything before or remove it otherwise.
      $sibling = $node->nextSibling;
      if ($sibling !== NULL && $sibling->nodeType === 3) {
        $replacement = $node->previousSibling !== NULL ? ' ' : '';
        $sibling->nodeValue = preg_replace('/^\s+/u', $replacement, $sibling->nodeValue);
      }
      $parent->removeChild($node);
    }
  }

  /**
   * Remove attributes from a node.
   *
   * @param \DOMNode $node
   *   Node from which to remove attributes.
   * @param array $allowed_attributes
   *   List of allowed attributes.
   */
  public static function removeAttributes(DOMNode $node, array $allowed_attributes = []) {
    if ($node->hasAttributes()) {
      $allowed_attributes = array_flip($allowed_attributes);

      // Remoe unallowed attributes.
      $attributes = $node->attributes;
      for ($i = $attributes->length - 1; $i >= 0; $i--) {
        $attribute = $attributes->item($i);
        if (!isset($allowed_attributes[$attribute->name])) {
          $node->removeAttribute($attribute->name);
        }
      }
    }
  }

  /**
   * Replace a node by a node with the new tag, moving content and attributes.
   *
   * @param \DOMNode $node
   *   Node to replace.
   * @param string $tag
   *   New tag name.
   * @param array $allowed_attributes
   *   Attributes to move to the new node.
   */
  public static function changeTag(DOMNode $node, $tag, array $allowed_attributes = []) {
    if (!empty($tag)) {
      $newNode = $node->ownerDocument->createElement($tag);
    }
    else {
      $newNode = $node->ownerDocument->createDocumentFragment();
    }
    // Move the content.
    while ($node->firstChild !== NULL) {
      $newNode->appendChild($node->firstChild);
    }
    // Copy the attributes.
    $allowed_attributes = array_flip($allowed_attributes);
    if (!empty($allowed_attributes) && $node->hasAttributes()) {
      foreach ($node->attributes as $attribute) {
        if (isset($allowed_attributes[$attribute->name])) {
          $newNode->setAttribute($attribute->name, $attribute->value);
        }
      }
    }
    $node->parentNode->replaceChild($newNode, $node);
    return $newNode;
  }

  /**
   * Get the nodes matching the tag name.
   *
   * DOMElement::GetElementsByTagName returns a live collection. We convert it
   * to a flat array so that the nodes can be manipulated during the iteration
   * without creating infinite loops for example when adding iframe wrappers.
   *
   * @param \DOMNode $node
   *   Node (DOMDocument or DOMElement)
   * @param string $tag
   *   Tag name or `*` for all nodes.
   *
   * @return array
   *   List of nodes with the given tag name.
   */
  public static function getElementsByTagName(DOMNode $node, $tag) {
    $elements = [];
    if (method_exists($node, 'getElementsByTagName')) {
      foreach ($node->getElementsByTagName($tag) as $element) {
        $elements[] = $element;
      }
    }
    return $elements;
  }

}
