<?php

    namespace best_import_namespace;

    function save_bi_template($name){
        global $path;
        preg_match("/^[\w\-]+$/", $name, $match);
        if(count($match)==0){
            return "Template name is incorrect.";
        }else if(file_exists($path.'/'.$name.'.json')){
            return "Template named '$name' already exists.";
        }else{
            unset($_POST['template']);
            unset($_POST['save-template']);
            unset($_POST['load-template']);
            $json = json_encode($_POST);
            file_put_contents($path.'/'.$name.'.json', $json);
            $link = "http://".$_SERVER["HTTP_HOST"].preg_replace("/\&template=[\w-]+/",'',$_SERVER["REQUEST_URI"])."&template=$name";
            return "Template '$name' has been successfully saved.<br>Link to template: $link<br>Link to import template: $link&import=on";
        }
    }

    function load_bi_template($name){
        global $path;
        if(!file_exists($path.'/'.$name.'.json')){
            return "Template named '$name' does not exist.";
        }else{
            $json = file_get_contents($path.'/'.$name.'.json');
            $_POST = json_decode($json, true);
            $link = "http://".$_SERVER["HTTP_HOST"].preg_replace("/\&template=[\w-]+/",'',$_SERVER["REQUEST_URI"])."&template=$name";
            return "Template '$name' has been successfully loaded.<br>Link to template: $link<br>Link to import template: $link&import=on";
        }
    }

    function remove_bi_template($name){
        global $path;
        if(!file_exists($path.'/'.$name.'.json')){
            return "Template named '$name' does not exist.";
        }else{
            unlink($path.'/'.$name.'.json');
            return "Template '$name' has been successfully removed.";
        }  
    }

    global $template_text;
    if(gpost('template')=='Save')$template_text = save_bi_template(gpost('save-template'));
    else if(gpost('template')=='Load')$template_text = load_bi_template(gpost('load-template'));
    else if(gpost('template')=='Remove')$template_text = remove_bi_template(gpost('load-template'));
    else if(isset($_GET['template']))$template_text = load_bi_template($_GET['template']);
    else $template_text = '';

?>