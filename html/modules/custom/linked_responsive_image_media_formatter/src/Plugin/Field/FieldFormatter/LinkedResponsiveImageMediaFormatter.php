<?php

namespace Drupal\linked_responsive_image_media_formatter\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\Utility\Token;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatterBase;
use Drupal\responsive_image\ResponsiveImageStyleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
      $container->get('token')
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
      'image_as_background' => FALSE,
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
      'media' => $this->t('Media'),
      'image' => $this->t('Image'),
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
      '#title' => $this->t('Custom URL to link to'),
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
      '#title' => $this->t('Custom image alt text'),
      '#type' => 'textarea',
      '#description' => $this->t('Tokens are supported. Image description in plain text with optional tokens. Leave empty for decorative images.'),
      '#default_value' => $this->getSetting('image_alt_value'),
      '#states' => [
        'visible' => [
          'select[name$="[settings][image_alt]"]' => ['value' => 'custom'],
        ],
      ],
    ];

    $elements['image_as_background'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use the image as background'),
      '#description' => $this->t('When checked the image will be used as background for the link and the image "alt" will be used as the link text.'),
      '#default_value' => $this->getSetting('image_as_background'),
      '#states' => [
        'invisible' => [
          'select[name$="[settings][image_link]"]' => ['value' => ''],
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

    $responsive_image_style = $this->responsiveImageStyleStorage->load($this->getSetting('responsive_image_style'));
    if ($responsive_image_style) {
      $summary[] = $this->t('Responsive image style: @responsive_image_style', ['@responsive_image_style' => $responsive_image_style->label()]);

      $link_types = [
        'content' => $this->t('Linking to content'),
        'media' => $this->t('Linking to media item'),
        'image' => $this->t('Linking to image file'),
        'custom' => $this->t('Linking to custom URL'),
      ];
      // Display this setting only if image is linked.
      $image_link_setting = $this->getSetting('image_link');
      if (isset($link_types[$image_link_setting])) {
        $summary[] = $link_types[$image_link_setting];
      }

      $alt_types = [
        'image' => $this->t('Using image alt text'),
        'custom' => $this->t('Using custom alt text'),
      ];
      $image_alt_setting = $this->getSetting('image_alt');
      if (isset($alt_types[$image_alt_setting])) {
        $summary[] = $alt_types[$image_alt_setting];
      }

      $image_as_background_setting = $this->getSetting('image_as_background');
      if (!empty($image_as_background_setting)) {
        $summary[] = $this->t('Image used as background');
      }
    }
    else {
      $summary[] = $this->t('Select a responsive image style.');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $media_items = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($media_items)) {
      return $elements;
    }

    // The parent entity information will be used for token replacement.
    $entity = $items->getEntity();
    $entity_type = $entity->getEntityType()->id();

    // Retrieve the settings values.
    $image_link = $this->getSetting('image_link');
    $image_alt = $this->getSetting('image_alt');
    $image_as_background = $this->getSetting('image_as_background');

    // Retrieve the url to link to.
    $url = NULL;
    if ($image_link === 'content') {
      // There is no link if the entity has not yet been saved.
      if (!$entity->isNew()) {
        // @todo Review because that may possibly not be what is expected when
        // used for example to format an image field in a paragraph entity.
        $url = $entity->toUrl();
      }
    }

    // Retrieve the reponsive image style and its cache metadata.
    $responsive_image_style = $this->getResponsiveImageStyle();
    $responsive_image_style_cache_metadata = $this->getResponsiveImageStyleCacheableMetadata($responsive_image_style);

    // Generate render arrays foreach field elements using the responsive
    // image formatter.
    foreach ($media_items as $delta => $media_item) {
      $image = $media_item->get('thumbnail')->first();

      // We do the token replacements here, because we need to pass the media
      // entity as well as the parent entity.
      $token_data = [
        $entity_type => $entity,
        'media' => $media_item,
      ];

      // Link to the original image file.
      if ($image_link === 'image') {
        $url = $image->entity->createFileUrl();
      }
      // Link to the media.
      elseif ($image_link === 'media') {
        $url = $media_item->toUrl();
      }
      // Link to a custom URL.
      elseif ($image_link === 'custom') {
        $url = $this->getCustomUrl($token_data);
      }

      // Set the custom alt text.
      if ($image_alt === 'custom') {
        $image->set('alt', $this->getCustomAlt($token_data));
      }
      // Ensure there is at least an empty alt tag for accessibility.
      elseif (empty($image->alt)) {
        $image->set('alt', '');
      }

      // If the image is to be used as background, we use its alt tag as text
      // for the link and empty the image alt as the image will then be
      // considered a decorative image.
      $link_title = NULL;
      if ($image_as_background) {
        $link_title = $image->alt;
        $image->set('alt', '');
      }

      $elements[$delta] = [
        '#theme' => 'linked_responsive_image_media_formatter',
        '#item' => $image,
        // We copy the alt to the iten attributes so that it's available
        // in the templates, for example to use as text for the link.
        '#item_attributes' => ['alt' => $image->alt],
        '#responsive_image_style_id' => $responsive_image_style ? $responsive_image_style->id() : '',
        '#url' => $url,
        '#link_title' => $link_title,
        // @todo add rel, target, download?
        '#link_attributes' => [],
      ];

      // Add cache metadata for the media entity so that the field is
      // re-rendered when the media changes.
      $this->renderer->addCacheableDependency($elements[$delta], $media_item);
    }

    // Ensure the field is re-rendered when the responsive image style's
    // images styles are changed.
    $responsive_image_style_cache_metadata->applyTo($elements);

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    // This formatter is only applicable to media reference fields.
    return $field_definition->getFieldStorageDefinition()->getSetting('target_type') === 'media';
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    $style = $this->getResponsiveImageStyle();
    if (!empty($style)) {
      $dependencies[$style->getConfigDependencyKey()][] = $style->getConfigDependencyName();
    }
    return $dependencies;
  }

  /**
   * Get the responsive image style entity.
   *
   * @return \Drupal\responsive_image\ResponsiveImageStyleInterface
   *   Responsive image style entity.
   */
  public function getResponsiveImageStyle() {
    return $this->responsiveImageStyleStorage->load($this->getSetting('responsive_image_style'));
  }

  /**
   * Get responsive style cache metadata.
   *
   * The responsive image style entity's cache metadata doesn't contain
   * information on the images styles it uses but this is needed here as we
   * want to make sure the cache of the rendered media is cleared when an
   * image style is updated.
   *
   * @todo check if that's really needed.
   *
   * @param \Drupal\responsive_image\ResponsiveImageStyleInterface $responsive_image_style
   *   Responsive image style entity.
   *
   * @return array
   *   Cache metadata.
   */
  public function getResponsiveImageStyleCacheableMetadata(ResponsiveImageStyleInterface $responsive_image_style) {
    $cache_metadata = CacheableMetadata::createFromObject($responsive_image_style);
    $image_styles = $this->imageStyleStorage->loadMultiple($responsive_image_style->getImageStyleIds());

    // Merge the cache data foreach image style of the responsive image style.
    foreach ($image_styles as $image_style) {
      $cache_metadata = $cache_metadata->merge(CacheableMetadata::createFromObject($image_style));
    }
    return $cache_metadata;
  }

  /**
   * Get and validate the custom URL.
   *
   * @todo Replace UrlHelper::isValid which has several issues (unless fixed):
   * - https://www.drupal.org/project/drupal/issues/2691099
   * - https://www.drupal.org/project/drupal/issues/2474191
   *
   * @param array $token_data
   *   Context token data used when doing token replacement.
   * @param array $token_options
   *   Token replacement options.
   *
   * @return string|null
   *   Validated URL or NULL.
   */
  public function getCustomUrl(array $token_data = [], array $token_options = []) {
    // Tokens can be used.
    $url = $this->replaceTokens($this->getSetting('image_link_url'), $token_data, $token_options);

    // Try to grab the href attribute if the replaced token is a link.
    preg_match('/<a[^>]* href="([^"]+)"[^>]*>/', $url, $match);
    $url = $match[1] ?? $url;

    // The URL maybe an internal url to an entity like entity:node/123 etc.
    // so we need to get the computed url. If the url is external we check its
    // validity.
    try {
      $url = Url::fromUri($url)->toString();

      if (UrlHelper::isExternal($url) && !UrlHelper::isValid($url)) {
        throw new \Exception();
      }
    }
    catch (\Exception $exception) {
      return NULL;
    }

    return $url;
  }

  /**
   * Get the custom alt text.
   *
   * @param array $token_data
   *   Context token data used when doing token replacement.
   * @param array $token_options
   *   Token replacement options.
   *
   * @return string
   *   Link title.
   */
  public function getCustomAlt(array $token_data = [], array $token_options = []) {
    // Tokens can be used.
    $alt = $this->replaceTokens($this->getSetting('image_alt_value'), $token_data, $token_options);
    // The text will be santized when displayed as an attribute so this prevents
    // double encoding.
    return Html::decodeEntities(strip_tags($alt));
  }

  /**
   * Replace tokens.
   *
   * @param string $text
   *   Text with replaceable tokens.
   * @param array $data
   *   Token replacement data, like the media entity etc.
   * @param array $options
   *   Token replacements options. Unless specified otherwise by setting 'clear'
   *   to false, tokens that couldn't be replaced will be removed.
   *
   * @return string
   *   Text with replaced token.
   */
  public function replaceTokens($text, array $data = [], array $options = []) {
    return $this->token->replace($text, $data, $options + ['clear' => TRUE]);
  }

}
