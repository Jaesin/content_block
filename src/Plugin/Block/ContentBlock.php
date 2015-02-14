<?php

/**
 * @file
 * Contains Drupal\content_block\Plugin\Block\ContentBlock.
 */

namespace Drupal\content_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'ContentBlock' block.
 *
 * @Block(
 *  id = "content_block",
 *  admin_label = @Translation("Content Block"),
 * )
 */
class ContentBlock extends BlockBase {
  
  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    // Blacklist some entity type (like block).
    $blacklist = array('block_content');
    \Drupal::moduleHandler()->alter('content_block_entity_blacklist', $blacklist);

    // Initialize options arrays.
    $type_options = array();
    $view_mode_options = array();

    // Get all entity view modes.
    $entity_view_modes =  \Drupal::entityManager()->getAllViewModes();

    // Loop through all view modes.
    foreach ($entity_view_modes as $entity_type=>$view_modes) {
      // Check to see if the view mode is blacklisted.
      if (!in_array($entity_type, $blacklist)) {
        // Add the view mode to the list.
        $type_options[$entity_type] = $entity_type;
      }
      $view_mode_options[$entity_type]['default'] = t('--default--');
      // Get view mode options for this entity type.
      foreach ($view_modes as $machine_name=>$view_mode) {
        // Make sure the view mode is enabled.
        if ($view_mode['status']) {
          // Add the option to the list.
          $view_mode_options[$entity_type][$machine_name] = $view_mode['label'];
        }
      }
    }

    // Add the Entity Type configuration setting.
    $form['entity_type'] = array(
      '#type' => 'select',
      '#title' => $this->t('Entity Type'),
      '#options' => $type_options,
      '#description' => $this->t('Select the type of content you would like to display.'),
      '#default_value' => isset($this->configuration['entity_type']) ? $this->configuration['entity_type'] : '',
    );

    // Loop through the available entity types.
    foreach ($type_options as $entity_type) {
      // Use a naming convention for the setting.
      $mode_id = "view_mode_{$entity_type}";
      // Add a view mode selector to use with each entity type.
      $form[$mode_id] = array(
        '#type' => 'select',
        '#title' => $this->t('View Mode'),
        '#description' => $this->t('Select the view mode to use for displaying the content.'),
        '#default_value' => isset($this->configuration[$mode_id]) ? $this->configuration[$mode_id] : '',
        '#options' => $view_mode_options[$entity_type],
        '#states' => array(
          'visible' => array(
            ':input[name="settings[entity_type]"]' => array('value' => $entity_type),
          ),
        ),
      );
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // Get the entity type.
    $entity_type = $form_state->getValue('entity_type');
    // Name of the settings form field that contains the view mode to use.
    $mode_id = "view_mode_{$entity_type}";

    // Add the configuration form the submitted values.
    $this->configuration['entity_type'] = $entity_type;
    $this->configuration[$mode_id] = $form_state->getValue($mode_id);

    // Loop through configuration options and remove outdated settings.
    foreach ($this->configuration as $name=>$value) {
      if (substr($name, 0, 10) == 'view_mode_' && $name != $mode_id) {
        unset($this->configuration[$name]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    // Get the entity type.
    $entity_type = $this->configuration['entity_type'];
    // Name of the settings form field that contains the view mode to use.
    $mode_id = "view_mode_{$entity_type}";

    // Get the requested view mode machine name.
    $view_mode = !empty($this->configuration[$mode_id]) ? $this->configuration[$mode_id] : NULL;

    // Get the current request object.
    $request = \Drupal::request();

    // Try to grab the entity from the request object.
    $entity = $request->attributes->get($entity_type);

    // Make sure we got the entity and a view_mode setting.
    if(!empty($entity) && !empty($view_mode)) {

      // Get the view modes for this display type.
      $entity_view_modes = \Drupal::entityManager()->getViewModes($entity_type);

      // Validate the requested view mode.
      if(!empty($entity_view_modes[$view_mode]['status']) && $entity_view_modes[$view_mode]['status'] === TRUE) {
        // Create the build array.
        $build = array(
          'content' => entity_view($entity, $this->configuration[$mode_id]),
        );
      }
    }
    return $build;
  }
}
