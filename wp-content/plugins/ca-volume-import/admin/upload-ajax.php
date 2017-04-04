<?php

    namespace best_import_namespace;

    global $path;
    $path = pathinfo(__FILE__, PATHINFO_DIRNAME);

    global $zip_file, $xml_file;

    $zip_file = $path.'/data.zip';
    $xml_file = $path.'/data.xml';

    include 'functions.php';

    if(isset($_FILES['file']))echo upload_xml($_FILES['file']);
    else echo 'Select a proper file (XML or ZIP) or check upload limit on your server.';   

?>