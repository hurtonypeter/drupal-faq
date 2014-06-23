<?php

/**
 * @file
 * Contains \Drupal\faq\Controller\FaqController.
 */

namespace Drupal\faq\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\Component\Utility\String;
use Drupal\faq\FaqHelper;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller routines for FAQ routes.
 */
class FaqController extends ControllerBase {
  /*   * *****************************************************
   * FAQ PAGES
   * **************************************************** */

  /**
   * Function to display the faq page.
   * 
   * @param int $tid
   *   Default is 0, determines if the questions and answers on the page
   *   will be shown according to a category or non-categorized.
   * @param string $faq_display
   *   Optional parameter to override default question layout setting.
   * @param string $category_display
   *   Optional parameter to override default category layout setting.
   * @return
   *   The page with FAQ questions and answers.
   * @throws NotFoundHttpException
   */
  public function faqPage($tid = 0, $faq_display = '', $category_display = '') {
    $faq_settings = \Drupal::config('faq.settings');

    $output = $output_answers = '';

    $build = array();
    $build['#type'] = 'markup';
    $build['#attached']['css'] = array(
      drupal_get_path('module', 'faq') . '/css/faq.css'
    );
    if (arg(0) == 'faq-page') {
      $build['#title'] = $faq_settings->get('title');
    }
    if (!$this->moduleHandler()->moduleExists('taxonomy')) {
      $tid = 0;
    }

    // Configure the breadcrumb trail.
    if (!empty($tid) && $current_term = Term::load($tid)) {
      //if (!\Drupal::service('path.alias_manager.cached')->getPathAlias(arg(0) . '/' . $tid) && $this->moduleHandler()->moduleExists('pathauto')) {
      //pathauto is not exists in D8 yet
      //$alias = pathauto_create_alias('faq', 'insert', arg(0) . '/' . arg(1), array('term' => $current_term));
      //if ($alias) {
      //  drupal_goto($alias['alias']);
      //}
      //}
      // drupal_match_path() is now deprecated, should 
      // use  \Drupal\Core\Path\PathMatcherInterface::matchPath()
      // accordint to the documentation, but it's not exists
      if (drupal_match_path(current_path(), 'faq-page/*')) {
        $this->_setFaqBreadcrumb($current_term);
      }
    }

    if (empty($faq_display)) {
      $faq_display = $faq_settings->get('display');
    }
    $use_categories = $faq_settings->get('use_categories');
    if (!empty($category_display)) {
      $use_categories = TRUE;
    }
    else {
      $category_display = $faq_settings->get('category_display');
    }
    if (!$this->moduleHandler()->moduleExists('taxonomy')) {
      $use_categories = FALSE;
    }

    if (($use_categories && $category_display == 'hide_qa') || $faq_display == 'hide_answer') {
      $build['#attached']['js'] = array(
        array(
          'data' => drupal_get_path('module', 'faq') . '/js/faq.js'
        ),
        array(
          'data' => array(
            'hide_qa_accordion' => $faq_settings->get('hide_qa_accordion'),
            'category_hide_qa_accordion' => $faq_settings->get('category_hide_qa_accordion')
          ),
          'type' => 'setting'
        )
      );
    }

    // Non-categorized questions and answers.
    if (!$use_categories || ($category_display == 'none' && empty($tid))) {
      if (!empty($tid)) {
        throw new NotFoundHttpException();
      }
      $default_sorting = $faq_settings->get('default_sorting');

      $query = db_select('node', 'n');
      $weight_alias = $query->leftJoin('faq_weights', 'w', '%alias.nid=n.nid');
      $node_data = $query->leftJoin('node_field_data', 'd', 'd.nid=n.nid');
      $query
        ->addTag('node_access')
        ->fields('n', array('nid'))
        ->condition('n.type', 'faq')
        ->condition('d.status', 1)
        ->condition(db_or()->condition("$weight_alias.tid", 0)->isNull("$weight_alias.tid"));

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

      // Only need the nid column.
      $nids = $query->execute()->fetchCol();
      $data = Node::loadMultiple($nids);

      $questions_to_render = array();
      $questions_to_render['#data'] = $data;

      switch ($faq_display) {
        case 'questions_top':
          $questions_to_render['#theme'] = 'faq_questions_top';
          break;

        case 'hide_answer':
          $questions_to_render['#theme'] = 'faq_hide_answer';
          break;

        case 'questions_inline':
          $questions_to_render['#theme'] = 'faq_questions_inline';
          break;

        case 'new_page':
          $questions_to_render['#theme'] = 'faq_new_page';
          break;
      } // End of switch.
      $output = drupal_render($questions_to_render);
    }

    // Categorize questions.
    else {
      $hide_child_terms = $faq_settings->get('hide_child_terms');

      // If we're viewing a specific category/term.
      if (!empty($tid)) {
        if ($term = Term::load($tid)) {
          $title = $faq_settings->get('title');
          if (arg(0) == 'faq-page' && is_numeric(arg(1))) {
            $build['#title'] = ($title . ($title ? ' - ' : '') . $this->t($term->getName()));
          }
          $this->_displayFaqByCategory($faq_display, $category_display, $term, 0, $output, $output_answers);
          $to_render = array(
            '#theme' => 'faq_page',
            '#content' => $output,
            '#answers' => $output_answers,
          );
          $build['#markup'] = drupal_render($to_render);
          return $build;
        }
        else {
          throw new NotFoundHttpException();
        }
      }

      $list_style = $faq_settings->get('category_listing');
      $vocabularies = Vocabulary::loadMultiple();
      $vocab_omit = $faq_settings->get('omit_vocabulary');
      $items = array();
      $vocab_items = array();
      foreach ($vocabularies as $vid => $vobj) {
        if (isset($vocab_omit[$vid]) && $vocab_omit[$vid] != 0) {
          continue;
        }

        if ($category_display == "new_page") {
          $vocab_items = $this->_getIndentedFaqTerms($vid, 0);
          $items = array_merge($items, $vocab_items);
        }
        // Not a new page.
        else {
          if ($hide_child_terms && $category_display == 'hide_qa') {
            $tree = taxonomy_get_tree($vid, 0, 1, TRUE);
          }
          else {
            $tree = taxonomy_get_tree($vid, 0, NULL, TRUE);
          }
          foreach ($tree as $term) {
            switch ($category_display) {
              case 'hide_qa':
              case 'categories_inline':
                if (FaqHelper::taxonomyTermCountNodes($term->id())) {
                  $this->_displayFaqByCategory($faq_display, $category_display, $term, 1, $output, $output_answers);
                }
                break;
            }
          }
        }
      }

      if ($category_display == "new_page") {
        $output = $this->_renderCategoriesToList($items, $list_style);
      }
    }

    $faq_description = $faq_settings->get('description');
    $format = $faq_settings->get('description_format');
    if ($format) {
      $faq_description = check_markup($faq_description, $format);
    }

    $markup = array(
      '#theme' => 'faq_page',
      '#content' => $output,
      '#answers' => $output_answers,
      '#description' => $faq_description,
    );
    $build['#markup'] = drupal_render($markup);

    return $build;
  }

