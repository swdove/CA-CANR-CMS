<?php

    namespace best_import_namespace;

    function csv_to_xml($csv_file){
        $file  = fopen($csv_file, 'rt');
        
        $headers = fgetcsv($inputFile);

        $doc  = new DomDocument();
        $doc->formatOutput = true;

        $root = $doc->createElement('rows');
        $root = $doc->appendChild($root);

        while(($row = fgetcsv($inputFile)) !== false){
             $container = $doc->createElement('row');

             foreach ($headers as $i => $header){
                $child = $doc->createElement($header);
                $child = $container->appendChild($child);
                 $value = $doc->createTextNode($row[$i]);
                 $value = $child->appendChild($value);
             }

             $root->appendChild($container);
        }

        return $doc->saveXML();
    }

?>