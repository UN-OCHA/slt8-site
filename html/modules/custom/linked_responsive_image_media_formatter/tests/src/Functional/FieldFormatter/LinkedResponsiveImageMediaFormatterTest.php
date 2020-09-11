<?php

namespace Drupal\Tests\linked_responsive_image_media_formatter\Functional\FieldFormatter;

use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\responsive_image\Entity\ResponsiveImageStyle;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\TestFileCreationTrait;

/**
 * @covers \Drupal\linked_responsive_image_media_formatter\Plugin\Field\FieldFormatter\LinkedResponsiveImageMediaFormatterFormatter
 *
 * @group media
 */
class LinkedResponsiveImageMediaFormatterTest extends BrowserTestBase {

  use ContentTypeCreationTrait;
  use EntityReferenceTestTrait;
  use MediaTypeCreationTrait;
  use NodeCreationTrait;
  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'node',
    'field',
    'image',
    'media',
    'responsive_image',
    'responsive_image_test_module',
    'linked_responsive_image_media_formatter',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * View display for the node type used in the tests.
   *
   * @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   */
  protected $viewDisplay;

  /**
   * Test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $testNode;

  /**
   * Test media.
   *
   * @var \Drupal\media\Entity\Media
   */
  protected $testMedia;

  /**
   * Test image.
   *
   * @var \Drupal\file\Entity\File
   */
  protected $testImage;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create responsive image styles.
    ResponsiveImageStyle::create([
      'id' => 'responsive_image_style',
      'label' => 'Responsive Image Style',
      'breakpoint_group' => 'responsive_image_test_module',
      'fallback_image_style' => 'medium',
    ])->addImageStyleMapping('responsive_image_test_module.mobile', '1x', [
      'image_mapping_type' => 'image_style',
      'image_mapping' => 'thumbnail',
    ])->addImageStyleMapping('responsive_image_test_module.wide', '1x', [
      'image_mapping_type' => 'image_style',
      'image_mapping' => 'large',
    ])->save();

    // Create image media type.
    $media_type = $this->createMediaType('image');

    // Create article node type.
    $this->drupalCreateContentType(['type' => 'article']);

    // Create the image media entity reference field.
    $this->createEntityReferenceField('node', 'article', 'field_media', 'field_media', 'media', 'default', [
      'target_bundles' => [$media_type->id()],
      'sort' => ['field' => 'created', 'direction' => 'DESC'],
    ]);

