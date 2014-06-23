<?php

/**
 * @file
 * Contains \Drupal\faq\FaqHelper.
 */

namespace Drupal\faq;

use Drupal\node\Entity\Node;

/**
 * Contains static helper functions for FAQ module.
 */
class FaqHelper {

  /**
   * Function to set up the FAQ breadcrumbs for a given taxonomy term.
   *
   * @param $term
   *   The taxonomy term object.
   */
  public static function setFaqBreadcrumb($term = NULL) {
    $faq_settings = \Drupal::config('faq.settings');
    $site_settings = \Drupal::config('system.site');

    $breadcrumb = array();
    if ($faq_settings->get('custom_breadcrumbs')) {
      if (\Drupal::moduleHandler()->moduleExists('taxonomy') && $term) {
        $breadcrumb[] = l(t($term->getName()), 'faq-page/' . $term->id());
        while ($parents = taxonomy_term_load_parents($term->id())) {
          $term = array_shift($parents);
          $breadcrumb[] = l(t($term->getName()), 'faq-page/' . $term->id());
        }
      }
      $breadcrumb[] = l($faq_settings->get('title'), 'faq-page');
      $breadcrumb[] = l(t('Home'), NULL, array('attributes' => array('title' => $site_settings->get('name'))));
      $breadcrumb = array_reverse($breadcrumb);
    }
    return $breadcrumb;
  }

  /**
   * Count number of nodes for a term and its children.
   * 
   * @param int $tid
   *   Id of the tadonomy term to count nodes in.
   * @return int
   *   Returns the count of the nodes in the given term.
   */
  public static function taxonomyTermCountNodes($tid) {
    static $count;

    if (!isset($count) || !isset($count[$tid])) {
      $query = db_select('node', 'n')
        ->fields('n', array('nid'))
        ->addTag('node_access');
      $query->join('taxonomy_index', 'ti', 'n.nid = ti.nid');
      $query->join('node_field_data', 'd', 'd.nid = n.nid');
      $query->condition('n.type', 'faq')
        ->condition('d.status', 1)
        ->condition('ti.tid', $tid);
      $count[$tid] = $query->countQuery()->execute()->fetchField();
    }

    $children_count = 0;
    foreach (FaqHelper::taxonomyTermChildren($tid) as $child_term) {
      $children_count += FaqHelper::taxonomyTermCountNodes($child_term);
    }

    return $count[$tid] + $children_count;
  }

  /**
   * Helper function to taxonomyTermCountNodes() to return list of child terms.
   */
  public static function taxonomyTermChildren($tid) {
    static $children;

    if (!isset($children)) {
      $result = db_select('taxonomy_term_hierarchy', 'tth')
        ->fields('tth', array('parent', 'tid'))
        ->execute();
      while ($term = $result->fetch()) {
        $children[$term->parent][] = $term->tid;
      }
    }

    return isset($children[$tid]) ? $children[$tid] : array();
  }

  /**
   * Helper function for retrieving the sub-categories faqs.
   *
   * @param $term
   *   The category / term to display FAQs for.
   * @param $theme_function
   *   Theme function to use to format the Q/A layout for sub-categories.
   * @param $default_weight
   *   Is 0 for $default_sorting = DESC; is 1000000 for $default_sorting = ASC.
   * @param $default_sorting
   *   If 'DESC', nodes are sorted by creation date descending; if 'ASC', nodes
   *   are sorted by creation date ascending.
   * @param $category_display
   *   The layout of categories which should be used.
   * @param $class
   *   CSS class which the HTML div will be using. A special class name is
   *   required in order to hide and questions / answers.
   * @param $parent_term
   *   The original, top-level, term we're displaying FAQs for.
   */
  public static function getChildCategoriesFaqs($term, $theme_function, $default_weight, $default_sorting, $category_display, $class, $parent_term = NULL) {
    $output = array();

    $list = taxonomy_term_load_children($term->id());

    if (!is_array($list)) {
      return '';
    }
    foreach ($list as $tid => $child_term) {
      $child_term->depth = $term->depth + 1;

      if (FaqHelper::taxonomyTermCountNodes($child_term->id())) {
        $query = db_select('node', 'n');
        $query->join('node_field_data', 'd', 'n.nid = d.nid');
        $ti_alias = $query->innerJoin('taxonomy_index', 'ti', '(n.nid = %alias.nid)');
        $w_alias = $query->leftJoin('faq_weights', 'w', "%alias.tid = {$ti_alias}.tid AND n.nid = %alias.nid");
        $query
          ->fields('n', array('nid'))
          ->condition('n.type', 'faq')
          ->condition('d.status', 1)
          ->condition("{$ti_alias}.tid", $child_term->id())
          ->addTag('node_access');

        $default_weight = 0;
        if ($default_sorting == 'ASC') {
          $default_weight = 1000000;
        }
        // Works, but involves variable concatenation - safe though, since
        // $default_weight is an integer.
        $query->addExpression("COALESCE(w.weight, $default_weight)", 'effective_weight');
        // Doesn't work in Postgres.
        //$query->addExpression('COALESCE(w.weight, CAST(:default_weight as SIGNED))', 'effective_weight', array(':default_weight' => $default_weight));
        $query->orderBy('effective_weight', 'ASC')
          ->orderBy('d.sticky', 'DESC');
        if ($default_sorting == 'ASC') {
          $query->orderBy('d.created', 'ASC');
        }
        else {
          $query->orderBy('d.created', 'DESC');
        }

        // We only want the first column, which is nid, so that we can load all
        // related nodes.
        $nids = $query->execute()->fetchCol();
        $data = Node::loadMultiple($nids);

        $to_render = array(
          '#theme' => $theme_function,
          '#data' => $data,
          '#display_header' => 1,
          '#category_display' => $category_display,
          '#term' => $child_term,
          '#class' => $class,
          '#parent_term' => $parent_term,
        );
        $output[] = drupal_render($to_render);
      }
    }

    return $output;
  }

  /**
   * Helper function to setup the list of sub-categories for the header.
   *
   * @param $term
   *   The term to setup the list of child terms for.
   * @return
   *   An array of sub-categories.
   */
  public static function viewChildCategoryHeaders($term) {

    $child_categories = array();
    $list = taxonomy_term_load_children($term->id());

    foreach ($list as $tid => $child_term) {
      $term_node_count = $this->taxonomyTermCountNodes($child_term->id());
      if ($term_node_count) {

        // Get taxonomy image.
        $term_image = '';
        //taxonomy_image does not exists in D8 yet
        //if (module_exists('taxonomy_image')) {
        //  $term_image = taxonomy_image_display($child_term->tid, array('class' => 'faq-tax-image'));
        //}

        $child_term_id = $child_term->id();
        $term_vars['link'] = l(t($child_term->getName()), "faq-page/$child_term_id");
        $term_vars['description'] = check_markup(t($child_term->getDescription()));
        $term_vars['count'] = $term_node_count;
        $term_vars['term_image'] = $term_image;
        $child_categories[] = $term_vars;
      }
    }

    return $child_categories;
  }

}
