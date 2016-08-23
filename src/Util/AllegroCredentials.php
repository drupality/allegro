<?php
/**
 * Created by PhpStorm.
 * User: marek.kisiel
 * Date: 23/08/16
 * Time: 11:16
 */

namespace Drupal\allegro\Util;


class AllegroCredentials {

  private $config;

  private static $instance;

  public static function get() {
    if (self::$instance == NULL) {
      self::$instance = new self;
    }
    return self::$instance;
  }

  private function __construct() {
    $this->config = \Drupal\allegro\Form\AllegroConfigForm::getConfig();
  }

  public function getUsername() {
    return $this->config->get('username');
  }

  public function getPassword() {
    return $this->config->get('password');
  }

  public function getWebAPIkey() {
    return $this->config->get('webapi_key');
  }

  public function isTestMode() {
    return $this->config->get('test_mode');
  }

  public function getCountryCode() {
    return $this->config->get('country_code');
  }


}