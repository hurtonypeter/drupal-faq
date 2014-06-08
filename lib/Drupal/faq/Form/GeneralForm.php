<?php

namespace Drupal\faq\Form;

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GeneralForm extends ConfigFormBase {
    
    public function getFormId() {
        return 'faq_general_settings_form';
    }
    
    public function buildForm(array $form, array &$form_state) {
        $faq_settings = $this->config('faq.settings');
        
        $form['faq_title'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Title'),
            '#default_value' => $faq_settings->get('title')
        );
        
        $form['body_filter']['faq_description'] = array(
            '#type' => 'textarea',
            '#title' => $this->t('FAQ Description'),
            '#default_value' => $faq_settings->get('description'),
            '#description' => $this->t('Your FAQ description.  This will be placed at the top of the page, above the questions and can serve as an introductory text.'),
            '#rows' => 5
        );

        $form['faq_custom_breadcrumbs'] = array(
            '#type' => 'checkbox',
            '#title' => $this->t('Create custom breadcrumbs for the FAQ'),
            '#description' => $this->t('This option set the breadcrumb path to "%home > %faqtitle > category trail".', 
                    array(
                        '%home' => $this->t('Home'), 
                        '%faqtitle' => $faq_settings->get('title')
                        )
                    ),
            '#default_value' => $faq_settings->get('custom_breadcrumbs')
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
        $faq_settings->set('title', $form_state['values']['faq_title']);
        $faq_settings->set('description', $form_state['values']['faq_description']);
        $faq_settings->set('custom_breadcrumbs', $form_state['values']['faq_custom_breadcrumbs']);
        $faq_settings->save();
    }

}