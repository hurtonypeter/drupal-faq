<?php

namespace Drupal\faq\Form;

use Drupal\Core\Form\FormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\taxonomy\Entity\Vocabulary;

class CategoriesForm extends FormBase {
    
    public function getFormId() {
        return 'faq_categories_settings_form';
    }
    
    public function buildForm(array $form, array &$form_state) {
        $faq_settings = \Drupal::config('faq.settings');
        
        // Set up a hidden variable.
        $form['faq_display'] = array(
            '#type' => 'hidden',
            '#value' => $faq_settings->get('display')
        );
        
        $form['faq_use_categories'] = array(
            '#type' => 'checkbox',
            '#title' => t('Categorize questions'),
            '#description' => t('This allows the user to display the questions according to the categories configured on the add/edit FAQ page.  Use of sub-categories is only recommended for large lists of questions.  The Taxonomy module must be enabled.'),
            '#default_value' => $faq_settings->get('use_categories')
        );

        $category_options['none'] = t("Don't display");
        $category_options['categories_inline'] = t('Categories inline');
        $category_options['hide_qa'] = t('Clicking on category opens/hides questions and answers under category');
        $category_options['new_page'] = t('Clicking on category opens the questions/answers in a new page');

        $form['faq_category_display'] = array(
            '#type' => 'radios',
            '#options' => $category_options,
            '#title' => t('Categories layout'),
            '#description' => t('This controls how the categories are displayed on the page and what happens when someone clicks on the category.'),
            '#default_value' => $faq_settings->get('category_display')
        );

        $form['faq_category_misc'] = array(
            '#type' => 'fieldset',
            '#title' => t('Miscellaneous layout settings'),
            '#collapsible' => TRUE,
        );

        $form['faq_category_misc']['faq_category_listing'] = array(
            '#type' => 'select',
            '#options' => array(
                'ol' => t('Ordered list'),
                'ul' => t('Unordered list'),
            ),
            '#title' => t('Categories listing style'),
            '#description' => t("This allows to select how the categories listing is presented.  It only applies to the 'Clicking on category opens the questions/answers in a new page' layout.  An ordered listing would number the categories, whereas an unordered list will have a bullet to the left of each category."),
            '#default_value' => $faq_settings->get('category_listing')
        );

        $form['faq_category_misc']['faq_category_hide_qa_accordion'] = array(
            '#type' => 'checkbox',
            '#title' => t('Use accordion effect for "opens/hides questions and answers under category" layout'),
            '#description' => t('This enables an "accordion" style effect where when a category is clicked, the questions appears beneath, and is then hidden when another category is opened.'),
            '#default_value' => $faq_settings->get('category_hide_qa_accordion')
        );

        $form['faq_category_misc']['faq_count'] = array(
            '#type' => 'checkbox',
            '#title' => t('Show FAQ count'),
            '#description' => t('This displays the number of questions in a category after the category name.'),
            '#default_value' => $faq_settings->get('count')
        );

        $form['faq_category_misc']['faq_answer_category_name'] = array(
            '#type' => 'checkbox',
            '#title' => t('Display category name for answers'),
            '#description' => t("This allows the user to toggle the visibility of the category name above each answer section for the 'Clicking on question takes user to answer further down the page' question/answer display."),
            '#default_value' => $faq_settings->get('answer_category_name')
        );

        $form['faq_category_misc']['faq_group_questions_top'] = array(
            '#type' => 'checkbox',
            '#title' => t("Group questions and answers for 'Categories inline'"),
            '#description' => t("This controls how categories are implemented with the 'Clicking on question takes user to answer further down the page' question/answer display."),
            '#default_value' => $faq_settings->get('group_questions_top')
        );

        $form['faq_category_misc']['faq_hide_child_terms'] = array(
            '#type' => 'checkbox',
            '#title' => t('Only show sub-categories when parent category is selected'),
            '#description' => t("This allows the user more control over how and when sub-categories are displayed.  It does not affect the 'Categories inline' display."),
            '#default_value' => $faq_settings->get('hide_child_terms')
        );

        $form['faq_category_misc']['faq_show_term_page_children'] = array(
            '#type' => 'checkbox',
            '#title' => t('Show sub-categories on FAQ category pages'),
            '#description' => t("Sub-categories with 'faq' nodes will be displayed on the per category FAQ page.  This will also happen if 'Only show sub-categories when parent category is selected' is set."),
            '#default_value' => $faq_settings->get('show_term_page_children')
        );
        
        // TODO: how to reach moduleHandler from FormBase
        if(true){
            $form['faq_category_advanced'] = array(
                '#type' => 'fieldset',
                '#title' => t('Advanced category settings'),
                '#collapsible' => TRUE,
                '#collapsed' => TRUE,
            );
            $vocab_options = array();
            $vocabularies = Vocabulary::loadMultiple();
            foreach ($vocabularies as $vid => $vobj) {
                $vocab_options[$vid] = $vobj->name;
            }
            if (!empty($vocab_options)) {
                $form['faq_category_advanced']['faq_omit_vocabulary'] = array(
                    '#type' => 'checkboxes',
                    '#title' => t('Omit vocabulary'),
                    '#description' => t('Terms from these vocabularies will be <em>excluded</em> from the FAQ pages.'),
                    '#default_value' => $faq_settings->get('omit_vocabulary'),
                    '#options' => $vocab_options,
                    '#multiple' => TRUE,
                );
            }
        }
        
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
        
        $faq_settings->set('use_categories', $form_state['values']['faq_use_categories']);
        $faq_settings->set('category_display', $form_state['values']['faq_category_display']);
        $faq_settings->set('category_listing', $form_state['values']['faq_category_listing']);
        $faq_settings->set('category_hide_qa_accordion', $form_state['values']['faq_category_hide_qa_accordion']);
        $faq_settings->set('count', $form_state['values']['faq_count']);
        $faq_settings->set('answer_category_name', $form_state['values']['faq_answer_category_name']);
        $faq_settings->set('group_questions_top', $form_state['values']['faq_group_questions_top']);
        $faq_settings->set('hide_child_terms', $form_state['values']['faq_hide_child_terms']);
        $faq_settings->set('show_term_page_children', $form_state['values']['faq_show_term_page_children']);
        $faq_settings->set('omit_vocabulary', $form_state['values']['faq_omit_vocabulary']);
        
        $faq_settings->save();
    }

}