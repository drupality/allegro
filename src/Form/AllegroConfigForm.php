<?php

/**
 * Created by PhpStorm.
 * User: marek.kisiel
 * Date: 11/08/16
 * Time: 13:37
 */

namespace Drupal\allegro\Form;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\allegro\Util\AllegroAPI;


class AllegroConfigForm extends ConfigFormBase
{

    public static function getConfig($isEditable = false) {
        if (! $isEditable) {
            return \Drupal::config('allegro.settings');
        }
        return \Drupal::configFactory()->getEditable('allegro.settings');
    }

    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = self::getConfig();

        $form['tabs'] = [
            '#type' => 'vertical_tabs',
            '#default_tab' => 'settings'
        ];

        $form['settings'] = [
            '#type' => 'details',
            '#title' => $this->t('Settings'),
        ];

        $form['settings']['username'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Username'),
            '#default_value' => $config->get('username'),
            '#required' => TRUE,
        ];
        $form['settings']['password'] = [
            '#type' => 'password',
            '#title' => $this->t('Password'),
            '#default_value' => '',
        ];
        $form['settings']['webapi_key'] = [
            '#type' => 'textfield',
            '#title' => $this->t('WebAPI Key'),
            '#default_value' => $config->get('webapi_key'),
            '#required' => TRUE,
        ];
        $form['settings']['test_mode'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Turn on test mode'),
            '#default_value' => $config->get('test_mode'),
        ];
        $form['settings']['country_code'] = [
          '#type' => 'select',
          '#options' => AllegroAPI::getSupportedWebsites(),
          '#title' => $this->t('Auction site'),
          '#default_value' => $config->get('country_code'),
          '#required' => TRUE,
        ];
        return parent::buildForm($form, $form_state);
    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $config = self::getConfig(true);

        foreach(['username', 'webapi_key','test_mode', 'country_code'] as $key) {
            $value = $form_state->getValue($key);
            $config->set($key, $value);
        }

        $password = $form_state->getValue('password');

        if ($password) {
            $config->set('password', $this->hashPassword($password));
        }

        $config->save();
        parent::submitForm($form, $form_state);

        if (allegro_establish_connection()) {
            drupal_set_message('Connected successfully to WebAPI service.');
        } else {
            drupal_set_message('Could not connect to WebAPI service.', 'error');
        }

    }

    /**
     * Gets the configuration names that will be editable.
     *
     * @return array
     *   An array of configuration object names that are editable if called in
     *   conjunction with the trait's config() method.
     */
    protected function getEditableConfigNames()
    {
        return [
          'allegro.settings'
        ];
    }

    /**
     * Returns a unique string identifying the form.
     *
     * @return string
     *   The unique string identifying the form.
     */
    public function getFormId()
    {
        return 'allegro_conf_form';
    }

    private function hashPassword($password) {
        if (AllegroAPI::isPasswordEncrypted()) {
            return AllegroAPI::hashPassword($password);
        }
        return $password;
    }
}