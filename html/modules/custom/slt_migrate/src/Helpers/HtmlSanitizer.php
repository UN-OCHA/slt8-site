<?php

namespace Drupal\slt_migrate\Helpers;

use Drupal\Component\Utility\UrlHelper;

/**
 * Html Sanitizer implementation.
 */
class HtmlSanitizer {

  /**
   * Sanitize a HTML string in regardst to the allowed SLT content.
   *
   * This also fixes the heading hierarchy to ensure continuity.
   *
   * @param string $html
   *   The HTML string.
   * @param int $heading_offset
   *   Heading offset from H1: `1` means headings start at h2.
   *
   * @return string
   *   The sanitized HTML string.
   */
  public static function sanitize($html, $heading_offset = 1) {
    if (!is_string($html)) {
      return '';
    }

    // Skip if the html string is empty.
    $html = trim($html);
    if (empty($html)) {
      return '';
    }

    // Convert all '&nbsp;' to normal spaces.
    $html = str_replace('&nbsp;', ' ', $html);

    // Supported tags and whether they can be empty (no children) or not.
    $tags = [
      // This is just for the added html structure when loading the html.
      'html' => FALSE,
      'head' => FALSE,
      'meta' => TRUE,
      'body' => FALSE,
      // No children.
      'br' => TRUE,
      'a' => FALSE,
      'em' => FALSE,
      'i' => FALSE,
      'strong' => FALSE,
      'b' => FALSE,
      'ul' => FALSE,
      'ol' => FALSE,
      'li' => FALSE,
      'blockquote' => FALSE,
      'cite' => FALSE,
      'p' => FALSE,
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
      // We allow table cells to be empty.
      'th' => TRUE,
      'td' => TRUE,
      'tr' => FALSE,
      'span' => FALSE,
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

    // Flags to load the HTML string.
    $flags = LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOERROR | LIBXML_NOWARNING;

    // Adding this meta tag is necessary to tell DOMDocument we are dealing
    // with UTF-8 encoded html.
    $meta = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    $prefix = '<!DOCTYPE html><html><head>' . $meta . '</head><body>';
    $suffix = '</body></html>';
    $dom = new \DOMDocument();
    $dom->loadHTML($prefix . $html . $suffix, $flags);

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
    // than without, when combined with `formatOutput` below.
    $xpath = new \DOMXPath($dom);
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
  public static function isEmpty(\DOMNode $node) {
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
  public static function handleHeading(\DOMNode $node) {
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
  public static function handleLink(\DOMNode $node) {
    $url = $node->getAttribute('href');

    // Check if the link is an anchor.
    if (empty($url)) {
      $id = $node->getAttribute('id');

      if (empty($id) || empty($node->parentNode)) {
        $node->parentNode->removeChild($node);
      }
      else {
        // Move the id to the parent and delete the node.
        $id = static::sanitizeId($id);
        $node->parentNode->setAttribute('id', $id);
        $node->parentNode->removeChild($node);
      }

      // Skip the rest of the process.
      return;
    }

    // Process anchors, updating the target ID to avoid ID clashes.
    if (strpos($url, '#') === 0) {
      $id = substr($url, 1);

      // Get the target element and modify the fragment.
      if ($id !== '') {
        $target = $node->ownerDocument->getElementById($id);
        $id = static::sanitizeId($id);
      }

      // Remove the link if we couldn't find the target.
      if (empty($target) || empty($target->parentNode)) {
        $node->parentNode->removeChild($node);

        // Skip the rest of the process.
        return;
      }
      elseif ($target->tagName === 'a') {
        // Move the id to the parent node as it is the new recommendation rather
        // than using anchors.
        $target->parentNode->setAttribute('id', $id);
        $target->parentNode->removeChild($target);
      }
      else {
        $target->setAttribute('id', $id);
      }

      // Update the url with the new fragment.
      $node->setAttribute('href', '#' . $id);
      static::removeAttributes($node, ['href']);

      // Skip the rest of the process.
      return;
    }

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
   * @todo change source URL and attempt to find corresponding media.
   *
   * @param \DOMNode $node
   *   Image node.
   */
  public static function handleImage(\DOMNode $node) {
    static::removeAttributes($node, ['src', 'alt', 'title']);
    // Ensure the is an alt tag.
    $node->setAttribute('alt', $node->getAttribute('alt') ?? '');
  }

  /**
   * Ensure tables have a proper structure.
   *
   * @param \DOMNode $node
   *   Table node.
   */
  public static function handleTable(\DOMNode $node) {
    $dom = $node->ownerDocument;

    // Create a new table.
    $table = $dom->createElement('table');
    $thead = $dom->createElement('thead');
    $tbody = $dom->createElement('tbody');

    $captions = static::getElementsByTagName($node, 'caption');
    if (!empty($captions)) {
      $table->appendChild(reset($captions));
    }

    $table->appendChild($thead);
    $table->appendChild($tbody);

    // Add the row to the thead or tbody.
    foreach (static::getElementsByTagName($node, 'tr') as $tr) {
      if (count(static::getElementsByTagName($node, 'th')) > 0) {
        $thead->appendChild($tr);
      }
      elseif (count(static::getElementsByTagName($node, 'td')) > 0) {
        $tbody->appendChild($tr);
      }
    }

    // Replace the table with the new one.
    $node->parentNode->replaceChild($table, $node);
  }

  /**
   * Remove table cell attributes, except colspan.
   *
   * @param \DOMNode $node
   *   Table cell node.
   */
  public static function handleTableCell(\DOMNode $node) {
    static::removeAttributes($node, ['colspan']);
  }

  /**
   * Ensure list items have a proper UL or OL parent.
   *
   * @param \DOMNode $node
   *   List item node.
   */
  public static function handleListItem(\DOMNode $node) {
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
   * Remove attributes from a node.
   *
   * @param \DOMNode $node
   *   Node from which to remove attributes.
   * @param array $allowed_attributes
   *   List of allowed attributes.
   */
  public static function removeAttributes(\DOMNode $node, array $allowed_attributes = []) {
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
  public static function changeTag(\DOMNode $node, $tag, array $allowed_attributes = []) {
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
  public static function getElementsByTagName(\DOMNode $node, $tag) {
    $elements = [];
    if (method_exists($node, 'getElementsByTagName')) {
      foreach ($node->getElementsByTagName($tag) as $element) {
        $elements[] = $element;
      }
    }
    return $elements;
  }

  /**
   * Sanitize internal link target id.
   *
   * @param string $id
   *   Id to santitize.
   *
   * @return string
   *   Sanitized Id.
   */
  public static function sanitizeId($id) {
    // Prefix the id. This is to avoid clashes with other IDs in the rest of
    // the HTML and is what the Filtered HTML format supports.
    $id = strpos($id, 'jump-') === 0 ? $id : 'jump-' . $id;
    return str_replace('_', '-', $id);
  }

}
