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
    ],
    'allegro-category-delete-all' => [
      'description' => 'Allows to delete all allegro categories from vocabulary',
      'drupal dependencies' => ['allegro_category'],
      'aliases' => ['allegro-csda']
    ]
  ];
}

function drush_allegro_category_synch() {
  $count = allegro_category_remote_data_count();
  $package = 5000;
  $index = 0;
  //download categories in $package portions
  for($index; $index < $count; $index += $package) {
      allegro_category_download($index, $package);
      drush_print('Fetched ' . $index + $package . ' categories');
  }
  drush_print('Remote categories amount: ' . $count);

}

function drush_allegro_category_delete_all() {
  $vocab = \Drupal\taxonomy\Entity\Vocabulary::load('allegro_categories');

  if (! $vocab) {
    throw new Exception("Missing 'allegro_categories' vocabulary");
  }

  $tree = \Drupal::service('entity_type.manager')
    ->getStorage("taxonomy_term")
    ->loadTree('allegro_categories', 0, NULL, FALSE);

  foreach($tree as $term) {
    $term_entity = \Drupal\taxonomy\Entity\Term::load($term->tid);
    if ($term_entity) {
      $term_entity->delete();
    }
  }

}