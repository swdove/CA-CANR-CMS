<?php

   namespace best_import_namespace;
   require($_SERVER['DOCUMENT_ROOT'].'/wp-load.php');
   require($_SERVER['DOCUMENT_ROOT'].'/wp-content/plugins/advanced-custom-fields-pro/acf.php');

    ini_set('post_max_size', '1024M');
    ini_set('upload_max_filesize', '1024M');

    function xml_from_zip($file){
        $zip = zip_open($file);
        if($zip){
            while($zip_entry = zip_read($zip)){
                $name = zip_entry_name($zip_entry);
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                if($ext == 'xml' && zip_entry_open($zip, $zip_entry)){
                    $contents = zip_entry_read($zip_entry, 1024*1024*100);
                    zip_entry_close($zip_entry);
                    return $contents;
                }
            }
        }
        zip_close($zip);
        return null;
    }

    function csv_to_xml($csv_file){
        $file = fopen($csv_file, 'r');
        $xml = "<csv>\n";
        $headers = fgetcsv($file);
        
        foreach($headers as $i=>$header)
            $headers[$i] = preg_replace("/\W/", "_", $header);
        
        while(($row = fgetcsv($file)) !== false){
            $xml .= "   <row>\n";
            foreach($headers as $i=>$header)$xml .= "       <$header>".$row[$i]."</$header>\n";
            $xml .= "   </row>\n";
        }
        
        $xml .= "</csv>";
        return $xml;
    }

    function upload_xml($file){
        global $xml_file, $zip_file;
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        if($ext=='xml'){
            
            if(!move_uploaded_file($file['tmp_name'], $xml_file))echo 'Error while uploading the file.';
            
        }else if($ext=='zip'){

            $fields = getPostFields();
            
            if(!move_uploaded_file($file['tmp_name'], $zip_file))echo 'Error while uploading the file.';
            else{
                //$zip = new ZipArchive;
                $zip = zip_open($zip_file);
                $zip_contents = Array();
                if($zip){
                    while($zip_entry = zip_read($zip)){
                        $name = zip_entry_name($zip_entry);
                        $ext = pathinfo($name, PATHINFO_EXTENSION);
                        if($ext == 'txt' && zip_entry_open($zip, $zip_entry)){
                           // $content = zip_entry_read($zip_entry, 1024*1024*100);
                            $content = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                            //determine if file contains SGML and add to array
                            if (strpos($content, 'biography') !== false) {
                                array_push($zip_contents, $content);
                            }
                            // if(SGMLstartsWith($content, "&lt;biography")) {
                            //     array_push($zip_contents, $content);
                            // }                                                        
                            zip_entry_close($zip_entry);
                        }
                    }
                }                
                zip_close($zip);

                $canrEntries = Array();

                foreach($zip_contents as $file) {
                    $canr = new CANR();
                    //var_dump($file);
                    $text = $file;
                    //isolate Gale data
                    preg_match('/<galedata>(.*?)<\/galedata>/s', $text, $galedata);
                    if(count($galedata) > 0) {
                        $canr->galedata = $galedata[0];                            
                    }
                    //isolate last name
                    preg_match('/<last>(.*?)<\/last>/', $text, $lastname);
                    if(count($lastname) > 0) {
                        $canr->name = $lastname[1];                            
                    }                  
                    //convert periods to underscores (period in tags breaks XML conversion)
                    $text = str_replace(".", "_", $text);
                    //strip all pubdate tags from writings (makes it easier to extract pub year)
                    $text = str_replace("<pubdate>", "", $text);
                    $text = str_replace("</pubdate>", "", $text);
                    //find all instances of "year" tags and add closing tag
                    $text = preg_replace_callback('/<year year="(.*?)">/', function($matches) {
                        $new_text = "\n" . $matches[0] .'</year>';
                        return $new_text;
                    }, $text);
                    // isolate secondary writings text from Writings section
                    preg_match('/<workgroup>(.*?)<\/workgroup>/s', $text, $workgroup);
                    if(count($workgroup) > 0) {
                        $workgroup_text = $workgroup[1];
                        preg_match('/<para>(.*?)<\/para>/s', $workgroup_text, $secondary_writings);
                        if(count($secondary_writings) > 0) {
                            $canr->secondary_writings = $secondary_writings[1];
                        }                             
                    }                                                                   

                    //Convert diacrit and tags to make them WYSIWIG-friendly 
                    $text = convertText($text); 
                    //Convert sticky emphasis tags
                    //Emphasis tags can contain line breaks, making them hard to detect using strreplace
                    //This regex finds any instance of a opening and closing emphasis tag and extracts the contents
                    $text = preg_replace_callback('/(<emphasis\s.*?)(n="1">)(.*?)(<\/emphasis>)/s', function($matches) {
                        $new_text = "(em)" . $matches[3] . "(/em)";
                        return $new_text;
                    }, $text);                                                                             

                    // 3/9/17 - force tags into biocrit for organization
                    // check if book biocrit citations exist
                    $has_books = strpos($text, '<grouptitle level="2">BOOKS</grouptitle>');
                    //check if periodicals exit
                    $has_periodicals = strpos($text, '<grouptitle level="2">PERIODICALS</grouptitle>');
                    // check if online citations exist
                    $has_online = strpos($text, '<grouptitle level="2">ONLINE</grouptitle>');

                    if($has_online == true && $has_books == false && $has_periodicals == false) {
                        $online_only = true;
                    } 

                    if($online_only) {
                        $text = str_replace('<grouptitle level="2">ONLINE</grouptitle>', '<online>', $text);
                        $text = str_replace('</readinggroup>', '</online></readinggroup>', $text);
                    } elseif ($has_books !== false && $has_online !== false) { //if books, periodicals and online exist
                        $text = str_replace('<grouptitle level="2">BOOKS</grouptitle>', '<books>', $text);
                        $text = str_replace('<grouptitle level="2">PERIODICALS</grouptitle>', '</books><periodicals>', $text);
                        $text = str_replace('<grouptitle level="2">ONLINE</grouptitle>', '</periodicals><online>', $text);
                        $text = str_replace('</readinggroup>', '</online></readinggroup>', $text);                        
                    } elseif($has_books === false && $has_online !== false) { //if periodicals and online exist
                        $text = str_replace('<grouptitle level="2">PERIODICALS</grouptitle>', '<periodicals>', $text);
                        $text = str_replace('<grouptitle level="2">ONLINE</grouptitle>', '</periodicals><online>', $text);
                        $text = str_replace('</readinggroup>', '</online></readinggroup>', $text);
                    } elseif($has_books === false && $has_online === false) { // if periodicals exist
                        $text = str_replace('<grouptitle level="2">PERIODICALS</grouptitle>', '<periodicals>', $text);
                        $text = str_replace('</readinggroup>', '</periodicals></readinggroup>', $text);
                    }                   
                    //split string into array on line breaks
	                $exploded = explode("\n", $text);
                    //clean up empty array indexes
                    foreach ($exploded as $index => $line) {
                        $exploded[$index] = trim($line);
                    }
                    //close all month and day tags  
                    // 4/3/17 - this should probably be handled by regex like year tags are                
                    foreach($exploded as $key => $value) {                                     
                         if(SGMLstartsWith($value, "&lt;month")) {
                            $exploded[$key] = $value . "</month>";
                            // break;
                         }
                         if(SGMLstartsWith($value, "&lt;day")) {
                            $exploded[$key] = $value . "</day>";
                            // break;
                         }                                                  
                     } 

                    # XML CONVERSION 
                    $imploded = implode("\r\n", $exploded);
                    //print("<pre>".print_r($exploded,true)."</pre>");
                    $converted_text = utf8_encode($imploded); //convert to utf8 to strip any remaining oddities
                    libxml_use_internal_errors(true); //turn on error reporting
                    $canr->xml = simplexml_load_string($converted_text); //convert string to xml
                    //if failed, say why
                    if ($canr->xml === false) {
                        echo "<b>FAILED: " . strtoupper($canr->name) . "</b><br/>";
                        echo "<ul>";
                        foreach(libxml_get_errors() as $error) {
                            echo "<li>" . $error->message . "</li>";
                        }
                        echo "</ul>";
                    } else {
                        //push into array of entries
                        $canr->sketch = $file;
                        $canr->pen = (string) $canr->xml->galedata->infobase->pen;
                        array_push($canrEntries, $canr);
                    }
                    // dumpText($converted_text);
                    //break;                                                           
                }                 

                foreach($canrEntries as $canr) {
                    $entry = $canr->xml;
                    //$pen = (string) $entry->galedata->infobase->pen;
                    $posts = null;
                    if($canr->pen) {
                        $posts = get_posts(array(
                            'post_type'        => 'post',
                            'post_status'      => 'any',
	                        'meta_key'		=> 'pen_id',
	                        'meta_value'	=> $canr->pen                      
                        ));
                    }
                    if(isset($posts) && count($posts) == 1) {
                        foreach($posts as $post) { 
                            #GALE DATA                        
                            $gale_field_key = getFieldKey($fields, "gale_data");
                            $gale_text = (string) $canr->galedata;
                            update_field( $gale_field_key, $gale_text, $post->ID );
                            #ATLAS UID
                            $atlas_field_key = getFieldKey($fields, "atlasuid");
                            $atlasuid = (string) $entry->bio_head->bioname->attributes();
                            update_field( $atlas_field_key, $atlasuid, $post->ID ); 
                            #SKETCH
                            $sketch_field_key = getFieldKey($fields, "canr_sketch");
                            $sketch_text = (string) $canr->sketch;
                            update_field( $sketch_field_key, $sketch_text, $post->ID );

                            #GENDER
                            $gender_field_key = getFieldKey($fields, "gender");
                            $gender = "";
                            $gender = (string) $entry->bio_head->bioname->mainname->attributes();
                            $gender = ucfirst($gender); //uppercase
                            update_field( $gender_field_key, $gender, $post->ID );  

                            #MAIN NAME
		                    $name_field_key = getFieldKey($fields, "main_name");
                            $main_name_prefix = (string) $entry->bio_head->bioname->mainname->prefix;
                            $main_name_prefix = str_replace("_", ".", $main_name_prefix);                            
                            $main_name_first = (string) $entry->bio_head->bioname->mainname->first;
                            $main_name_first = str_replace("_", ".", $main_name_first);
                            $main_name_middle = (string) $entry->bio_head->bioname->mainname->middle;
                            $main_name_middle = str_replace("_", ".", $main_name_middle);
                            $main_name_last = (string) $entry->bio_head->bioname->mainname->last;
                            $main_name_last = str_replace("_", ".", $main_name_last);
                            $main_name_suffix = (string) $entry->bio_head->bioname->mainname->suffix;
                            $main_name_suffix = str_replace("_", ".", $main_name_suffix);
                            $name_text = array (
	                            array (
                                    "name_prefix"   => $main_name_prefix,
		                            "name_first"	=> $main_name_first,
                                    "name_middle"   => $main_name_middle,
		                            "name_last"	=> $main_name_last,
                                    "name_suffix"   => $main_name_suffix
	                            )
                            );                            
                            update_field( $name_field_key, $name_text, $post->ID );  

                            #VARIANT NAMES
                            if($entry->bio_head->bioname->variantname) {
		                        $variant_name_field_key = getFieldKey($fields, "variant_name");
                                $var_names = array ();                                
                                foreach($entry->bio_head->bioname->variantname as $name) {
                                    $var_type = (string) $name->attributes();
                                    $var_type = ucfirst($var_type);
                                    $var_prefix = (string) $name->prefix;
                                    $var_prefix = str_replace("_", ".", $var_prefix);
                                    $var_first = (string) $name->first;
                                    $var_first = str_replace("_", ".", $var_first);
                                    $var_middle = (string) $name->middle;
                                    $var_middle = str_replace("_", ".", $var_middle);
                                    $var_last = (string) $name->last;
                                    $var_last = str_replace("_", ".", $var_last);
                                    $var_suffix = (string) $name->suffix;
                                    $var_suffix = str_replace("_", ".", $var_suffix);
                                    $var_name = array (
                                        "var_name_type" => $var_type,
                                        "var_prefix" => $var_prefix,
		                                "var_first_name"	=> $var_first,
                                        "var_middle_name"   => $var_middle,
		                                "var_last_name"	=> $var_last,
                                        "var_suffix" => $var_suffix
                                    );
                                    array_push($var_names, $var_name);
                                }                                              
                                update_field( $variant_name_field_key, $var_names, $post->ID );
                            }

                            #BIRTHDATE
                            if ($entry->bio_body->personal->birth->birthdate) {
		                        $birthdate_field_key = getFieldKey($fields, "birth_date"); 
                                if($entry->bio_body->personal->birth->birthdate->year) {
                                    $birth_year = (string) $entry->bio_body->personal->birth->birthdate->year->attributes();
                                } else {
                                    $birth_year = "";
                                }                           
                                if($entry->bio_body->personal->birth->birthdate->month) {
                                    $birth_month = (string) $entry->bio_body->personal->birth->birthdate->month->attributes();
                                } else {
                                    $birth_month = "";
                                }
                                if($entry->bio_body->personal->birth->birthdate->day) {
                                    $birth_day= (string) $entry->bio_body->personal->birth->birthdate->day->attributes();
                                } else {
                                    $birth_day = "";
                                }                                                           
                                $birthdate = array (
	                                array (
		                                "birth_year"	=> $birth_year,
		                                "birth_month"	=> $birth_month,
                                        "birth_day" => $birth_day
	                                )
                                );                            
                                update_field( $birthdate_field_key, $birthdate, $post->ID );
                            }

                            #BIRTH LOCATION
                            if($entry->bio_body->personal->birth->birthlocation){
		                        $birthlocation_field_key = getFieldKey($fields, "birth_info");
                            
                                $birth_city = (string) $entry->bio_body->personal->birth->birthlocation->city;
                                $birth_city = str_replace("_", ".", $birth_city);
                                $birth_state = (string) $entry->bio_body->personal->birth->birthlocation->state;
                                $birth_state = str_replace("_", ".", $birth_state);
                                $birth_country = (string) $entry->bio_body->personal->birth->birthlocation->country; 
                                $birth_country = str_replace("_", ".", $birth_country);                           

                                $birth_location = array (
	                                array (
		                                "birth_city"	=> $birth_city,
		                                "birth_state"	=> $birth_state,
                                        "birth_country" => $birth_country
	                                )
                                );                            
                                update_field( $birthlocation_field_key, $birth_location, $post->ID );  
                            }                          

                            #DEATHDATE
                            if($entry->bio_body->personal->death) {
		                        $deathdate_field_key = getFieldKey($fields, "death_date");
                                if($entry->bio_body->personal->death->deathdate->year) {
                                    $death_year = (string) $entry->bio_body->personal->death->deathdate->year->attributes();
                                } else {
                                    $death_year = "";
                                }
                                if($entry->bio_body->personal->death->deathdate->month) {
                                    $death_month = (string) $entry->bio_body->personal->death->deathdate->month->attributes();
                                } else {
                                    $death_month = "";
                                }
                                if($entry->bio_body->personal->death->deathdate->day) {
                                    $death_day= (string) $entry->bio_body->personal->death->deathdate->day->attributes(); 
                                } else {
                                    $death_day = "";
                                }                                                     

                                $deathhdate = array (
	                                array (
		                                "death_year"	=> $death_year,
		                                "death_month"	=> $death_month,
                                        "death_day" => $death_day
	                                )
                                );                            
                                update_field( $deathdate_field_key, $deathhdate, $post->ID );   
                            }

                            #DEATH LOCATION 
                            if($entry->bio_body->personal->death->deathlocation){
		                        $deathlocation_field_key = getFieldKey($fields, "death_location");
                            
                                $death_city = (string) $entry->bio_body->personal->death->deathlocation->city;
                                $death_city = str_replace("_", ".", $death_city);
                                $death_state = (string) $entry->bio_body->personal->death->deathlocation->state;
                                $death_state = str_replace("_", ".", $death_state);
                                $death_country = (string) $entry->bio_body->personal->death->deathlocation->country; 
                                $death_country = str_replace("_", ".", $death_country);                          

                                $death_location = array (
	                                array (
		                                "death_city"	=> $death_city,
		                                "death_state"	=> $death_state,
                                        "death_country" => $death_country
	                                )
                                );                            
                                update_field( $deathlocation_field_key, $death_location, $post->ID );  
                            }

                            #DEATH REASON
                            if ($entry->bio_body->personal->death->reason) {
		                        $reason_field_key = getFieldKey($fields, "death_reason");
                                $reason_text = (string) $entry->bio_body->personal->death->reason;
                                $reason_text = str_replace("_", ".", $reason_text);
                                $reason_text = stripLineBreaks($reason_text);
                                update_field( $reason_field_key, $reason_text, $post->ID );
                            }  

                            #ETHNICITY
                            if ($entry->bio_body->personal->heritage->ethnicity) {
		                        $ethnicity_field_key = getFieldKey($fields, "ethnicity");
                                $ethnicity_text = (string) $entry->bio_body->personal->heritage->ethnicity;
                                $ethnicity_text = str_replace("_", ".", $ethnicity_text);
                                $ethnicity_text = stripLineBreaks($ethnicity_text);
                                update_field( $ethnicity_field_key, $ethnicity_text, $post->ID );
                            }                            


                            #NATIONALITY
                            if($entry->bio_body->personal->heritage->nationality) {
		                        $nationalities_field_key = getFieldKey($fields, "nationalities");
                                $nationalities = array ();
                                foreach($entry->bio_body->personal->heritage->nationality as $nationality) {
                                    $nationality_text = (string) $nationality;
                                    $nationality_text = str_replace("_", ".", $nationality_text);
                                    $nationality_text = stripLineBreaks($nationality_text);                                
                                    $nationalities_text = array (
                                        "author_nationality" => $nationality_text
                                    );
                                    array_push($nationalities, $nationalities_text);
                                }                                              
                                update_field( $nationalities_field_key, $nationalities, $post->ID );   
                            }                           

                            #EDUCATION
                            if ($entry->bio_body->personal->educate->composed_educate) {
		                        $education_field_key = getFieldKey($fields, "education");
                                $education_text = (string) $entry->bio_body->personal->educate->composed_educate;
                                $education_text = str_replace("_", ".", $education_text);
                                $education_text = stripLineBreaks($education_text);
                                update_field( $education_field_key, $education_text, $post->ID );
                            }

                            #WORK HISTORY
                            if ($entry->bio_body->personal->career->workhistory->composed_workhist) {
		                        $work_field_key = getFieldKey($fields, "work_history");
                                $work_text = (string) $entry->bio_body->personal->career->workhistory->composed_workhist;
                                $work_text = convertText_postxml($work_text);
                                $work_text = stripLineBreaks($work_text);
                                update_field( $work_field_key, $work_text, $post->ID ); 
                            }                           

                            #MILITARY
                            if ($entry->bio_body->personal->career->military->composed_military) {
		                        $military_field_key = getFieldKey($fields, "military");
                                $military_text = (string) $entry->bio_body->personal->career->military->composed_military;
                                $military_text = convertText_postxml($military_text);
                                $military_text = stripLineBreaks($military_text);
                                update_field( $military_field_key, $military_text, $post->ID ); 
                            }                            

                            #AVOCATIONS
                            if ($entry->bio_body->personal->avocation) {
		                        $avocations_field_key = getFieldKey($fields, "avocations");
                                $avocations_text = (string) $entry->bio_body->personal->avocation;
                                $avocations_text = convertText_postxml($avocations_text);
                                $avocations_text = stripLineBreaks($avocations_text);
                                update_field( $avocations_field_key, $avocations_text, $post->ID ); 
                            }                           

                            #MEMBER
                            if($entry->bio_body->personal->member->composed_member) {
		                        $member_field_key = getFieldKey($fields, "member");
                                $member_text = (string) $entry->bio_body->personal->member->composed_member;
                                $member_text = convertText_postxml($member_text);
                                $member_text = stripLineBreaks($member_text);
                                update_field( $member_field_key, $member_text, $post->ID );    
                            }                          

                            #AWARDS
                            if ($entry->bio_body->personal->award->composed_award) {
		                        $awards_field_key = getFieldKey($fields, "awards");
                                $awards_text = (string) $entry->bio_body->personal->award->composed_award;
                                $awards_text = convertText_postxml($awards_text);                               
                                $awards_text = stripLineBreaks($awards_text);
                                update_field( $awards_field_key, $awards_text, $post->ID ); 
                            }                             

                            #POLITICS
                            if ($entry->bio_body->personal->politics) {
		                        $politics_field_key = getFieldKey($fields, "politics");
                                $politics_text = (string) $entry->bio_body->personal->politics;
                                $politics_text = str_replace("_", ".", $politics_text);
                                $politics_text = stripLineBreaks($politics_text);
                                update_field( $politics_field_key, $politics_text, $post->ID ); 
                            }                             

                            #RELIGION
                            if ($entry->bio_body->personal->religion) {
		                        $religion_field_key = getFieldKey($fields, "religion");
                                $religion_text = (string) $entry->bio_body->personal->religion;
                                $religion_text = str_replace("_", ".", $religion_text);
                                $religion_text = stripLineBreaks($religion_text);
                                update_field( $religion_field_key, $religion_text, $post->ID ); 
                            }                                                                                                                                             

                            #COMPOSED PERSONAL
		                    $personal_field_key = getFieldKey($fields, "personal");
                            $personal_text = (string) $entry->bio_body->personal->composed_personal;
                            $personal_text = convertText_postxml($personal_text);
                            $personal_text = stripLineBreaks($personal_text);
                            update_field( $personal_field_key, $personal_text, $post->ID );

                            #ADDRESS
                            if ($entry->bio_body->address) {
		                        $address_field_key = getFieldKey($fields, "author_address");
                                $addresses = array ();
                                foreach($entry->bio_body->address as $add) {
                                    if(!empty($add->attributes())) {
                                        $type = (string) $add->attributes();
                                        $type = ucfirst($type);
                                        $text = (string) $add->geo->geoother;
                                        $text = str_replace("_", ".", $text);
                                        $address = array (
                                            "author_address_type" => $type, 
                                            "author_address_text" => $text
                                        );
                                        array_push($addresses, $address);
                                    }
                                }                                              
                                update_field( $address_field_key, $addresses, $post->ID ); 
                            }                           

                            #EMAIL
                            $email_text = "";
                            if ($entry->bio_body->address) {
                                foreach($entry->bio_body->address as $add) {
                                    if(empty($add->attributes())) {
                                        $text = (string) $add->telecom->email;
                                        $text = str_replace("_", ".", $text);
                                        $email_text = $text;
                                    }
                                }                             
                                if($email_text) {
		                            $email_field_key = getFieldKey($fields, "author_email");                                          
                                    update_field( $email_field_key, $email_text, $post->ID ); 
                                }   
                            }                   

                            #WRITINGS
		                    $writings_field_key = getFieldKey($fields, "collected_writings");
                            $writings_field = get_field_object("collected_writings", $post->ID);
                            $writings = array ();

                            #SUBHEADS
                            if($entry->bio_body->works->workgroup->grouptitle) {
                                $subheads = array ();
                                $grouptitles = $entry->bio_body->works->workgroup->grouptitle;
                                foreach ($grouptitles as $grouptitle) {
                                    $text = (string) $grouptitle[0];
                                    if($text != "WRITINGS:") {
                                        array_push($subheads, $text);
                                    }                                    
                                }
                                $subheads_unique = array_unique($subheads);                            
                                foreach($subheads_unique as $subhead) {                            
                                    $text = (string) $subhead;
                                    $text = str_replace("_", ".", $text);
                                    $text = stripLineBreaks($text);                              
                                    $subhead_text = array (
                                        "imported_subhead_title" => $text,
                                        "acf_fc_layout" => "imported_subhead"
                                    );
                                    array_push($writings, $subhead_text);
                                }                                                                          
                            }

                            # IMPORTED WRITINGS
                            foreach($entry->bio_body->works->workgroup->bibcitation as $citation) { 
                                $pub_year = (string) $citation->bibcit_composed->year->attributes();                            
                                $text = (string) $citation->bibcit_composed;
                                $text = convertText_postxml($text);
                                //trim vestigial ", ." at end of citation text caused by parsing out pub year
                                //searches for last instance of a comma and trims to end of string
                                $text = substr($text, 0, strrpos($text, ','));
                                $text = stripLineBreaks($text);                              
                                $writings_text = array (
                                    "imported_pub_year" => $pub_year,
                                    "imported_writing_text" => $text,
                                    "acf_fc_layout" => "imported_writing"
                                );
                                array_push($writings, $writings_text);
                            }

		                    //insert generated entries into Writings repeater
		                    if(count($writings) > 0) {
			                    //can't append new entries to existing ones using update function, have to retrieve and pass back existing entries as well		
			                    $existing_entries = $writings_field['value'];
			                    if($existing_entries == false) {
				                    update_field( $writings_field_key, $writings, $post->ID );
			                    } else {
                                    $non_imported_writings = Array ();
                                    // loop through existing entries to parse imported writings from non-imported
                                    // to avoid pushing duplicates if file is imported multiple times
                                    foreach($existing_entries as $existing) {
                                        $template = $existing['acf_fc_layout'];
                                        if($template != "imported_writing" && $template != "imported_subhead") {
                                            array_push($non_imported_writings, $existing);
                                        }
                                    }
				                    foreach($writings as $writing) {
					                    array_push($non_imported_writings, $writing);
				                    }			
				                    update_field( $writings_field_key, $non_imported_writings, $post->ID );
			                    }		
		                    }                                                                          
                           // update_field( $writings_field_key, $writings, $post->ID );                                                       

                            #SECONDARY WRITINGS
                            if($canr->secondary_writings) {
                           	    $secondary_field_key = getFieldKey($fields, "secondary_writings");
                                $secondary_text = $canr->secondary_writings;
                                $secondary_text = convertText($secondary_text, false);
                                $secondary_text = convertText_postxml($secondary_text);
                                $secondary_text = stripLineBreaks($secondary_text);
                                update_field( $secondary_field_key, $secondary_text, $post->ID );  
                            }                           

                            #ADAPTATIONS
                            $adaptations_field_key = getFieldKey($fields, "adaptations");
                            $adaptations_text = (string) $entry->bio_body->works->adaptations;
                            $adaptations_text = convertText_postxml($adaptations_text);
                            $adaptations_text = stripLineBreaks($adaptations_text);
                            update_field( $adaptations_field_key, $adaptations_text, $post->ID ); 
                            
                            #SIDELIGHTS
                           	$narrative_field_key = getFieldKey($fields, "narrative");
                            $narrative_text = (string) $entry->bio_body->narrative;
                            $narrative_text = convertText_postxml($narrative_text);
                            $narrative_text = stripLineBreaks($narrative_text);
                            update_field( $narrative_field_key, $narrative_text, $post->ID ); 

                            #BOOKS
                            if($entry->bio_foot->readinggroup->books) {
                                $biocrit_has_books_field_key = getFieldKey($fields, "biocrit_has_books");
		                        $biocrit_books_field_key = getFieldKey($fields, "biocrit_books");
                                $books = array ();
                                foreach($entry->bio_foot->readinggroup->books->bibcitation as $citation) {
                                    $text = (string) $citation->bibcit_composed;
                                    $text = convertText_postxml($text);
                                    $text = stripLineBreaks($text);                                
                                    $bib_text = array (
                                        "biocrit_book_entry" => $text
                                    );
                                    array_push($books, $bib_text);
                                }                                              
                                update_field( $biocrit_books_field_key, $books, $post->ID );
                                if(count($books) > 0) {
                                    update_field( $biocrit_has_books_field_key, "1", $post->ID );
                                } else {
                                    update_field( $biocrit_has_books_field_key, "0", $post->ID );
                                } 
                            }                              

                            #PERIODICALS
                            if($entry->bio_foot->readinggroup->periodicals) {
                                $periodicals_field_key = getFieldKey($fields, "biocrit_entries");
                                $periodicals = array ();                            
                                foreach($entry->bio_foot->readinggroup->periodicals->bibcitation as $citation) {
                                    $text = (string) $citation->bibcit_composed;
                                    $text = convertText_postxml($text);
                                    $text = stripLineBreaks($text);                                
                                    $bib_text = array (
                                        "biocrit_entry" => $text
                                    );
                                    array_push($periodicals, $bib_text);
                                }                                              
                                update_field( $periodicals_field_key, $periodicals, $post->ID ); 
                            }                           

                            #ONLINE
                            if($entry->bio_foot->readinggroup->online) {
		                        $online_field_key = getFieldKey($fields, "online_biocrit_entries");
                                $online = array ();
                                foreach($entry->bio_foot->readinggroup->online->bibcitation as $citation) {
                                    $text = (string) $citation->bibcit_composed;
                                    $text = convertText_postxml($text);
                                    $text = stripLineBreaks($text);
                                    $bib_text = array (
                                        "online_biocrit_entry" => $text
                                    );
                                    array_push($online, $bib_text);
                                }                                              
                                update_field( $online_field_key, $online, $post->ID );    
                            }

                            echo "<b>COMPLETE:</b> " . $post->post_title . "<br/>";                       
                        }
                    } else {
                        echo "<b>SKIPPED:</b> " . strtoupper($canr->name) . "<br/>";
                    }
                }

                // if ($zip->open($zip_file) === TRUE) {
                //     $zip->extractTo('/wp-content/uploads/temp/');
                //     $zip->close();
                //     echo 'ok';
                // } else {
                //     echo 'failed';
                // }          

                // $xml_content = xml_from_zip($zip_file);   
                // if(!$xml_content){
                //     echo 'The ZIP archive does not contain XML file.';
                //     unlink($zip_file);
                // }else file_put_contents($xml_file, $xml_content);
            }
            
        }else if($ext=='csv'){
            
            $csv_file = str_replace('.xml', '.csv', $xml_file);
            if(!move_uploaded_file($file['tmp_name'], $csv_file))echo 'Error while uploading the file.';
            else file_put_contents($xml_file, csv_to_xml($csv_file));
            unlink($csv_file);
            
        }else{
            
            return 'Select a proper file (XML/CSV/ZIP).';
            
        }
        
        return '';
    }

    function dumpText($input) {
        header("Content-type: text/plain");
	    header("Content-disposition: attachment; filename=Filedump" . date("Y-m-d_H-i-s"). ".txt");
        echo $input;
        exit;
    }

    function getPostFields() {
        $fields = array ();
        $groups = acf_get_field_groups();                            
        foreach( $groups as $group ) {
            $group_fields = acf_get_fields($group);
            foreach ($group_fields as $group_field) {
                array_push($fields, $group_field);
            }                            
        }
        return $fields;           
    }

    function getFieldKey($fields, $fieldName) {
        foreach($fields as $field) {
            if($field['name'] == $fieldName) {
                return $field['key'];               
            }
        }
    }

    function convertText ($text, $xml = true) {
        //strip unneeded line breaks
	    $text = str_replace("\r", " ", $text);
        //add line breaks between tags to assist XML conversion                                                       
        $text = str_replace("><", ">\n<", $text);

        // 3/7/17 - convert diacrits to hex code
        $text = str_replace("&mdash;", "&#x2014;", $text); 
        $text = str_replace("&ldquo;", "&#x201c;", $text);
        $text = str_replace("&rdquo;", "&#x201d;", $text);
        $text = str_replace("&lsquo;", "&#x2018;", $text);
        $text = str_replace("&rsquo;", "&#x2019;", $text);
        $text = str_replace("&lsqb;", "[", $text);
        $text = str_replace("&rsqb;", "]", $text);
        $text = str_replace("&apos;", "&#x27;", $text);
        $text = str_replace("&num;", "&#x23;", $text); // #
        $text = str_replace("&hellip;", "&#x2026;", $text); //… 
        $text = str_replace("&Hellip;", "&#x2026;", $text); //… 
        $text = str_replace("&amp;", "&#x26;", $text); // &               
        $text = str_replace("&plus;", "&#x2B;", $text); //+
        $text = str_replace("&dollar;", "&#x24;", $text); // $
        $text = str_replace("&pound;", "&#xa3;", $text); // £
        $text = str_replace("&equal;", "&#x3D;", $text); // =
        $text = str_replace("&equals;", "&#x3D;", $text); // =

        $text = str_replace("&auml;", "&#xe4;", $text); // ä
        $text = str_replace("&Auml;", "&#xc4;", $text); // Ä
        $text = str_replace("&euml;", "&#xeb;", $text); // ë
        $text = str_replace("&Euml;", "&#xcb;", $text); // Ë        
        $text = str_replace("&ouml;", "&#xf6;", $text); // ö
        $text = str_replace("&Ouml;", "&#xd6;", $text); // Ö
        $text = str_replace("&oumlaut;", "&#xf6;", $text); // ö
        $text = str_replace("&Oumlaut;", "&#xd6;", $text); // Ö        

        $text = str_replace("&Aacute;", "&#xc1;", $text); // Á 
        $text = str_replace("&aacute;", "&#xe1;", $text); // á              
        $text = str_replace("&Eacute;", "&#xc9;", $text); // É 
        $text = str_replace("&eacute;", "&#xe9;", $text); // é
        $text = str_replace("&Iacute;", "&#xcd;", $text); // Í 
        $text = str_replace("&iacute;", "&#xed;", $text); // í        
        $text = str_replace("&Oacute;", "&#xd3;", $text); // Ó 
        $text = str_replace("&oacute;", "&#xf3;", $text); // ó
        $text = str_replace("&Uacute;", "&#xda;", $text); // Ú
        $text = str_replace("&uacute;", "&#xfa;", $text); //ú 

        $text = str_replace("&Acirc;", "&#xc2;", $text); // Â 
        $text = str_replace("&acirc;", "&#xe2;", $text); // â
        $text = str_replace("&ccirc;", "&#265;", $text); // ĉ 
        $text = str_replace("&Ecirc;", "&#xca;", $text); // Ê 
        $text = str_replace("&ecirc;", "&#xea;", $text); // ê  
        $text = str_replace("&Icirc;", "&#xce;", $text); // Î 
        $text = str_replace("&icirc;", "&#238;", $text); // î 
        $text = str_replace("&Ocirc;", "&#xd4;", $text); // Ô 
        $text = str_replace("&ocirc;", "&#xf4;", $text); // ô 
        $text = str_replace("&scirc;", "&#349;", $text); // ŝ     
        $text = str_replace("&Ucirc;", "&#xdb;", $text); // Û 
        $text = str_replace("&ucirc;", "&#xfb;", $text); // û                                     

        $text = str_replace("&Agrave;", "&#xc0;", $text); // À
        $text = str_replace("&agrave;", "&#xe0;", $text); // à
        $text = str_replace("&Egrave;", "&#xc8;", $text); // È               
        $text = str_replace("&egrave;", "&#xe8;", $text); // è
        $text = str_replace("&Igrave;", "&#xcc;", $text); // Ì              
        $text = str_replace("&igrave;", "&#xec;", $text); // ì        
        $text = str_replace("&Ograve;", "&#xd2;", $text); // Ò               
        $text = str_replace("&ograve;", "&#xf2;", $text); // ò
        $text = str_replace("&Ugrave;", "&#xd9;", $text); // Ù               
        $text = str_replace("&ugrave;", "&#xf9;", $text); // ù  

        $text = str_replace("&ccedil;", "&#xe7;", $text); // ç    
        $text = str_replace("&Ccedil;", "&#xc7;", $text); // Ç        

        $text = str_replace("&iuml;", "&#xef;", $text); // ï
        $text = str_replace("&Iuml;", "&#xcf;", $text); // Ï                   
        $text = str_replace("&uuml;", "&#xfc;", $text); //ü
        $text = str_replace("&Uuml;", "&#xdc;", $text); // Ü        
        
        $text = str_replace("&Atilde;", "&#xc3;", $text); // Ã
        $text = str_replace("&atilde;", "&#xe3;", $text); // ã
        $text = str_replace("&Ntilde;", "&#xd1;", $text); // Ñ
        $text = str_replace("&ntilde;", "&#xf1;", $text); // ñ
        $text = str_replace("&Otilde;", "&#xd5;", $text); // Õ
        $text = str_replace("&otilde;", "&#xf5;", $text); // õ      
        $text = str_replace("&tilde;", "&#x7e;", $text); // ~

	    $text = str_replace("&scaron;", "&#x161;", $text); // š
        $text = str_replace("&Scaron;", "&#x160;", $text); // Š
        $text = str_replace("&ccaron;", "&#x10d;", $text); // č
	    $text = str_replace("&Ccaron;", "&#x10c;", $text); // Č
	    $text = str_replace("&cacute;", "&#x107;", $text); // ć
	    $text = str_replace("&imacr;", "&#x12b;", $text); // ī
	    $text = str_replace("&oslash;", "&#xf8;", $text); // ø
	    $text = str_replace("&aelig;", "&#xe6;", $text); // æ
	    $text = str_replace("&aring;", "&#xe5;", $text); // å 
        $text = str_replace("&Abreve;", "&#x102;", $text); // Ă
        $text = str_replace("&abreve;", "&#x103;", $text); // ă   
	    $text = str_replace("&iexcl;", "&#xa1;", $text); // ¡         

        $text = str_replace("&szlig;", "&#xdf;", $text); // ß   
        $text = str_replace("&Tcedil;", "&#x162;", $text); // Ţ  
        $text = str_replace("&scedil;", "&#x15F;", $text); // ş  
        $text = str_replace("&inodot;", "&#x131;", $text); // ı
        $text = str_replace("&gbreve;", "&#x11f;", $text); // ğ          

        $text = str_replace("&THORN;", "&#xfe;", $text); // þ
        $text = str_replace("&eth;", "&#xf0;", $text); // ð   
        $text = str_replace("&omacr;", "&#x14d;", $text); // ō
        $text = str_replace("&umacr;", "&#x16b;", $text); // ū

        $text = str_replace("&odblac;", "&#x151;", $text); // ő
        $text = str_replace("&zcaron;", "&#x17E;", $text); // ž
        $text = str_replace("&Zcaron;", "&#x17D;", $text); // Ž
        $text = str_replace("&amacr;", "&#x101;", $text); // ā


        if($xml === false) {
            // 3/7/17 - convert format tags for WYSIWIG
            $text = str_replace('<head n="5">', "<code>", $text);
            $text = str_replace("</head>", "</code>", $text);                    
            $text = str_replace("<para>", "<p>", $text);
            $text = str_replace("</para>", "</p>", $text);
            $text = str_replace('<title>', '<strong>', $text);
            $text = str_replace("</title>", "</strong>", $text);
            // $text = str_replace('<emphasis n="1">', "<em>", $text);
            // $text = str_replace("</emphasis>", "</em>", $text);
            // $text = str_replace('<emphasis n="1">', "[em]", $text);
            // $text = str_replace("</emphasis>", "[/em]", $text);  
           // $text = str_replace("emphasis", "em", $text);               
        } else {
            // 3/7/17 - convert format tags for WYSIWIG
            $text = str_replace('<head n="5">', "&lt;code&gt;", $text);
            $text = str_replace("</head>", "&lt;/code&gt;", $text);                    
            $text = str_replace("<para>", "&lt;p&gt;", $text);
            $text = str_replace("</para>", "&lt;/p&gt;", $text);
            $text = str_replace('<title>', '&lt;strong&gt;', $text);
            $text = str_replace("</title>", "&lt;/strong&gt;", $text);
        //    $text = str_replace("emphasis", "em", $text); 
            // $text = str_replace('<emphasis n="1">', "&lt;em&gt;", $text);
            // $text = str_replace("</emphasis>", "&lt;/em&gt;", $text); 
            // $text = str_replace('<emphasis n="1">', "[em]", $text);
            // $text = str_replace("</emphasis>", "[/em]", $text);             
        }
        return $text;  
    }

    function convertText_postxml($text) {
        $text = str_replace("_", ".", $text);
        $text = str_replace("(em)", "<em>", $text);
        $text = str_replace("(/em)", "</em>", $text); 
        return $text;    
    }

    function SGMLstartsWith($haystack, $needle)
    {
	    $haystack = htmlentities($haystack);
        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }

    function stripLineBreaks ($text) {
        $haystack = htmlentities($text);
        return str_replace(array("\r", "\n"), ' ', $text);
    }

