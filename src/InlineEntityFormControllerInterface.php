<?php

/**
 * @file
 * Contains \Drupal\inline_entity_form\InlineEntityFormControllerInterface.
 */

namespace Drupal\inline_entity_form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the interface for entity browser widgets.
 */
interface InlineEntityFormControllerInterface {

  /**
   * Returns an array of css filepaths for the current entity type, keyed
   * by theme name.
   *
   * If provided, the "base" CSS file is included for all themes.
   * If a CSS file matching the current theme exists, it will also be included.
   *
   * @code
   * return array(
   *   'base' => drupal_get_path('module', 'test_module') . '/css/inline_entity_form.base.css',
   *   'seven' => drupal_get_path('module', 'test_module') . '/css/inline_entity_form.seven.css',
   * );
   * @endcode
   */
  public function css();

  /**
   * Returns an array of entity type labels (singular, plural) fit to be
   * included in the UI text.
   */
  public function defaultLabels();

  // TODO
  public function labels();

  /**
   * Returns an array of fields used to represent an entity in the IEF table.
   *
   * The fields can be either Field API fields or properties defined through
   * hook_entity_property_info().
   *
   * Modules can alter the output of this method through
   * hook_inline_entity_form_table_fields_alter().
   *
   * @param $bundles
   *   An array of allowed bundles for this widget.
   *
   * @return
   *   An array of field information, keyed by field name. Allowed keys:
   *   - type: 'field' or 'property',
   *   - label: Human readable name of the field, shown to the user.
   *   - weight: The position of the field relative to other fields.
   *   Special keys for type 'field', all optional:
   *   - formatter: The formatter used to display the field, or "hidden".
   *   - settings: An array passed to the formatter. If empty, defaults are used.
   *   - delta: If provided, limits the field to just the specified delta.
   */
  public function tableFields($bundles);

  /**
   * Returns a setting value.
   *
   * @param $name
   *   The name of the setting value to return.
   *
   * @return
   *   A setting value.
   */
  public function getSetting($name);

  /**
   * Returns an array of default settings in the form of key => value.
   */
  public function defaultSettings();

  /**
   * Returns the entity type managed by this controller.
   *
   * @return
   *   The entity type.
   */
  public function entityType();

  /**
   * Returns the entity form to be shown through the IEF widget.
   *
   * When adding data to $form_state it should be noted that there can be
   * several IEF widgets on one master form, each with several form rows,
   * leading to possible key collisions if the keys are not prefixed with
   * $entity_form['#parents'].
   *
   * @param $entity_form
   *   The entity form.
   * @param $form_state
   *   The form state of the parent form.
   */
  public function entityForm($entity_form, FormStateInterface $form_state);

  /**
   * Validates the entity form.
   *
   * @param $entity_form
   *   The entity form.
   * @param $form_state
   *   The form state of the parent form.
   */
  public function entityFormValidate($entity_form, &$form_state);

  /**
   * Handles the submission of an entity form.
   *
   * Prepares the entity stored in $entity_form['#entity'] for saving by copying
   * the values from the form to matching properties and, if the entity is
   * fieldable, invoking Field API submit.
   *
   * @param $entity_form
   *   The entity form.
   * @param $form_state
   *   The form state of the parent form.
   */
  public function entityFormSubmit(&$entity_form, FormStateInterface $form_state);

  /**
   * Returns the remove form to be shown through the IEF widget.
   *
   * @param $remove_form
   *   The remove form.
   * @param $form_state
   *   The form state of the parent form.
   */
  public function removeForm($remove_form, &$form_state);

  /**
   * Handles the submission of a remove form.
   * Decides what should happen to the entity after the removal confirmation.
   *
   * @param $remove_form
   *   The remove form.
   * @param $form_state
   *   The form state of the parent form.
   *
   * @return
   *   IEF_ENTITY_UNLINK or IEF_ENTITY_UNLINK_DELETE.
   */
  public function removeFormSubmit($remove_form, FormStateInterface $form_state);

  /**
   * Permanently saves the given entity.
   *
   * @param $entity
   *   The entity to save.
   * @param array $context
   *   Available keys:
   *   - parent_entity_type: The type of the parent entity.
   *   - parent_entity: The parent entity.
   */
  public function save(EntityInterface $entity, $context);

  /**
   * Delete permanently saved entities.
   *
   * @param $ids
   *   An array of entity IDs.
   * @param array $context
   *   Available keys:
   *   - parent_entity_type: The type of the parent entity.
   *   - parent_entity: The parent entity.
   */
  public function delete($ids, $context);

}
