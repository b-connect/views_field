<?php

namespace Drupal\views_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Serialization\Json;
use Drupal\views\Views;

/**
 * Plugin implementation of the 'views_field_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "views_field_formatter",
 *   label = @Translation("Views field formatter"),
 *   field_types = {
 *     "views_field"
 *   }
 * )
 */
class ViewsFieldFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      // Implement default settings.
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return [
      // Implement settings form.
    ] + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $elements[$delta] = $this->viewValue($item);
    }

    return $elements;
  }

  /**
   * Generate the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return string
   *   The textual output generated.
   */
  protected function viewValue(FieldItemInterface $item) {
    extract($item->getValue());
    $settings = Json::decode($settings);
    list($view, $display) = explode('::', $view);
    $view = Views::getView($view);
    $view->setDisplay($display);
    $view->setExposedInput($settings);
    $render = $view->render($display);
    return $render;
  }

}
