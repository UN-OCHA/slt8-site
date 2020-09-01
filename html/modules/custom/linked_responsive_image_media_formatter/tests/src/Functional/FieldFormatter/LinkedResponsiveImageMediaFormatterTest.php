<?php

namespace Drupal\Tests\linked_responsive_image_media_formatter\Functional\FieldFormatter;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\media\Entity\Media;
use Drupal\Tests\media\Functional\MediaFunctionalTestBase;

/**
 * @covers \Drupal\linked_responsive_image_media_formatter\Plugin\Field\FieldFormatter\LinkedResponsiveImageMediaFormatterFormatter
 *
 * @group media
 */
class LinkedResponsiveImageMediaFormatterTest extends MediaFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'field_ui',
    'link',
    'media',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Data provider for testRender().
   *
   * @see ::testRender()
   *
   * @return array
   *   Data for the ::testRender().
   */
  public function providerRender() {
    return [];
  }

  /**
   * Tests that oEmbed media types' display can be configured correctly.
   */
  public function testDisplayConfiguration() {
    $account = $this->drupalCreateUser(['administer media display']);
    $this->drupalLogin($account);

    $media_type = $this->createMediaType('image');
    $this->drupalGet('/admin/structure/media/manage/' . $media_type->id() . '/display');
    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);
    // Test that the formatter doesn't try to check applicability for fields
    // which do not have a specific target bundle.
    // @see https://www.drupal.org/project/drupal/issues/2976795.
    $assert->pageTextNotContains('Can only flip STRING and INTEGER values!');
  }

  /**
   * Tests the linked responsive image media field formatter.
   *
   * @param string $url
   *   The canonical URL of the media asset to test.
   * @param array $formatter_settings
   *   Settings for the linked responsive image media field formatter.
   * @param array $selectors
   *   An array of arrays. Each key is a CSS selector targeting an element in
   *   the rendered output, and each value is an array of attributes, keyed by
   *   name, that the element is expected to have.
   *
   * @dataProvider providerRender
   */
  public function testRender($url, array $formatter_settings, array $selectors) {
    $account = $this->drupalCreateUser(['view media']);
    $this->drupalLogin($account);

    $media_type = $this->createMediaType('image');

    $source = $media_type->getSource();
    $source_field = $source->getSourceFieldDefinition($media_type);

    EntityViewDisplay::create([
      'targetEntityType' => 'media',
      'bundle' => $media_type->id(),
      'mode' => 'full',
      'status' => TRUE,
    ])->removeComponent('thumbnail')
      ->setComponent($source_field->getName(), [
        'type' => 'image',
        'settings' => $formatter_settings,
      ])
      ->save();

    // @todo is this needed?
    $this->hijackProviderEndpoints();

    // @todo Create an image and get its ID.
    $image_target_id = '??';

    // @todo create a node with an entity reference field using this media.
    $entity = Media::create([
      'bundle' => $media_type->id(),
      $source_field->getName() => $image_target_id,
    ]);
    $entity->save();

    $this->drupalGet($entity->toUrl());
    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);
    foreach ($selectors as $selector => $attributes) {
      $element = $assert->elementExists('css', $selector);
      foreach ($attributes as $attribute => $value) {
        if (isset($value)) {
          $this->assertStringContainsString($value, $element->getAttribute($attribute));
        }
        else {
          $this->assertFalse($element->hasAttribute($attribute));
        }
      }
    }
  }

}
