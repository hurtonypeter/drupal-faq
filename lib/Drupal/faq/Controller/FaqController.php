<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Drupal\faq\Controller;

use Drupal\Core\Controller\ControllerBase;

class FaqController extends ControllerBase{
    
    
    public function faqPage(){
        $build = array(
            '#type' => 'markup',
            '#markup' => t('hellobello')
        );
        return $build;
    }
}