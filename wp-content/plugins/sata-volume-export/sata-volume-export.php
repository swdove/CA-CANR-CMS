<?php
/*
Plugin Name: SATA Volume Export
Plugin URI: https://github.com/swdove
Description: Exports selected posts as SGML files contained in a zip archive
Version: 1.0
Author: Sean Dove
Author URI: https://github.com/swdove
 
*/
/*
Developed for Schlager Group to export SATA entries as custom SGML files. Base export plugin code borrowed and modified from "Advanced Export" plugin by Ron Rennick.
*/

if(!defined('ABSPATH')) {
	die("Don't call this file directly.");
}
if(isset($_GET['page']) && $_GET['page'] == 'sata_export' && isset( $_GET['download'] ) ) {
	add_action('init', 'sata_do_export');
}
function sata_do_export() {
	if(current_user_can('edit_files')) {
		$author = isset($_GET['author']) ? $_GET['author'] : 'all';
		$category = isset($_GET['category']) ? $_GET['category'] : 'all';
		$post_type = isset($_GET['post_type']) ? stripslashes($_GET['post_type']) : 'all';
		$status = isset($_GET['status']) ? stripslashes($_GET['status']) : 'all';
		$mm_start = isset($_GET['mm_start']) ? $_GET['mm_start'] : 'all';
		$mm_end = isset($_GET['mm_end']) ? $_GET['mm_end'] : 'all';
		$aa_start = isset($_GET['aa_start']) ? intval($_GET['aa_start']) : 0;
		$aa_end = isset($_GET['aa_end']) ? intval($_GET['aa_end']) : 0;
		$terms = isset($_GET['terms']) ? stripslashes($_GET['terms']) : 'all';
		if($mm_start != 'all' && $aa_start > 0) {
			$start_date = sprintf( "%04d-%02d-%02d", $aa_start, $mm_start, 1 );
		} else {
			$start_date = 'all';
		}
		if($mm_end != 'all' && $aa_end > 0) {
			if($mm_end == 12) {
				$mm_end = 1;
				$aa_end++;
			} else {
				$mm_end++;
			}
			$end_date = sprintf( "%04d-%02d-%02d", $aa_end, $mm_end, 1 );
		} else {
			$end_date = 'all';
		}
		sata_export_setup();
		//ra_export_wp( $author, $category, $post_type, $status, $start_date, $end_date, $terms );
		sata_export_wp_xml( $author, $category, $post_type, $status, $start_date, $end_date, $terms );
		die();
	}
}	
function sata_export_wp_xml($author='', $category='', $post_type='', $status='', $start_date='', $end_date='', $terms = '') {
 	global $wpdb, $post_ids, $post;

	define('WXR_VERSION', '1.0');

	//do_action('export_wp');

	if(strlen($start_date) > 4 && strlen($end_date) > 4) {
		$filename = 'wordpress.' . $start_date . '.' . $end_date . '.xml';
	} else {
		$filename = 'wordpress.' . date('Y-m-d') . '.xml';
	}
	header('Content-Description: File Transfer');
	header("Content-Disposition: attachment; filename=$filename");
	header('Content-Type: text/xml; charset=' . get_option('blog_charset'), true);

	if ( $post_type and $post_type != 'all' ) {
		$where = $wpdb->prepare("WHERE post_type = %s ", $post_type);
	} else {
		$where = "WHERE post_type != 'revision' ";
	}
	if ( $author and $author != 'all' ) {
		$author_id = (int) $author;
		$where .= $wpdb->prepare("AND post_author = %d ", $author_id);
	}
	if ( $start_date and $start_date != 'all' ) {
		$where .= $wpdb->prepare("AND post_date >= %s ", $start_date);
	}
	if ( $end_date and $end_date != 'all' ) {
		$where .= $wpdb->prepare("AND post_date < %s ", $end_date);
	}
	if ( $category and $category != 'all' and version_compare($wpdb->db_version(), '4.1', 'ge')) {
		$taxomony_id = (int) $category;
		$where .= $wpdb->prepare("AND ID IN (SELECT object_id FROM {$wpdb->term_relationships} " .
			"WHERE term_taxonomy_id = %d) ", $taxomony_id);
	}
	if ( $status and $status != 'all' ) {
		$where .= $wpdb->prepare("AND post_status = %s ", $status);
	}

	// grab a snapshot of post IDs, just in case it changes during the export
	$post_ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts $where ORDER BY post_date_gmt ASC");

	class Post {
		public $id;
		public $pen;
		public $atlasuid;
		public $galeData;
		public $xml_declaration;
		public $title;
		public $gender;
		public $prefix;
		public $firstName;
		public $middleName;
		public $lastName;
		public $suffix;
		public $variantNames = Array();

		public $birth_day;
		public $birth_month;
		public $birth_year;
		public $birth_city;
		public $birth_state;
		public $birth_country;
		public $death_day;
		public $death_month;
		public $death_year;
		public $death_city;
		public $death_state;
		public $death_country;
		public $death_reason;

		public $ethnicity;
		public $nationality;
		public $nationalities = Array();
		public $education;

		public $workHistory;
		public $awards;
		public $personal;
		public $addresses = Array();
		public $writings = Array();
		public $secondary_writings;
		public $adaptations;
		public $narrative;
		public $loc_entries;
		public $biocritEntries = Array();
		public $biocrit_books = Array();
		public $biocrit_periodicals = Array();
		public $biocrit_online = Array();
		public $biocrit_obits = Array();

		public $fields = Array();
	}

	class Content {
		public $name;
		public $value;
	}

	class VariantName {
		public $type;
		public $prefix;
		public $firstName;
		public $middleName;
		public $lastName;
		public $suffix;
	}

	class Address {
		public $type;
		public $address_text;
	}

	class Writing {
		public $id;
		public $title;
		public $type;
		public $publisher;
		public $location;
		public $year;
		public $role;
		public $reprints = Array();
		public $text;		
	}

	class Reprint {
		public $title;
		public $publisher;
		public $location;
		public $year;
	}

	class BiocritEntry {
		public $entry_type;
		public $entry_text;
		public $last = false;
	}


	$posts = array();

	//loop through post ids, get post content, metadata fields and values
	foreach ($post_ids as $id) {
		$post = new Post();
		$thepost = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID = %s", $id ) );
		$post->id = $id;
		$post->title = $thepost->post_title;
		$postmeta = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $wpdb->postmeta WHERE post_id = %d", $post->id) );
		if ( $postmeta ) {
			$myFields = array();
			foreach( $postmeta as $meta ) {
				$myContent = new Content();
				$myContent->name = $meta->meta_key;
				$myContent->value = $meta->meta_value;
				array_push($myFields, $myContent);
			}
			$post->fields = $myFields;
			parse_export_values($post);
			get_repeater_values($post);		
		}
		array_push($posts, $post);
	}

	if($category){
		$cat_name = get_cat_name($category);
		$zipname = $cat_name . '_' . date('Y-m-d H:i:s') . '.zip';
	} else {
		$zipname = "SATA_" . date('y-m-d H:i:s') . '.zip';
	}
	$zip = new ZipArchive;
	$zip->open($zipname, ZipArchive::CREATE);
	foreach ($posts as $post) {
		$file_name = build_file_name($post);
		$content = build_SGML_file($post);
		//check for existing files in zip archive with same filename
		$nameCheck = $file_name . ".txt";
		$dupeCheck = $zip->locateName($nameCheck);
		if ($dupeCheck !== false) {
			$file_name = $file_name . "_2";
		}
  		$zip->addFromString($file_name . '.TXT', $content);
	}
	$zip->close();

	header('Content-Type: application/zip');
	header('Content-disposition: attachment; filename='.$zipname);
	header('Content-Length: ' . filesize($zipname));
	readfile($zipname);
}

