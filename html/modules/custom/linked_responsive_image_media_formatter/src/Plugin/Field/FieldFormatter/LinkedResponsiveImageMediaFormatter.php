<?php

namespace Drupal\linked_responsive_image_media_formatter\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\Utility\Token;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Render\RendererInterface;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Plugin implementation of a reponsive image formatter for media.
 *
 * @FieldFormatter(
 *   id = "linked_responsive_image_media_formatter",
 *   label = @Translation("Responsive Media Image with Link"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class LinkedResponsiveImageMediaFormatter extends ImageFormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The responsive image style entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $responsiveImageStyleStorage;

  /**
   * The image style entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $imageStyleStorage;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The link generator.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $linkGenerator;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Load the parent Media entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $parentMediaStorage;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Constructs a MediaResponsiveThumbnailFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityStorageInterface $responsive_image_style_storage
   *   The responsive image style storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $image_style_storage
   *   The image style storage.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The link generator service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Entity\EntityStorageInterface $parent_media_storage
   *   The media storage.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityStorageInterface $responsive_image_style_storage, EntityStorageInterface $image_style_storage, LinkGeneratorInterface $link_generator, AccountInterface $current_user, RendererInterface $renderer, EntityStorageInterface $parent_media_storage, Token $token) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->responsiveImageStyleStorage = $responsive_image_style_storage;
    $this->imageStyleStorage = $image_style_storage;
    $this->linkGenerator = $link_generator;
    $this->currentUser = $current_user;
    $this->renderer = $renderer;
    $this->parentMediaStorage = $parent_media_storage;
    $this->token = $token;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')->getStorage('responsive_image_style'),
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('link_generator'),
      $container->get('current_user'),
      $container->get('renderer'),
      $container->get('entity_type.manager')->getStorage('media'),
      $container->get('token'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * This has to be overridden because FileFormatterBase expects $item to be
   * of type \Drupal\file\Plugin\Field\FieldType\FileItem and calls
   * isDisplayed() which is not in FieldItemInterface.
   */
  protected function needsEntityLoad(EntityReferenceItem $item) {
    return !$item->hasNewEntity();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'responsive_image_style' => '',
      'image_link' => '',
      'image_link_url' => '',
      'image_alt' => 'image',
      'image_alt_value' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $responsive_image_options = [];
    $responsive_image_styles = $this->responsiveImageStyleStorage->loadMultiple();
    if ($responsive_image_styles && !empty($responsive_image_styles)) {
      foreach ($responsive_image_styles as $machine_name => $responsive_image_style) {
        if ($responsive_image_style->hasImageStyleMappings()) {
          $responsive_image_options[$machine_name] = $responsive_image_style->label();
        }
      }
    }

    $elements['responsive_image_style'] = [
      '#title' => $this->t('Responsive image style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('responsive_image_style') ?: NULL,
      '#required' => TRUE,
      '#options' => $responsive_image_options,
      '#description' => [
        '#markup' => $this->linkGenerator->generate($this->t('Configure Responsive Image Styles'), new Url('entity.responsive_image_style.collection')),
        '#access' => $this->currentUser->hasPermission('administer responsive image styles'),
      ],
    ];

    // @todo add an option to link to media?
    $link_types = [
      'content' => $this->t('Content'),
      'file' => $this->t('File'),
      'custom' => $this->t('Custom'),
    ];
    $elements['image_link'] = [
      '#title' => $this->t('Link image to'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_link'),
      '#empty_option' => $this->t('Nothing'),
      '#options' => $link_types,
    ];

    $elements['image_link_url'] = [
      '#title' => $this->t('Link image to'),
      '#type' => 'textfield',
      '#maxlength' => 2048,
      '#default_value' => $this->getSetting('image_link_url'),
      '#description' => $this->t('Tokens are supported. Internal path must be prefixed with a <code>internal:/</code>.'),
      '#states' => [
        'visible' => [
          'select[name$="[settings][image_link]"]' => ['value' => 'custom'],
        ],
        'required' => [
          'select[name$="[settings][image_link]"]' => ['value' => 'custom'],
        ],
      ],
    ];

    $alt_types = [
      'image' => $this->t('Image'),
      'custom' => $this->t('Custom'),
    ];
    $elements['image_alt'] = [
      '#title' => $this->t('Alt attribute to use'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_link'),
      '#options' => $alt_types,
    ];

    $elements['image_alt_value'] = [
      '#title' => $this->t('Image alt'),
      '#type' => 'textarea',
      '#description' => $this->t('Tokens are supported. Image description in plain text with optional tokens. Leave empty for decorative images.'),
      '#default_value' => $this->getSetting('image_alt_value'),
      '#states' => [
        'visible' => [
          'select[name$="[settings][image_alt]"]' => ['value' => 'custom'],
        ],
      ],
    ];

    $elements['token'] = [
      '#type' => 'item',
      '#theme' => 'token_tree_link',
      '#token_types' => 'all',
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $link_types = [
      'content' => $this->t('Linked to content'),
      'media' => $this->t('Linked to media item'),
      'custom' => $this->t('Linked to custom URL'),
    ];
    // Display this setting only if image is linked.
    $image_link_setting = $this->getSetting('image_link');
    if (isset($link_types[$image_link_setting])) {
      $summary[] = $link_types[$image_link_setting];
    }

    $alt_types = [
      'image' => $this->t('Use image alt text'),
      'custom' => $this->t('Use custom alt text'),
    ];
    $image_alt_setting = $this->getSetting('image_alt');
    if (isset($alt_types[$image_alt_setting])) {
      $summary[] = $alt_types[$image_alt_setting];
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $entity = $items->getEntity();
    $media_items = $this->getEntitiesToView($items, $langcode);

    // Make sure the entity data is available.
    $token_data = [$entity->getEntityType()->id() => $entity];
    $token_options = ['clear' => TRUE];

    // Early opt-out if the field is empty.
    if (empty($media_items)) {
      return $elements;
    }

    // Retrieve the url to link to.
    $url = NULL;
    if ($this->getSetting('image_link') === 'content') {
      if (!$entity->isNew()) {
        $url = $entity->toUrl();
      }
    }
    elseif ($this->getSetting('image_link') === 'file') {
      $link_file = TRUE;
    }
    elseif ($this->getSetting('image_link') === 'custom') {
      $url = $this->token->replace($this->getSetting('image_link_url'), $token_data, $token_options);
      // Try to grab the href attribute if the replaced token is a link.
      preg_match('/<a[^>]* href="([^"]+)"[^>]*>/', $url, $match);
      $url = isset($match[1]) ? $match[1] : $url;
    }

    // Retrieve the alt to use.
    $alt = '';
    if ($this->getSetting('image_alt') === 'image') {
      $use_image_alt = TRUE;
    }
    elseif ($this->getSetting('image_alt') === 'custom') {
      $alt = $this->token->replace($this->getSetting('image_alt_value'), $token_data, $token_options);
    }

    // Collect cache tags to be added for each item in the field.
    $responsive_image_style = $this->responsiveImageStyleStorage->load($this->getSetting('responsive_image_style'));
    $image_styles_to_load = [];
    $cache_tags = [];
    if ($responsive_image_style) {
      $cache_tags = Cache::mergeTags($cache_tags, $responsive_image_style->getCacheTags());
      $image_styles_to_load = $responsive_image_style->getImageStyleIds();
    }

    $image_styles = $this->imageStyleStorage->loadMultiple($image_styles_to_load);
    foreach ($image_styles as $image_style) {
      $cache_tags = Cache::mergeTags($cache_tags, $image_style->getCacheTags());
    }

    foreach ($media_items as $delta => $media_item) {
      $image = $media_item->get('thumbnail')->first();

      // Link the <picture> element to the original image file.
      if (isset($link_file)) {
        assert($image instanceof FileInterface);
        $url = $image->createFileUrl();
      }

      if (!isset($use_image_alt)) {
        $image->set('alt', $alt);
      }

      $elements[$delta] = [
        '#theme' => 'responsive_image_formatter',
        '#item' => $image,
        '#item_attributes' => [],
        '#responsive_image_style_id' => $responsive_image_style ? $responsive_image_style->id() : '',
        '#url' => $url,
        '#cache' => [
          'tags' => $cache_tags,
        ],
      ];
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $storage_definition = $field_definition->getFieldStorageDefinition();

    // This formatter is only applicable to media reference field with a
    // cardinality of 1.
    // @todo this should only be applicable to media of type image but it's
    // not easy to retrieve this information.
    return $storage_definition->getSetting('target_type') === 'media' &&
       $storage_definition->getCardinality() == 1;
  }

}
