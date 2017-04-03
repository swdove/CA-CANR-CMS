<?php

    namespace best_import_namespace;

    function preview($str){
        global $max_preview;
        if(strlen($str)>$max_preview)$str = substr($str, 0, $max_preview).'...';
        return htmlentities($str, ENT_COMPAT|ENT_HTML401, "UTF-8");
    }

    global $suggest, $subtags;
    $suggest = '';
    $subtags = array();

    function structure($xml){
        global $tags, $subtags, $suggest, $xml_content;
        
        foreach($xml as $tag=>$node){

            if(isset($tags[$tag])){
                $tags[$tag][] = $node;
                continue;
            }

            foreach($node as $subtag=>$data)
                if($data->count()==0){

                    if(!isset($subtags["$tag/$subtag"])){
                        if(!isset($tags[$tag]))$tags[$tag] = array($node);
                        $subtags["$tag/$subtag"] = true;
                        $suggest .= "&lt;$tag/$subtag&gt;\r\n";
                    }

                    $attributes = $data->attributes();
                    foreach($attributes as $k=>$v)
                        if(!isset($subtags["$tag/$subtag/$k/$v"])){
                            $subtags["$tag/$subtag/$k/$v"] = true;
                            $suggest .= "&lt;$tag/$subtag&gt;[$k=$v]\r\n";
                            break;
                        }

                }

            structure($node);
        }
    }

    echo '<h3>Structure</h3>';

    if($xml){
        
        echo '<h4>Check the structure of the XML file.</h4>';
        structure($xml);
        
        foreach($tags as $tag=>$array){
            $count = count($array);
            if($import_number==0 && $tag == $main_tag)$import_number = $count;
            echo '<h4 class="tag-content"><span>&lt;'.$tag.'&gt;</span>';
            echo '<input type="button" name="prev" value="&laquo;">';
            echo '<span>1</span>/<span>'.$count.'</span>';
            echo '<input type="button" name="next" value="&raquo;"></h4>';
            echo '<div>';
            foreach($array as $n=>$xml){
                echo '<table class="wp-list-table widefat fixed"'.($n>0?' style="display:none;"':'').'>';
                foreach($xml as $subtag=>$data){
                    echo '<tr><td class="td-tag">&lt;'.$tag.'/'.$subtag.'&gt;<span class="attributes">';
                    foreach($data->attributes() as $k=>$v)echo " [$k=$v]";
                    echo '</span></td><td>'.preview(get_text($data)).'</td></tr>';
                }
                echo '</table>';
            }
            echo '</div>';
        }

        $suggest .= '&lt;media&gt;';
        file_put_contents($path.'/suggest.txt', $suggest);
        
    }else{
     
        echo '<h4>You have not uploaded XML file yet.</h4>';

    }

?>