function parse_export_values($post) {
	//loops through single-value meta fields and retrieves values
	foreach($post->fields as $field) {
		switch ($field->name) {
			case "gender":
				$post->gender = $field->value;
				break;			
			case "main_name_0_name_prefix":
				$post->prefix = $field->value;
				break;
			case "main_name_0_name_first":
				$post->firstName = $field->value;
				break;
			case "main_name_0_name_middle":
				$post->middleName = $field->value;
				break;
			case "main_name_0_name_last":
				$post->lastName = $field->value;
				break;
			case "main_name_0_name_suffix":
				$post->suffix = $field->value;
				break;
			case "birth_date_0_birth_day":
				$post->birth_day = $field->value;
				break;
			case "birth_date_0_birth_month":
				$post->birth_month = $field->value;
				break;
			case "birth_date_0_birth_year":
				$post->birth_year = $field->value;
				break;
			case "birth_info_0_birth_city":
				$post->birth_city = $field->value;
				break;
			case "birth_info_0_birth_state":
				$post->birth_state = $field->value;
				break;
			case "birth_info_0_birth_country":
				$post->birth_country= $field->value;
				break;
			case "death_date_0_death_day":
				$post->death_day = $field->value;
				break;
			case "death_date_0_death_month":
				$post->death_month = $field->value;
				break;
			case "death_date_0_death_year":
				$post->death_year = $field->value;
				break;
			case "death_location_0_death_city":
				$post->death_city = $field->value;
				break;
			case "death_location_0_death_state":
				$post->death_state = $field->value;
				break;
			case "death_location_0_death_country":
				$post->death_country = $field->value;
				break;
			case "ethnicity":
				$post->ethnicity = $field->value;
				break;
			case "nationality":
				$post->nationality = $field->value;
				break;
			case "education":
				$post->education = $field->value;
				break;																																
			case "work_history":
				$post->workHistory = $field->value;
				break;
			case "military":
				$post->military = $field->value;
				break;
			case "avocations":
				$post->avocations = $field->value;
				break;
			case "member":
				$post->member = $field->value;
				break;												
			case "awards":
				$post->awards = $field->value;
				break;
			case "politics":
				$post->politics = $field->value;
				break;
			case "religion":
				$post->religion = $field->value;
				break;								
			case "personal":
				$post->personal = $field->value;
				break;
			case "narrative":
				$post->narrative = $field->value;
				break;
			case "gale_data":
				$post->galeData = $field->value;
				break;
			case "atlasuid":
				$post->atlasuid = $field->value;
				break;
			case "secondary_writings":
				$post->secondary_writings = $field->value;
				break;
			case "adaptations":
				$post->adaptations = $field->value;
				break;
			case "xml_declaration":
				$post->xml_declaration = $field->value;
				break;			
			case "pen_id":
				$post->pen = $field->value;
				break;															
			default:
				//exclude "field identifier" fields
				if(!(preg_match('/^_/', $field->name))) {				
				};
				break;
		}
	}
}

function get_repeater_values($post) {
	//loop through repeater fields and get values
	$variants = get_field('variant_name', $post->id);
	if($variants) {
		foreach($variants as $variant) {
			$varName = new VariantName();
			$varName->type = $variant['var_name_type'];
			$varName->prefix = $variant['var_prefix'];
			$varName->firstName = $variant['var_first_name'];
			$varName->middleName = $variant['var_middle_name'];
			$varName->lastName = $variant['var_last_name'];
			$varName->suffix = $variant['var_suffix'];

			array_push($post->variantNames, $varName);
		}
	}

	$addresses = get_field('author_address', $post->id);
	if($addresses) {
		foreach($addresses as $address) {
			$addr = new Address();
			$addr->type = $address['author_address_type'];
			$addr->address_text = $address['author_address_text'];
			array_push($post->addresses, $addr);
		}
	}
	$books = get_field('biocrit_books', $post->id);
	if($books) {
		foreach($books as $citation) {
			$entry = new BiocritEntry();
			$entry->entry_type = "b";			
			$entry->entry_text = $citation['biocrit_book_entry'];
			array_push($post->biocritEntries, $entry);
		}
	}	
	$periodicals = get_field('biocrit_entries', $post->id);
	if($periodicals) {
		foreach($periodicals as $citation) {
			$entry = new BiocritEntry();
			$entry->entry_type = "p";
			$entry->entry_text = $citation['biocrit_entry'];
			array_push($post->biocritEntries, $entry);
		}
	}
	$online = get_field('online_biocrit_entries', $post->id);
	if(is_array($online) || is_object($online)) {
		foreach($online as $citation) {
			$entry = new BiocritEntry();
			$entry->entry_type = "o";
			$entry->entry_text = $citation['online_biocrit_entry'];
			array_push($post->biocritEntries, $entry);
		}
	}
	$obits = get_field('biocrit_obits', $post->id);
	if(is_array($obits) || is_object($obits)) {
		foreach($obits as $citation) {
			$entry = new BiocritEntry();
			$entry->entry_type = "ob";
			$entry->entry_text = $citation['biocrit_obituary_entry'];
			array_push($post->biocritEntries, $entry);
		}
	}	
	$nationalities = get_field('nationalities', $post->id);
	if(is_array($nationalities) || is_object($nationalities)) {
		foreach($nationalities as $nationality) {
			$entry = $nationality['author_nationality'];
			array_push($post->nationalities, $entry);
		}
	}	
	// check if the flexible content field has rows of data
	if( have_rows('collected_writings', $post->id) ):
 		// loop through the rows of data
    	while ( have_rows('collected_writings', $post->id) ) : $writing_row = the_row(true);
			$wrt = new Writing();
			// check current row layout
        	if( get_row_layout() == 'loc_writing' ):
			//$writing = get_sub_field('loc_writing_title');
				$wrt->id = "loc";
				$wrt->title = get_sub_field('loc_writing_title');
				$wrt->type = get_sub_field('loc_writing_type');
				$wrt->publisher = get_sub_field('loc_writing_publisher');
				$wrt->location = get_sub_field('loc_writing_location');
				$wrt->year = get_sub_field('loc_writing_year');
				$wrt->role = get_sub_field('loc_writing_role');
        		// check if the nested repeater field has rows of data
				//if( have_rows('loc_reprinted_as') ):
				if( $writing_row['loc_writing_reprinted'] === true ):
			 		// loop through the rows of data
					$rpt = new Reprint();
			    	while ( have_rows('loc_reprinted_as') ) : $loc_row = the_row(true);
						$rpt->title = get_sub_field('loc_reprinted_title');
						$rpt->publisher = get_sub_field('loc_reprinted_publisher');
						$rpt->location = get_sub_field('loc_reprinted_location');
						$rpt->year = get_sub_field('loc_reprinted_year');
						array_push($wrt->reprints, $rpt);
					endwhile;
				endif;
				array_push($post->writings, $wrt);
			elseif( get_row_layout() == 'misc_writing' ):
				$wrt->id = "misc";
				$wrt->title = get_sub_field('misc_writing_title');
				$wrt->type = get_sub_field('misc_writing_type');
				$wrt->publisher = get_sub_field('misc_writing_publisher');
				$wrt->location = get_sub_field('misc_writing_location');
				$wrt->year = get_sub_field('misc_writing_year');
				$wrt->role = get_sub_field('misc_writing_role');			
        		// check if the nested repeater field has rows of data
				//if( have_rows('misc_reprinted_as') ):
				if( $writing_row['misc_writing_reprinted'] === true ):
			 		// loop through the rows of data
					$rpt = new Reprint();
			    	while ( have_rows('misc_reprinted_as') ) : $misc_row = the_row(true);
						$rpt->title = get_sub_field('misc_reprinted_title');
						$rpt->publisher = get_sub_field('misc_reprinted_publisher');
						$rpt->location = get_sub_field('misc_reprinted_location');
						$rpt->year = get_sub_field('misc_reprinted_year');
						array_push($wrt->reprints, $rpt);
					endwhile;
				endif;
				array_push($post->writings, $wrt);
			elseif( get_row_layout() == 'writings_subhead' ):
				$wrt->id = "subhead";
				$wrt->title = get_sub_field('writing_subhead_title');
				array_push($post->writings, $wrt);   
			elseif( get_row_layout() == 'imported_subhead' ):
				$wrt->id = "imported_subhead";
				$wrt->title = get_sub_field('imported_subhead_title');
				array_push($post->writings, $wrt); 				     	
			elseif( get_row_layout() == 'imported_writing' ):
				$wrt->id = "imported";
				$wrt->text = get_sub_field('imported_writing_text');
				$wrt->year = get_sub_field('imported_pub_year');
				array_push($post->writings, $wrt);
        	endif;			
    	endwhile;
	else :
    	// no layouts found
	endif;							
}

function build_file_name($post) {
	if($post->lastName && $post->firstName) {
		$last = substr($post->lastName, 0, 6);
		$first = substr($post->firstName, 0, 1);
		if($post->middleName){
			$middle = substr($post->middleName, 0, 1);
		} else {
			$middle = "";
		}
		$filename = $last . $first . $middle;
	} else {
		$filename = $post->title;
	}
	return strtoupper($filename);
}

