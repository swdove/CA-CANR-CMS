<?php

    if(file_exists($zip_file)){
        echo '<h3>Import images</h3>';
        $zip = zip_open($zip_file);

        echo '<h4>You can import images from the ZIP file only in <a href="http://zefirstudio.pl/wp-best-import/">Best Import Pro</a>.</h4>';
        echo '<textarea class="ziptable" disabled="disabled">';

        $zip_jpg = 0;
        if($zip){
            while($zip_entry = zip_read($zip)){
                $name = zip_entry_name($zip_entry);
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                if($ext == 'jpg' && zip_entry_open($zip, $zip_entry)){
                    echo $name."\r\n";
                    $zip_jpg++;
                }
            }
        }

        echo '</textarea>';
        echo '/wp-content/uploads/<input type="text" name="zip-path" value="">';
        zip_close($zip);
    }

?>