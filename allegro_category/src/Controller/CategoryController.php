<?php
/**
 * Created by PhpStorm.
 * User: marek.kisiel
 * Date: 23/08/16
 * Time: 09:37
 */

namespace Drupal\allegro_category\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;


class CategoryController extends ControllerBase {

  public function page() {
    return [
      '#markup' => '<p>Hello</p>'
    ];
  }

}