  /**
   * Define the elements for the FAQ Settings page - order tab.
   *
   * @param $category
   *   The category id of the FAQ page to reorder.
   * @return
   *   The form code, before being converted to HTML format.
   */
  public function orderPage($category = NULL) {

    $faq_settings = \Drupal::config('faq.settings');
    $build = array();

    $build['#attached']['js'] = array(
      array(
        'data' => drupal_get_path('module', 'faq') . '/js/faq.js'
      ),
      array(
        'data' => array(
          'hide_qa_accordion' => $faq_settings->get('hide_qa_accordion'),
          'category_hide_qa_accordion' => $faq_settings->get('category_hide_qa_accordion')
        ),
        'type' => 'setting'
      )
    );
    $build['#attached']['css'] = array(
      drupal_get_path('module', 'faq') . '/css/faq.css'
    );

    $build['faq_order'] = $this->formBuilder()->getForm('Drupal\faq\Form\OrderForm');

    return $build;
  }

  /**
   * Renders the form for the FAQ Settings page - General tab.
   *
   * @return
   *   The form code inside the $build array.
   */
  public function generalSettings() {
    $build = array();

    $build['faq_general_settings_form'] = $this->formBuilder()->getForm('Drupal\faq\Form\GeneralForm');

    return $build;
  }

  /**
   * Renders the form for the FAQ Settings page - Questions tab.
   *
   * @return
   *   The form code inside the $build array.
   */
  public function questionsSettings() {
    $faq_settings = \Drupal::config('faq.settings');

    $build = array();

    $build['#attached']['js'] = array(
      array(
        'data' => drupal_get_path('module', 'faq') . '/js/faq.js'
      ),
      array(
        'data' => array(
          'hide_qa_accordion' => $faq_settings->get('hide_qa_accordion'),
          'category_hide_qa_accordion' => $faq_settings->get('category_hide_qa_accordion')
        ),
        'type' => 'setting'
      )
    );

    $build['faq_questions_settings_form'] = $this->formBuilder()->getForm('Drupal\faq\Form\QuestionsForm');

    return $build;
  }

