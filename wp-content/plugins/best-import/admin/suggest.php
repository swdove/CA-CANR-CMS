<?php

    namespace best_import_namespace;

    function suggest_sort($a, $b){
        $q = $_GET['q'];    
        $aq = strpos($a, $q);
        $bq = strpos($b, $q);
        if($aq===false)$aq = 1024;
        if($bq===false)$bq = 1024;
        return $aq == $bq ? strlen($a) - strlen($b) : $aq - $bq;
    }

    $suggest = explode("\n", file_get_contents('suggest.txt'));
    uasort($suggest, '\best_import_namespace\suggest_sort');
    echo implode("\n", $suggest);

?>