<?php

    namespace best_import_namespace;

    echo '<h3>Upload a file</h3>';

    if(isset($_POST['remove'])){
        if(file_exists($zip_file))unlink($zip_file);
        if(file_exists($xml_file))unlink($xml_file);
    }

    $error = '';
    if(isset($_FILES['file']))$error = upload_xml($_FILES['file']);

    if(file_exists($xml_file)){
        echo '<h4>File has been successfully uploaded.</h4>';
        echo '<input type="submit" name="remove" value="Remove">';
    }else{
        if($error)echo '<h4 class="h4error">'.$error.'</h4>';
        else echo '<h4>Select a file you want to import (XML/CSV/ZIP).</h4>';
        echo '<input type="file" name="file">';
        echo '<input type="submit" name="send" value="Send">';
        echo '<div class="bar"><div></div></div>';
    }

?>