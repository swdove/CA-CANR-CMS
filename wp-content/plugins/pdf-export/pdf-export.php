<?php
/*
Plugin Name: PDF Export
Plugin URI: https://github.com/swdove
Description: Exports selected posts as PDF files contained in a zip archive
Version: 1.0
Author: Sean Dove
Author URI: https://github.com/swdove
 
*/
/*
Developed for Schlager Group to export CA and CANR entries as custom PDF files. Base export plugin code borrowed and modified from "Advanced Export" plugin by Ron Rennick.
*/

require($_SERVER['DOCUMENT_ROOT'].'/wp-includes/pdf/fpdf.php');

if(!defined('ABSPATH')) {
	die("Don't call this file directly.");
}
if(isset($_GET['page']) && $_GET['page'] == 'pdf_export' && isset( $_GET['download'] ) ) {
	add_action('init', 'pdf_do_export');
}



class Post {
    public $id;
    public $atlasuid;
    public $galeData;
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
    public $biocritEntries;
    public $biocrit_books = Array();
    public $biocrit_periodicals = Array();
    public $biocrit_online = Array();
    public $biocritTest;

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
    public $reprints = Array();
    public $text;
}

class Reprint {
    public $title;
    public $publisher;
    public $location;
    public $year;
    public $text;
}
function pdf_do_export() {
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
		pdf_export_setup();
		//ra_export_wp( $author, $category, $post_type, $status, $start_date, $end_date, $terms );
		export_pdf( $author, $category, $post_type, $status, $start_date, $end_date, $terms );
		die();
	}
}	

function export_pdf ($author='', $category='', $post_type='', $status='', $start_date='', $end_date='', $terms = '') {
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
			pdf_parse_export_values($post);
			pdf_get_repeater_values($post);			
		}
		array_push($posts, $post);
	}

    $zipname = "PDF_" . date('y-m-d H:i:s') . '.zip';

    $zip = new ZipArchive;
    $zip->open($zipname, ZipArchive::CREATE);
    $i = 1;
	foreach ($posts as $post) {
		//$file_name = $i . ".pdf";
        $content = build_PDF_file($post);
        $filename = pdf_build_file_name($post);
        $zip->addFromString($filename . '.pdf', $content);
	}
	$zip->close();

	header('Content-Type: application/zip');
	header('Content-disposition: attachment; filename='.$zipname);
	header('Content-Length: ' . filesize($zipname));
	readfile($zipname);
}

function pdf_parse_export_values($post) {
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
			default:
				//exclude "field identifier" fields
				if(!(preg_match('/^_/', $field->name))) {				
				};
				break;
		}
	}
}

