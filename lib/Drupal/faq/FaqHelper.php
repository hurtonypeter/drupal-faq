<?php

namespace Drupal\faq;

/**
 * Contains static functions for FAQ module.
 */
class FaqHelper{
    
    /**
     * Helper function for when i18ntaxonomy module is not installed.
     */
    public static function faq_tt($string_id, $default, $language = NULL){
        return function_exists('tt') ? tt($string_id, $default, $language) : $default;
    }
    
    /**
     * Count number of nodes for a term and its children.
     */
    public static function faq_taxonomy_term_count_nodes($tid) {
        static $count;
        
        if (!isset($count) || !isset($cound[$tid])) {
            $query = db_select('node', 'n')
                    ->fields('n', array('nid'))
                    ->addTag('node_access');
            $query->join('taxonomy_index', 'ti', 'n.nid = ti.nid');
            $query->join('node_field_data', 'd', 'd.nid = n.nid');
            $query->condition('n.type', 'faq')
                  ->condition('d.status', 1)
                  ->condition('ti.tid', $tid);
        }
        
        $children_count = 0;
        foreach ($this->faq_taxonomy_term_children($tid) as $child_term){
            $children_count += $this->faq_taxonomy_term_count_nodes($child_term);
        }
        
        return $cound[$tid] + $children_count;
    }
    
}