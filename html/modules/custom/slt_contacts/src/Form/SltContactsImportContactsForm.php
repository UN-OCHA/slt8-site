<?php

namespace Drupal\slt_contacts\Form;

use Drupal\Component\Utility\Unicode;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\Entity\Node;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Row;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * SLT contacts import form implementation.
 */
class SltContactsImportContactsForm extends FormBase {

  /**
   * Supported spreadsheet columns.
   *
   * Each column defintion can contain the following values:
   * - legacy: indicates that the column itself is not used as is anymore
   *   though it may still be used when processing other columns.
   * - mandatory: indicates that the column must be present.
   * - alternatives: list of alternative columns that may contain the data if
   *   the column is not present
   * - multiple: indicates that there may be multiple values for the column.
   * - field: indicates the contact node field to which the column data will be
   *   added.
   * - preprocess: array with a callback as first item and additional
   *   arguments. The cell data will be preprocessed by this callback when
   *   parsing a row. The callback will be passed the whole row's data by
   *   reference and is expecting to modify the data (no return value).
   *   The callback should return FALSE if the value was invalid.
   * - process: array with a callback as first item and additional
   *   arguments. The cell data will be processed by this callback when
   *   added to the field.
   * - table_display: array with a callback as first item and additional
   *   arguments. The function will be called to generate the value to
   *   display in the contacts table in the confirmation step. The callback will
   *   be passed the whole row's data.
   *
   * @var array
   */
  public static $columns = [
    'agency' => [
      'field' => 'field_agency',
      'process' => [
        '\Drupal\slt_contacts\Form\SltContactsImportContactsForm::getTermId',
        'agency',
      ],
    ],
    'country' => [
      'mandatory' => TRUE,
      'multiple' => TRUE,
      'alternatives' => ['duty station country'],
      'field' => 'field_country',
      'process' => [
        '\Drupal\slt_contacts\Form\SltContactsImportContactsForm::getTermId',
        'country',
      ],
      'preprocess' => [
        '\Drupal\slt_contacts\Form\SltContactsImportContactsForm::preprocessCountry',
      ],
      'table_display' => [
        '\Drupal\slt_contacts\Form\SltContactsImportContactsForm::displayCountry',
      ],
    ],
    'duty station country' => [
      'field' => 'field_duty_station_country',
      'process' => [
        '\Drupal\slt_contacts\Form\SltContactsImportContactsForm::getTermId',
        'country',
      ],
      'preprocess' => [
        '\Drupal\slt_contacts\Form\SltContactsImportContactsForm::preprocessDutyStationCountry',
      ],
    ],
    'duty station region' => [
      'field' => 'field_duty_station_region',
      'process' => [
        '\Drupal\slt_contacts\Form\SltContactsImportContactsForm::getTermId',
        'duty_station_region',
      ],
    ],
    'email' => [
      'mandatory' => TRUE,
      'field' => 'field_email',
      'preprocess' => [
        '\Drupal\slt_contacts\Form\SltContactsImportContactsForm::preprocessEmail',
      ],
    ],
    'first name' => [
      'legacy' => TRUE,
    ],
    'functional title' => [
      'field' => 'field_functional_title',
    ],
    'last name' => [
      'legacy' => TRUE,
    ],
    'name' => [
      'mandatory' => TRUE,
      'alternatives' => ['last name'],
      'field' => 'title',
      'preprocess' => [
        '\Drupal\slt_contacts\Form\SltContactsImportContactsForm::preprocessName',
      ],
    ],
    'phone' => [
      'multiple' => TRUE,
      'alternatives' => ['phone type'],
      'field' => 'field_phone',
      'preprocess' => [
        '\Drupal\slt_contacts\Form\SltContactsImportContactsForm::preprocessPhone',
      ],
      'table_display' => [
        '\Drupal\slt_contacts\Form\SltContactsImportContactsForm::displayPhone',
      ],
    ],
    'phone type' => [
      'legacy' => TRUE,
      'multiple' => TRUE,
    ],
    'security title' => [
      'field' => 'field_security_title',
    ],
  ];

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Configuration Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   File system service.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   Config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(Connection $database, FileSystemInterface $file_system, ConfigFactory $config_factory, EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger) {
    $this->database = $database;
    $this->fileSystem = $file_system;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('file_system'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('messenger')
    );
  }

  /**
   * Get a setting for the slt_contacts module.
   *
   * @param string $setting
   *   Setting name.
   * @param mixed $default
   *   Default value for the setting.
   *
   * @return mixed
   *   Value for the setting.
   */
  public function getSetting($setting, $default = NULL) {
    static $settings;
    if (!isset($settings)) {
      $settings = $this->configFactory->get('slt_contacts.settings');
    }
    return $settings->get($setting) ?? $default;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'slt_contacts_import_contacts_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['actions'] = [
      '#type' => 'actions',
      // Ensure it's at the bottom of the list.
      '#weight' => 10,
    ];