function build_SGML_file($post) {
	$export = "";

	if(!empty($post->xml_declaration)) {
		$export .= $post->xml_declaration . PHP_EOL;
	}	

	$export .= "<biography>" . PHP_EOL;

	if(!empty($post->galeData)) {
		$fake_pen = (substr( $post->pen, 0, 1) === "s");
		if($fake_pen === true) {
			$export .= "<galedata>". PHP_EOL;
			$export .= "<infobase>". PHP_EOL;
			$export .= "<pen>". PHP_EOL;
			$export .= "</pen>". PHP_EOL;
			$export .= "</infobase>". PHP_EOL;
			$export .= "</galedata>". PHP_EOL;			
		} else {
			$export .= $post->galeData . PHP_EOL;
		}
	}	

	$export .= "<bio.head>" . PHP_EOL;
	if(!empty($post->atlasuid)) {
		$export .= '<bioname atlasuid="'. $post->atlasuid . '">' . PHP_EOL;
	} else {
		$export .= "<bioname>" . PHP_EOL;
	}

	#MAIN NAME
	$export .= '<mainname gender="' . strtolower($post->gender) . '">' . PHP_EOL;

	if(!empty($post->prefix)) {
		$export .= "<prefix>" . convert_wyswig_punctuation($post->prefix) . "</prefix>" . PHP_EOL;
	}
	if(!empty($post->firstName)) {
		$export .= "<first>" . convert_wyswig_punctuation($post->firstName) . "</first>" . PHP_EOL;
	}
	if(!empty($post->middleName)) {
		$export .= "<middle>" . convert_wyswig_punctuation($post->middleName) . "</middle>" . PHP_EOL;
	}
	if(!empty($post->lastName)) {
		$export .= "<last>" . convert_wyswig_punctuation($post->lastName) . "</last>" . PHP_EOL;
	}
	if(!empty($post->suffix)) {
		$export .= "<suffix>" . convert_wyswig_punctuation($post->suffix) . "</suffix>" . PHP_EOL;
	}
	$export .= "</mainname>" . PHP_EOL;
	
	#VARIANT NAMES
	foreach($post->variantNames as $variantName) {
		$typeCode = "";
		switch ($variantName->type) {
			case "House Pseudonym":
				$typeCode = "housepseudonym";
				break;
			case "Joint Pseudonym":
				$typeCode = "jointpseudonym";
				break;
			case "Birth Name":
				$typeCode = "birth";
				break;
			case "Common Name":
				$typeCode = "common";									
				break;
			default:
				$typeCode = "pseudonym";
				break;				
		}
		$export .= PHP_EOL;
		$export .= '<variantname nametype="'. $typeCode .'">' . PHP_EOL;
		if(!empty($variantName->prefix)) {
			$export .= "<prefix>" . convert_wyswig_punctuation($variantName->prefix) . "</prefix>" . PHP_EOL;
		}
		if(!empty($variantName->firstName)) {
			$export .= "<first>" . convert_wyswig_punctuation($variantName->firstName) . "</first>" . PHP_EOL;
		}
		if(!empty($variantName->middleName)) {
			$export .= "<middle>" . convert_wyswig_punctuation($variantName->middleName) . "</middle>" . PHP_EOL;
		}
		if(!empty($variantName->lastName)) {
			$export .= "<last>" . convert_wyswig_punctuation($variantName->lastName) . "</last>" . PHP_EOL;
		}
		if(!empty($variantName->suffix)) {
			$export .= "<suffix>" . convert_wyswig_punctuation($variantName->suffix) . "</suffix>" . PHP_EOL;
		}
		$export .= "</variantname>" . PHP_EOL;
	}
	$export .= PHP_EOL;

	$export .= "</bioname>" . PHP_EOL;
	$export .= "</bio.head>" . PHP_EOL . PHP_EOL;

	$export .= "<bio.body>" . PHP_EOL . PHP_EOL;
	$export .= "<personal>" . PHP_EOL;
	#BIRTH
	if(!empty($post->birth_year) || !empty($post->birth_month) || !empty($post->birth_day) || !empty($post->birth_city) || !empty($post->birth_state) || !empty($post->birth_country)) {
		$export .= "<birth>" . PHP_EOL;
		if(!empty($post->birth_year) || !empty($post->birth_month) || !empty($post->birth_day)) {
			$export .= "<birthdate>" . PHP_EOL;
			if(!empty($post->birth_year)) {
				$export .= '<year year="' . $post->birth_year . '">' . PHP_EOL;
			}
			if(!empty($post->birth_month)) {
				$export .= '<month month="' . $post->birth_month . '">' . PHP_EOL;
			}
			if(!empty($post->birth_day)) {
				$export .= '<day day="' . $post->birth_day . '">' . PHP_EOL;
			}					
			$export .= "</birthdate>" . PHP_EOL . PHP_EOL;
		}
		if(!empty($post->birth_city) || !empty($post->birth_state) || !empty($post->birth_country)) {
			$export .= "<birthlocation>" . PHP_EOL;
			if(!empty($post->birth_city)) {
				$export .= "<city>" . $post->birth_city . "</city>" . PHP_EOL;
			}
			if(!empty($post->birth_state)) {
				$export .= "<state>" . $post->birth_state . "</state>" . PHP_EOL;
			}
			if(!empty($post->birth_country)) {
				$export .= "<country>" . $post->birth_country . "</country>" . PHP_EOL;
			}					
			$export .= "</birthlocation>" . PHP_EOL;
		}
		$export .= "</birth>" . PHP_EOL . PHP_EOL;
	}

	#DEATH
	if(!empty($post->death_year) || !empty($post->death_month) || !empty($post->death_day) || !empty($post->death_city) || !empty($post->death_state) || !empty($post->death_country)) {
		$export .= "<death>" . PHP_EOL;	
		if(!empty($post->death_city) || !empty($post->death_state) || !empty($post->death_country)) {
			$export .= "<deathdate>" . PHP_EOL;
			if(!empty($post->death_year)) {
				$export .= '<year year="' . $post->death_year . '">' . PHP_EOL;
			}
			if(!empty($post->death_month)) {
				$export .= '<month month="' . $post->death_month . '">' . PHP_EOL;
			}
			if(!empty($post->death_day)) {
				$export .= '<day day="' . $post->death_day . '">' . PHP_EOL;
			}		
			$export .= "</deathdate>" . PHP_EOL . PHP_EOL;
		}
		if(!empty($post->death_city) || !empty($post->death_state) || !empty($post->death_country)) {
			$export .= "<deathlocation>" . PHP_EOL;
			if(!empty($post->death_city)) {
				$export .= "<city>" . $post->death_city . "</city>" . PHP_EOL;
			}
			if(!empty($post->death_state)) {
				$export .= "<state>" . $post->death_state . "</state>" . PHP_EOL;
			}
			if(!empty($post->death_country)) {
				$export .= "<country>" . $post->death_country . "</country>" . PHP_EOL;
			}		
			$export .= "</deathlocation>" . PHP_EOL;
		}
		if(!empty($post->death_reason)) {
			$export .= "<reason>" . $post->death_reason . "</reason>" . PHP_EOL;
		}	
		$export .= "</death>" . PHP_EOL . PHP_EOL;		
	}

	#HERITAGE
	if(!empty($post->ethnicity) || !empty($post->nationalities) || !empty($post->nationality)) {
		$export .= "<heritage>" . PHP_EOL;
		#ETHNICITY
		if(!empty($post->ethnicity)) {
			$export .= "<ethnicity>" . PHP_EOL;
			$export .= $post->ethnicity . PHP_EOL;
			$export .= "</ethnicity>" . PHP_EOL;
		}
		#NATIONALITY
		if(!empty($post->nationalities)) {
			foreach($post->nationalities as $nationality) {
				$export .= "<nationality>" . $nationality . "</nationality>" . PHP_EOL;
			}			
		} elseif(!empty($post->nationality)) {
				$export .= "<nationality>" . $post->nationality . "</nationality>" . PHP_EOL;
		}			
		$export .= "</heritage>" . PHP_EOL;
	}		

	#EDUCATION
	if(!empty($post->education)) {
		$export .= "<educate>" . PHP_EOL;
		$export .= "<composed.educate>" . PHP_EOL;
		$export .= WYSIWYG_conversion($post->education, false) . PHP_EOL;
		$export .= "</composed.educate>" . PHP_EOL;
		$export .= "</educate>" . PHP_EOL;
	}	

	#CAREER
	if(!empty($post->workHistory) || !empty($post->military)) {
		$export .= "<career>" . PHP_EOL;
		if(!empty($post->workHistory)) {
			$export .= "<workhistory>" . PHP_EOL;
			$export .= "<composed.workhist>" . PHP_EOL;
			$export .= WYSIWYG_conversion($post->workHistory) . PHP_EOL;
			$export .= "</composed.workhist>" . PHP_EOL;
			$export .= "</workhistory>" . PHP_EOL;
		}

		#MILITARY
		if(!empty($post->military)) {
			$export .= "<military>" . PHP_EOL;
			$export .= "<composed.military>" . PHP_EOL;
			$export .= WYSIWYG_conversion($post->military, false) . PHP_EOL;
			$export .= "</composed.military>" . PHP_EOL;
			$export .= "</military>" . PHP_EOL . PHP_EOL;
		}
		$export .= "</career>" . PHP_EOL . PHP_EOL;
	}

	#AVOCATION
	if(!empty($post->avocations)) {
		$export .= "<avocation>" . PHP_EOL;
		$export .= WYSIWYG_conversion($post->avocations) . PHP_EOL;
		$export .= "</avocation>" . PHP_EOL . PHP_EOL;
	}

	#MEMBER
	if(!empty($post->member)) {
		$export .= "<member>" . PHP_EOL;
		$export .= "<composed.member>" . PHP_EOL;
		$export .= WYSIWYG_conversion($post->member, false) . PHP_EOL;
		$export .= "</composed.member>" . PHP_EOL;
		$export .= "</member>" . PHP_EOL . PHP_EOL;
	}

	#AWARD
	if(!empty($post->awards)) {
		$export .= "<award>" . PHP_EOL;
		$export .= "<composed.award>" . PHP_EOL;
		$export .= WYSIWYG_conversion($post->awards) . PHP_EOL;
		$export .= "</composed.award>" . PHP_EOL;
		$export .= "</award>" . PHP_EOL . PHP_EOL;
	}

	#POLITICS
	if(!empty($post->politics)) {
		$export .= "<politics>" . PHP_EOL;
		$export .= $post->politics . PHP_EOL;
		$export .= "</politics>" . PHP_EOL;
	}		

	#RELIGION
	if(!empty($post->religion)) {
		$export .= "<religion>" . PHP_EOL;
		$export .= $post->religion . PHP_EOL;
		$export .= "</religion>" . PHP_EOL;
	}		

	#PERSONAL
	if(!empty($post->personal)) {
		$export .= "<composed.personal>" . PHP_EOL;
		$export .= WYSIWYG_conversion($post->personal, false) . PHP_EOL;
		$export .= "</composed.personal>" . PHP_EOL;
	}

	$export .= "</personal>" . PHP_EOL . PHP_EOL;
	#ADDRESSES
	foreach($post->addresses as $address) {
		$export .= '<address addresstype="' . strtolower($address->type) . '">' . PHP_EOL;
		if(!empty($address->address_text)) {
			$export .= "<geo>" . PHP_EOL;
			$export .= "<geoother>" . $address->address_text . "</geoother>" . PHP_EOL;
			$export .= "</geo>" . PHP_EOL;
		}
		$export .= "</address>" . PHP_EOL . PHP_EOL;
	}	

	#WORKS
	$export .= "<works>" . PHP_EOL;
	$export .= "<workgroup>" . PHP_EOL;
	$export .= '<grouptitle level="1">WRITINGS:</grouptitle>' . PHP_EOL;
	foreach($post->writings as $writing) {
		if($writing->id == "subhead") {
			$subhead_title = WYSIWYG_conversion($writing->title, false);
			$export .= '<grouptitle level="2">'. $subhead_title .'</grouptitle>' . PHP_EOL;
		} elseif($writing->id == "imported_subhead") {
			$subhead_title = WYSIWYG_conversion($writing->title, false);
			$export .= '<grouptitle level="2">'. $subhead_title .'</grouptitle>' . PHP_EOL;
		} elseif($writing->id == "imported") {
			$writingsText = WYSIWYG_conversion($writing->text, false);
			$writingsText = trim($writingsText);
			$export .= "<bibcitation>" . PHP_EOL;
			$export .= "<bibcit.composed>" . PHP_EOL;
			$export .= $writingsText . ', <pubdate><year year="' . $writing->year . '"></pubdate>.' ;
			$export .= "</bibcit.composed>" . PHP_EOL;
			$export .= "</bibcitation>" . PHP_EOL;	
		} else {
			$writing_role = WYSIWYG_conversion($writing->role, false);
			$writing_title = WYSIWYG_conversion($writing->title, false);
			$writing_publisher = WYSIWYG_conversion($writing->publisher, false);
			$writing_location = WYSIWYG_conversion($writing->location, false);
			$export .= "<bibcitation>" . PHP_EOL;
			$export .= "<bibcit.composed>" . PHP_EOL;
			if(!empty($writing_role)) {
				$export .= "(" . $writing_role . ")" ;
			}
			if(!empty($writing->reprints)){
				$reprint_text = "";
				foreach($writing->reprints as $reprint){
					if(!empty($reprint->title)){
						$reprint_title = WYSIWYG_conversion($reprint->title, false);
						$reprint_publisher = WYSIWYG_conversion($reprint->publisher, false);
						$reprint_location = WYSIWYG_conversion($reprint->location, false);
						$reprint_text .= ', published as <title><emphasis n="1">' . $reprint_title . ',</emphasis></title> ' . $reprint_publisher . ' (' . $reprint_location . '), <pubdate><year year="' . $reprint->year . '"></pubdate>';
					} else {
						$reprint_publisher = WYSIWYG_conversion($reprint->publisher, false);
						$reprint_location = WYSIWYG_conversion($reprint->location, false);
						$reprint_text .= ', reprinted, ' . $reprint_publisher . ' (' . $reprint_location . '), <pubdate><year year="' . $reprint->year . '"></pubdate>';
					}
				}				
				$export .= '<title><emphasis n="1">' . $writing_title . ',</emphasis></title> ' . $writing_publisher . ' (' . $writing_location . '), <pubdate><year year="' . $writing->year . '"></pubdate>' . $reprint_text ;
			} else {
				$export .= '<title><emphasis n="1">' . $writing_title . ',</emphasis></title> ' . $writing_publisher . ' (' . $writing_location . '), <pubdate><year year="' . $writing->year . '"></pubdate>.' ;
			}
			$export .= "</bibcit.composed>" . PHP_EOL;
			$export .= "</bibcitation>" . PHP_EOL;	
		}
	}
	if(!empty($post->secondary_writings)) {
		$export .= WYSIWYG_conversion($post->secondary_writings) . PHP_EOL;
	}	
	$export .= "</workgroup>" . PHP_EOL;
	$export .= "</works>" . PHP_EOL . PHP_EOL;

	$export .= '<narrative type="sidelights">' . PHP_EOL; 
	$export .= WYSIWYG_conversion($post->narrative, true, true) . PHP_EOL;
	$export .= "</narrative>" . PHP_EOL;

	$export .= "</bio.body>" . PHP_EOL;

	$export .= "<bio.foot>" . PHP_EOL;
	$export .= "<readinggroup>" . PHP_EOL;
	//organize biocrits
	sortBiocrit($post);
	$export .= '<grouptitle level="1">BIOGRAPHICAL AND CRITICAL SOURCES:</grouptitle>' . PHP_EOL;
	if(!empty($post->biocrit_books)) {
		$export .= '<grouptitle level="2">BOOKS</grouptitle>' . PHP_EOL;
		foreach($post->biocrit_books as $book) {
			$export .= "<bibcitation>" . PHP_EOL;
			$export .= "<bibcit.composed>" . PHP_EOL;
			$citation = WYSIWYG_conversion($book->entry_text, false, false);
			//strip existing asterisks
			$export .= str_replace("*", "", $citation);
			//add asterisk to last entry
			if($book->last === true){
				$export .= " * </bibcit.composed>" . PHP_EOL;
			} else {
				$export .= "</bibcit.composed>" . PHP_EOL;
			}
			$export .= "</bibcitation>" . PHP_EOL;
		}
	}			
	if(is_array($post->biocrit_periodicals) || is_object($post->biocrit_periodicals)) {
		$export .= '<grouptitle level="2">PERIODICALS</grouptitle>' . PHP_EOL;
		foreach($post->biocrit_periodicals as $periodical) {
			$export .= "<bibcitation>" . PHP_EOL;
			$export .= "<bibcit.composed>" . PHP_EOL;
			$citation = WYSIWYG_conversion($periodical->entry_text, false, false);
			//strip existing asterisks
			$export .= str_replace("*", "", $citation);
			//add asterisk to last entry
			if($periodical->last === true){
				$export .= " * </bibcit.composed>" . PHP_EOL;
			} else {
				$export .= "</bibcit.composed>" . PHP_EOL;
			}
			$export .= "</bibcitation>" . PHP_EOL;
		}
	}	
	if(is_array($post->biocrit_online) || is_object($post->biocrit_online)) {
		$export .= '<grouptitle level="2">ONLINE</grouptitle>' . PHP_EOL;
		foreach($post->biocrit_online as $online) {
			$export .= "<bibcitation>" . PHP_EOL;
			$export .= "<bibcit.composed>" . PHP_EOL;
			$citation = WYSIWYG_conversion($online->entry_text, false, false);
			//strip existing asterisks
			$export .= str_replace("*", "", $citation);
			//add asterisk to last entry
			if($online->last === true){
				$export .= "*</bibcit.composed>" . PHP_EOL;
			} else {
				$export .= "</bibcit.composed>" . PHP_EOL;
			}
			$export .= "</bibcitation>" . PHP_EOL;
		}
	}	
	if(is_array($post->biocrit_obits) || is_object($post->biocrit_obits)) {
		$export .= '<grouptitle level="2">OBITUARIES</grouptitle>' . PHP_EOL;
		foreach($post->biocrit_obits as $obit) {
			$export .= "<bibcitation>" . PHP_EOL;
			$export .= "<bibcit.composed>" . PHP_EOL;
			$citation = WYSIWYG_conversion($obit->entry_text, false, false);
			//strip existing asterisks
			$export .= str_replace("*", "", $citation);
			//add asterisk to last entry
			if($obit->last === true){
				$export .= "*</bibcit.composed>" . PHP_EOL;
			} else {
				$export .= "</bibcit.composed>" . PHP_EOL;
			}
			$export .= "</bibcitation>" . PHP_EOL;
		}
	}					
	$export .= "</readinggroup>" . PHP_EOL;
	$export .= "</bio.foot>" . PHP_EOL;
	$export .= "</biography>" . PHP_EOL;

	//$export = utf8_encode($export);
	//strip weird empty space characters
	$export = str_replace('Â', ' ', $export); //Â
	$export = str_replace(chr(194), ' ', $export); //Â
	setlocale(LC_CTYPE, 'cs_CZ');
	$export = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE',$export);
	//$export = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $export);
	//$export = utf8_encode($export);

	return $export;
}

