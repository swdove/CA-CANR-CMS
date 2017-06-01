<?php

   namespace sata_import_namespace;

    // xml

    global $xml, $xml_content;

    if(file_exists($xml_file)){
        $xml = simplexml_load_file($xml_file); 
        $xml_content = file_get_contents($xml_file);
    }else{
        $xml = null;
        $xml_content = null;
    }

    // others
    
    global $tags, $max_preview, $import_number, $post_types, $post_type, $main_tag, $default_action;

    $tags = array();
    $max_preview = 1024;
    $import_number = intval(gpost('import-number'));
    $post_types = get_post_types();
    $post_type = gpost('post-type', 'post');
    $main_tag = gpost('main-tag', '');
    $default_action = 'add';

    // default fields

    global $default_fields;

    $default_fields = array(
        'title' => array('name'=>'title', 'value'=>gpost('title'), 'type'=>'string', 'label'=>'Title'),
        'content' => array('name'=>'content', 'value'=>gpost('content'), 'type'=>'string', 'label'=>'Content'),
        'date' => array('name'=>'date', 'value'=>gpost('date'), 'type'=>'date', 'label'=>'Date'),
    );

    // taxonomies

    global $taxonomies, $taxonomies_objects;

    $taxonomies = array();
    $taxonomies_objects = get_taxonomies(array(), 'objects');   

    foreach($taxonomies_objects as $taxonomy)
        if(in_array($post_type, $taxonomy->object_type)){
            $value = gpost('taxonomies', array());
            $value = isset($value[$taxonomy->name])?$value[$taxonomy->name]:'';
            $taxonomies[$taxonomy->name] = array(
                'name' => $taxonomy->name,
                'value' => $value,
                'type' => 'string',
                'label' => $taxonomy->label
            );
        }

    // mapping

    global $mapping;
    $mapping = array();

    $mapping_names = gpost('mapping_names', array());
    $mapping_from = gpost('mapping_from', array());;
    $mapping_to = gpost('mapping_to', array());
    $mapping_count = count($mapping_names);
    for($j=0; $j<$mapping_count; ++$j)
        $mapping[] = array(
            'name' => $mapping_names[$j],
            'from' => $mapping_from[$j],
            'to' => $mapping_to[$j]
        );

    // merge
        
    global $all_fields;

    $all_fields = array_merge($default_fields, $taxonomies);
        
    // input types

    global $input_types;

    $input_types = array(
        'string' => 'String',
        'int' => 'Integer',
        'float' => 'Float',
        'date' => 'Date',
    );
        
?>