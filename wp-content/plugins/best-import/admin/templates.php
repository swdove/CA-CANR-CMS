<?php

    namespace best_import_namespace;

    if($xml){                
        echo '<h3>Templates</h3>';
        
        if($template_text)echo '<h4 class="h4error">'.$template_text.'</h4>';
        else echo '<h4>Save or load your data.</h4>';
        
        echo '<table class="form-table">';
        echo '<tr>';
            echo '<td><label for="bi-save-template">Template name</label></td>';
            echo '<td>';
                echo '<input type="text" name="save-template" value="" id="bi-save-template"> ';
                echo '<input type="submit" name="template" value="Save">';
            echo '</td>';
        echo '</tr>';
        echo '<tr>';
            echo '<td><label for="bi-load-template">Select template</label></td>';
            echo '<td>';
                echo '<select name="load-template" vid="bi-load-template">';
                $templates = glob($path.'/*.json');
                if(count($templates)==0)echo '<option value="">...</option>';
                else foreach($templates as $template){
                    $name = basename($template, '.json');
                    echo '<option value="'.$name.'">'.$name.'</option>';
                }
                echo '</select> ';
                echo '<input type="submit" name="template" value="Remove">';
                echo '<input type="submit" name="template" value="Load">';
            echo '</td>';
        echo '</tr>';
        echo '</table>';
    }

?>