<?php

namespace Drupal\faq\Form;

use Drupal\Core\Form\FormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CategoriesForm extends FormBase {
    
    public function getFormId() {
        return 'faq_categories_settings_form';
    }
    
    public function buildForm(array $form, array &$form_state) {
        $form = array();
        
        return $form;
    }

    public function submitForm(array &$form, array &$form_state) {
        
    }

}