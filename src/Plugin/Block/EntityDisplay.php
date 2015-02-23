<?php

/**
 * @file
 * Contains Drupal\content_block\Plugin\Block\EntityDisplay.
 */

namespace Drupal\content_block\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'EntityDisplay' block.
 *
 * @Block(
 *  id = "entity_display",
 *  admin_label = @Translation("Entity Display"),
 *  category = @Translation("Content Block"),
 * )
 */
class EntityDisplay extends ContentBlock {

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    foreach ($this->available_types as $entity_type=>$value) {
      // Name of the settings form field that contains the referenced entity.
      $uuid_key = "entity_uuid_{$entity_type}";
      $type_definition = \Drupal::entityManager()->getDefinition($entity_type);

      // Add the form field for selecting the content to be referenced.
      // @todo: Convert this textfield to a autocomplete field widget.
      $form[$uuid_key] = array(
        '#type' => 'textfield',
        '#title' => $this->t('%type Selection', array('%type'=>$type_definition->getLabel())),
        '#description' => $this->t('Enter the uuid of the %type you would like to display.', array(
          '%type'=>$entity_type,
        )),
        '#default_value' => isset($this->configuration[$uuid_key]) ? $this->configuration[$uuid_key] : '',
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
    // Save config from the content block.
    parent::blockSubmit($form, $form_state);

    // Loop through configuration options and remove outdated settings.
    foreach ($this->configuration as $name=>$value) {
      if (substr($name, 0, 12) == 'entity_uuid_') {
        unset($this->configuration[$name]);
      }
    }
    // Name of the settings form field that contains the referenced entity.
    $uuid_key = "entity_uuid_{$this->configuration['entity_type']}";
    // Save the referenced entity uuid to configuration.
    $this->configuration[$uuid_key] = $form_state->getValue($uuid_key);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Name of the settings form field that contains the referenced entity.
    $uuid_key = "entity_uuid_{$this->configuration['entity_type']}";
    // build the content block with the specific entity.
    return parent::build($this->configuration[$uuid_key]);
  }
}
