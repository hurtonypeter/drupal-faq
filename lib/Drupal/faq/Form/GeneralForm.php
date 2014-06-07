<?php

namespace Drupal\faq\Form;

use Drupal\Core\Form\FormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GeneralForm extends FormBase {
    
    public function getFormId() {
        return 'faq_general_settings_form';
    }
    
    public function buildForm(array $form, array &$form_state) {
        $form = array();
        
        $form['faq_title'] = array(
            '#type' => 'textfield',
            '#title' => t('Title'),
        );
        
        $form['body_filter']['faq_description'] = array(
            '#type' => 'textarea',
            '#title' => t('FAQ Description'),
            '#description' => t('Your FAQ description.  This will be placed at the top of the page, above the questions and can serve as an introductory text.'),
            '#rows' => 5
        );

        $form['faq_custom_breadcrumbs'] = array(
            '#type' => 'checkbox',
            '#title' => t('Create custom breadcrumbs for the FAQ'),
            '#description' => t('This option set the breadcrumb path to "%home > %faqtitle > category trail".', 
                    array(
                        '%home' => t('Home'), 
                        '%faqtitle' => 'Frequently Asked Questions'
                        )
                    )
        );
        
        return $form;
    }

    public function submitForm(array &$form, array &$form_state) {
        
    }

}