function pdf_get_repeater_values($post) {
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
			$entry = $citation['biocrit_book_entry'];
			array_push($post->biocrit_books, $entry);
		}
	}	
	$periodicals = get_field('biocrit_entries', $post->id);
	if($periodicals) {
		foreach($periodicals as $citation) {
			$entry = $citation['biocrit_entry'];
			array_push($post->biocrit_periodicals, $entry);
		}
	}
	$online = get_field('online_biocrit_entries', $post->id);
	if(is_array($online) || is_object($online)) {
		foreach($online as $citation) {
			$entry = $citation['online_biocrit_entry'];
			array_push($post->biocrit_online, $entry);
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
    	while ( have_rows('collected_writings', $post->id) ) : the_row();
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
        		// check if the nested repeater field has rows of data
        		if( have_rows('loc_reprinted_as') ):
			 		// loop through the rows of data
					$rpt = new Reprint();
			    	while ( have_rows('loc_reprinted_as') ) : the_row();
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
        		// check if the nested repeater field has rows of data
        		if( have_rows('misc_reprinted_as') ):
			 		// loop through the rows of data
					$rpt = new Reprint();
			    	while ( have_rows('misc_reprinted_as') ) : the_row();
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

function pdf_build_file_name($post) {
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
	return $filename;
}

function build_PDF_file($post) {
    $authName = $post->lastName . ", " . $post->firstName;
    $authPersonal = pdf_convert_wyswig_punctuation($post->personal);
    $authEducation = pdf_convert_wyswig_punctuation($post->education);    
    $authCareer = pdf_convert_wyswig_punctuation($post->workHistory);
    $authMilitary = pdf_convert_wyswig_punctuation($post->military);
    $authAvocations = pdf_convert_wyswig_punctuation($post->avocations);
    $authMember = pdf_convert_wyswig_punctuation($post->member);
    $authAwards = pdf_convert_wyswig_punctuation($post->awards);
    $authPolitics = $post->politics;
    $authReligion = $post->religion;
    $authSidelights = pdf_convert_wyswig_punctuation($post->narrative);

    #ARRAYS
    $authAddress = $post->addresses;
    $authWriting = $post->writings;
    $authBiocrit = array();

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial','B',16);
    $pdf->Cell(0,10,$authName,0,1);
    $pdf->Ln();
    #PERSONAL
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,10,'PERSONAL',0,1);
    $pdf->SetFont('Arial','',12);
    $pdf->WriteHTML($authPersonal, false);
    $pdf->Ln();
   // $pdf->MultiCell(0,5,$authPersonal);
    #EDUCATION
    if($post->education) {
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(0,10,'EDUCATION',0,1);
        $pdf->SetFont('Arial','',12);
        $pdf->WriteHTML($authEducation, false);
        $pdf->Ln();
    }
    #ADDRESSES
    if($post->addresses) {
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(0,10,'ADDRESS',0,1);
        $pdf->SetFont('Arial','',12);
        foreach ($post->addresses as $address) {
            $addr = "   * " . $address->type . " - " . $address->address_text;
            $pdf->WriteHTML($addr);
            $pdf->Ln();
        }
    }
    #CAREER
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,10,'CAREER',0,1);
    if($post->workHistory) {
        $pdf->SetFont('Arial','',12);
        $pdf->WriteHTML($authCareer, false);
        $pdf->Ln();
    }
    #MILITARY
    if($post->military) {
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(0,10,'MILITARY',0,1);
        $pdf->SetFont('Arial','',12);
        $pdf->WriteHTML($authMilitary, false);
        $pdf->Ln();
    } 
    #AVOCATIONS
    if($post->avocations) {
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(0,10,'AVOCATIONS',0,1);
        $pdf->SetFont('Arial','',12);
        $pdf->WriteHTML($authAvocations, false);
        $pdf->Ln();
    }     
    #MEMBER
    if($post->member) {
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(0,10,'MEMBER',0,1);
        $pdf->SetFont('Arial','',12);
        $pdf->WriteHTML($authMember, false);
        $pdf->Ln();
    }    
    #AWARDS
    if($post->awards) {
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(0,10,'AWARDS',0,1);
        $pdf->SetFont('Arial','',12);
        $pdf->WriteHTML($authAwards, false);
        $pdf->Ln();
    } 
    #POLITICS
    if($post->politics) {
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(0,10,'POLITICS',0,1);
        $pdf->SetFont('Arial','',12);
        $pdf->WriteHTML($authPolitics);
        $pdf->Ln();
    } 
    #RELIGION
    if($post->religion) {
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(0,10,'RELIGION',0,1);
        $pdf->SetFont('Arial','',12);
        $pdf->WriteHTML($authReligion);
        $pdf->Ln();
    }               
    #WRITINGS
    if($post->writings) {
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(0,10,'WRITINGS',0,1);
        $pdf->SetFont('Arial','',12);
        foreach ($post->writings as $writing) {
            if($writing->id == "subhead") {
                $wrt = pdf_convert_wyswig_punctuation($writing->title);
                $pdf->SetFont('Arial','B',12);
                $pdf->Cell(0,10,"    " . $wrt,0,1);
                //$pdf->Ln();              
            }
            elseif($writing->id == "imported_subhead") {
                $wrt = pdf_convert_wyswig_punctuation($writing->title);
                $pdf->SetFont('Arial','B',12);
                $pdf->Cell(0,10,"    " . $wrt,0,1);
                //$pdf->Ln();              
            }              
            elseif($writing->id == "misc") {
                $wrt = "   * ";
                if(!empty($writing->role)) {
                    $wrt .= $writing->role;
                }
                if($writing->type){
                    $wrt .= "<b><i>" . $writing->title . "</i></b> (" . $writing->type . "), ";
                } else {
                    $wrt .= "<b><i>" . $writing->title . "</i></b>, ";
                }
                $wrt .= $writing->publisher;
                if($writing->location) {
                    $wrt .= " (" . $writing->location . "), ";
                }
                $wrt .= $writing->year;
                $pdf->SetFont('Arial','',12);
                $pdf->WriteHTML($wrt);
                $pdf->Ln();
               // $pdf->Cell(0,10,"   * " . $wrt,0,1);
                //$pdf->Ln();                
            }
            elseif($writing->id == "loc") {
                $wrt = "   * ";
                if(!empty($writing->role)) {
                    $wrt .= $writing->role;
                }
                if($writing->type){
                    $wrt .= "<b><i>" . $writing->title . "</i></b> (" . $writing->type . "), ";
                } else {
                    $wrt .= "<b><i>" . $writing->title . "</i></b>, ";
                }
                $wrt .= $writing->publisher;
                if($writing->location) {
                    $wrt .= " (" . $writing->location . "), ";
                }
                $wrt .= $writing->year;
                $pdf->SetFont('Arial','',12);
                $pdf->WriteHTML($wrt);
                $pdf->Ln();
              //  $pdf->Cell(0,10,"   * " . $wrt,0,1);
               // $pdf->Ln();                
            }
            elseif($writing->id == "imported") {
                $wrt = "   * ";
                $wrt_text = trim($writing->text) . ", " . $writing->year;
                $wrt .= pdf_convert_wyswig_punctuation($wrt_text);
                //$wrt .= $writing->year;
                $pdf->SetFont('Arial','',12);
                $pdf->WriteHTML($wrt, false);
                $pdf->Ln();
               // $pdf->Ln();                
            }            

        }
    }  
    #SIDELIGHTS
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,10,'SIDELIGHTS',0,1);    
    $pdf->SetFont('Arial','',12);
    $pdf->WriteHTML($authSidelights);
    #BIOCRIT
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,10,'BIOCRIT',0,1); 
    if($post->biocrit_books) {
        $pdf->SetFont('Arial','I',12);
        $pdf->Cell(0,10,'BOOKS',0,1);
        $pdf->SetFont('Arial','',12);
        foreach ($post->biocrit_books as $book) {
            $text = "   * " . pdf_convert_wyswig_punctuation($book);
            $pdf->WriteHTML($text);
           // $pdf->Ln();
        }
    }     
    if($post->biocrit_periodicals) {
        $pdf->SetFont('Arial','I',12);
        $pdf->Cell(0,10,'PERIODICALS',0,1);
        $pdf->SetFont('Arial','',12);
        foreach ($post->biocrit_periodicals as $periodical) {
            $text = "   * " . pdf_convert_wyswig_punctuation($periodical);
            $pdf->WriteHTML($text);
           // $pdf->Ln();
        }
    }
    if($post->biocrit_online) {
        $pdf->SetFont('Arial','I',12);
        $pdf->Cell(0,10,'ONLINE',0,1);
        $pdf->SetFont('Arial','',12);
        foreach ($post->biocrit_online as $online) {
            $text = "   * " . pdf_convert_wyswig_punctuation($online);
            $pdf->WriteHTML($text);
            //$pdf->Ln();
        }
    }
   // $pdf->Output();
    $pdf_string = $pdf->Output('S');
	return $pdf_string;
}