function sortBiocrit($post) {
	//mark last entry in list 
	$i = 0;
	$biocritList = $post->biocritEntries;
	$len = count($biocritList);
	foreach ($biocritList as $item) {
		if ($i == $len - 1) {
			$item->last = true;
		}
		$i++;
	}
	//sort entries into lists by type
	foreach($biocritList as $entry) {
		switch ($entry->entry_type) {
			case "b":
				array_push($post->biocrit_books, $entry);
				break;
			case "p":
				array_push($post->biocrit_periodicals, $entry);
				break;
			case "o":
				array_push($post->biocrit_online, $entry);
				break;
			case "ob":
				array_push($post->biocrit_obits, $entry);
				break;				
		}
	}
}


function format_WYSIWYG_tags($text) {
	$text = str_replace('<p>', '<para>', $text);
	$text = str_replace('</p>', '</para>' . PHP_EOL, $text);
	$text = str_replace('<strong>', '<title>', $text);
	$text = str_replace('</strong>', '</title>', $text);
	$text = str_replace('<em>', '<emphasis n="1">', $text);
	$text = str_replace('</em>', '</emphasis>', $text);

	return $text;
}

function WYSIWYG_conversion($text, $includePara = true, $includeTitle = true) {
	//convert all non-quotation special characters to codes
	$text = htmlentities($text);

	//strip para tags from sections where they aren't required
	if($includePara == false) {
		//strip <p> tags
		$text = str_replace('&lt;p&gt;', '', $text);
		//$text = str_replace('&lt;/p&gt;', '' . PHP_EOL, $text);
		$text = str_replace('&lt;/p&gt;', '', $text);		
	} else {
		//convert <p> tags to <para>
		$text = str_replace('&lt;p&gt;', '<para>', $text);
		//$text = str_replace('&lt;/p&gt;', '</para>' . PHP_EOL, $text);	
		$text = str_replace('&lt;/p&gt;', '</para>', $text);	
	}

	if($includeTitle == false) {
		//strip <strong> and <b> tags
		$text = str_replace('&lt;strong&gt;', '', $text);
		$text = str_replace('&lt;/strong&gt;', '', $text);	
		$text = str_replace('&lt;b&gt;', '', $text);
		$text = str_replace('&lt;/b&gt;', '', $text);			
	}
	//move emphasis tags inside title tags
	$text = str_replace('&lt;em&gt;&lt;strong&gt;', '<title><emphasis n="1">', $text);
	$text = str_replace('&lt;/strong&gt;&lt;/em&gt;', '</emphasis></title>', $text);
	$text = str_replace('&lt;em&gt;&lt;b&gt;', '<title><emphasis n="1">', $text);
	$text = str_replace('&lt;/b&gt;&lt;/em&gt;', '</emphasis></title>', $text);
	//move emphasis tags inside code tags
	$text = str_replace('&lt;em&gt;&lt;code&gt;', '<head n="5"><emphasis n="1">', $text);
	$text = str_replace('&lt;/code&gt;&lt;/em&gt;', '</emphasis></head>', $text);

	//convert <strong> tags to <title>
	$text = str_replace('&lt;strong&gt;', '<title>', $text);
	$text = str_replace('&lt;/strong&gt;', '</title>', $text);
	//convert <b> tags to <title>
	$text = str_replace('&lt;b&gt;', '<title>', $text);
	$text = str_replace('&lt;/b&gt;', '</title>', $text);	
	//convert <em> tags to <emphasis>
	$text = str_replace('&lt;em&gt;', '<emphasis n="1">', $text);
	$text = str_replace('&lt;/em&gt;', '</emphasis>', $text);
	//convert <i> tags to <emphasis>
	$text = str_replace('&lt;i&gt;', '<emphasis n="1">', $text);
	$text = str_replace('&lt;/i&gt;', '</emphasis>', $text);
	//convert <code> tags to <head>
	$text = str_replace('&lt;code&gt;', '<head n="5">', $text);
	$text = str_replace('&lt;/code&gt;', '</head>', $text);	

	//move adjacent punctuation inside tags
	$text = str_replace('</emphasis></title>.', '.</emphasis></title>', $text);
	$text = str_replace('</emphasis></title>,', ',</emphasis></title>', $text);
	$text = str_replace('</emphasis></title>;', ';</emphasis></title>', $text);

	$text = str_replace('</emphasis>.', '.</emphasis>', $text);
	$text = str_replace('</emphasis>,', ',</emphasis>', $text);
	$text = str_replace('</emphasis>;', ';</emphasis>', $text);

	$text = html_entity_decode($text);
	$text = convert_wyswig_punctuation($text);

	//included this to convert media tags but they aren't needed i guess

	// if($sidelights == true) {
	// 	//converting media tags back to angle brackets
    // 		$text = preg_replace_callback('/(<para>)(.*?)(<\/para>)/s', function($matches) {
	// 			$new_text = $matches[0];
	// 			$inner_text = $matches[2];
	// 			if(preg_match('/\[media\]/', $inner_text)) {
    //     			$new_text = str_replace("[", "<", $new_text);
    //     			$new_text = str_replace("]", ">" . PHP_EOL, $new_text);	
	// 				$new_text = str_replace("©", "&copy;", $new_text); // ©				
	// 			} else {
	// 				$new_text = convert_wyswig_punctuation($new_text);
	// 			}        
    //     	return $new_text;
    // 	}, $text); 	
	// } else {
	// 	$text = convert_wyswig_punctuation($text);
	// }	

	// //$text = html_entity_decode($text);

	return $text;
}

