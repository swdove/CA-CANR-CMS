<?php

   namespace sata_import_namespace;

    function apply_mapping($type, $value){
        global $mapping;
        foreach($mapping as $map)
            if($map['name']==$type && $map['from']==$value)
                return $map['to'];
        return $value;
    }

    function print_mapping($name, $from, $to){
        global $default_fields, $taxonomies;
        echo '<tr><td>';
            echo '<select name="mapping_names[]">';
                echo '<optgroup label="Default fields">';
                    foreach($default_fields as $field)echo '<option value="'.$field['name'].'"'.($field['name']==$name?' selected="selected"':'').'>'.$field['label'].'</option>';
                echo '</optgroup>';
                echo '<optgroup label="Taxonomies">';
                    foreach($taxonomies as $taxonomy)echo '<option value="'.$taxonomy['name'].'"'.($taxonomy['name']==$name?' selected="selected"':'').'>'.$taxonomy['label'].'</option>';
                echo '</optgroup>';
            echo '</select>';
        echo '</td><td>';
            echo '<input type="text" name="mapping_from[]" value="'.$from.'"> &raquo;';
            echo '<input type="text" name="mapping_to[]" value="'.$to.'"> ';
            echo '<input type="button" name="removeField" value="&times;">';
        echo '</td></tr>';
    }

    if($xml){                
        echo '<h3>Mapping</h3>';
        echo '<h4>Add mapping to your data if necessarily.</h4>';
        echo '<table class="form-table">';
        foreach($mapping as $map)print_mapping($map['name'], $map['from'], $map['to']);
        print_mapping('', 'from', 'to');
        echo '<tr><td><label for="bi-add-3">Add mapping</label></td><td><input type="button" value="Add" id="bi-add-3"></td></tr>';
        echo '</table>';
    }

?>