function stripPara($text){
    $text = str_replace('<p>', '', $text);
    $text = str_replace('</p>', '', $text);
    return $text;
}

function pdf_convert_wyswig_punctuation($text) {
    $text = str_replace("&#8217;", "'", $text);
    $text = str_replace("'", "'", $text);
    $text = str_replace('’', "'", $text);
    $text = str_replace('“', '"', $text);
    $text = str_replace('”', '"', $text);
    $text = str_replace('—', '-', $text);
    $text = str_replace('&#038;', '&', $text);

	$text = str_replace('Â', ' ', $text); //Â
	$text = str_replace(chr(194), ' ', $text); //Â
	$text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
	return $text;
}

function pdf_export_wp($author='', $category='', $post_type='', $status='', $start_date='', $end_date='', $terms = '') {
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

function pdf_export_page() {
	global $wpdb, $wp_locale; 

	if ( ! current_user_can( 'edit_files' ) )
		die( 'You don\'t have permissions to use this page.' );

	load_plugin_textdomain( 'pdf-export', false, '/pdf-volume-export/languages/' );

	$months = "";
	for ( $i = 1; $i < 13; $i++ ) {
		$months .= "\t\t\t<option value=\"" . zeroise($i, 2) . '">' . 
			$wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) ) . "</option>\n";
	} ?>
