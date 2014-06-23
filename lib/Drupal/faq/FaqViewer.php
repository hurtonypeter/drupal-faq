<?php

/**
 * @file
 * Contains \Drupal\faq\FaqViewController.
 */

namespace Drupal\faq;

use Drupal\Component\Utility\String;

/**
 * Controlls the display of questions and answers.
 */
class FaqViewer {

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
  public static function viewQuestion(&$data, \Drupal\node\NodeInterface $node, $path = NULL, $anchor = NULL) {

    $faq_settings = \Drupal::config('faq.settings');
    $disable_node_links = $faq_settings->get('disable_node_links');
    $question = '';

    // Don't link to faq node, instead provide no link, or link to current page.
    if ($disable_node_links) {
      if (empty($path) && empty($anchor)) {
        $question = String::checkPlain($node->getTitle());
      }
      elseif (empty($path)) {
        // Can't seem to use l() function with empty string as screen-readers
        // don't like it, so create anchor name manually.
        $question = '<a id="' . $anchor . '"></a>' . String::checkPlain($node->getTitle());
      }
      else {
        $options = array();
        if ($anchor) {
          $options['attributes'] = array('id' => $anchor);
        }
        $question = l($node->getTitle(), $path, $options);
      }
    }

    // Link to faq node.
    else {
      $node_id = $node->id();
      if (empty($anchor)) {
        $question = l($node->getTitle(), "node/$node_id)");
      }
      else {
        $question = l($node->getTitle(), "node/$node_id", array("attributes" => array("id" => "$anchor")));
      }
    }
    $question = '<span datatype="" property="dc:title">' . $question . '</span>';

    $detailed_question = $node->get('field_detailed_question')->getValue();
    if ($faq_settings->get('display') != 'hide_answer' && !empty($detailed_question) && $faq_settings->get('question_length') == 'both') {
      $question .= '<div class="faq-detailed-question">' . $detailed_question[0]['value'] . '</div>';
    }
    $data['question'] = $question;
  }

  /**
   * Helper function to setup the faq answer.
   *
   * @param &$data
   *   Array reference to store display data in.
   * @param Drupal\node\NodeInterface $node
   *   The node object.
   * @param $back_to_top
   *   An array containing the "back to top" link.
   * @param $teaser
   *   Whether or not to use teasers.
   * @param $links
   *   Whether or not to show node links.
   */
  public static function viewAnswer(&$data, \Drupal\node\NodeInterface $node, $back_to_top, $teaser, $links) {

    // TODO: hide 'submitted by ... on ...'
    $view_mode = $teaser ? 'teaser' : 'full';

    // we don't want to display title and detailed questions two times
    $node->set('title', '');
    $node->set('field_detailed_question', '');

    $node_build = node_view($node, $view_mode);

    $content = drupal_render($node_build);
    
    $content .= $back_to_top;

    $data['body'] = $content;
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
  public static function initBackToTop($path) {
    
    $faq_settings = \Drupal::config('faq.settings');

    $back_to_top = array();
    $back_to_top_text = trim($faq_settings->get('back_to_top'));
    if (!empty($back_to_top_text)) {
      $options = array(
        'attributes' => array('title' => t('Go back to the top of the page.')),
        'html' => TRUE,
        'fragment' => 'top',
      );
      $back_to_top = l(String::checkPlain($back_to_top_text), $path, $options);
    }

    return $back_to_top;
  }

}