function convert_wyswig_punctuation($text) {  
	$text = wptexturize($text);

	//remove spaces between tags
	$text = str_replace('> <', '><', $text);

	//remove para tags around subheads
	$text = str_replace('<para><head n="5">', '<head n="5">', $text);
	$text = str_replace('</head></para>', '</head>', $text);
	//double quotes
	$text = str_replace('&#8220;', '&ldquo;', $text);
	$text = str_replace('“', '&ldquo;', $text);
	$text = str_replace('&#8221;', '&rdquo;', $text);
	$text = str_replace('”', '&rdquo;', $text);	
	//ellipses
	$text = str_replace('&#8230;', '&hellip;', $text);
	$text = str_replace('…', '&hellip;', $text);
	$text = str_replace('...', '&hellip;', $text);
	$text = str_replace('. . .', '&hellip;', $text);
	//apostrophe
	// $text = str_replace('&#039;', '&apos;', $text);	
	// $text = str_replace('’', '&apos;', $text);	//‘
	// $text = str_replace('‘', '&apos;', $text);
	// $text = str_replace("'", '&apos;', $text);
	//preg_replace("/\S(')\S/", "&apos;", $text);
	//single quotes
	$text = str_replace('&#8216;', '&lsquo;', $text);

	//trying to handle all instances of apostrophes/single quotes here
    $text = preg_replace_callback("/\S(&#8217;)\S/s", function($matches) {
        $new1 = $matches[0];
		$new_text = str_replace("&#8217;", "&apos;", $new1);
        return $new_text;
    }, $text);
    $text = preg_replace_callback("/\s(&#8217;)/s", function($matches) {
        $new1 = $matches[0];
		$new_text = str_replace("&#8217;", "&lsquo;", $new1);
        return $new_text;
    }, $text);	
    $text = preg_replace_callback("/\s(&#8216;)/s", function($matches) {
        $new1 = $matches[0];
		$new_text = str_replace("&#8216;", "&lsquo;", $new1);
        return $new_text;
    }, $text);
    $text = preg_replace_callback("/\s(‘)/s", function($matches) {
        $new1 = $matches[0];
		$new_text = str_replace("‘", "&lsquo;", $new1);
        return $new_text;
    }, $text);				
    $text = preg_replace_callback("/(&#8217;)\s/s", function($matches) {
        $new1 = $matches[0];
		$new_text = str_replace("&#8217;", "&rsquo;", $new1);
        return $new_text;
    }, $text);	
    $text = preg_replace_callback("/(’)\s/s", function($matches) {
        $new1 = $matches[0];
		$new_text = str_replace("’", "&rsquo;", $new1);
        return $new_text;
    }, $text);

	$text = str_replace("’", '&apos;', $text);		

	//$text = str_replace('&#8216;', '&lsquo;', $text);
	// //$text = str_replace(" &apos;", ' &lsquo;', $text);
	// $text = str_replace(" '", ' &lsquo;', $text);
	// $text = str_replace(" ‘", ' &lsquo;', $text);
	// $text = str_replace('&#8217;', '&rsquo;', $text);
	// //$text = str_replace("&apos; ", '&rsquo; ', $text);
	// $text = str_replace("' ", '&rsquo; ', $text);
	// $text = str_replace("‘ ", '&rsquo; ', $text);

	//remove non-breaking spaces
	$text = str_replace('&nbsp;', '', $text);
	//remove empty tags
	$text = str_replace('<para></para>', '', $text);
	$text = str_replace('<head n="5"></head>', '', $text);
	$text = str_replace('<title></title>', '', $text);
	$text = str_replace('<emphasis n="1"></emphasis>', '', $text);
	//replace asterisk dividers
	$text = str_replace("***", '<para type="asterisk">&ast;</para>', $text); 

	//ampersand
	$text = str_replace("&#038;", "&amp;", $text); // & 
	$text = str_replace("&#38;", "&amp;", $text); // & 
	$text = str_replace(" & ", "&amp;", $text); // &   
	//mdash
	$text = str_replace('&#8211;', '&mdash;', $text);
	$text = str_replace('—', '&mdash;', $text);
	//square brackets
	$text = str_replace('&#91;', '&lsqb;', $text);	
	$text = str_replace('[', '&lsqb;', $text);	
	$text = str_replace('&#93;', '&rsqb;', $text);	
	$text = str_replace(']', '&rsqb;', $text);

    $text = str_replace(" # ", "&num;", $text); // #
    //$text = str_replace("&amp;", "&#x26;", $text); // &               
    $text = str_replace("+", "&plus;", $text); //+
    $text = str_replace("$", "&dollar;", $text); // $
	$text = str_replace("©", "&copy;", $text); // ©

	$text = str_replace("ä", "&auml;", $text); // ä
	$text = str_replace("ä", "&auml;", $text); // ä
    $text = str_replace("Ä", "&Auml;", $text); // Ä
    $text = str_replace("ë", "&euml;", $text); // ë
    $text = str_replace("Ë", "&Euml;", $text); // Ë        
    $text = str_replace("ö", "&ouml;", $text); // ö
    $text = str_replace("Ö", "&Ouml;", $text); // Ö

 	$text = str_replace("á", "&aacute;", $text); // á         
    $text = str_replace("Á", "&Aacute;", $text); // Á 
	$text = str_replace("é", "&eacute;", $text); // é	             
    $text = str_replace("É", "&Eacute;", $text); // É 
    $text = str_replace("í", "&iacute;", $text); // í     
	$text = str_replace("Í", "&Iacute;", $text); // Í        
    $text = str_replace("ó", "&oacute;", $text); // ó    
	$text = str_replace("Ó", "&Oacute;", $text); // Ó 
    $text = str_replace("ú", "&uacute;", $text); //ú      
	$text = str_replace("Ú", "&Uacute;", $text); // Ú      

    $text = str_replace("À", "&Agrave;", $text); // À
    $text = str_replace("à", "&agrave;", $text); // à
    $text = str_replace("È", "&Egrave;", $text); // È               
    $text = str_replace("è", "&egrave;", $text); // è
    $text = str_replace("Ì", "&Igrave;", $text); // Ì              
    $text = str_replace("ì", "&igrave;", $text); // ì        
    $text = str_replace("Ò", "&Ograve;", $text); // Ò               
    $text = str_replace("ò", "&ograve;", $text); // ò
    $text = str_replace("Ù", "&Ugrave;", $text); // Ù               
    $text = str_replace("ù", "&ugrave;", $text); // ù  

    $text = str_replace("ç", "&ccedil;", $text); // ç    
    $text = str_replace("Ç", "&Ccedil;", $text); // Ç        

    $text = str_replace("ï", "&iuml;", $text); // ï
    $text = str_replace("Ï", "&Iuml;", $text); // Ï                   
	$text = str_replace("ü", "&uuml;", $text); //ü
	$text = str_replace("ü", "&uuml;", $text); //ü
    $text = str_replace("Ü", "&Uuml;", $text); // Ü   
        
    $text = str_replace("Ã", "&Atilde;", $text); // Ã
    $text = str_replace("ã", "&atilde;", $text); // ã
    $text = str_replace("Ñ", "&Ntilde;", $text); // Ñ
    $text = str_replace("ñ", "&ntilde;", $text); // ñ
    $text = str_replace("Õ", "&Otilde;", $text); // Õ
    $text = str_replace("õ", "&otilde;", $text); // õ    

    $text = str_replace("š", "&scaron;", $text); // š		
    $text = str_replace("Š", "&Scaron;", $text); // Š		
    $text = str_replace("č", "&ccaron;", $text); // č		
	$text = str_replace("Č", "&Ccaron;", $text); // Č		
	$text = str_replace("ć", "&cacute;", $text); // ć				
	$text = str_replace("ø", "&oslash;", $text); // ø		
	$text = str_replace("æ", "&aelig;", $text); // æ		
	$text = str_replace("å", "&aring;", $text); // å		
	$text = str_replace("¡", "&iexcl;", $text); // ¡ 	

	$text = str_replace("ḥ", "&hunddot;", $text);
	$text = str_replace("ḥ", "&hunddot;", $text); // ḥ ḥ
	$text = str_replace("ṣ", "&sunddot;", $text); // ṣ
	$text = str_replace("ṭ", "&tunddot;", $text); // ṭ 
	$text = str_replace("ā", "&amacr;", $text); // ā
	$text = str_replace("ā", "&amacr;", $text); // ā	
	$text = str_replace("ī", "&imacr;", $text); // ī
	$text = str_replace("ī", "&imacr;", $text); // ī
	$text = str_replace("ū", "&umacr;", $text); // ū
	$text = str_replace("ū", "&umacr;", $text); // ū	
	$text = str_replace("ʹ", "&prime;", $text); // ʹ
	$text = str_replace("ʻ", "&prime;", $text); // ʹ

	return $text;
}

