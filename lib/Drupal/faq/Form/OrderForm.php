<?php

namespace Drupal\faq\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\faq\FaqHelper;
use Drupal\Component\Utility\String;

class OrderForm extends ConfigFormBase {
    
    public function getFormId() {
        return 'faq_order_form';
    }

    public function buildForm(array $form, array &$form_state, $category = NULL) {
        
        $order = $date_order = '';
        $faq_settings = $this->config('faq.settings');
        
        $use_categories = $faq_settings->get('use_categories');
        if (!$use_categories) {
            $step = "order";
        }
        elseif (!isset($form_state['values']) && empty($category)) {
            $step = "categories";
        }
        else {
            $step = "order";
        }
        $form['step'] = array(
            '#type' => 'value',
            '#value' => $step,
        );
        
        // Categorized q/a.
        if ($step == "categories") {

            // Get list of categories.
            $vocabularies = Vocabulary::loadMultiple();
            $options = array();
            foreach ($vocabularies as $vid => $vobj) {
                $tree = taxonomy_get_tree($vid);
                foreach ($tree as $term) {
                    if (!FaqHelper::faq_taxonomy_term_count_nodes($term->tid)) {
                        continue;
                    }
                    $options[$term->tid] = FaqHelper::faq_tt("taxonomy:term:$term->tid:name", $term->name);
                    $form['choose_cat']['faq_category'] = array(
                        '#type' => 'select',
                        '#title' => t('Choose a category'),
                        '#description' => t('Choose a category that you wish to order the questions for.'),
                        '#options' => $options,
                        '#multiple' => FALSE,
                    );

                    $form['choose_cat']['search'] = array(
                        '#type' => 'submit',
                        '#value' => t('Search'),
                        '#submit' => array('faq_order_settings_choose_cat_form_submit'),
                    );
                }
            }

        } else {
            $default_sorting = $faq_settings->get('default_sorting');
            $default_weight = 0;
            if ($default_sorting != 'DESC') {
                $default_weight = 1000000;
            }

            $options = array();
            if (!empty($form_state['values']['faq_category'])) {
                $category = $form_state['values']['faq_category'];
            }

            // Uncategorized ordering.
            $query = db_select('node', 'n');
            $query->join('node_field_data', 'd', 'n.nid = d.nid');
            $query->fields('n', array('nid'))
                ->fields('d', array('title'))
                ->addTag('node_access')
                ->condition('n.type', 'faq')
                ->condition('d.status', 1);

            // Works, but involves variable concatenation - safe though, since
            // $default_weight is an integer.
            $query->addExpression("COALESCE(w.weight, $default_weight)", 'effective_weight');
            // Doesn't work in Postgres.
            //$query->addExpression('COALESCE(w.weight, CAST(:default_weight as SIGNED))', 'effective_weight', array(':default_weight' => $default_weight));

            if (empty($category)) {
                $category = 0;
                $w_alias = $query->leftJoin('faq_weights', 'w', 'n.nid = %alias.nid AND %alias.tid = :category', array(':category' => $category));
                $query->orderBy('effective_weight', 'ASC')
                    ->orderBy('d.sticky', 'DESC')
                    ->orderBy('d.created', $default_sorting == 'DESC' ? 'DESC' : 'ASC');
            }
            // Categorized ordering.
            else {
                $ti_alias = $query->innerJoin('taxonomy_index', 'ti', '(n.nid = %alias.nid)');
                $w_alias = $query->leftJoin('faq_weights', 'w', 'n.nid = %alias.nid AND %alias.tid = :category', array(':category' => $category));
                $query->condition('ti.tid', $category);
                $query->orderBy('effective_weight', 'ASC')
                    ->orderBy('d.sticky', 'DESC')
                    ->orderBy('d.created', $default_sorting == 'DESC' ? 'DESC' : 'ASC');
            }

            $options = $query->execute()->fetchAll();

            $form['weight']['faq_category'] = array(
                '#type' => 'value',
                '#value' => $category,
            );

            // Show table ordering form.
            $form['order_no_cats']['#tree'] = TRUE;
            $form['order_no_cats']['#theme'] = 'faq-draggable-question-order-table';

            foreach ($options as $i=>$record) {
                $form['order_no_cats'][$i]['nid'] = array(
                    '#type' => 'hidden',
                    '#value' => $record->nid,
                );
                $form['order_no_cats'][$i]['title'] = array('#markup' => String::checkPlain($record->title));
                $form['order_no_cats'][$i]['sort'] = array(
                    '#type' => 'weight',
                    '#delta' => count($options),
                    '#default_value' => $i,
                );
            }
        }
        
        return parent::buildForm($form, $form_state);
    }

    public function submitForm(array &$form, array &$form_state) {
        
    }

}