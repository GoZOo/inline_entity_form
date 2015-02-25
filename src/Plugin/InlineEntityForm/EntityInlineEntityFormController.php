<?php

/**
 * @file
 * Defines the base inline entity form controller.
 */

namespace Drupal\inline_entity_form\Plugin\InlineEntityForm;

use Drupal\Component\Utility\NestedArray;
use Drupal;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\inline_entity_form\InlineEntityFormControllerInterface;

/**
 * Generic entity inline form.
 *
 * @Plugin(
 *   id = "entity",
 *   deriver = "Drupal\inline_entity_form\Plugin\Deriver\EntityInlineEntityForm",
 * )
 *
 * @see \Drupal\inline_entity_form\Plugin\Deriver\EntityInlineEntityForm
 */
class EntityInlineEntityFormController implements InlineEntityFormControllerInterface {

  protected $entityType;
  public $settings;

  public function __construct($configuration, $plugin_id, $plugin_definition) {
    list(, $this->entityType) = explode(':', $plugin_id, 2);
    $this->settings = $configuration + $this->defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function css() {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function labels() {
    // The admin has specified the exact labels that should be used.
    if ($this->settings['override_labels']) {
      $labels = [
        'singular' => $this->settings['label_singular'],
        'plural' => $this->settings['label_plural'],
      ];
    }
    else {
      $labels = [
        'singular' => t('entity'),
        'plural' => t('entities'),
      ];
    }

    return $labels;
  }

  /**
   * {@inheritdoc}
   */
  public function tableFields($bundles) {
    $info = \Drupal::entityManager()->getDefinition($this->entityType);
    // $metadata = \Drupal::entityManager()->getFieldDefinitions($this->entityType);
    $metadata = array();

    $fields = array();
    if ($info->hasKey('label')) {
      $label_key = $info->getKey('label');
      $fields[$label_key] = array(
        'type' => 'property',
        'label' => $metadata ? $metadata[$label_key]['label'] : t('Label'),
        'weight' => 1,
      );
    }
    else {
      $id_key = $info->getKey('id');
      $fields[$id_key] = array(
        'type' => 'property',
        'label' => $metadata ? $metadata[$id_key]['label'] : t('ID'),
        'weight' => 1,
      );
    }
    if (count($bundles) > 1) {
      $bundle_key = $info->getKey('bundle');
      $fields[$bundle_key] = array(
        'type' => 'property',
        'label' => $metadata ? $metadata[$bundle_key]['label'] : t('Type'),
        'weight' => 2,
      );
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getSetting($name) {
    return $this->settings[$name];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSettings() {
    $defaults = array();
    $defaults['allow_existing'] = FALSE;
    $defaults['match_operator'] = 'CONTAINS';
    $defaults['delete_references'] = FALSE;
    $defaults['override_labels'] = FALSE;
    $defaults['label_singular'] = '';
    $defaults['label_plural'] = '';

    return $defaults;
  }

  /**
   * {@inheritdoc}
   */
  public function entityType() {
    return $this->entityType;
  }

  /**
   * {@inheritdoc}
   */
  public function entityForm($entity_form, FormStateInterface $form_state) {
    /**
     * @var \Drupal\Core\Entity\EntityInterface $entity
     */
    $entity = $entity_form['#entity'];
    $operation = 'default';

    $child_form_state = new Drupal\Core\Form\FormState();
    $controller = \Drupal::entityManager()->getFormObject($entity->getEntityTypeId(), $operation);
    $controller->setEntity($entity);
    $child_form_state->addBuildInfo('callback_object', $controller);
    $child_form_state->addBuildInfo('base_form_id', $controller->getBaseFormID());
    $child_form_state->addBuildInfo('form_id', $controller->getFormID());
    $child_form_state->addBuildInfo('args', array());

    // Copy values to child form.
    $child_form_state->setUserInput($form_state->getUserInput());
    $child_form_state->setValues($form_state->getValues());
    $child_form_state->setStorage($form_state->getStorage());

    $child_form_state->set('form_display', entity_load('entity_form_display', $entity->getEntityTypeId() . '.' . $entity->bundle() . '.' . $operation));

    // Since some of the submit handlers are run, redirects need to be disabled.
    $child_form_state->set('no_redirect', TRUE);

    // When a form is rebuilt after Ajax processing, its #build_id and #action
    // should not change.
    // @see drupal_rebuild_form()
    $rebuild_info = $child_form_state->getRebuildInfo();
    $rebuild_info['copy']['#build_id'] = TRUE;
    $rebuild_info['copy']['#action'] = TRUE;
    $child_form_state->setRebuildInfo($rebuild_info);

    $child_form_state->set('inline_entity_form', $form_state->get('inline_entity_form'));
    $child_form_state->set('langcode', $entity->langcode->value);

    $child_form_state->set('field', $form_state->get('field'));
    $child_form_state->setTriggeringElement($form_state->getTriggeringElement());
    $child_form_state->setSubmitHandlers($form_state->getSubmitHandlers());

    $entity_form['#ief_parents'] = $entity_form['#parents'];

    $entity_form = $controller->buildForm($entity_form, $child_form_state);

    foreach ($child_form_state->get('inline_entity_form') as $id => $data) {
      $form_state->set(['inline_entity_form', $id], $data);
    }

    $form_state->set('field', $child_form_state->get('field'));
    return $entity_form;
  }

  /**
   * {@inheritdoc}
   */
  public function entityFormValidate($entity_form, &$form_state) {
    /*
    $info = \Drupal::entityManager()->getDefinition($this->entityType);
    $entity = $entity_form['#entity'];
    $form_state['form_display']->validateFormValues($entity, $entity_form, $form_state);
    */
  }

  /**
   * {@inheritdoc}
   */
  public function entityFormSubmit(&$entity_form, FormStateInterface $form_state) {
    /**
     * @var \Drupal\Core\Entity\EntityInterface $entity
     */
    $entity = $entity_form['#entity'];
    $operation = 'default';

    $child_form = $entity_form;

    $child_form_state = new FormState();
//    $child_form_state->set('values', NestedArray::getValue($form_state['values'], $entity_form['#parents']));

    $controller = \Drupal::entityManager()->getFormObject($entity->getEntityTypeId(), $operation);
    $controller->setEntity($entity);

    $child_form_state->addBuildInfo('callback_object', $controller);
    $child_form_state->addBuildInfo('base_form_id', $controller->getBaseFormID());
    $child_form_state->addBuildInfo('form_id', $controller->getFormID());
    $child_form_state->addBuildInfo('args', array());

    // Copy values to child form.
    $child_form_state->setUserInput($form_state->getUserInput());
    $child_form_state->setValues($form_state->getValues());
    $child_form_state->setStorage($form_state->getStorage());

    $child_form_state->set('form_display', entity_get_form_display($entity->getEntityTypeId(), $entity->bundle(), $operation));

    // Since some of the submit handlers are run, redirects need to be disabled.
    $child_form_state->disableRedirect();

    // When a form is rebuilt after Ajax processing, its #build_id and #action
    // should not change.
    // @see drupal_rebuild_form()
    $rebuild_info = $child_form_state->getRebuildInfo();
    $rebuild_info['copy']['#build_id'] = TRUE;
    $rebuild_info['copy']['#action'] = TRUE;
    $child_form_state->setRebuildInfo($rebuild_info);

    $child_form_state->set('inline_entity_form', $form_state->get('inline_entity_form'));
    $child_form_state->set('langcode', $entity->langcode->value);

    $child_form_state->set('field', $form_state->get('field'));
    $child_form_state->setTriggeringElement($form_state->getTriggeringElement());
    $child_form_state->setSubmitHandlers($form_state->getSubmitHandlers());

    $child_form['#ief_parents'] = $entity_form['#parents'];

    $controller->submitForm($child_form, $child_form_state);
    $controller->save($child_form, $child_form_state);
    $entity_form['#entity'] = $controller->getEntity();

    foreach ($child_form_state->get('inline_entity_form') as $id => $data) {
      $form_state->set(['inline_entity_form', $id], $data);
    }
  }

  /**
   * Cleans up the form state for each field.
   *
   * After field_attach_submit() has run and the entity has been saved, the form
   * state still contains field data in $form_state['field']. Unless that
   * data is removed, the next form with the same #parents (reopened add form,
   * for example) will contain data (i.e. uploaded files) from the previous form.
   *
   * @param $entity_form
   *   The entity form.
   * @param $form_state
   *   The form state of the parent form.
   */
  protected function cleanupFieldFormState($entity_form, &$form_state) {
    $bundle = $entity_form['#entity']->bundle();
    /**
     * @var \Drupal\field\Entity\FieldInstanceConfig[] $instances
     */
    $instances = field_info_instances($entity_form['#entity_type'], $bundle);
    foreach ($instances as $instance) {
      $field_name = $instance->getFieldName();
      if (isset($entity_form[$field_name])) {
        $parents = $entity_form[$field_name]['#parents'];

        $field_state = WidgetBase::getWidgetState($parents, $field_name, $form_state);
        unset($field_state['items']);
        unset($field_state['entity']);
        $field_state['items_count'] = 0;
        WidgetBase::getWidgetState($parents, $field_name, $form_state, $field_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeForm($remove_form, &$form_state) {
    $entity = $remove_form['#entity'];
    $entity_id = $entity->id();
    $entity_label = $entity->label();

    $remove_form['message'] = array(
      '#markup' => '<div>' . t('Are you sure you want to remove %label?', array('%label' => $entity_label)) . '</div>',
    );
    if (!empty($entity_id) && $this->getSetting('allow_existing')) {
      $access = $entity->access('delete');
      if ($access) {
        $labels = $this->labels();
        $remove_form['delete'] = array(
          '#type' => 'checkbox',
          '#title' => t('Delete this @type_singular from the system.', array('@type_singular' => $labels['singular'])),
        );
      }
    }

    return $remove_form;
  }

  /**
   * {@inheritdoc}
   */
  public function removeFormSubmit($remove_form, FormStateInterface $form_state) {
    $entity = $remove_form['#entity'];
    $entity_id = $entity->id();
    $form_values = NestedArray::getValue($form_state->getValues(), $remove_form['#parents']);
    // This entity hasn't been saved yet, we can just unlink it.
    if (empty($entity_id)) {
      return IEF_ENTITY_UNLINK;
    }
    // If existing entities can be referenced, the delete happens only when
    // specifically requested (the "Permanently delete" checkbox).
    if ($this->getSetting('allow_existing') && empty($form_values['delete'])) {
      return IEF_ENTITY_UNLINK;
    }

    return IEF_ENTITY_UNLINK_DELETE;
  }

  /**
   * {@inheritdoc}
   */
  public function save(EntityInterface $entity, $context) {
    return $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function delete($ids, $context) {
    entity_delete_multiple($this->entityType, $ids);
  }

  /**
   * @param $entity_form
   * @param $form_state
   * @param $entity
   * @param $operation
   * @return array
   */
  protected function buildChildFormState(&$entity_form, &$form_state, $entity, $operation) {
    $child_form_state = new FormState();
    $controller = \Drupal::entityManager()->getFormObject($entity->getEntityTypeId(), $operation);
    $controller->setEntity($entity);

    $child_form_state->addBuildInfo('callback_object', $controller);
    $child_form_state->addBuildInfo('base_form_id', $controller->getBaseFormID());
    $child_form_state->addBuildInfo('form_id', $controller->getFormID());
    $child_form_state->addBuildInfo('args', array());
    $child_form_state->set('form_display', entity_load('entity_form_display', $entity->getEntityTypeId() . '.' . $entity->bundle() . '.' . $operation));

    // Since some of the submit handlers are run, redirects need to be disabled.
    $child_form_state->disableRedirect();

    // When a form is rebuilt after Ajax processing, its #build_id and #action
    // should not change.
    // @see drupal_rebuild_form()
    $rebuild_info = $child_form_state->getRebuildInfo();
    $rebuild_info['copy']['#build_id'] = TRUE;
    $rebuild_info['copy']['#action'] = TRUE;
    $child_form_state->setRebuildInfo($rebuild_info);

    // $child_form_state['values'] = NestedArray::getValue($form_state['values'], $entity_form['#parents']);
    // $child_form_state['#parents'] = array();
    $child_form_state->setValues($form_state->getValues());

    $child_form_state->setValue('menu', []);
    $child_form_state->setButtons([]);
    $child_form_state->set('inline_entity_form', $form_state->get('inline_entity_form'));
    $child_form_state->set('langcode', $entity->langcode->value);

    $child_form_state->setTriggeringElement($form_state->getTriggeringElement());
    $child_form_state->setSubmitHandlers($form_state->getSubmitHandlers());

    $this->child_form_state = $child_form_state;
    $this->child_form_controller = $controller;
  }

}