class Citation {
	public $periodicalTitle;
	public $reviewTitle;
	public $authorName;
	public $pubTitle;
	public $pubDay;
	public $pubMonth;
	public $pubYear;
	public $pageNumber;
	public $titleStartPos;
	public $titleEndPos;
	public $fieldsStartPos;
	public $fieldsCutoffPos;
	public $fieldsEndPos;
	public $includesAuthor;
	public $explodedData;
}

function sata_export_wp($author='', $category='', $post_type='', $status='', $start_date='', $end_date='', $terms = '') {
	global $wpdb, $post_ids, $post;

	define('WXR_VERSION', '1.0');

	do_action('export_wp');

	if(strlen($start_date) > 4 && strlen($end_date) > 4) {
		$filename = 'wordpress.' . $start_date . '.' . $end_date . '.xml';
	} else {
		$filename = 'wordpress.' . date('Y-m-d') . '.xml';
	}
	header('Content-Description: File Transfer');
	header("Content-Disposition: attachment; filename=$filename");
	header('Content-Type: text/xml; charset=' . get_option('blog_charset'), true);

	if ( $post_type and $post_type != 'all' ) {
		$where = $wpdb->prepare("WHERE post_type = %s ", $post_type);
	} else {
		$where = "WHERE post_type != 'revision' ";
	}
	if ( $author and $author != 'all' ) {
		$author_id = (int) $author;
		$where .= $wpdb->prepare("AND post_author = %d ", $author_id);
	}
	if ( $start_date and $start_date != 'all' ) {
		$where .= $wpdb->prepare("AND post_date >= %s ", $start_date);
	}
	if ( $end_date and $end_date != 'all' ) {
		$where .= $wpdb->prepare("AND post_date < %s ", $end_date);
	}
	if ( $category and $category != 'all' and version_compare($wpdb->db_version(), '4.1', 'ge')) {
		$taxomony_id = (int) $category;
		$where .= $wpdb->prepare("AND ID IN (SELECT object_id FROM {$wpdb->term_relationships} " .
			"WHERE term_taxonomy_id = %d) ", $taxomony_id);
	}
	if ( $status and $status != 'all' ) {
		$where .= $wpdb->prepare("AND post_status = %s ", $status);
	}

	// grab a snapshot of post IDs, just in case it changes during the export
	$post_ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts $where ORDER BY post_date_gmt ASC");

	$categories = (array) get_categories('get=all');
	$tags = (array) get_tags('get=all');

	while ( $parents = wxr_missing_parents($categories) ) {
		$found_parents = get_categories("include=" . join(', ', $parents));
		if ( is_array($found_parents) && count($found_parents) )
			$categories = array_merge($categories, $found_parents);
		else
			break;
	}

	// Put them in order to be inserted with no child going before its parent
	$pass = 0;
	$passes = 1000 + count($categories);
	while ( ( $cat = array_shift($categories) ) && ++$pass < $passes ) {
		if ( $cat->parent == 0 || isset($cats[$cat->parent]) ) {
			$cats[$cat->term_id] = $cat;
		} else {
			$categories[] = $cat;
		}
	}
	unset($categories);

	echo '<?xml version="1.0" encoding="' . get_bloginfo('charset') . '"?' . ">\n";
?>
<!-- This is a WordPress eXtended RSS file generated by WordPress as an export of your blog. -->
<!-- It contains information about your blog's posts, comments, and categories. -->
<!-- You may use this file to transfer that content from one site to another. -->
<!-- This file is not intended to serve as a complete backup of your blog. -->

<!-- To import this information into a WordPress blog follow these steps. -->
<!-- 1. Log into that blog as an administrator. -->
<!-- 2. Go to Tools: Import in the blog's admin panels (or Manage: Import in older versions of WordPress). -->
<!-- 3. Choose "WordPress" from the list. -->
<!-- 4. Upload this file using the form provided on that page. -->
<!-- 5. You will first be asked to map the authors in this export file to users -->
<!--    on the blog.  For each author, you may choose to map to an -->
<!--    existing user on the blog or to create a new user -->
<!-- 6. WordPress will then import each of the posts, comments, and categories -->
<!--    contained in this file into your blog -->

<?php the_generator('export');?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/<?php echo WXR_VERSION; ?>/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:wp="http://wordpress.org/export/<?php echo WXR_VERSION; ?>/"
>

<channel>
	<title><?php bloginfo_rss('name'); ?></title>
	<link><?php bloginfo_rss('url') ?></link>
	<description><?php bloginfo_rss("description") ?></description>
	<pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_lastpostmodified('GMT'), false); ?></pubDate>
	<generator>http://wordpress.org/?v=<?php bloginfo_rss('version'); ?></generator>
	<language><?php echo get_option('rss_language'); ?></language>
	<wp:wxr_version><?php echo WXR_VERSION; ?></wp:wxr_version>
	<wp:base_site_url><?php echo wxr_site_url(); ?></wp:base_site_url>
	<wp:base_blog_url><?php bloginfo_rss('url'); ?></wp:base_blog_url>
