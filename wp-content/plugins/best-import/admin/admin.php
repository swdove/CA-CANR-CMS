<?php
    namespace best_import_namespace;
   //require($_SERVER['DOCUMENT_ROOT'].'/wp-load.php');
?>


<h2 id="bi-menu" class="nav-tab-wrapper woo-nav-tab-wrapper">
    <a href="#home" class="nav-tab">Best Import</a>
    <a href="#instruction" class="nav-tab">Instruction</a>
    <a href="#upload" class="nav-tab">Upload</a>
    <a href="#structure" class="nav-tab">Structure</a>
    <a href="#data" class="nav-tab">Data</a>
    <a href="#advanced" class="nav-tab">Advanced</a>
    <a href="#import" class="nav-tab">Import</a>
</h2>

<?php

    global $path;
    $path = pathinfo(__FILE__, PATHINFO_DIRNAME);
	$dir = plugins_url('', __FILE__);

	echo '<script type="text/javascript">dir = "'.$dir.'";</script>';

    global $zip_file, $xml_file;

    $zip_file = $path.'/data.zip';
    $xml_file = $path.'/data.xml';

    function gpost($id, $default=''){
        $result = isset($_POST[$id])?$_POST[$id]:$default;
        $result = is_array($result)?array_map('stripslashes', $result):stripslashes($result);
        return $result;
    }

    include $path.'/template.php';

    echo '<form id="bi" method="post" enctype="multipart/form-data">';

        echo '<div id="tab-home">';
            include $path.'/home.php';
        echo '</div>';

        echo '<div id="tab-instruction">';
            include $path.'/instruction.php';
        echo '</div>';

        echo '<div id="tab-upload">';
            include $path.'/functions.php';
            include $path.'/upload.php';
            include $path.'/variables.php';
            include $path.'/zip.php';
        echo '</div>';

        echo '<div id="tab-structure">';
            include $path.'/structure.php';
        echo '</div>';

        echo '<div id="tab-data">';
            include $path.'/data.php';
            include $path.'/taxonomies.php';
            include $path.'/media.php';
            include $path.'/templates.php';
        echo '</div>';

        echo '<div id="tab-advanced">';
            include $path.'/custom.php';
            include $path.'/filtering.php';
            include $path.'/mapping.php';
        echo '</div>';

        echo '<div id="tab-import">';
            include $path.'/import.php';
            include $path.'/preview.php';            
        echo '</div>';

    echo '</form>';

?>