<?php

    namespace best_import_namespace;

    echo '<h3>Data</h3>';
    if($xml){
        echo '<h4>Fill the form below to match appropriate attributes with the data.</h4>';
        echo '<table class="form-table">';
        
        echo '<tr><td><label for="bi-main-tag">Main tag</label></td><td><select name="main-tag" value="" id="bi-main-tag">';
            foreach($tags as $tag=>$array)echo '<option name="import-number" value="'.$tag.'"'.($tag==$main_tag?' selected="selected"':'').'>&lt;'.$tag.'&gt;</option>';
        echo '</select></td></tr>';
        
        echo '<tr><td><label for="bi-type">Type</label></td><td><select name="post-type" id="bi-type">';
            foreach($post_types as $type)echo '<option value="'.$type.'"'.($type==$post_type?' selected="selected"':'').'>'.$type.'</option>';
        echo '</select></td></tr>';

        echo '<tr><td><label for="bi-title">Title</label></td><td><input type="text" value="'.gpost('title').'" name="title" id="bi-title"  class="suggest"></td></tr>';
        echo '<tr><td><label for="bi-content">Content</label></td><td><textarea name="content" id="bi-content"  class="suggest">'.gpost('content').'</textarea></td></tr>';
        echo '<tr><td><label for="bi-date">Date</label></td><td><input type="text" value="'.gpost('date').'" name="date" id="bi-date"  class="suggest"></td></tr>';
        
        echo '</table>';
    }else{
        echo '<h4>You have not uploaded XML file yet.</h4>';
    }

?>