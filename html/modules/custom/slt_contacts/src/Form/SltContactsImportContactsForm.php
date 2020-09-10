<?php

namespace Drupal\slt_contacts\Form;

use Drupal\Component\Utility\Unicode;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
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
      'legacy' => TRUE,
    ],
    'duty station country' => [
      'alternatives' => ['country'],
      'field' => 'field_station_country',
      'process' => [
        '\Drupal\slt_contacts\Form\SltContactsImportContactsForm::getTermId',
        'country',
      ],
      'preprocess' => [
        '\Drupal\slt_contacts\Form\SltContactsImportContactsForm::preprocessDutyStationCountry',
      ],
    ],
    'duty station region' => [
      'field' => 'field_station_region',
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
  private $database;

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
   * Class constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   Config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   */
  public function __construct(Connection $database, ConfigFactory $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->database = $database;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('config.factory'),
      $container->get('entity_type.manager')
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
    return 'slt_contacts_import_spreadsheet_form';
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
      $file = $this->entityTypeManager->getStorage('file')->load(reset($fids));
      $max_rows = $this->getSetting('max_rows', 9999);

      // Extract the contacts and store them temporarily.
      // @todo Delete the file after parsing it?
      $contacts = $this->parseSpreadsheet($file, $max_rows);
      if (empty($contacts['contacts']) && !empty($contacts['errors'])) {
        $form_state->setErrorByName('file', $this->t("Unable to extract contacts from the spreadsheet: \n@errors.", [
          '@errors' => static::formatList($contacts['errors']),
        ]));
      }
      else {
        $form_state->setTemporaryValue('contacts', $contacts);
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

    // Maximum number of rows and errors to show.
    $extract_size = $this->getSetting('extract_size', 50);

    // Display the table with the list of contacts to import.
    $contacts = $form_state->getTemporaryValue('contacts');
    if (!empty($contacts['contacts'])) {
      $count = count($contacts['contacts']);
      $headers = static::getTableHeaders($contacts['columns']);
      $rows = static::getTableRows($headers, $contacts['contacts'], $extract_size);

      $form['contacts'] = [
        '#type' => 'table',
        '#caption' => $this->formatPlural($count, '@count contact to import - Sample:', '@count contacts to import - Sample:'),
        '#header' => $headers,
        '#rows' => $rows,
      ];
    }

    // Display the errors detected while parsing the spreadsheet.
    if (!empty($contacts['errors'])) {
      $form['errors'] = [
        '#type' => 'item_list',
        '#title' => $this->t('Parsing errors'),
        '#items' => array_slice($contacts['errors'], 0, $extract_size),
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
          $mapping[$alternative] = $name;
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
   * @param int $limit
   *   Maximum number of row to display.
   *
   * @return array
   *   Table rows. Each row contains cells for each column. Each cell can be
   *   either a string or a render array.
   */
  public static function getTableRows(array $headers, array $contacts, $limit = 50) {
    $rows = [];
    foreach (array_slice($contacts, 0, $limit) as $data) {
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
   * Generate the contacts.
   *
   * @todo show a progress bar?
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch_size = $this->getSetting('batch_size', 50);
    $base_class = '\Drupal\slt_contacts\Form\SltContactsImportContactsForm';

    $operations = [];

    $delete_contacts = $form_state->get('delete_contacts');
    if (!empty($delete_contacts)) {
      // Create batch steps to delete the existing nodes.
      $records = $this->database->select('node', 'n')
        ->fields('n', ['nid'])
        ->condition('n.type', 'contact', '=')
        ->execute();

      if (!empty($records)) {
        foreach (array_chunk($records->fetchCol(), $batch_size) as $ids) {
          $operations[] = [$base_class . '::deleteContactNodes', [$ids]];
        }
      }
    }

    // Create batch steps to create the new contact nodes.
    $contacts = $form_state->getTemporaryValue('contacts') ?? [];
    foreach (array_chunk($contacts, $batch_size) as $data) {
      $operations[] = [$base_class . '::createContactNodes', [$data]];
    }

    $batch = [
      'title' => $this->t('Importing contacts...'),
      'operations' => $operations,
      'finished' => $base_class . '::batchFinished',
    ];
    batch_set($batch);
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
    $context['message'] = t('Deleted %count contacts.', ['%count' => $count]);
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
    $context['message'] = t('Created %count contacts.', ['%count' => $count]);
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
      $parts = [];
      if (!empty($results['deleted'])) {
        $parts[] = t('Deleted %deleted old contacts.', [
          '%deleted' => array_sum($results['deleted']),
        ]);
      }
      if (!empty($results['created'])) {
        $parts[] = t('Created %created old contacts.', [
          '%created' => array_sum($results['created']),
        ]);
      }
      $message = implode(' ', $parts);
    }
    else {
      // @todo Show a more useful error message?
      $message = t('Finished with an error.');
    }
    drupal_set_message($message);
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
    $name = trim(is_array($name) ? reset($name) : $name);

    if (empty($name)) {
      return NULL;
    }

    $term_storage = \Drupal::entityTypeManager()->getStorage('term');

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
        'name' => $term,
      ]);
      $term->save();
    }

    return $term->id();
  }

  /**
   * Extract contacts from a spreadsheet.
   *
   * @param \Drupal\file\Entity\File $file
   *   Spreadsheet file object.
   * @param int $max_rows
   *   Maximum number rows to parse.
   *
   * @return array
   *   Associative array with the header columnss, contacts and potential
   *   parsing errors.
   */
  public static function parseSpreadsheet(File $file, $max_rows) {
    $columns = [];
    $contacts = [];
    $errors = [];

    // We wrap this code in a try...catch because PHPSpreadsheet can throw
    // various exceptions when parsing a spreadsheet.
    try {
      // Get the worksheet to work with (pun intended).
      $sheet = static::getWorksheet($file);

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
          if (!empty($data['errors'])) {
            $errors = array_merge($errors, $data['errors']);
          }
          elseif (!empty($data['data']['email'])) {
            $email = $data['data']['email'];
            if (isset($contacts[$email])) {
              $contacts[$email] = static::mergeContactData($contacts[$email], $data['data']);
            }
            else {
              $contacts[$email] = $data['data'];
            }
          }
        }
      }
    }
    catch (\Exception $exception) {
      $errors[] = $exception->getMessage();
    }

    return [
      'columns' => $columns,
      'contacts' => $contacts,
      'errors' => $errors,
    ];
  }

  /**
   * Load a spreadsheet and return its first worksheet.
   *
   * @param \Drupal\file\Entity\File $file
   *   Spreadsheet file object.
   *
   * @return \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
   *   First worksheet.
   */
  public static function getWorksheet(File $file) {
    if (!($file instanceof File)) {
      throw new \Exception('Invalid file.');
    }

    $filename = \Drupal::service('file_system')->realpath($file->getFileUri());

    // Get the spreadsheet type.
    $filetype = IOFactory::identify($filename);

    // Create the spreadsheet reader.
    $reader = IOFactory::createReader($filetype);

    // Start reading the file.
    $spreadsheet = $reader->load($filename);

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
   *   with the column name (header) as value and the column index as value.
   *   If the row is the header one but some mandatory columns are missing, the
   *   returnning array will have an `errors` key with the list of errors.
   */
  public static function parseHeaderRow(Worksheet $sheet, Row $row) {
    $columns = [];
    foreach ($row->getCellIterator() as $cell) {
      $value = mb_strtolower(static::getCellValue($sheet, $cell));
      // Fix malformed column names...
      $value = trim(preg_replace('/\s+/u', ' ', $value));
      if (isset($value, static::$columns[$value]) && !isset($columns[$value])) {
        $columns[$value] = Coordinate::columnIndexFromString($cell->getColumn());
      }
    }

    if (empty($columns)) {
      return [];
    }

    // Validate mandatory columns.
    $errors = [];
    foreach (static::$columns as $name => $definition) {
      if (!static::checkMandatoryField($name, $definition, $columns)) {
        $errors[] = t('Missing @column column.', [
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
   *   Associative array with the data per header column name. If some mandatory
   *   columns are missing, the returning array will have an `errors` key with
   *   the list of errors.
   */
  public static function parseDataRow(array $columns, Worksheet $sheet, Row $row) {
    $index = $row->getRowIndex();
    $data = [];

    // Get the data from the row foreach column with a recognized header.
    foreach ($columns as $name => $column) {
      $cell = $sheet->getCellByColumnAndRow($column, $index);
      $data[$name] = static::getCellValue($sheet, $cell);
    }

    // Skip the row if empty.
    if (count(array_filter($data)) === 0) {
      return [];
    }

    // Process the row's data.
    foreach (static::$columns as $name => $definition) {
      if (isset($definition['preprocess'])) {
        static::call($definition['preprocess'], $data);
      }
    }

    // Check mandatory fields. This is not done in the loop above because
    // the data may change during preprocessing.
    $errors = [];
    foreach (static::$columns as $name => $definition) {
      if (!static::checkMandatoryField($name, $definition, $data)) {
        $errors[] = t('Missing @column on row @row.', [
          '@column' => $name,
          '@row' => $index,
        ]);
      }
    }

    return [
      'data' => $data,
      'errors' => $errors,
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
   * @param \PhpOffice\PhpSpreadsheet\Cell\Cell $cell
   *   Cell from which to extract the value.
   *
   * @return string
   *   Extracted value, defaulting to an empty string.
   */
  public static function getCellValue(Worksheet $sheet, Cell $cell) {
    static $cache = [];
    $value = '';
    // If the cell is in a range, retrieve the value for the range by
    // concatenating the cell values.
    if ($cell->isInMergeRange()) {
      $range = $cell->getMergeRange();

      // Returned the cached value for the range. This avoids calculating the
      // value everytime we try to get the value for a cell in a range.
      if (isset($cache[$range])) {
        return $cache[$range];
      }

      $parts = [];
      $rows = $sheet->rangeToArray($cell->getMergeRange(), '', FALSE, FALSE);
      foreach ($rows as $cells) {
        foreach ($cells as $cell) {
          if (!empty($cell)) {
            $parts[] = trim($cell);
          }
        }
      }
      $value = implode('', $parts);
      $cache[$range] = $value;
    }
    elseif (!empty($cell)) {
      $value = trim($cell->getValue());
    }
    return $value;
  }

  /**
   * Preprocess the email field.
   *
   * This checks that the value is a valid email address and discard it
   * otherwise.
   *
   * @param array $data
   *   Row data.
   */
  public static function preprocessEmail(array &$data) {
    static $validator;
    if (!isset($validator)) {
      $validator = \Drupal::service('email.validator');
    }
    if (!empty($data['email']) && !$validator->isValid($data['email'])) {
      unset($data['email']);
    }
  }

  /**
   * Preprocess the name field.
   *
   * This attempts to generate the name by combining the first name and last
   * name if the name is not defined.
   *
   * @param array $data
   *   Row data.
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
  }

  /**
   * Preprocess the duty station country field.
   *
   * This gets the first country from the `duty station country` and/or
   * the `country` field.
   *
   * @param array $data
   *   Row data.
   */
  public static function preprocessDutyStationCountry(array &$data) {
    $countries = [];
    if (!empty($data['duty station country'])) {
      $countries = array_merge($countries, array_map('trim', explode('|', $data['duty station country'])));
    }
    if (!empty($data['country'])) {
      $countries = array_merge($countries, array_map('trim', explode('|', $data['country'])));
    }
    $countries = array_unique(array_filter($countries), SORT_STRING);
    $data['duty station country'] = reset($countries);
    unset($data['country']);
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
   *
   * @return array
   *   Merged contact data.
   */
  public static function mergeContactData(array $data1, array $data2) {
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
      elseif (empty($data1[$name]) && !empty($data2[$name])) {
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
    if (isset($data[$name])) {
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

}
