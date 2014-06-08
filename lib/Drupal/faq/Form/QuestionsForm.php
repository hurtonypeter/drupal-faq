<?php

namespace Drupal\faq\Form;

use Drupal\Core\Form\FormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class QuestionsForm extends FormBase {
    
    public function getFormId() {
        return 'faq_questions_settings_form';
    }
    
    public function buildForm(array $form, array &$form_state) {
        $faq_settings = \Drupal::config('faq.settings');
        
        $display_options['questions_inline'] = t('Questions inline');
        $display_options['questions_top'] = t('Clicking on question takes user to answer further down the page');
        $display_options['hide_answer'] = t('Clicking on question opens/hides answer under question');
        $display_options['new_page'] = t('Clicking on question opens the answer in a new page');

        $form['faq_display'] = array(
            '#type' => 'radios',
            '#options' => $display_options,
            '#title' => t('Page layout'),
            '#description' => t('This controls how the questions and answers are displayed on the page and what happens when someone clicks on the question.'),
            '#default_value' => $faq_settings->get('display')
        );
        
        $form['faq_questions_misc'] = array(
            '#type' => 'fieldset',
            '#title' => t('Miscellaneous layout settings'),
            '#collapsible' => TRUE,
        );
        
        $form['faq_questions_misc']['faq_question_listing'] = array(
            '#type' => 'select',
            '#options' => array(
                'ol' => t('Ordered list'),
                'ul' => t('Unordered list'),
            ),
            '#title' => t('Questions listing style'),
            '#description' => t("This allows to select how the questions listing is presented.  It only applies to the layouts: 'Clicking on question takes user to answer further down the page' and 'Clicking on question opens the answer in a new page'.  An ordered listing would number the questions, whereas an unordered list will have a bullet to the left of each question."),
            '#default_value' => $faq_settings->get('question_listing')
        );

        $form['faq_questions_misc']['faq_qa_mark'] = array(
            '#type' => 'checkbox',
            '#title' => t('Label questions and answers'),
            '#description' => t('This option is only valid for the "Questions Inline" and "Clicking on question takes user to answer further down the page" layouts.  It labels all questions on the faq page with the "question label" setting and all answers with the "answer label" setting.  For example these could be set to "Q:" and "A:".'),
            '#default_value' => $faq_settings->get('qa_mark')
        );

        $form['faq_questions_misc']['faq_question_label'] = array(
            '#type' => 'textfield',
            '#title' => t('Question Label'),
            '#description' => t('The label to pre-pend to the question text in the "Questions Inline" layout if labelling is enabled.'),
            '#default_value' => $faq_settings->get('question_label')
        );

        $form['faq_questions_misc']['faq_answer_label'] = array(
            '#type' => 'textfield',
            '#title' => t('Answer Label'),
            '#description' => t('The label to pre-pend to the answer text in the "Questions Inline" layout if labelling is enabled.'),
            '#default_value' => $faq_settings->get('answer_label')
        );

        $form['faq_questions_misc']['faq_question_length'] = array(
            '#type' => 'radios',
            '#title' => t('Question length'),
            '#options' => array(
                'long' => t('Display longer text'),
                'short' => t('Display short text'),
                'both' => t('Display both short and long questions'),
            ),
            '#description' => t("The length of question text to display on the FAQ page.  The short question will always be displayed in the FAQ blocks."),
            '#default_value' => $faq_settings->get('question_length')
        );

        $form['faq_questions_misc']['faq_question_long_form'] = array(
            '#type' => 'checkbox',
            '#title' => t('Allow long question text to be configured'),
            '#default_value' => $faq_settings->get('question_long_form')
        );

        $form['faq_questions_misc']['faq_hide_qa_accordion'] = array(
            '#type' => 'checkbox',
            '#title' => t('Use accordion effect for "opens/hides answer under question" layout'),
            '#description' => t('This enables an "accordion" style effect where when a question is clicked, the answer appears beneath, and is then hidden when another question is opened.'),
            '#default_value' => $faq_settings->get('hide_qa_accordion')
        );

        $form['faq_questions_misc']['faq_show_expand_all'] = array(
            '#type' => 'checkbox',
            '#title' => t('Show "expand / collapse all" links for collapsed questions'),
            '#description' => t('The links will only be displayed if using the "opens/hides answer under question" or "opens/hides questions and answers under category" layouts.'),
            '#default_value' => $faq_settings->get('show_expand_all')
          );

        $form['faq_questions_misc']['faq_use_teaser'] = array(
            '#type' => 'checkbox',
            '#title' => t('Use answer teaser'),
            '#description' => t("This enables the display of the answer teaser text instead of the full answer when using the 'Questions inline' or 'Clicking on question takes user to answer further down the page' display options.  This is useful when you have long descriptive text.  The user can see the full answer by clicking on the question."),
            '#default_value' => $faq_settings->get('use_teaser')
        );

        $form['faq_questions_misc']['faq_show_node_links'] = array(
            '#type' => 'checkbox',
            '#title' => t('Show node links'),
            '#description' => t('This enables the display of links under the answer text on the faq page.  Examples of these links include "Read more", "Add comment".'),
            '#default_value' => $faq_settings->get('show_node_links')
        );

        $form['faq_questions_misc']['faq_back_to_top'] = array(
            '#type' => 'textfield',
            '#title' => t('"Back to Top" link text'),
            '#description' => t('This allows the user to change the text displayed for the links which return the user to the top of the page on certain page layouts.  Defaults to "Back to Top".  Leave blank to have no link.'),
            '#default_value' => $faq_settings->get('back_to_top')
        );

        $form['faq_questions_misc']['faq_disable_node_links'] = array(
            '#type' => 'checkbox',
            '#title' => t('Disable question links to nodes'),
            '#description' => t('This allows the user to prevent the questions being links to the faq node in all layouts except "Clicking on question opens the answer in a new page".'),
            '#default_value' => $faq_settings->get('disable_node_links'),
        );

        $form['faq_questions_misc']['faq_default_sorting'] = array(
            '#type' => 'select',
            '#title' => t('Default sorting for unordered FAQs'),
            '#options' => array(
                'DESC' => t('Date Descending'),
                'ASC' => t('Date Ascending'),
            ),
            '#description' => t("This controls the default ordering behaviour for new FAQ nodes which haven't been assigned a position."),
            '#default_value' => $faq_settings->get('default_sorting')
        );
        
        $form['actions'] = array('#type' => 'actions');
        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Save configuration')
        );
        
        return $form;
    }

    public function submitForm(array &$form, array &$form_state) {
        // Remove unnecessary values.
        form_state_values_clean($form_state);
        
        $faq_settings = \Drupal::config('faq.settings');
        
        $faq_settings->set('display', $form_state['values']['faq_display']);
        $faq_settings->set('question_listing', $form_state['values']['faq_question_listing']);
        $faq_settings->set('qa_mark', $form_state['values']['faq_qa_mark']);
        $faq_settings->set('question_label', $form_state['values']['faq_question_label']);
        $faq_settings->set('answer_label', $form_state['values']['faq_answer_label']);
        $faq_settings->set('question_length', $form_state['values']['faq_question_length']);
        $faq_settings->set('question_long_form', $form_state['values']['faq_question_long_form']);
        $faq_settings->set('hide_qa_accordion', $form_state['values']['faq_hide_qa_accordion']);
        $faq_settings->set('show_expand_all', $form_state['values']['faq_show_expand_all']);
        $faq_settings->set('use_teaser', $form_state['values']['faq_use_teaser']);
        $faq_settings->set('show_node_links', $form_state['values']['faq_show_node_links']);
        $faq_settings->set('back_to_top', $form_state['values']['faq_back_to_top']);
        $faq_settings->set('disable_node_links', $form_state['values']['faq_disable_node_links']);
        $faq_settings->set('default_sorting', $form_state['values']['faq_default_sorting']);
        
        $faq_settings->save();
    }

}