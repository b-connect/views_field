<?php

namespace Drupal\views_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Views;
use Drupal\Core\Form\FormState;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\SortArray;
use Drupal\Component\Serialization\Json;

/**
 * Plugin implementation of the 'views_field_widget' widget.
 *
 * @FieldWidget(
 *   id = "views_field_widget",
 *   label = @Translation("Views field widget"),
 *   field_types = {
 *     "views_field"
 *   }
 * )
 */
class ViewsFieldWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'views' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = [];
    $views = \Drupal::entityTypeManager()->getStorage('view')->loadMultiple();

    $options = [];
    foreach ($views as $id => $view) {
      $displays = $view->get('display');
      foreach ($displays as $key => $display) {
        $options[$view->id() . '::' . $key] = $view->label() . ' - ' . $display['display_title'];
      }
    }

    $elements['views'] = [
      '#type' => 'select',
      '#multiple' => TRUE,
      '#options' => $options,
      '#title' => t('Choose selectable views'),
      '#default_value' => $this->getSetting('views'),
      '#required' => TRUE,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = t('View: @view', ['@view' => implode(', ', $this->getSetting('views'))]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = [];
    $views = [];
    $viewsIds = $this->getSetting('views');

    foreach ($viewsIds as $viewId) {
      $view = explode('::', $viewId);
      $display = $view[1];
      $view = $viewId = $view[0];
      $view = \Drupal::entityTypeManager()->getStorage('view')->loadByProperties(['id' => $view]);
      if ($view[$viewId]) {
        $displays = $view[$viewId]->get('display');
        foreach ($displays as $key => $display) {
          $views[$viewId . '::' . $key] = $view[$viewId]->label() . ' - ' . $display['display_title'];
        }
      }
    }

    $element['view'] = $element + array(
      '#type' => 'select',
      '#options' => $views,
      '#default_value' => isset($items[$delta]->view) ? $items[$delta]->view : NULL,
      '#ajax' => array(
        'callback' => array($this, 'viewsSettings'),
        'event' => 'change',
        'progress' => array(
          'type' => 'throbber',
          'message' => NULL,
        ),
        'wrapper' => 'state-dropdown',
      ),
    );

    $view = explode('::', $items[0]->view);
    $display = $view[1];
    $view = $this->getView($view[0], $view[1]);

    $element['settings'] = $this->getViewSettings($view, $display, $form_state);
    $element['settings']['#prefix'] = '<div id="state-dropdown">';
    $element['settings']['#suffix'] = '</div>';

    return $element;
  }

  /**
   * Doc.
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    // dsm($form_state->getValues());
    // dsm($form_state->getValues(),'Settings');
    parent::extractFormValues($items, $form, $form_state);
    $field_name = $this->fieldDefinition->getName();
    $path = array_merge($form['#parents'], array($field_name));
    $key_exists = NULL;
    $values = NestedArray::getValue($form_state->getValues(), $path, $key_exists);
    if ($key_exists) {
      if (!$this->handlesMultipleValues()) {
        // Remove the 'value' of the 'add more' button.
        unset($values['add_more']);

        // The original delta, before drag-and-drop reordering, is needed to
        // route errors to the correct form element.
        foreach ($values as $delta => &$value) {
          $value['_original_delta'] = $delta;
        }

        usort($values, function($a, $b) {
          return SortArray::sortByKeyInt($a, $b, '_weight');
        });
      }

      // Let the widget massage the submitted values.
      $values = $this->massageFormValues($values, $form, $form_state);
      foreach ($values as $delta => $value) {
        if ($value['settings']) {
          $value['settings'] = Json::encode($value['settings']);
        }
      }

      $items->setValue($values);
    }
  }

  /**
   * Doc.
   */
  public function getViewSettings($view, $display) {
    $form_state = new FormState();
    $view->initHandlers();
    $form = [];

    // Let form plugins know this is for exposed widgets.
    $form_state->set('exposed', TRUE);
    $form['settings'] = [];

    // Go through each handler and let it generate its exposed widget.
    foreach ($view->display_handler->handlers as $type => $value) {
      /** @var \Drupal\views\Plugin\views\ViewsHandlerInterface $handler */
      foreach ($view->$type as $id => $handler) {
        if ($handler->canExpose() && $handler->isExposed()) {
          if ($handler->isAGroup()) {
            $handler->groupForm($form, $form_state);
            $id = $handler->options['group_info']['identifier'];
          }
          else {
            $handler->buildExposedForm($form, $form_state);
          }
          if ($info = $handler->exposedInfo()) {
            $form['#info']["$type-$id"] = $info;
          }
        }
      }
    }

    /** @var \Drupal\views\Plugin\views\exposed_form\ExposedFormPluginBase $exposed_form_plugin */
    $exposed_form_plugin = $view->display_handler->getPlugin('exposed_form');
    $exposed_form_plugin->exposedFormAlter($form, $form_state);
    unset($form['actions']);
    return $form;

  }

  /**
   * Doc.
   */
  public function getView($view_id, $display) {
    $view = Views::getView($view_id);
    $view->setDisplay($display);
    return $view;
  }

  /**
   * Doc.
   */
  public function viewsSettings(array $form, FormStateInterface $form_state) {
    $view = $form_state->getTriggeringElement()['#value'];
    $view = explode('::', $view);
    $display = $view[1];
    $view = $this->getView($view[0], $view[1]);
    return @$this->getViewSettings($view, $display);
  }

}
