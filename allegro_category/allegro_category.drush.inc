<?php
/**
 * Created by PhpStorm.
 * User: marek.kisiel
 * Date: 23/08/16
 * Time: 10:37
 */

/**
 * Implements hook_drush_command()
 */
function allegro_category_drush_command() {
  return [
    'allegro-category-synch' => [
      'description' => 'Allows to synch allegro categories with related taxonomy vocabulary',
      'drupal dependencies' => ['allegro_category'],
      'aliases' => ['allegro-cs']
    ]
  ];
}

function drush_allegro_category_synch() {
  drush_print('Hello');
}