<?php if ( $cats && ($terms == 'all' || $terms == 'cats')) : foreach ( $cats as $c ) : ?>
	<wp:category><wp:category_nicename><?php echo $c->slug; ?></wp:category_nicename><wp:category_parent><?php echo $c->parent ? $cats[$c->parent]->name : ''; ?></wp:category_parent><?php wxr_cat_name($c); ?><?php wxr_category_description($c); ?></wp:category>
<?php endforeach; endif; ?>
<?php if ( $tags && ($terms == 'all' || $terms == 'tags')) : foreach ( $tags as $t ) : ?>
	<wp:tag><wp:tag_slug><?php echo $t->slug; ?></wp:tag_slug><?php wxr_tag_name($t); ?><?php wxr_tag_description($t); ?></wp:tag>
<?php endforeach; endif; ?>
	<?php do_action('rss2_head'); ?>
	<?php if ($post_ids) {
		global $wp_query;
		$wp_query->in_the_loop = true;  // Fake being in the loop.
		// fetch 20 posts at a time rather than loading the entire table into memory
		while ( $next_posts = array_splice($post_ids, 0, 20) ) {
			$where = "WHERE ID IN (".join(',', $next_posts).")";
			$posts = $wpdb->get_results("SELECT * FROM $wpdb->posts $where ORDER BY post_date_gmt ASC");
				foreach ($posts as $post) {
					setup_postdata($post); ?>
<item>
<title><?php echo apply_filters('the_title_rss', $post->post_title); ?></title>
<link><?php the_permalink_rss() ?></link>
<pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_post_time('Y-m-d H:i:s', true), false); ?></pubDate>
<dc:creator><?php echo wxr_cdata(get_the_author()); ?></dc:creator>
<?php wxr_post_taxonomy() ?>

<guid isPermaLink="false"><?php the_guid(); ?></guid>
<description></description>
<content:encoded><?php echo wxr_cdata( apply_filters('the_content_export', $post->post_content) ); ?></content:encoded>
<excerpt:encoded><?php echo wxr_cdata( apply_filters('the_excerpt_export', $post->post_excerpt) ); ?></excerpt:encoded>
<wp:post_id><?php echo $post->ID; ?></wp:post_id>
<wp:post_date><?php echo $post->post_date; ?></wp:post_date>
<wp:post_date_gmt><?php echo $post->post_date_gmt; ?></wp:post_date_gmt>
<wp:comment_status><?php echo $post->comment_status; ?></wp:comment_status>
<wp:ping_status><?php echo $post->ping_status; ?></wp:ping_status>
<wp:post_name><?php echo $post->post_name; ?></wp:post_name>
<wp:status><?php echo $post->post_status; ?></wp:status>
<wp:post_parent><?php echo $post->post_parent; ?></wp:post_parent>
<wp:menu_order><?php echo $post->menu_order; ?></wp:menu_order>
<wp:post_type><?php echo $post->post_type; ?></wp:post_type>
<wp:post_password><?php echo $post->post_password; ?></wp:post_password>
<?php
if ($post->post_type == 'attachment') { ?>
<wp:attachment_url><?php echo wp_get_attachment_url($post->ID); ?></wp:attachment_url>
<?php } ?>
<?php
$postmeta = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $wpdb->postmeta WHERE post_id = %d", $post->ID) );
if ( $postmeta ) {
?>
<?php foreach( $postmeta as $meta ) { ?>
<wp:postmeta>
<wp:meta_key><?php echo $meta->meta_key; ?></wp:meta_key>
<wp:meta_value><?Php echo $meta->meta_value; ?></wp:meta_value>
</wp:postmeta>
<?php } ?>
<?php } ?>
<?php
$comments = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $wpdb->comments WHERE comment_post_ID = %d", $post->ID) );
if ( $comments ) { foreach ( $comments as $c ) { ?>
<wp:comment>
<wp:comment_id><?php echo $c->comment_ID; ?></wp:comment_id>
<wp:comment_author><?php echo wxr_cdata($c->comment_author); ?></wp:comment_author>
<wp:comment_author_email><?php echo $c->comment_author_email; ?></wp:comment_author_email>
<wp:comment_author_url><?php echo $c->comment_author_url; ?></wp:comment_author_url>
<wp:comment_author_IP><?php echo $c->comment_author_IP; ?></wp:comment_author_IP>
<wp:comment_date><?php echo $c->comment_date; ?></wp:comment_date>
<wp:comment_date_gmt><?php echo $c->comment_date_gmt; ?></wp:comment_date_gmt>
<wp:comment_content><?php echo wxr_cdata($c->comment_content) ?></wp:comment_content>
<wp:comment_approved><?php echo $c->comment_approved; ?></wp:comment_approved>
<wp:comment_type><?php echo $c->comment_type; ?></wp:comment_type>
<wp:comment_parent><?php echo $c->comment_parent; ?></wp:comment_parent>
<wp:comment_user_id><?php echo $c->user_id; ?></wp:comment_user_id>
</wp:comment>
<?php } } ?>
	</item>
<?php } } } ?>
</channel>
</rss>
<?php
}

function sata_export_page() {
	global $wpdb, $wp_locale; 

	if ( ! current_user_can( 'edit_files' ) )
		die( 'You don\'t have permissions to use this page.' );

	load_plugin_textdomain( 'sata-export', false, '/sata-volume-export/languages/' );

	$months = "";
	for ( $i = 1; $i < 13; $i++ ) {
		$months .= "\t\t\t<option value=\"" . zeroise($i, 2) . '">' . 
			$wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) ) . "</option>\n";
	} ?>
