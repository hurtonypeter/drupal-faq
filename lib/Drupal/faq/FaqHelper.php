<?php

/**
 * @file
 * Contains \Drupal\user\FaqHelper.
 */

namespace Drupal\faq;

use \Drupal\Component\Utility\String;
use \Drupal\Core\Extension\ModuleHandler;
use \Drupal\node\Entity\Node;

/**
 * Contains static helper functions for FAQ module.
 */
class FaqHelper {

  /**
   * Count number of nodes for a term and its children.
   * 
   * @param int $tid
   *   Id of the tadonomy term to count nodes in.
   * @return int
   *   Returns the count of the nodes in the given term.
   */
  public static function faq_taxonomy_term_count_nodes($tid) {
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
    foreach ($this->faq_taxonomy_term_children($tid) as $child_term) {
      $children_count += $this->faq_taxonomy_term_count_nodes($child_term);
    }

    return $count[$tid] + $children_count;
  }

  /**
   * Helper function to faq_taxonomy_term_count_nodes() to return list of child terms.
   */
  public static function faq_taxonomy_term_children($tid) {
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
   * Helper function to setup the faq question.
   *
   * @param &$data
   *   Array reference to store display data in.
   * @param $node
   *   The node object.
   * @param $path
   *   The path/url which the question should link to if links are disabled.
   * @param $anchor
   *   Link anchor to use in question links.
   */
  public static function faq_view_question(&$data, \Drupal\node\NodeInterface $node, $path = NULL, $anchor = NULL) {
    $faq_settings = \Drupal::config('faq.settings');
    $disable_node_links = $faq_settings->get('disable_node_links');
    $question = '';

    // Don't link to faq node, instead provide no link, or link to current page.
    if ($disable_node_links) {
      if (empty($path) && empty($anchor)) {
        $question = String::checkPlain($node->title);
      }
      elseif (empty($path)) {
        // Can't seem to use l() function with empty string as screen-readers
        // don't like it, so create anchor name manually.
        $question = '<a id="' . $anchor . '"></a>' . String::checkPlain($node->title);
      }
      else {
        $options = array();
        if ($anchor) {
          $options['attributes'] = array('id' => $anchor);
        }
        $question = l($node->title, $path, $options);
      }
    }

    // Link to faq node.
    else {
      if (empty($anchor)) {
        $question = l($node->title, "node/$node->nid");
      }
      else {
        $question = l($node->title, "node/$node->nid", array("attributes" => array("id" => "$anchor")));
      }
    }
    $question = '<span datatype="" property="dc:title">' . $question . '</span>';

    if ($faq_settings->get('display') != 'hide_answer' && !empty($node->detailed_question) && $faq_settings->get('question_length') == 'both') {
      $node->detailed_question = check_markup($node->detailed_question, 'filtered_html', '', FALSE);
      $question .= '<div class="faq-detailed-question">' . $node->detailed_question . '</div>';
    }
    $data['question'] = $question;
  }

  /**
   * Helper function to setup the faq answer.
   *
   * @param &$data
   *   Array reference to store display data in.
   * @param $node
   *   The node object.
   * @param $back_to_top
   *   An array containing the "back to top" link.
   * @param $teaser
   *   Whether or not to use teasers.
   * @param $links
   *   Whether or not to show node links.
   */
  public static function faq_view_answer(&$data, \Drupal\node\NodeInterface $node, $back_to_top, $teaser, $links) {

    $moduleHandler = new ModuleHandler();

    $view_mode = $teaser ? 'teaser' : 'full';
    $langcode = $GLOBALS['language_content']->language;

    // Build the faq node content and invoke other modules' links, etc, functions.
    $node = (object) $node;
    //TODO: node_build_content() in D8?
    node_build_content($node, $view_mode, $langcode);

    // Add "edit answer" link if they have the correct permissions.
    //TODO: node_access() in D8?
    if (node_access('update', $node)) {
      $node->content['links']['node']['#links']['faq_edit_link'] = array(
        'title' => t('Edit answer'),
        'href' => "node/$node->nid/edit",
        'query' => drupal_get_destination(),
        'attributes' => array('title' => t('Edit answer')),
      );
    }

    // Add "back to top" link.
    if (!empty($back_to_top)) {
      $node->content['links']['node']['#links']['faq_back_to_top'] = $back_to_top;
    }
    $build = $node->content;
    // We don't need duplicate rendering info in node->content.
    unset($node->content);

    $build += array(
      '#theme' => 'node',
      '#node' => $node,
      '#view_mode' => $view_mode,
      '#language' => $langcode,
    );

    // Add contextual links for this node.
    if (!empty($node->nid) && !($view_mode == 'full' && node_is_page($node))) {
      $build['#contextual_links']['node'] = array('node', array($node->nid));
    }

    // Allow modules to modify the structured node.
    $type = 'node';
    $moduleHandler->alter(array('node_view', 'entity_view'), $build, $type);

    $node_links = ($links ? $build['links']['node']['#links'] : (!empty($back_to_top) ? array($build['links']['node']['#links']['faq_back_to_top']) : NULL));
    unset($build['links']);
    unset($build['#theme']); // We don't want node title displayed.

    $content = drupal_render($build);

    // Unset unused $node text so that a bad theme can not open a security hole.
    // $node->body = NULL;
    // $node->teaser = NULL;

    $data['body'] = $content;
    //todo: change theme()
    //$data['links'] = !empty($node_links) ? theme('links', array('links' => $node_links, 'attributes' => array('class' => 'links inline'))) : '';
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
  public static function faq_get_child_categories_faqs($term, $theme_function, $default_weight, $default_sorting, $category_display, $class, $parent_term = NULL) {
    $output = array();

    $list = taxonomy_term_load_children($term->tid);

    if (!is_array($list)) {
      return '';
    }
    foreach ($list as $tid => $child_term) {
      $child_term->depth = $term->depth + 1;

      if ($this->faq_taxonomy_term_count_nodes($child_term->tid)) {
        $query = db_select('node', 'n');
        $query->join('node_field_data', 'd', 'n.nid = d.nid');
        $ti_alias = $query->innerJoin('taxonomy_index', 'ti', '(n.nid = %alias.nid)');
        $w_alias = $query->leftJoin('faq_weights', 'w', "%alias.tid = {$ti_alias}.tid AND n.nid = %alias.nid");
        $query
          ->fields('n', array('nid'))
          ->condition('n.type', 'faq')
          ->condition('d.status', 1)
          ->condition("{$ti_alias}.tid", $child_term->tid)
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

        //TODO: change theme() 
        //$output[] = theme($theme_function, array('data' => $data, 'display_header' => 1, 'category_display' => $category_display, 'term' => $child_term, 'class' => $class, 'parent_term' => $parent_term));
      }
    }

    return $output;
  }

  /**
   * Helper function to setup the "back to top" link.
   *
   * @param $path
   *   The path/url where the "back to top" link should bring the user too.  This
   *   could be the 'faq-page' page or one of the categorized faq pages, e.g 'faq-page/123'
   *   where 123 is the tid.
   * @return
   *   An array containing the "back to top" link.
   */
  public static function faq_init_back_to_top($path) {

    $faq_settings = \Drupal::config('faq.settings');

    $back_to_top = array();
    $back_to_top_text = trim($faq_settings->get('back_to_top'));
    if (!empty($back_to_top_text)) {
      $back_to_top = array(
        'title' => String::checkPlain($back_to_top_text),
        'href' => $path,
        'attributes' => array('title' => t('Go back to the top of the page.')),
        'fragment' => 'top',
        'html' => TRUE,
      );
    }

    return $back_to_top;
  }

  /**
   * Helper function to setup the list of sub-categories for the header.
   *
   * @param $term
   *   The term to setup the list of child terms for.
   * @return
   *   An array of sub-categories.
   */
  public static function faq_view_child_category_headers($term) {

    $child_categories = array();
    $list = taxonomy_term_load_children($term->tid);

    foreach ($list as $tid => $child_term) {
      $term_node_count = $this->faq_taxonomy_term_count_nodes($child_term->tid);
      if ($term_node_count) {

        // Get taxonomy image.
        $term_image = '';
        //taxonomy_image does not exists in D8 yet
        //if (module_exists('taxonomy_image')) {
        //  $term_image = taxonomy_image_display($child_term->tid, array('class' => 'faq-tax-image'));
        //}

        $term_vars['link'] = l(t($child_term->name), "faq-page/$child_term->tid");
        $term_vars['description'] = check_markup(t($child_term->description));
        $term_vars['count'] = $term_node_count;
        $term_vars['term_image'] = $term_image;
        $child_categories[] = $term_vars;
      }
    }

    return $child_categories;
  }

}
