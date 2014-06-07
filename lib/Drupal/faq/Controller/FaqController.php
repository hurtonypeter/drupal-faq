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
            '#markup' => t($set->get('faq_title'))
        );
        return $build;
    }
    
    public function generalSettings(){
        $build = array();
        
        $build['faq_general_settings_form'] = $this->formBuilder()->getForm('Drupal\faq\Form\GeneralForm');
        
        return $build;
    }
    
    public function questionsSettings(){
        $build = array(
            '#type' => 'markup',
            '#markup' => t("questions settings page")
        );
        return $build;
    }
    
    public function categoriesSettings(){
        $build = array(
            '#type' => 'markup',
            '#markup' => t("categories settings page")
        );
        return $build;
    }
}