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
 *  admin_label = @Translation("Display Mode"),
 *  category = @Translation("Content Block"),
 * )
 */
class ContentBlock extends BlockBase {

  /**
   * Types of entities that are available.
   *
   * @var array
   */
  protected $available_types;

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    // Blacklist some entity type (like block).
    $blacklist = array('block', 'block_content');
    \Drupal::moduleHandler()->alter('content_block_entity_blacklist', $blacklist);

    // Initialize options arrays.
    $this->available_types = array();
    $view_mode_options = array();

    // Get all entity view modes.
    $entity_view_modes =  \Drupal::entityManager()->getAllViewModes();

    // Loop through all view modes.
    foreach ($entity_view_modes as $entity_type=>$view_modes) {
      // Check to see if the view mode is blacklisted.
      if (!in_array($entity_type, $blacklist)) {
        // Add the view mode to the list.
        $this->available_types[$entity_type] = $entity_type;
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
      '#options' => $this->available_types,
      '#description' => $this->t('Select the type of content you would like to display.'),
      '#default_value' => isset($this->configuration['entity_type']) ? $this->configuration['entity_type'] : 'node',
    );

    // Loop through the available entity types.
    foreach ($this->available_types as $entity_type) {
      // Use a naming convention for the setting.
      $mode_key = "view_mode_{$entity_type}";
      // Add a view mode selector to use with each entity type.
      $form[$mode_key] = array(
        '#type' => 'select',
        '#title' => $this->t('View Mode'),
        '#description' => $this->t('Select the view mode to use for displaying the content.'),
        '#default_value' => isset($this->configuration[$mode_key]) ? $this->configuration[$mode_key] : '',
        '#options' => $view_mode_options[$entity_type],
        '#states' => array(
          'visible' => array(
            ':input[name="settings[entity_type]"]' => array('value' => $entity_type),
          ),
        ),
      );
    }

    // Allow fallback to default entity view display.
    $form['force_display'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Force Display'),
      '#description' => $this->t('If enabled, the "default" display will be used if a valid configuration is not found for the entity type (i.e. "content type").'),
      '#default_value' => isset($this->configuration['force_display']) ? $this->configuration['force_display'] : 0,
    );


    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // Get the entity type.
    $entity_type = $form_state->getValue('entity_type');
    // Name of the settings form field that contains the view mode to use.
    $mode_key = "view_mode_{$entity_type}";

    // Loop through configuration options and remove outdated settings.
    foreach ($this->configuration as $name=>$value) {
      if (substr($name, 0, 10) === 'view_mode_') {
        unset($this->configuration[$name]);
      }
    }
    // Add the configuration form the submitted values.
    $this->configuration['entity_type'] = $entity_type;
    $this->configuration[$mode_key] = $form_state->getValue($mode_key);
    $this->configuration['force_display'] = $form_state->getValue('force_display');

  }

  /**
   * {@inheritdoc}
   *
   * @param string $uuid The entity uuid to render.
   */
  public function build($uuid=NULL) {

    // Allow modules to override the entity, and view mode that will be output.
    \Drupal::moduleHandler()->alter('content_block_pre_build', $uuid, $this->configuration);

    // Get the entity type.
    $entity_type = $this->configuration['entity_type'];
    // Name of the settings form field that contains the view mode to use.
    $mode_key = "view_mode_{$entity_type}";

    // Get the requested view mode machine name.
    $view_mode = !empty($this->configuration[$mode_key]) ? $this->configuration[$mode_key] : NULL;

    // Make sure we have the necessary pieces to load an alternate entity.
    if (!empty($entity_type) && !empty($uuid)) {
      // Load the entity by UUID.
      $entity = \Drupal::entityManager()->loadEntityByUuid($entity_type, $uuid);
    } else {
      // We don't have an alternate entity. Get the current request object.
      $request = \Drupal::request();
      // Try to grab the entity from the request object.
      $entity = $request->attributes->get($entity_type);
    }

    // Make sure we got the entity and a view_mode setting.
    if(!empty($entity) && !empty($view_mode)) {

      // Get the view modes for this display type.
      $entity_view_modes = \Drupal::entityManager()->getViewModes($entity_type);
      // Load the entity display settings.
      $entity_display = entity_load('entity_view_display', "{$entity_type}.{$entity->bundle()}.{$view_mode}");
      $display_status = get_class($entity_display) === 'Drupal\Core\Entity\Entity\EntityViewDisplay'
        ? $entity_display->get('status')
        : (boolean)(!empty($this->configuration['force_display']) && $this->configuration['force_display'] == 1);

      // Validate the requested view mode.
      if ($display_status === TRUE &&
        !empty($entity_view_modes[$view_mode]['status']) &&
        $entity_view_modes[$view_mode]['status'] === TRUE
      ) {
        // Create the build array.
        return array(
          'content' => entity_view($entity, $this->configuration[$mode_key]),
        );
      }
    }
    return NULL;
  }
}