<div class="wrap">
<?php screen_icon(); ?>
<h2><?php esc_html_e( 'PDF Export', 'pdf-export' ); ?></h2>

<p><?php esc_html_e('Select the volume to export from the Category dropdown below or filter by other parameters as needed.'); ?></p>
<p><?php esc_html_e('Clicking "Download Export File" will create a zip file containing the selected entries in txt files.'); ?></p>
<form action="" method="get">
<input type="hidden" name="page" value="pdf_export" />
<h3><?php esc_html_e('Options', 'pdf-export' ); ?></h3>

<table class="form-table">
<?php if(version_compare($wpdb->db_version(), '4.1', 'ge')) { ?>
<tr>
<th><label for="category"><?php esc_html_e('Select Volume', 'pdf-export' ); ?></label></th>
<td>
<select name="category" id="category">
<option value="all" selected="selected"><?php esc_html_e('All Categories', 'pdf-export' ); ?></option>
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
<th><label for="mm_start"><?php esc_html_e('Filter by Date', 'pdf-export' ); ?></label></th>
<td><strong><?php esc_html_e('Start:', 'pdf-export' ); ?></strong> <?php esc_html_e('Month', 'pdf-export' ); ?>&nbsp;
<select name="mm_start" id="mm_start">
<option value="all" selected="selected"><?php esc_html_e('All Dates', 'pdf-export' ); ?></option>
<?php echo $months; ?>
</select>&nbsp;<?php esc_html_e('Year', 'pdf-export' ); ?>&nbsp;
<input type="text" id="aa_start" name="aa_start" value="" size="4" maxlength="5" />
</td>
<td><strong><?php esc_html_e('End:', 'pdf-export' ); ?></strong> <?php esc_html_e('Month', 'pdf-export' ); ?>&nbsp;
<select name="mm_end" id="mm_end">
<option value="all" selected="selected"><?php esc_html_e('All Dates', 'pdf-export' ); ?></option>
<?php echo $months; ?>
</select>&nbsp;<?php esc_html_e('Year', 'pdf-export' ); ?>&nbsp;
<input type="text" id="aa_end" name="aa_end" value="" size="4" maxlength="5" />
</td>
</tr>
<tr>
<th><label for="author"><?php esc_html_e('Filter by Author', 'pdf-export' ); ?></label></th>
<td>
<select name="author" id="author">
<option value="all" selected="selected"><?php esc_html_e('All Authors', 'pdf-export' ); ?></option>
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
<th><label for="post_type"><?php  esc_html_e('Restrict Content', 'pdf-export' ); ?></label></th>
<td>
<select name="post_type" id="post_type">
<option value="all" selected="selected"><?php esc_html_e('All Content', 'pdf-export' ); ?></option>
<option value="page"><?php esc_html_e('Pages', 'pdf-export' ); ?></option>
<option value="post"><?php esc_html_e('Posts', 'pdf-export' ); ?></option>
</select>
</td>
</tr>-->
<tr>
<th><label for="status"><?php esc_html_e('Filter by Status', 'pdf-export' ); ?></label></th>
<td>
<select name="status" id="status">
<option value="all" selected="selected"><?php esc_html_e('All Statuses', 'pdf-export' ); ?></option>
<option value="draft"><?php esc_html_e('Draft', 'pdf-export' ); ?></option>
<option value="private"><?php esc_html_e('Privately published', 'pdf-export' ); ?></option>
<option value="publish"><?php esc_html_e('Published', 'pdf-export' ); ?></option>
<option value="future"><?php esc_html_e('Scheduled', 'pdf-export' ); ?></option>
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
<p class="submit"><input type="submit" name="submit" class="button" value="<?php esc_html_e('Download Export File', 'pdf-export' ); ?>" />
<input type="hidden" name="download" value="true" />
</p>
</form>
</div>
<?php
}
function pdf_add_export_page() {
   	add_management_page('PDF Export', 'PDF Export', 'manage_options', 'pdf_export', 'pdf_export_page');
}
add_action('admin_menu', 'pdf_add_export_page');

function pdf_export_setup() {
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