    // Create User and log in.
    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'create article content',
      'edit own article content',
    ]));

    // Create an image.
    \Drupal::service('file_system')->copy($this->root . '/core/misc/druplicon.png', 'public://example.png');
    $image = File::create(['uri' => 'public://example.png']);
    $image->save();

    // Create the media and reference the image.
    $media = Media::create([
      'bundle' => $media_type->id(),
      'name' => 'Test media',
      'field_media_image' => [
        'target_id' => $image->id(),
        'alt' => 'test image alt text',
      ],
    ]);
    $media->save();

    // Create the node and reference the media.
    $node = $this->drupalCreateNode(['type' => 'article', 'id' => 1]);
    $node->field_media->target_id = $media->id();
    $node->save();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');

    $this->viewDisplay = $display_repository->getViewDisplay('node', 'article');
    $this->testImage = $image;
    $this->testMedia = $media;
    $this->testNode = $node;
  }

  /**
   * Data provider for testRender().
   *
   * Note: this is called before ::setUp() so $this->node for example doesn't
   * yet have a value. Also it's not possible to use a closure so instead we
   * use an array with a "callback" that will be called when checking the
   * attribute value so that we can use data from the test node.
   *
   * @see ::testRender()
   *
   * @return array
   *   Data for the ::testRender().
   */
  public function providerRender() {
    return [
      'Responsive image style only' => [
        'settings' => [
          'responsive_image_style' => 'responsive_image_style',
          'image_link' => '',
          'image_link_url' => '',
          'image_alt' => 'image',
          'image_alt_value' => '',
          'image_as_background' => FALSE,
        ],
        'selectors' => [
          '.field--name-field-media > .field__item > picture > source[media="(min-width: 0px)"]' => [
            'attributes' => [
              'srcset' => '/files/styles/thumbnail/public/example.png',
            ],
          ],
          '.field--name-field-media > .field__item > picture > source[media="(min-width: 851px)"]' => [
            'attributes' => [
              'srcset' => '/files/styles/large/public/example.png',
            ],
          ],
          '.field--name-field-media > .field__item > picture > img' => [
            'attributes' => [
              'alt' => 'test image alt text',
            ],
          ],
        ],
      ],
      'Responsive image with custom alt text' => [
        'settings' => [
          'responsive_image_style' => 'responsive_image_style',
          'image_link' => '',
          'image_link_url' => '',
          'image_alt' => 'custom',
          'image_alt_value' => 'Custom alt text',
          'image_as_background' => FALSE,
        ],
        'selectors' => [
          '.field--name-field-media > .field__item > picture > img' => [
            'attributes' => [
              'alt' => 'Custom alt text',
            ],
          ],
        ],
      ],
      'Responsive image with custom alt text using a token' => [
        'settings' => [
          'responsive_image_style' => 'responsive_image_style',
          'image_link' => '',
          'image_link_url' => '',
          'image_alt' => 'custom',
          'image_alt_value' => '[node:title]',
          'image_as_background' => FALSE,
        ],
        'selectors' => [
          '.field--name-field-media > .field__item > picture > img' => [
            'attributes' => [
              'alt' => ['callback' => 'getTestNodeTitle'],
            ],
          ],
        ],
      ],
      'Responsive image with link to media' => [
        'settings' => [
          'responsive_image_style' => 'responsive_image_style',
          'image_link' => 'media',
          'image_link_url' => '',
          'image_alt' => 'image',
          'image_alt_value' => '',
          'image_as_background' => FALSE,
        ],
        'selectors' => [
          '.field--name-field-media > .field__item > a' => [
            'attributes' => [
              'href' => ['callback' => 'getTestMediaUrl'],
            ],
          ],
        ],
      ],
      'Responsive image with link to image' => [
        'settings' => [
          'responsive_image_style' => 'responsive_image_style',
          'image_link' => 'image',
          'image_link_url' => '',
          'image_alt' => 'image',
          'image_alt_value' => '',
          'image_as_background' => FALSE,
        ],
        'selectors' => [
          '.field--name-field-media > .field__item > a' => [
            'attributes' => [
              'href' => ['callback' => 'getTestImageUrl'],
            ],
          ],
        ],
      ],
      'Responsive image with custom link' => [
        'settings' => [
          'responsive_image_style' => 'responsive_image_style',
          'image_link' => 'custom',
          'image_link_url' => 'https://example.org',
          'image_alt' => 'image',
          'image_alt_value' => '',
          'image_as_background' => FALSE,
        ],
        'selectors' => [
          '.field--name-field-media > .field__item > a' => [
            'attributes' => [
              'href' => 'https://example.org',
            ],
          ],
          '.field--name-field-media > .field__item > a > picture > img' => [
            'attributes' => [
              'alt' => 'test image alt text',
            ],
          ],
        ],
      ],
      'Responsive image with custom link using a token' => [
        'settings' => [
          'responsive_image_style' => 'responsive_image_style',
          'image_link' => 'custom',
          'image_link_url' => '[node:url]',
          'image_alt' => 'image',
          'image_alt_value' => '',
          'image_as_background' => FALSE,
        ],
        'selectors' => [
          '.field--name-field-media > .field__item > a' => [
            'attributes' => [
              'href' => ['callback' => 'getTestNodeUrl'],
            ],
          ],
        ],
      ],
      'Responsive image with custom entity link' => [
        'settings' => [
          'responsive_image_style' => 'responsive_image_style',
          'image_link' => 'custom',
          'image_link_url' => 'entity:node/1',
          'image_alt' => 'image',
          'image_alt_value' => '',
          'image_as_background' => FALSE,
        ],
        'selectors' => [
          '.field--name-field-media > .field__item > a' => [
            'attributes' => [
              'href' => ['callback' => 'getTestNodeUrl'],
            ],
          ],
          '.field--name-field-media > .field__item > a > picture > img' => [
            'attributes' => [
              'alt' => 'test image alt text',
            ],
          ],
        ],
      ],
      'Responsive image with image as background' => [
        'settings' => [
          'responsive_image_style' => 'responsive_image_style',
          'image_link' => 'custom',
          'image_link_url' => 'https://example.org',
          'image_alt' => 'image',
          'image_alt_value' => '',
          'image_as_background' => TRUE,
        ],
        'selectors' => [
          '.field--name-field-media > .field__item > a' => [
            'attributes' => [
              'href' => 'https://example.org',
            ],
          ],
          '.field--name-field-media > .field__item > a > picture > img' => [
            'attributes' => [
              'alt' => '',
            ],
          ],
          '.field--name-field-media > .field__item > a > .link__title' => [
            'text' => 'test image alt text',
          ],
        ],
      ],
      'Responsive image with image as background and custom link and alt with tokens' => [
        'settings' => [
          'responsive_image_style' => 'responsive_image_style',
          'image_link' => 'custom',
          'image_link_url' => '[node:url]',
          'image_alt' => 'custom',
          'image_alt_value' => '[node:title]',
          'image_as_background' => TRUE,
        ],
        'selectors' => [
          '.field--name-field-media > .field__item > a' => [
            'attributes' => [
              'href' => ['callback' => 'getTestNodeUrl'],
            ],
          ],
          '.field--name-field-media > .field__item > a > picture > img' => [
            'attributes' => [
              'alt' => '',
            ],
          ],
          '.field--name-field-media > .field__item > a > .link__title' => [
            'text' => ['callback' => 'getTestNodeTitle'],
          ],
        ],
      ],
    ];
  }

  /**
   * Tests the linked responsive image media field formatter.
   *
   * @param array $settings
   *   Settings for the linked responsive image media field formatter.
   * @param array $selectors
   *   An array of arrays. Each key is a CSS selector targeting an element in
   *   the rendered output, and each value is an array of attributes, keyed by
   *   name, that the element is expected to have.
   *
   * @dataProvider providerRender
   */
  public function testRender(array $settings, array $selectors) {
    // Set the display settings for the media field.
    $this->viewDisplay->setComponent('field_media', [
      'type' => 'linked_responsive_image_media_formatter',
      'weight' => 2,
      'settings' => $settings,
    ])->save();

    // Get the content of the test node page.
    $this->drupalGet($this->getTestNodeUrl());
    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);

    // Ensure the media field output is correct.
    foreach ($selectors as $selector => $data) {
      $element = $assert->elementExists('css', $selector);
      // Check the element's attributes.
      if (isset($data['attributes'])) {
        foreach ($data['attributes'] as $attribute => $value) {
          if (is_array($value)) {
            if (isset($value['callback'])) {
              $value = call_user_func([$this, $value['callback']]);
            }
            else {
              $value = reset($value);
            }
          }
          if (isset($value)) {
            if ($value === '') {
              $this->assertTrue($element->getAttribute($attribute) === '');
            }
            else {
              $this->assertStringContainsString($value, $element->getAttribute($attribute));
            }
          }
          else {
            $this->assertFalse($element->hasAttribute($attribute));
          }
        }
      }
      // Check the element's text content.
      if (isset($data['text'])) {
        $value = $data['text'];
        if (is_array($value)) {
          if (isset($value['callback'])) {
            $value = call_user_func([$this, $value['callback']]);
          }
          else {
            $value = reset($value);
          }
        }
        $this->assertStringContainsString($value, $element->getText());
      }
    }
  }

  /**
   * Helper method to get the node title.
   *
   * @return string
   *   Test node title.
   */
  protected function getTestNodeTitle() {
    return $this->testNode->getTitle();
  }

  /**
   * Helper method to get the node url.
   *
   * @return string
   *   Test node url.
   */
  protected function getTestNodeUrl() {
    return $this->testNode->toUrl()->toString();
  }

  /**
   * Helper method to get the media url.
   *
   * @return string
   *   Test media url.
   */
  protected function getTestMediaUrl() {
    return $this->testMedia->toUrl()->toString();
  }

  /**
   * Helper method to get the image url.
   *
   * @return string
   *   Test image url.
   */
  protected function getTestImageUrl() {
    return $this->testImage->createFileUrl();
  }

}