    if ($form_state->has('step') && $form_state->get('step') == 2) {
      return self::buildFormStepTwo($form, $form_state);
    }
    else {
      return self::buildFormStepOne($form, $form_state);
    }
  }

  /**
   * Build the first step of the form with the file upload.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Modified form.
   */
  public function buildFormStepOne(array $form, FormStateInterface $form_state) {
    $form_state->set('step', 1);

    $form['description'] = [
      '#type' => 'item',
      '#title' => $this->t('<h2>Step 1: File Upload</h2>'),
    ];

    $max_rows = $this->getSetting('max_rows', 9999);

    $form['tips'] = [
      '#type' => 'item',
      '#markup' => $this->t('
        <h3>Tips</h3>
        <ul>
          <li>Make sure there is a <strong>header</strong> row.</li>
          <li><strong>Email</strong> column is mandatory</li>
          <li>Either <strong>Name</strong> column or <strong>Last name</strong> column is mandatory</li>
          <li>Maximum %max_rows rows will be processed</li>
          <li>Allowed column names:
            @columns
          </li>
        </ul>
      ', [
        '%max_rows' => $max_rows,
        '@columns' => static::formatList(array_keys(static::$columns)),
      ]),
    ];

    $form['file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Spreadsheet file'),
      '#description' => $this->t('Spreadsheet file with the contacts. Accepted formats are: xls, xlsx, ods, csv.'),
      '#required' => TRUE,
      '#default_value' => $form_state->getValue('file'),
      '#upload_location' => 'temporary://contacts-import',
      '#upload_validators' => [
        'file_validate_extensions' => ['xls xlsx ods csv'],
      ],
    ];

    // Proceed to step 2.
    $form['actions']['next'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Next'),
      '#submit' => ['::submitFormStepOne'],
      '#validate' => ['::validateFormStepOne'],
    ];

    return $form;
  }

  /**
   * Validate the first step of the form.
   *
   * Here we also parse the spreadsheet file and extract the contacts.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateFormStepOne(array &$form, FormStateInterface $form_state) {
    $fids = $form_state->getValue('file');
    if (!empty($fids)) {
      // Load the file object and get its real path as that's what
      // PHPSpreadsheet expects.
      $file = $this->entityTypeManager->getStorage('file')->load(reset($fids));
      $path = $this->fileSystem->realpath($file->getFileUri());
      $max_rows = $this->getSetting('max_rows', 9999);

      // Extract the contacts from the spreadsheet.
      $contacts = static::parseSpreadsheet($path, $max_rows);
      if (empty($contacts['contacts']) && !empty($contacts['errors'])) {
        $form_state->setErrorByName('file', $this->t("Unable to extract contacts from the spreadsheet: \n@errors.", [
          '@errors' => static::formatList($contacts['errors']),
        ]));
      }
      else {
        $form_state->set('contacts', $contacts);
      }
    }
  }

  /**
   * Submit the first step of the form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitFormStepOne(array &$form, FormStateInterface $form_state) {
    $form_state
      ->set('first_step_values', ['file' => $form_state->getValue('file')])
      ->set('step', 2)
      ->setRebuild(TRUE);
  }

  /**
   * Build the second step of the form with the confirmation.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Modified form.
   */
  public function buildFormStepTwo(array $form, FormStateInterface $form_state) {
    $form_state->set('step', 2);

    $form['description'] = [
      '#type' => 'item',
      '#title' => $this->t('<h2>Step 2: Confirmation</h2>'),
    ];

    // Display the table with the list of contacts to import.
    $contacts = $form_state->get('contacts');
    if (!empty($contacts['contacts'])) {
      // Maximum number of contacts to show.
      $count = count($contacts['contacts']);
      $headers = static::getTableHeaders($contacts['columns']);
      $rows = static::getTableRows($headers, $contacts['contacts']);

      $form['contact-list'] = [
        '#type' => 'details',
        '#title' => $this->formatPlural($count,
          '@count contact to import',
          '@count contacts to import'
        ),
        'table' => [
          '#type' => 'table',
          '#header' => $headers,
          '#rows' => $rows,
        ],
      ];
    }

    // Display the errors detected while parsing the spreadsheet.
    if (!empty($contacts['errors'])) {
      $count = count($contacts['errors']);

      $form['error-list'] = [
        '#type' => 'details',
        '#title' => $this->formatPlural($count,
          '@count parsing error',
          '@count parsing errors'
        ),
        'list' => [
          '#theme' => 'item_list',
          '#list_type' => 'ul',
          '#items' => $contacts['errors'],
        ],
      ];
    }

    // Flag to either delete the olds contacts or to append the new list.
    $form['delete_contacts'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete existing contacts'),
      '#default_value' => $form_state->get('delete_contacts') ?? TRUE,
      '#description' => $this->t('Uncheck this box to add the new contacts to the existing list instead of deleting the old ones.'),
    ];

    // Button to go back to the step 1.
    $form['actions']['back'] = [
      '#type' => 'submit',
      '#value' => $this->t('Back'),
      '#submit' => ['::cancelFormStepTwo'],
      // Prevent validation errors as we are going back and the values from
      // the step 2 should be ignored then.
      '#limit_validation_errors' => [],
    ];

    // Submit the form, actually replacing the contacts.
    // We default to base form submit and validation callbacks.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Import contacts'),
    ];

    return $form;
  }

  /**
   * Get the contacts table headers to display in the import confirmation step.
   *
   * @param array $columns
   *   Array of Header columns with the names as keys.
   *
   * @return array
   *   List of table headers.
   */
  public static function getTableHeaders(array $columns) {
    $headers = [];

    // Extract the column alternatives so we can map them to the proper column,
    // ignoring legacy columns.
    $mapping = [];
    foreach (static::$columns as $name => $definition) {
      if (!empty($definition['alternatives'])) {
        foreach ($definition['alternatives'] as $alternative) {
          if (!empty(static::$columns[$alternative]['legacy'])) {
            $mapping[$alternative] = $name;
          }
        }
      }
      if (empty($definition['legacy'])) {
        $mapping[$name] = $name;
      }
    }

    foreach ($columns as $name => $dummy) {
      if (isset($mapping[$name])) {
        $headers[$mapping[$name]] = TRUE;
      }
    }

    return array_keys($headers);
  }

  /**
   * Get the contacts table rows to display in the import confirmation step.
   *
   * @param array $headers
   *   List of header column names.
   * @param array $contacts
   *   List of contact data extracted from the spreadsheet.
   *
   * @return array
   *   Table rows. Each row contains cells for each column. Each cell can be
   *   either a string or a render array.
   */
  public static function getTableRows(array $headers, array $contacts) {
    $rows = [];
    foreach ($contacts as $data) {
      $row = [];
      foreach ($headers as $name) {
        if (isset(static::$columns[$name]['table_display'])) {
          $row[$name] = static::call(static::$columns[$name]['table_display'], $data);
        }
        else {
          $row[$name] = $data[$name] ?? '';
        }
      }
      $rows[] = $row;
    }
    return $rows;
  }

  /**
   * Return to the first step.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function cancelFormStepTwo(array &$form, FormStateInterface $form_state) {
    $form_state
      ->setValues($form_state->get('first_step_values'))
      ->set('step', 1)
      ->setRebuild(TRUE);
  }

  /**
   * Validate the form (after step 2).
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Nothing to validate.
  }

  /**
   * Submit the form (after step 2).
   *
   * Generate the batch to delete/create the contacts.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch_size = $this->getSetting('batch_size', 50);

    // We will (optionally) delete the old contacts and create the new ones
    // in batches as that takes time.
    $contacts = $form_state->get('contacts');
    if (!empty($contacts['contacts'])) {
      $operations = [];

      // We log the parsing errors (if any) during the batch process because
      // otherwise it slows down too much the parsing. Also it makes more sense
      // to log them when actually proceeding with the import.
      $operations[] = [
        __CLASS__ . '::logParsingInfo',
        [$contacts['path'], $contacts['errors']],
      ];

      $delete_contacts = $form_state->getValue('delete_contacts');
      if (!empty($delete_contacts)) {
        // Create batch steps to delete the existing nodes.
        $records = $this->entityTypeManager->getStorage('node')->getQuery()
          ->condition('type', 'contact')
          ->accessCheck(FALSE)
          ->execute();

        if (!empty($records)) {
          foreach (array_chunk($records, $batch_size) as $ids) {
            $operations[] = [__CLASS__ . '::deleteContactNodes', [$ids]];
          }
        }
      }

      // Create batch steps to create the new contact nodes.
      foreach (array_chunk($contacts['contacts'], $batch_size) as $data) {
        $operations[] = [__CLASS__ . '::createContactNodes', [$data]];
      }

      $batch = [
        'title' => $this->t('Importing contacts...'),
        'operations' => $operations,
        'finished' => __CLASS__ . '::batchFinished',
      ];
      batch_set($batch);
    }
    else {
      $this->messenger->addWarning($this->t('No contacts to import. Old contacts were not deleted.'));
    }
  }

  /**
   * Log the parsing errors.
   *
   * @param string $path
   *   The path of the spreadsheet.
   * @param array $errors
   *   The parsing errors.
   * @param array $context
   *   The batch context.
   */
  public static function logParsingInfo($path, array $errors, array &$context) {
    // Log the filename to help make sense of the parsing errors.
    static::log(new FormattableMarkup('Parsed spreadsheet: @path', [
      '@path' => $path,
    ]), 'info');

    // We log the errors as notices because they don't impact the whole site.
    foreach ($errors as $error) {
      static::log($error, 'notice');
    }

    // Set a message for the current batch and update the progress status.
    $count = count($errors);
    $context['message'] = \Drupal::translation()->formatPlural($count,
      'Logged @count parsing error.',
      'Logged @count parsing errors.'
    );
    $context['results']['logged'][] = $count;
  }

  /**
   * Delete the nodes with the given ids.
   *
   * @param array $ids
   *   Node ids.
   * @param array $context
   *   The batch context.
   */
  public static function deleteContactNodes(array $ids, array &$context) {
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');

    // Delete the old contact nodes.
    $nodes = $node_storage->loadMultiple($ids);
    $node_storage->delete($nodes);

    // Set a message for the current batch and update the progress status.
    $count = count($ids);
    $context['message'] = \Drupal::translation()->formatPlural($count,
      'Deleted @count old contact.',
      'Deleted @count old contacts.'
    );
    $context['results']['deleted'][] = $count;
  }

  /**
   * Create new contact nodes.
   *
   * @param array $contacts
   *   Contact data.
   * @param array $context
   *   The batch context.
   */
  public static function createContactNodes(array $contacts, array &$context) {
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');

    // Create a new node for each contact.
    foreach ($contacts as $contact) {
      // Check if a node with the email address already exists and re-use it.
      $nodes = $node_storage->loadByProperties([
        'type' => 'contact',
        'field_email' => $contact['email'],
      ]);

      if (!empty($nodes)) {
        $node = reset($nodes);
      }
      else {
        $node = $node_storage->create(['type' => 'contact']);
      }

      // Update the node fields.
      static::updateNode($node, $contact);
      $node->save();
    }

    // Set a message for the current batch and update the progress status.
    $count = count($contacts);
    $context['message'] = \Drupal::translation()->formatPlural($count,
      'Created @count new contact.',
      'Created @count new contacts.'
    );
    $context['results']['created'][] = $count;
  }

  /**
   * Update a contact node's fields with new data.
   *
   * @param \Drupal\node\Entity\Node $node
   *   Node to update.
   * @param array $data
   *   New contact data.
   *
   * @todo review if we should get the node fields via ::getFields() and
   * reset all the fields for which there is no corresponding data.
   */
  public static function updateNode(Node $node, array $data) {
    foreach (static::$columns as $name => $definition) {
      if (!isset($definition['field'])) {
        continue;
      }
      $field = $definition['field'];
      $value = NULL;
      if (!empty($data[$name])) {
        $value = $data[$name];

        if (isset($definition['process'])) {
          $value = static::call($definition['process'], $value);
        }
      }
      $node->set($field, $value);
    }
  }

  /**
   * Display message after the batch import is finished.
   *
   * @param bool $success
   *   Whether the batch process succeeeded or not.
   * @param array $results
   *   Batch results.
   * @param array $operations
   *   List of batch operations.
   */
  public static function batchFinished($success, array $results, array $operations) {
    if ($success) {
      if (!empty($results['deleted'])) {
        \Drupal::messenger()->addStatus(t('Deleted %deleted old contacts.', [
          '%deleted' => array_sum($results['deleted']),
        ]));
      }
      if (!empty($results['created'])) {
        \Drupal::messenger()->addStatus(t('Created %created new contacts.', [
          '%created' => array_sum($results['created']),
        ]));
      }
    }
    else {
      // @todo Show a more useful error message?
      \Drupal::messenger()->addError(t('No contacts to import. Old contacts were not deleted.'));
    }
  }

  /**
   * Get a taxonomy term ID. Create a new term if necessary.
   *
   * @param string $vocabulary
   *   Taxonomy vocabulary.
   * @param string|array $name
   *   Taxonomy term name or array of names.
   *
   * @return int|null
   *   Id of the first term, newly created if doesn't already exists.
   */
  public static function getTermId($vocabulary, $name) {
    $multiple = is_array($name);

    $names = array_filter(array_map('trim', $multiple ? $name : [$name]));

    if (empty($names)) {
      return NULL;
    }

    $results = [];
    foreach ($names as $name) {
      $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

      // Sanitize and truncate the term if necessary.
      $words = preg_split('/' . Unicode::PREG_CLASS_WORD_BOUNDARY . '/u', $name, -1, PREG_SPLIT_NO_EMPTY);
      if (count($words) > 10) {
        $name = implode(' ', array_slice($words, 0, 10)) . ' ...';
      }

      // Get any existing taxonomy term matching the given term name.
      $terms = $term_storage->loadByProperties([
        'vid' => $vocabulary,
        'name' => $name,
      ]);

      // Get the first existing term or create one.
      if (!empty($terms)) {
        $term = reset($terms);
      }
      else {
        $term = $term_storage->create([
          'vid' => $vocabulary,
          'name' => $name,
        ]);
        $term->save();
      }
      $results[] = $term->id();
    }

    return $multiple ? $results : reset($results);
  }

  /**
   * Extract contacts from a spreadsheet.
   *
   * @param string $path
   *   File path.
   * @param int $max_rows
   *   Maximum number rows to parse.
   *
   * @return array
   *   Associative array with the spreadsheet file path, the header columns,
   *   contact list and potential parsing errors.
   */
  public static function parseSpreadsheet($path, $max_rows) {
    $columns = [];
    $contacts = [];
    $errors = [];

    // We wrap this code in a try...catch because PHPSpreadsheet can throw
    // various exceptions when parsing a spreadsheet.
    try {
      // Get the worksheet to work with (pun intended).
      $sheet = static::getWorksheet($path);

      // Get the row to which will stop the parsing.
      $max_rows = min($sheet->getHighestDataRow(), $max_rows);

      // Parse the sheet, extracting contact data.
      $header_row_found = FALSE;
      foreach ($sheet->getRowIterator(1, $max_rows) as $row) {
        // Parse the row to see if it's the header one.
        if ($header_row_found === FALSE) {
          $data = static::parseHeaderRow($sheet, $row);
          // There are errors if the header row was found but some mandatory
          // columns are missing. In that case we abort the parsing.
          if (!empty($data['errors'])) {
            $errors = array_merge($errors, $data['errors']);
            break;
          }
          // Otherwise if the header row was found, we store the columns and
          // make sure we can start parsing the data. If not, we continue
          // looking for it.
          elseif (!empty($data['columns'])) {
            $columns = $data['columns'];
            $header_row_found = TRUE;
          }
        }
        // Parse a contact data row.
        else {
          $data = static::parseDataRow($columns, $sheet, $row);
          if (!empty($data['data'])) {
            // Log the errors only for "useful" rows with some data.
            if (!empty($data['errors'])) {
              $errors = array_merge($errors, $data['errors']);
            }
            // If there is an email, merge the data with the contact entry with
            // the same email address if any, otherwise create a new entry if
            // the data is "valid", meaning, it has all the mandatory fields.
            if (!empty($data['data']['email'])) {
              $email = $data['data']['email'];
              if (isset($contacts[$email])) {
                $contacts[$email] = static::mergeContactData($contacts[$email], $data['data']);
              }
              elseif (!empty($data['valid'])) {
                $contacts[$email] = $data['data'];
              }
            }
            // Otherwise, merge the fields that can accept multiple values with
            // the latest contact entry. This is, for example, to handle cases
            // where a phone number is on its own row and needs to be added to
            // the list of phone number of the previously extracted contact.
            elseif (!empty($contacts)) {
              $email = array_key_last($contacts);
              $contacts[$email] = static::mergeContactData($contacts[$email], $data['data'], TRUE);
            }
          }
        }
      }
    }
    catch (\Exception $exception) {
      $errors[] = $exception->getMessage();
    }

    return [
      'path' => $path,
      'columns' => $columns,
      'contacts' => $contacts,
      'errors' => $errors,
    ];
  }

  /**
   * Load a spreadsheet and return its first worksheet.
   *
   * @param string $path
   *   File path.
   *
   * @return \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
   *   First worksheet.
   */
  public static function getWorksheet($path) {
    if (!file_exists($path)) {
      throw new \Exception("The spreadsheet file doesn't exist.");
    }

    // Get the spreadsheet type.
    $filetype = IOFactory::identify($path);

    // Create the spreadsheet reader.
    $reader = IOFactory::createReader($filetype);

    // Load only the first sheet to save memory (see comment below).
    $reader->setLoadSheetsOnly(0);

    // Start reading the file.
    $spreadsheet = $reader->load($path);

    // We only deal with the first sheet.
    //
    // @todo if ever necessary we could add an option to the form to select
    // which sheet to use or attempt to parse all the sheets.
    return $spreadsheet->getSheet(0);
  }

  /**
   * Parse a row, attempting to determine if its the header row.
   *
   * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
   *   Spreadsheet worksheet.
   * @param \PhpOffice\PhpSpreadsheet\Worksheet\Row $row
   *   Spreadsheet row.
   *
   * @return array
   *   Empty array if the row is not the header row, otherwise, return an
   *   associative array with the found columns which is an associative array
   *   with the column name (header) as value and the column letter as value.
   *   If the row is the header one but some mandatory columns are missing, the
   *   returnning array will have an `errors` key with the list of errors.
   */
  public static function parseHeaderRow(Worksheet $sheet, Row $row) {
    $columns = [];
    foreach ($row->getCellIterator() as $cell) {
      $value = mb_strtolower(static::getCellValue($sheet, $cell->getCoordinate()));
      // Fix malformed column names...
      $value = trim(preg_replace('/\s+/u', ' ', $value));
      if (isset($value, static::$columns[$value]) && !isset($columns[$value])) {
        $columns[$value] = $cell->getColumn();
      }
    }

    if (empty($columns)) {
      return [];
    }

    // Validate mandatory columns.
    $errors = [];
    foreach (static::$columns as $name => $definition) {
      if (!static::checkMandatoryField($name, $definition, $columns)) {
        // We use TranslatableMarkup so that the error can be displayed
        // translated in the confirmation step.
        $errors[] = new TranslatableMarkup('Missing @column column.', [
          '@column' => $name,
        ]);
      }
    }

    return [
      'columns' => $columns,
      'errors' => $errors,
    ];
  }

  /**
   * Parse a row with contact data.
   *
   * @todo Check if we need to use getCalculatedValue() instead of getValue().
   *
   * @param array $columns
   *   Associative array of header columns with their associated column index.
   * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
   *   Spreadsheet worksheet.
   * @param \PhpOffice\PhpSpreadsheet\Worksheet\Row $row
   *   Spreadsheet row.
   *
   * @return array
   *   Associative array with the following keys:
   *   - data: associative array mapping the column names to their values,
   *   - errors: array with a list of parsing errors,
   *   - valid: a flag to indicate that the data contains all the mandatory
   *   fields.
   */
  public static function parseDataRow(array $columns, Worksheet $sheet, Row $row) {
    $index = $row->getRowIndex();
    $data = [];
    $errors = [];
    $valid = TRUE;

    // Get the data from the row foreach column with a recognized header.
    foreach ($columns as $name => $column) {
      $data[$name] = static::getCellValue($sheet, $column . $index);
    }

    // Skip the row if empty.
    if (count(array_filter($data)) === 0) {
      return [];
    }

    // Process the row's data.
    foreach (static::$columns as $name => $definition) {
      if (isset($definition['preprocess'])) {
        if (!static::call($definition['preprocess'], $data)) {
          $errors[$name] = new TranslatableMarkup('Invalid @column on row @row.', [
            '@column' => $name,
            '@row' => $index,
          ]);
        }
      }
    }

    // Check if the data contains only "multiple" fields, in which case, we
    // can skip the validation of the mandatory fields, because it's a row that
    // should be merged with the previous one.
    $skip = TRUE;
    foreach ($data as $name => $value) {
      if (!empty($value) && empty(static::$columns[$name]['multiple'])) {
        $skip = FALSE;
        break;
      }
    }

    // Check mandatory fields. This is not done in the loop above because
    // the data may change during preprocessing.
    if ($skip === FALSE) {
      foreach (static::$columns as $name => $definition) {
        if (!static::checkMandatoryField($name, $definition, $data)) {
          // No need to add different error messages for the same field, for
          // example if the field data was found invalid during preprocessing.
          if (!isset($errors[$name])) {
            $errors[$name] = new TranslatableMarkup('Missing @column on row @row.', [
              '@column' => $name,
              '@row' => $index,
            ]);
          }
          $valid = FALSE;
        }
      }
    }

    return [
      'data' => $data,
      'errors' => array_values($errors),
      'valid' => $valid,
    ];
  }

  /**
   * Get a cell value.
   *
   * This extracts the value of a cell. If the cell is merged with other cells
   * we extract the combine value for the whole merge range.
   *
   * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
   *   Spreadsheet worksheet.
   * @param string $reference
   *   Cell reference (ex: A1).
   *
   * @return string
   *   Extracted value, defaulting to an empty string.
   */
  public static function getCellValue(Worksheet $sheet, $reference) {
    static $references;
    static $values;

    if (!isset($references, $values)) {
      list($references, $values) = static::extractMergedCells($sheet);
    }

    if (isset($references[$reference])) {
      return $values[$references[$reference]];
    }
    elseif ($sheet->getCellCollection()->has($reference)) {
      return trim($sheet->getCellCollection()->get($reference)->getValue());
    }
    return '';
  }

  /**
   * Extract the values for the merged cells.
   *
   * We store the merged cells references and the merge range values so that
   * we don't have to parse the merge ranges every time we try to get a cell
   * value. This speeds tremendously the spreadsheet parsing at the cost of
   * increased memory usage.
   *
   * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
   *   Worksheet from which to extract the merged cells data.
   *
   * @return array
   *   Array containing 2 elements: an associative array  of the references of
   *   the cells included in merge ranges mapped to to the range reference, and
   *   an associative array mapping range references to their values.
   */
  public static function extractMergedCells(Worksheet $sheet) {
    $references = [];
    $values = [];

    foreach ($sheet->getMergeCells() as $range) {
      // Extract all the merged cell references and store their mapping to
      // the merge range. We don't copy directly the merge range value to
      // reduce memory usage.
      foreach (Coordinate::extractAllCellReferencesInRange($range) as $index => $reference) {
        // The first cell of the range is supposed to contain the value of the
        // range.
        // @see \PhpOffice\PhpSpreadsheet\Cell\Cell::isMergeRangeValueCell()
        if ($index === 0) {
          if ($sheet->getCellCollection()->has($reference)) {
            $values[$range] = trim($sheet->getCellCollection()->get($reference)->getValue());
          }
          else {
            $values[$range] = '';
          }
        }
        $references[$reference] = $range;
      }
    }
    return [$references, $values];
  }

  /**
   * Preprocess the email field.
   *
   * This checks that the value is a valid email address and discard it
   * otherwise.
   *
   * @param array $data
   *   Row data.
   *
   * @return bool
   *   FALSE if the data was invalid, TRUE otherwise.
   */
  public static function preprocessEmail(array &$data) {
    static $validator;
    if (!isset($validator)) {
      $validator = \Drupal::service('email.validator');
    }
    if (!empty($data['email']) && !$validator->isValid($data['email'])) {
      unset($data['email']);
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Preprocess the name field.
   *
   * This attempts to generate the name by combining the first name and last
   * name if the name is not defined.
   *
   * @param array $data
   *   Row data.
   *
   * @return bool
   *   FALSE if the data was invalid, TRUE otherwise.
   */
  public static function preprocessName(array &$data) {
    if (empty($data['name'])) {
      $name = [];
      if (!empty($data['first name'])) {
        $name[] = $data['first name'];
      }
      if (!empty($data['last name'])) {
        $name[] = $data['last name'];
      }
      $data['name'] = implode(' ', $name);
    }
    unset($data['first name']);
    unset($data['last name']);
    return TRUE;
  }

  /**
   * Preprocess the duty station country field.
   *
   * This extracts the countries from the `country` field or from the
   * `duty station country` field if the former is empty.
   *
   * @param array $data
   *   Row data.
   *
   * @return bool
   *   FALSE if the data was invalid, TRUE otherwise.
   */
  public static function preprocessCountry(array &$data) {
    $countries = [];
    if (!empty($data['country'])) {
      $countries = array_merge($countries, array_map('trim', explode('|', $data['country'])));
    }
    elseif (!empty($data['duty station country'])) {
      $countries = array_merge($countries, array_map('trim', explode('|', $data['duty station country'])));
    }
    $data['country'] = array_unique(array_filter($countries), SORT_STRING);
    return TRUE;
  }

  /**
   * Preprocess the duty station country field.
   *
   * This gets the first country from the `duty station country` field or from
   * the `country` field if the former is empty.
   *
   * @param array $data
   *   Row data.
   *
   * @return bool
   *   FALSE if the data was invalid, TRUE otherwise.
   */
  public static function preprocessDutyStationCountry(array &$data) {
    $countries = [];
    if (!empty($data['duty station country'])) {
      $countries = array_map('trim', explode('|', $data['duty station country']));
    }
    elseif (!empty($data['country'])) {
      $countries = array_map('trim', explode('|', $data['country']));
    }
    $countries = array_filter($countries);
    $data['duty station country'] = reset($countries) ?? '';
    return TRUE;
  }

  /**
   * Preprocess the phone field.
   *
   * This extract the phone data (type and number) from the potential phone
   * fields: `phone` and `phone type`.
   *
   * Note: the phone field as opposed to the other field can contain multiple
   * values.
   *
   * @param array $data
   *   Row data.
   *
   * @return bool
   *   FALSE if the data was invalid, TRUE otherwise.
   */
  public static function preprocessPhone(array &$data) {
    $phone = [];
    if (!empty($data['phone'])) {
      $phone[] = static::extractPhoneData($data['phone']);
    }
    if (!empty($data['phone type'])) {
      $phone[] = static::extractPhoneData($data['phone type']);
    }
    $data['phone'] = array_unique(array_filter($phone), SORT_REGULAR);
    unset($data['phone type']);
    return TRUE;
  }

  /**
   * Parse a string containing a phone number and optionally its type.
   *
   * @todo Preserve the random text between the phone type and the phone
   * number?
   *
   * @param string $data
   *   String containing the phone number.
   *
   * @return array
   *   Associative array with the phone type and phone number.
   */
  public static function extractPhoneData($data) {
    static $pattern = '
      (?<type>\(?[^0-9):]+[):])?                    # Phone type.
      \s*                                           # Any space.
      ([a-zA-Z-]+\s*)?                              # N/A value.
      (?<number>[0-9 ()+-]+)                        # Phone number.
      \s*                                           # Any space.
      ((,|Ext\s*:)\s*(?<extension>.+))?             # Extension.
    ';

    if (preg_match('~^' . $pattern . '$~x', trim($data), $matches) === 1) {
      $number = trim($matches['number']);

      // Add the extension to the phone number if defined.
      $extension = trim($matches['extension'] ?? '');
      if (!empty($extension)) {
        $number .= ',' . $extension;
      }

      return [
        'type' => trim($matches['type'] ?? '', '(): '),
        'number' => $number,
      ];
    }

    return [];
  }

  /**
   * Get a render array to display a contact's phone numbers as a list.
   *
   * @param array $data
   *   Contact data.
   *
   * @return array|string
   *   Render array or empty string.
   */
  public static function displayCountry(array $data) {
    if (empty($data['country'])) {
      return '';
    }
    return static::formatList($data['country']);
  }

  /**
   * Get a render array to display a contact's phone numbers as a list.
   *
   * @param array $data
   *   Contact data.
   *
   * @return array|string
   *   Render array or empty string.
   */
  public static function displayPhone(array $data) {
    if (empty($data['phone'])) {
      return '';
    }

    $items = [];
    foreach ($data['phone'] as $phone) {
      $number = $phone['number'];
      if (!empty($phone['type'])) {
        $number = $phone['type'] . ': ' . $number;
      }
      $items[] = $number;
    }

    return static::formatList($items);
  }

  /**
   * Merge contact data, replacing empty values and adding new phone numbers.
   *
   * @param array $data1
   *   Contact data.
   * @param array $data2
   *   Contact data.
   * @param bool $multiple_only
   *   Whether to limit the merging to fields that can have multiple values or
   *   not.
   *
   * @return array
   *   Merged contact data.
   */
  public static function mergeContactData(array $data1, array $data2, $multiple_only = FALSE) {
    $data = [];

    foreach (static::$columns as $name => $definition) {
      if (!empty($definition['multiple'])) {
        $values = [];
        if (!empty($data1[$name])) {
          $values = array_merge($values, $data1[$name]);
        }
        if (!empty($data2[$name])) {
          $values = array_merge($values, $data2[$name]);
        }
        $data[$name] = array_unique($values, SORT_REGULAR);
      }
      elseif (empty($multiple_only) && empty($data1[$name]) && !empty($data2[$name])) {
        $data[$name] = $data2[$name];
      }
      elseif (!empty($data1[$name])) {
        $data[$name] = $data1[$name];
      }
    }

    return $data;
  }

  /**
   * Helper method to display a list of items as a HTML list.
   *
   * @param array $items
   *   List of strings.
   *
   * @return \Drupal\Component\Render\FormattableMarkup
   *   FormattableMarkup object containing the HTML list that can be passed
   *   as placeholder replacement to `t()`.
   */
  public static function formatList(array $items) {
    $html = '<ul><li>' . implode('</li><li>', $items) . '</li></ul>';
    return new FormattableMarkup($html, []);
  }

  /**
   * Check of the given data contains the mandatory field data.
   *
   * @param string $name
   *   Column name.
   * @param array $definition
   *   Column definition.
   * @param array $data
   *   Field data.
   *
   * @return bool
   *   Whether the field is present or not.
   */
  public static function checkMandatoryField($name, array $definition, array $data) {
    if (empty($definition['mandatory'])) {
      return TRUE;
    }
    if (!empty($data[$name])) {
      return TRUE;
    }
    // Check if there is an alternative column.
    if (isset($definition['alternatives'])) {
      foreach ($definition['alternatives'] as $alternative) {
        if (isset($data[$alternative])) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Call a function with the given arguments and data.
   *
   * @param array $arguments
   *   Array with the callable function/method as first element and with the
   *   rest as parameters to pass to the callable.
   * @param mixed $data
   *   Additional data to pass to the callable. It is passed by reference and
   *   may be modified by the callable.
   *
   * @return mixed
   *   The result of the call.
   */
  public static function call(array $arguments, &$data) {
    $callable = array_shift($arguments);
    $arguments[] = &$data;
    return call_user_func_array($callable, $arguments);
  }

  /**
   * Log a message.
   *
   * @param mixed $message
   *   String-ish value. If the message is an instance of
   *   \Drupal\Core\StringTranslation\TranslatableMarkup then we build a non
   *   translated message as the logs are for internal information and it
   *   doesn't make sense to have them in the display language of the current
   *   user.
   * @param string $level
   *   Log level.
   */
  public static function log($message, $level = 'info') {
    if ($message instanceof TranslatableMarkup) {
      $message = new FormattableMarkup($message->getUntranslatedString(), $message->getArguments());
    }
    \Drupal::logger('slt-contact-import')->log($level, $message);
  }

}