<div class="wrap">
<?php screen_icon(); ?>
<h2><?php esc_html_e( 'SATA Volume Export', 'sata-export' ); ?></h2>

<p><?php esc_html_e('Select the volume to export from the Category dropdown below or filter by other parameters as needed.'); ?></p>
<p><?php esc_html_e('Clicking "Download Export File" will create a zip file containing the selected entries in txt files.'); ?></p>
<form action="" method="get">
<input type="hidden" name="page" value="sata_export" />
<h3><?php esc_html_e('Options', 'ca-export' ); ?></h3>

<table class="form-table">
<?php if(version_compare($wpdb->db_version(), '4.1', 'ge')) { ?>
<tr>
<th><label for="category"><?php esc_html_e('Select Volume', 'sata-export' ); ?></label></th>
<td>
<select name="category" id="category">
<option value="all" selected="selected"><?php esc_html_e('All Categories', 'sata-export' ); ?></option>
<?php
$categories = (array) get_categories('get=all');
if($categories) {
	foreach ( $categories as $cat ) {
		echo "<option value='{$cat->term_taxonomy_id}'>{$cat->name}</option>\n";
	}
}
?>
</select>
</td>
</tr>
<?php } ?>
<tr>
<th><label for="mm_start"><?php esc_html_e('Filter by Date', 'ca-export' ); ?></label></th>
<td><strong><?php esc_html_e('Start:', 'ca-export' ); ?></strong> <?php esc_html_e('Month', 'ca-export' ); ?>&nbsp;
<select name="mm_start" id="mm_start">
<option value="all" selected="selected"><?php esc_html_e('All Dates', 'ca-export' ); ?></option>
<?php echo $months; ?>
</select>&nbsp;<?php esc_html_e('Year', 'ca-export' ); ?>&nbsp;
<input type="text" id="aa_start" name="aa_start" value="" size="4" maxlength="5" />
</td>
<td><strong><?php esc_html_e('End:', 'ca-export' ); ?></strong> <?php esc_html_e('Month', 'ca-export' ); ?>&nbsp;
<select name="mm_end" id="mm_end">
<option value="all" selected="selected"><?php esc_html_e('All Dates', 'ca-export' ); ?></option>
<?php echo $months; ?>
</select>&nbsp;<?php esc_html_e('Year', 'ca-export' ); ?>&nbsp;
<input type="text" id="aa_end" name="aa_end" value="" size="4" maxlength="5" />
</td>
</tr>
<tr>
<th><label for="author"><?php esc_html_e('Filter by Author', 'ca-export' ); ?></label></th>
<td>
<select name="author" id="author">
<option value="all" selected="selected"><?php esc_html_e('All Authors', 'ca-export' ); ?></option>
<?php
$authors = $wpdb->get_col( "SELECT post_author FROM $wpdb->posts GROUP BY post_author" );
foreach ( $authors as $id ) {
	$o = get_userdata( $id );
	echo "<option value='{$o->ID}'>{$o->display_name}</option>\n";
}
?>
</select>
</td>
</tr>
<!--<tr>
<th><label for="post_type"><?php  esc_html_e('Restrict Content', 'ca-export' ); ?></label></th>
<td>
<select name="post_type" id="post_type">
<option value="all" selected="selected"><?php esc_html_e('All Content', 'ca-export' ); ?></option>
<option value="page"><?php esc_html_e('Pages', 'ca-export' ); ?></option>
<option value="post"><?php esc_html_e('Posts', 'ca-export' ); ?></option>
</select>
</td>
</tr>-->
<tr>
<th><label for="status"><?php esc_html_e('Filter by Status', 'ca-export' ); ?></label></th>
<td>
<select name="status" id="status">
<option value="all" selected="selected"><?php esc_html_e('All Statuses', 'ca-export' ); ?></option>
<option value="draft"><?php esc_html_e('Draft', 'ca-export' ); ?></option>
<option value="private"><?php esc_html_e('Privately published', 'ca-export' ); ?></option>
<option value="publish"><?php esc_html_e('Published', 'ca-export' ); ?></option>
<option value="future"><?php esc_html_e('Scheduled', 'ca-export' ); ?></option>
</select>
</td>
</tr>
<!--<tr>
<th><label for="terms"><?php esc_html_e('Include Blog Tag/Category Terms', 'ca-export' ); ?></label></th>
<td>
<select name="terms" id="terms">
<option value="all" selected="selected"><?php esc_html_e('All Terms', 'ca-export' ); ?></option>
<option value="cats"><?php esc_html_e('Categories', 'ca-export' ); ?></option>
<option value="tags"><?php esc_html_e('Tags', 'ca-export' ); ?></option>
<option value="none"><?php esc_html_e('None', 'ca-export' ); ?></option>
</select>
</td>
</tr>-->
</table>
<p class="submit"><input type="submit" name="submit" class="button" value="<?php esc_html_e('Download Export File', 'ca-export' ); ?>" />
<input type="hidden" name="download" value="true" />
</p>
</form>
</div>
<?php
}
function ca_add_export_page() {
   	add_management_page('SATA Volume Export', 'SATA Volume Export', 'manage_options', 'sata_export', 'sata_export_page');
}
add_action('admin_menu', 'ca_add_export_page');

function sata_export_setup() {
	if(!function_exists('wxr_missing_parents')) {
		function wxr_missing_parents($categories) {
			if ( !is_array($categories) || empty($categories) )
				return array();

			foreach ( $categories as $category )
				$parents[$category->term_id] = $category->parent;

			$parents = array_unique(array_diff($parents, array_keys($parents)));

			if ( $zero = array_search('0', $parents) )
				unset($parents[$zero]);

			return $parents;
		}
	}
	if(!function_exists('wxr_cdata')) {
		function wxr_cdata($str) {
			if ( seems_utf8($str) == false )
				$str = utf8_encode($str);

			// $str = ent2ncr(wp_specialchars($str));

			$str = "<![CDATA[$str" . ( ( substr($str, -1) == ']' ) ? ' ' : '') . "]]>";

			return $str;
		}
	}
	if(!function_exists('wxr_site_url')) {
		function wxr_site_url() {
			global $current_site;

			// mu: the base url
			if ( isset($current_site->domain) ) {
				return 'http://'.$current_site->domain.$current_site->path;
			}
			// wp: the blog url
			else {
				return get_bloginfo_rss('url');
			}
		}
	}
	if(!function_exists('wxr_cat_name')) {
		function wxr_cat_name($c) {
			if ( empty($c->name) )
				return;

			echo '<wp:cat_name>' . wxr_cdata($c->name) . '</wp:cat_name>';
		}
	}
	if(!function_exists('wxr_category_description')) {
		function wxr_category_description($c) {
			if ( empty($c->description) )
				return;

			echo '<wp:category_description>' . wxr_cdata($c->description) . '</wp:category_description>';
		}
	}
	if(!function_exists('wxr_tag_name')) {
		function wxr_tag_name($t) {
			if ( empty($t->name) )
				return;

			echo '<wp:tag_name>' . wxr_cdata($t->name) . '</wp:tag_name>';
		}
	}
	if(!function_exists('wxr_tag_description')) {
		function wxr_tag_description($t) {
			if ( empty($t->description) )
				return;

			echo '<wp:tag_description>' . wxr_cdata($t->description) . '</wp:tag_description>';
		}
	}
	if(!function_exists('wxr_post_taxonomy')) {
		function wxr_post_taxonomy() {
			$categories = get_the_category();
			$tags = get_the_tags();
			$the_list = '';
			$filter = 'rss';

			if ( !empty($categories) ) foreach ( (array) $categories as $category ) {
				$cat_name = sanitize_term_field('name', $category->name, $category->term_id, 'category', $filter);
				// for backwards compatibility
				$the_list .= "\n\t\t<category><![CDATA[$cat_name]]></category>\n";
				// forwards compatibility: use a unique identifier for each cat to avoid clashes
				// http://trac.wordpress.org/ticket/5447
				$the_list .= "\n\t\t<category domain=\"category\" nicename=\"{$category->slug}\"><![CDATA[$cat_name]]></category>\n";
			}

			if ( !empty($tags) ) foreach ( (array) $tags as $tag ) {
				$tag_name = sanitize_term_field('name', $tag->name, $tag->term_id, 'post_tag', $filter);
				$the_list .= "\n\t\t<category domain=\"tag\"><![CDATA[$tag_name]]></category>\n";
				// forwards compatibility as above
				$the_list .= "\n\t\t<category domain=\"tag\" nicename=\"{$tag->slug}\"><![CDATA[$tag_name]]></category>\n";
			}

			echo $the_list;
		}
	}
}
?>