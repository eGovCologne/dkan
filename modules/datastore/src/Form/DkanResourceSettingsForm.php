<?php

namespace Drupal\datastore\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class DkanResourceSettingsForm
 *
 * @package Drupal\datastore\Form
 * @codeCoverageIgnore
 */
class DkanResourceSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'datastore_dkan_resource_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['datastore.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['unneeded_resources'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Purge unneeded dataset resources'),
      '#description' => $this->t('Upon dataset publication, delete these resources if they are no longer necessary.'),
    ];
    $form['unneeded_resources']['purge_tables'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Datastore tables'),
      '#default_value' => $this->config('datastore.settings')->get('purge_unneeded_tables'),
    ];
    $form['unneeded_resources']['purge_files'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Files'),
      '#default_value' => $this->config('datastore.settings')->get('purge_unneeded_files'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('datastore.settings')
      ->set('purge_unneeded_tables', $form_state->getValue('purge_tables'))
      ->set('purge_unneeded_files', $form_state->getValue('purge_files'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
