<?php

    namespace best_import_namespace;

    function check_condition($a, $operator, $b){
        return $operator=='' && $a ||
            $operator=='=' && $a==$b ||
            $operator=='!=' && $a!=$b ||
            $operator=='^=' && substr($a,0,strlen($b))==$b ||
            $operator=='$=' && substr($a,-strlen($b))==$b ||
            $operator=='*=' && ($b=='' || strpos($a,$b)!==false) ||
            $operator=='<' && $a<$b ||
            $operator=='<=' && $a<=$b ||
            $operator=='>' && $a>$b ||
            $operator=='>=' && $a>=$b;
    }

    function parse_tag($str, $type, $i){
        global $tags;

        preg_match_all("/\<\w+\/\w+\>(\s*\[[^\]]+?\])*/", $str, $strtags);
        $strtags = $strtags[0];

        foreach($strtags as $strtag){
                        
            preg_match_all("/\<(\w+)\/(\w+)\>/", $strtag, $tag_subtag);
            $tag = $tag_subtag[1][0];
            $subtag = $tag_subtag[2][0];
            
            preg_match_all("/\[(\w+)\s*(?:(\=|\!\=|\^\=|\$\=|\*\=|\<|\>|\<\=|\>\=)\s*(\"[^\"]*\"|[^\]]*))?\]/", $strtag, $attributes);
            list($attributes, $names, $operators, $values) = $attributes;

            $data = '';
            $nodes = $tags[$tag][$i]->$subtag;

            foreach($nodes as $node){
                $add = true;
                foreach($attributes as $n=>$attr){
                    $attr = $names[$n];
                    if(!check_condition($node->attributes()->$attr, $operators[$n], $values[$n])){
                        $add = false;
                        break;
                    }
                }
                if($add)$data .= get_text($node)."\n";
            }
            
            $data = preg_replace("/\n$/", '', $data);
            $str = str_replace($strtag, $data, $str);
        }
        
        if($type=='int'){
            return intval($str);
        }elseif($type=='float'){
            return floatval(str_replace(',', '.', $str));
        }elseif($type=='date'){
            $str = strtotime($str);
            return date('Y-m-d H:i:s', $str==false?time():$str);
        }elseif($type=='array'){
            return $str==''?array():explode("\n", $str);
        }else{
            return $str;
        }
        
    };

    function parse_field($field, $i){
        return apply_mapping($field['name'], parse_tag($field['value'], $field['type'], $i));
    }

    function find_post_id($name, $value){
        global $wpdb, $default_fields, $post_type;
        if(isset($default_fields[$name])){
            $post = $wpdb->get_row("SELECT ID FROM $wpdb->posts WHERE post_$name='$value' AND post_type='$post_type' AND post_status='publish'");
            return $post ? $post->ID : NULL;
        }else{
            $post = $wpdb->get_row("SELECT ID FROM $wpdb->postmeta LEFT JOIN $wpdb->posts ON post_id=ID WHERE meta_key='$name' AND meta_value='$value' AND post_type='$post_type' AND post_status='publish'");
            return $post ? $post->ID : NULL;
        }
    }

    echo '<h3>Import</h3>';
    if($xml){
        
        $wp_error = '';
        $added = 0;
        $updated = 0;
        $deleted = 0;

        if(gpost('import')=='Import' || isset($_GET['import'])){
                              
            for($i=0; $i<$import_number; ++$i){
                
                // action
                $post_id = NULL;
                $action = $default_action;
                            
                // post
                $post = array('ID' => $post_id, 'post_status' => 'publish');
                
                // default fields
                $post['post_type'] = $post_type;
                $post['post_title'] = parse_field($default_fields['title'], $i);
                $post['post_name'] = sanitize_title($post['post_title']);
                $post['post_content'] =  parse_field($default_fields['content'], $i);
                $post['post_date'] =  parse_field($default_fields['date'], $i);

                // insert/update post
                if($post_id){
                    $post_id = wp_update_post($post, $wp_error);
                    $updated++;
                }else{
                    $post_id = wp_insert_post($post, $wp_error);
                    $added++;
                }

                // taxonomies
                foreach($taxonomies as $taxonomy){
                    $categories = parse_tag($taxonomy['value'], 'array', $i);
                    foreach($categories as $n=>$category)$categories[$n] = apply_mapping($taxonomy['name'], $category);
                    wp_set_object_terms($post_id, $categories, $taxonomy['name']);
                }
                
            }
            
            echo '<h4>'.$added.' posts added | '.$updated.' posts updated | '.$deleted.' posts deleted</h4>';
            echo '<label for="bi-import-number">How many?</label> <input type="text" value="'.$import_number.'" name="import-number" id="bi-import-number"> ';
            echo '<input type="submit" name="preview" value="Update Preview"><br><br>';
            
        }else if(gpost('preview')){
            
            echo '<h4>The table below contains the data to import.</h4>';
            echo '<label for="bi-import-number">How many?</label> <input type="text" value="'.$import_number.'" name="import-number" id="bi-import-number"> ';
            echo '<input type="submit" name="preview" value="Update Preview"> ';
            echo '<input type="submit" name="import" value="Import"><br><br>';
            
        }else{
                
            echo '<h4>Click on the button below to see preview.</h4>';
            echo '<label for="bi-import-number">How many?</label> <input type="text" value="'.$import_number.'" name="import-number" id="bi-import-number"> ';
            echo '<input type="submit" name="preview" value="Preview">';
                
        }

    }else{
        echo '<h4>You have not uploaded XML file yet.</h4>';
    }

?>