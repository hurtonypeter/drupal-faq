<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Drupal\faq\Controller;

use Drupal\Core\Controller\ControllerBase;

class FaqController extends ControllerBase{
    
    protected $config;
    
    public function __construct() {
        $this->config = \Drupal::config('faq.settings');
    }
    
    public function faqPage(){
        $set = \Drupal::config('faq.settings');
        $build = array(
            '#type' => 'markup',
            '#markup' => t($set->get('title'))
        );
        return $build;
    }
    
    public function generalSettings(){
        $build = array();
        
        $build['faq_general_settings_form'] = $this->formBuilder()->getForm('Drupal\faq\Form\GeneralForm');
        
        return $build;
    }
    
    public function questionsSettings(){
        $build = array();
        
        $build['#attached']['js'] = array(
            array(
                'data' => drupal_get_path('module', 'faq') . '/js/faq.js'
            ),
            array(
                'data' => array('faq' => \Drupal::config('faq.settings')->getRawData()),
                'type' => 'setting'
            )
        );
        
        $build['faq_questions_settings_form'] = $this->formBuilder()->getForm('Drupal\faq\Form\QuestionsForm');
        
        return $build;
    }
    
    public function categoriesSettings(){
        $build = array();
        
        $build['#attached']['js'] = array(
            array(
                'data' => drupal_get_path('module', 'faq') . '/js/faq.js'
            ),
            array(
                'data' => array('faq' => \Drupal::config('faq.settings')->getRawData()),
                'type' => 'setting'
            )
        );
        
        if(!$this->moduleHandler()->moduleExists('taxonomy')){
            drupal_set_message(t('Categorization of questions will not work without the "taxonomy" module being enabled.'), 'error');
        }
        
        $build['faq_categories_settings_form'] = $this->formBuilder()->getForm('Drupal\faq\Form\CategoriesForm');
        
        return $build;
    }
}