  /**
   * Renders the form for the FAQ Settings page - Categories tab.
   *
   * @return
   *   The form code inside the $build array.
   */
  public function categoriesSettings() {
    $faq_settings = \Drupal::config('faq.settings');

    $build = array();

    $build['#attached']['js'] = array(
      array(
        'data' => drupal_get_path('module', 'faq') . '/js/faq.js'
      ),
      array(
        'data' => array(
          'hide_qa_accordion' => $faq_settings->get('hide_qa_accordion'),
          'category_hide_qa_accordion' => $faq_settings->get('category_hide_qa_accordion')
        ),
        'type' => 'setting'
      )
    );

    if (!$this->moduleHandler()->moduleExists('taxonomy')) {
      drupal_set_message(t('Categorization of questions will not work without the "taxonomy" module being enabled.'), 'error');
    }

    $build['faq_categories_settings_form'] = $this->formBuilder()->getForm('Drupal\faq\Form\CategoriesForm');

    return $build;
  }

  /*   * ***************************************************************
   * PRIVATE HELPER FUCTIONS
   * *************************************************************** */

  /**
   * Function to set up the FAQ breadcrumbs for a given taxonomy term.
   *
   * @param $term
   *   The taxonomy term object.
   */
  private function _setFaqBreadcrumb($term = NULL) {
    $faq_settings = $this->config('faq.settings');
    $site_settings = $this->config('system.site');
    $breadcrumbManager = new \Drupal\Core\Breadcrumb\BreadcrumbManager($this->moduleHandler());

    $breadcrumb = array();
    if ($faq_settings->get('custom_breadcrumbs')) {
      if ($this->moduleHandler()->moduleExists('taxonomy') && $term) {
        $breadcrumb[] = l($this->t($term->getName()), 'faq-page/' . $term->id());
        while ($parents = taxonomy_term_load_parents($term->id())) {
          $term = array_shift($parents);
          $breadcrumb[] = l($this->t($term->getName()), 'faq-page/' . $term->id());
        }
      }
      $breadcrumb[] = l($faq_settings->get('title'), 'faq-page');
      $breadcrumb[] = l(t('Home'), NULL, array('attributes' => array('title' => $site_settings->get('name'))));
      $breadcrumb = array_reverse($breadcrumb);var_dump($breadcrumb);
      return $breadcrumbManager->build($breadcrumb);
    }
    // This is also used to set the breadcrumbs in the faq_preprocess_page()
    // so we need to return a valid trail.
    return $breadcrumbManager->build($breadcrumb);
  }

  /**
   * Display FAQ questions and answers filtered by category.
   *
   * @param $faq_display
   *   Define the way the FAQ is being shown; can have the values:
   *   'questions top',hide answers','questions inline','new page'.
   * @param $category_display
   *   The layout of categories which should be used.
   * @param $term
   *   The category / term to display FAQs for.
   * @param $display_header
   *   Set if the header will be shown or not.
   * @param &$output
   *   Reference which holds the content of the page, HTML formatted.
   * @param &$output_answer
   *   Reference which holds the answers from the FAQ, when showing questions
   *   on top.
   */
  private function _displayFaqByCategory($faq_display, $category_display, $term, $display_header, &$output, &$output_answers) {
    $default_sorting = \Drupal::config('faq.settings')->get('default_sorting');

    $term_id = $term->id();

    $query = db_select('node', 'n');
    $query->join('node_field_data', 'd', 'd.nid = n.nid');
    $query->innerJoin('taxonomy_index', 'ti', 'n.nid = ti.nid');
    $query->leftJoin('faq_weights', 'w', 'w.tid = ti.tid AND n.nid = w.nid');
    $query->fields('n', array('nid'))
      ->condition('n.type', 'faq')
      ->condition('d.status', 1)
      ->condition("ti.tid", $term_id)
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

    // Handle indenting of categories.
    $depth = 0;
    if (!isset($term->depth)) {
      $children = taxonomy_term_load_children($term->id());
      $term->depth = count($children);
    }
    while ($depth < $term->depth) {
      $display_header = 1;
      $indent = '<div class="faq-category-indent">';
      $output .= $indent;
      $depth++;
    }

    // Set up the class name for hiding the q/a for a category if required.
    $faq_class = "faq-qa";
    if ($category_display == "hide_qa") {
      $faq_class = "faq-qa-hide";
    }

    $output_render = $output_answers_render = array(
      '#data' => $data,
      '#display_header' => $display_header,
      '#category_display' => $category_display,
      '#term' => $term,
      '#class' => $faq_class,
      '#parent_term' => $term,
    );

    switch ($faq_display) {
      case 'questions_top':
        $output_render['#theme'] = 'faq_category_questions_top';
        $output .= drupal_render($output_render);
        $output_answers_render['#theme'] = 'faq_category_questions_top_answers';
        $output_answers .= drupal_render($output_answers_render);
        break;

      case 'hide_answer':
        $output_render['#theme'] = 'faq_category_hide_answer';
        $output .= drupal_render($output_render);
        break;

      case 'questions_inline':
        $output_render['#theme'] = 'faq_category_questions_inline';
        $output .= drupal_render($output_render);
        break;

      case 'new_page':
        $output_render['#theme'] = 'faq_category_new_page';
        $output .= drupal_render($output_render);
        break;
    }
    // Handle indenting of categories.
    while ($depth > 0) {
      $output .= '</div>';
      $depth--;
    }
  }

