<?php

namespace Drupal\views_field\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'views_field' field type.
 *
 * @FieldType(
 *   id = "views_field",
 *   label = @Translation("Views field"),
 *   description = @Translation("My Field Type"),
 *   default_widget = "views_field_widget",
 *   default_formatter = "views_field_formatter"
 * )
 */
class ViewsField extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'max_length' => 255,
      'is_ascii' => FALSE,
      'case_sensitive' => FALSE,
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    // Prevent early t() calls by using the TranslatableMarkup.
    $properties['view'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('View ID'))
      ->setSetting('case_sensitive', $field_definition->getSetting('case_sensitive'))
      ->setRequired(TRUE);

    $properties['settings'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('View settings'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = [
      'columns' => [
        'view' => [
          'type' => 'varchar_ascii',
          'length' => 255,
        ],
        'settings' => [
          'type' => 'text',
          'size' => 'big',
        ],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('view')->getValue();
    return $value === NULL || $value === '';
  }

}