function sanitizeXML($xml_content, $xml_followdepth=true){

    if (preg_match_all('%<((\w+)\s?.*?)>(.+?)</\2>%si', $xml_content, $xmlElements, PREG_SET_ORDER)) {

        $xmlSafeContent = '';

        foreach($xmlElements as $xmlElem){
            $xmlSafeContent .= '<'.$xmlElem['1'].'>';
            if (preg_match('%<((\w+)\s?.*?)>(.+?)</\2>%si', $xmlElem['3'])) {
                $xmlSafeContent .= sanitizeXML($xmlElem['3'], false);
            }else{
                $xmlSafeContent .= htmlspecialchars($xmlElem['3'],ENT_NOQUOTES);
            }
            $xmlSafeContent .= '</'.$xmlElem['2'].'>';
        }

        if(!$xml_followdepth)
            return $xmlSafeContent;
        else
            return "<?xml version='1.0' encoding='UTF-8'?>".$xmlSafeContent;

    } else {
        return htmlspecialchars($xml_content,ENT_NOQUOTES);
    }

}


    function get_text($xml){
		return preg_replace("/^\<[^>]+?\>|\<[^>]+?\>$/",'',$xml->asXML());
		/*
        if($xml->count()==0)return $xml;
        $text = '';
        foreach($xml as $k=>$v)
            if($k!='@attributes')
                $text .= get_text($v)."\n";
        return $text;
		*/
    }

    class CANR {
	    public $pen;
        public $xml;
        public $sketch;
        public $secondary_writings;
        public $galedata;
        public $name;
    }
    

?>