  /**
   * Return a structured array that consists a list of terms indented according to the term depth.
   *
   * @param $vid
   *   Vocabulary id.
   * @param $tid
   *   Term id.
   * @return
   *   Return a HTML formatted list of terms indented according to the term depth.
   */
  private function _getIndentedFaqTerms($vid, $tid) {
    //if ($this->moduleHandler()->moduleExists('pathauto')) {
    // pathauto does't exists in D8 yet
    //}
    $faq_settings = \Drupal::config('faq.settings');

    $display_faq_count = $faq_settings->get('count');
    $hide_child_terms = $faq_settings->get('hide_child_terms');

    $items = array();
    $tree = taxonomy_get_tree($vid, $tid, 1, TRUE);

    foreach ($tree as $term) {
      $term_id = $term->id();
      $tree_count = FaqHelper::taxonomyTermCountNodes($term_id);

      if ($tree_count) {
        // Get term description.
        $desc = '';
        $term_description = $term->getDescription();
        if (!empty($term_description)) {
          $desc = '<div class="faq-qa-description">';
          $desc .= $term_description . "</div>";
        }


        $query = db_select('node', 'n');
        $query->join('node_field_data', 'd', 'n.nid = d.nid');
        $query->innerJoin('taxonomy_index', 'ti', 'n.nid = ti.nid');
        $term_node_count = $query->condition('d.status', 1)
          ->condition('n.type', 'faq')
          ->condition("ti.tid", $term_id)
          ->addTag('node_access')
          ->countQuery()
          ->execute()
          ->fetchField();


        if ($term_node_count > 0) {
          $path = "faq-page/$term_id";

          // pathauto is not exists in D8 yet
          //if (!\Drupal::service('path.alias_manager.cached')->getPathAlias(arg(0) . '/' . $tid) && $this->moduleHandler()->moduleExists('pathauto')) {
          //}

          if ($display_faq_count) {
            $count = $term_node_count;
            if ($hide_child_terms) {
              $count = $tree_count;
            }
            $cur_item = l($this->t($term->getName()), $path) . " ($count) " . $desc;
          }
          else {
            $cur_item = l($this->t($term->getName()), $path) . $desc;
          }
        }
        else {
          $cur_item = String::checkPlain($this->t($term->getName())) . $desc;
        }
        if (!empty($term_image)) {
          $cur_item .= '<div class="clear-block"></div>';
        }

        $term_items = array();
        if (!$hide_child_terms) {
          $term_items = $this->_getIndentedFaqTerms($vid, $term_id);
        }
        $items[] = array(
          "items" => $cur_item,
          "children" => $term_items,
        );
      }
    }

    return $items;
  }

  /**
   * Renders the output of getIntendedTerms to HTML list.
   * 
   * @param array $items
   *   The structured array made by getIntendedTerms function
   * @param string $list_style
   *   List style type: ul or ol.
   * @param int $first
   *   Default value is 0, it's used for only to controll the recursive iteration.
   * @return string
   *   HTML formatted output.
   */
  private function _renderCategoriesToList($items, $list_style, $first = 0) {
    $output = '';
    $first_iter = array();

    foreach ($items as $item) {
      $pre = '';
      if (!empty($item['children'])) {
        $pre = $this->_renderCategoriesToList($item['children'], $list_style, $first + 1);
      }
      $render = array(
        '#theme' => 'item_list',
        '#items' => array($item['items'] . $pre),
        '#list_style' => $list_style,
      );
      if ($first == 0) {
        $output .= drupal_render($render);
      }
      elseif ($first == 1) {
        $first_iter [] = $item['items'] . $pre;
      }
      else {
        $first_iter[] = drupal_render($render);
      }
    }

    if (!$first) {
      return $output;
    }
    else {
      $render_first = array(
        '#theme' => 'item_list',
        '#items' => $first_iter,
        '#list_style' => $list_style,
      );
      return drupal_render($render_first);
    }
  }

}
