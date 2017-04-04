<?php

   namespace best_import_namespace;

    if($xml){ 
        echo '<h3>Taxonomies</h3>';
        if(count($taxonomies)==0)echo "<h4>Taxonomies for type '".$post_type."' not found.</h4>";
        else{
            echo '<h4>Define what taxonomies you want to set.</h4>';
            echo '<table class="form-table">';
            foreach($taxonomies as $taxonomy)echo '<tr><td><label for="bi-taxonomy-'.$taxonomy['name'].'">'.$taxonomy['label'].'</label></td><td><input type="text" value="'.$taxonomy['value'].'" name="taxonomies['.$taxonomy['name'].']" id="bi-taxonomy-'.$taxonomy['name'].'"  class="suggest"></td></tr>';
            echo '</table>';
        }   
    }

?>