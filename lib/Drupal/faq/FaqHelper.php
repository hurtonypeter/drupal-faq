<?php

/**
 * @file
 * Contains \Drupal\user\FaqHeler.
 */

namespace Drupal\faq;

/**
 * Contains static helper functions for FAQ module.
 */
class FaqHelper{
    
    /**
     * Count number of nodes for a term and its children.
     * 
     * @param int $tid
     *   Id of the tadonomy term to count nodes in.
     * @return int
     *   Returns the count of the nodes in the given term.
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