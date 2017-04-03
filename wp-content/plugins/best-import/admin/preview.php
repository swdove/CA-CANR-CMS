<?php

    namespace best_import_namespace;

    if($xml && gpost('preview')){
        
        global $wpdb;
        
        echo '<table class="wp-list-table widefat fixed">';
        echo '<thead><tr>';
        echo '<th style="width: 80px;">No</th>';
        foreach($default_fields as $field)echo '<th>'.$field['name'].'</th>';
        foreach($taxonomies as $taxonomy)echo '<th>'.$taxonomy['label'].'</th>';
        echo '</tr></thead><tbody>';
        
        for($i=0; $i<$import_number; ++$i){
            
            // action
            $post_id = NULL;
            $action = $default_action;
            
            // default fields
            echo '<tr>';
            echo '<td>#'.($i+1).' '.$action.'</td>';
            foreach($default_fields as $field)
                echo '<td>'.preview(parse_field($field, $i)).'</td>';

            // taxonomies
            foreach($taxonomies as $taxonomy){
                echo '<td>';
                foreach(parse_tag($taxonomy['value'], 'array', $i) as $category)echo preview(apply_mapping($taxonomy['name'], $category)).' ';
                echo '</td>';
            }
            
            echo '</tr>';
        }

        echo '</tbody></table>';

    }

?>