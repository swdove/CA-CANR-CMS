<?php 
/*
Plugin Name: Citation Generator
Plugin URI: https://github.com/swdove
Description: Functions that auto-generate citations in entries based on the content of various research fields
Version: 1.0
Author: Sean Dove
Author URI: https://github.com/swdove
 
*/

// SEAN'S CUSTOM FUNCTIONS

// CREATES AND INSERTS LOC CITATION ENTRIES FOR WRITINGS
// SECTION ON POST SAVE WHEN LOC EXPORT DATA IS PASTED INTO
// RESEARCH "LOC ENTRIES" FIELD
function generate_writings( $post_id ) {

	class LOC_citation {
		public $title;
		public $pubInfo;
		public $publisher;
		public $pubyear;
	}

	class misc_citation {
		public $title;
		public $pubLocation;
		public $publisher;
		public $pubYear;
	}

	$writing_entries = Array();		
	$post_status = get_post_status( $post_id );
	if($post_status == 'publish' || $post_status == 'pending') {
		//get details for Writings repeater field
		$writings_field = get_field_object("collected_writings", $post_id);
		//get repeater field's unique ID
		$field_key = $writings_field['key'];

		$loc_count = 0;
		$misc_count = 0;

		//GET COUNTS OF EXISTING LOC AND MISC ENTRIES (IF ANY)
		if($writings_field['value'] != false ) {
			foreach($writings_field['value'] as $value) {
				if($value['acf_fc_layout'] == "loc_writing") {
					$loc_count++;
				} elseif ($value['acf_fc_layout'] == "misc_writing") {
					$misc_count++;
				}
			}
		}
    
		//IF NO LOC ENTRIES EXIST, GENERATE FROM RESEARCH
		if($writings_field['value'] == false || $loc_count == 0) {
			//get LOC citation text 
			$loc_text = get_field('bib_loc_entries', $post_id );

			$citationList = Array();
			//determine number of entries
			$citationCount = substr_count($loc_text, "LCCN");
			//format and explode on line breaks
			$loc_text = str_replace("\r", "", $loc_text);
			$exploded = explode("\n", $loc_text);

			$i = 0;
			//create index for each entry
			while ($i < $citationCount) {
				$citation = new LOC_citation();
				array_push($citationList, $citation);
				$i++;
			}
			//GET TITLE
			$i = 0;
			foreach($exploded as $key => $val) {
				if($val == "LCCN" && $key > 1){
					$title = $exploded[$key - 2];
					$citationList[$i]->title = substr($title, 4);
					$i++;
				}
			}
			//GET PUBLISHER INFO
			$i = 0;
			foreach($exploded as $key => $val) {
				if($val == "Published/Created"){
					$citationList[$i]->pubInfo = $exploded[$key + 1];
					$i++;
				}
			}
			
			//format citation entry text and add to array
			foreach ($citationList as $citation) {
				if(!empty($citation->title)) {
					$entry = $citation->title . " " . $citation->pubInfo;
					$content = array( "loc_generated_entry" => $entry, "acf_fc_layout" => "loc_writing" );
					array_push($writing_entries, $content);
				}
			}

			//insert generated entries into Writing repeater
			//update_field( $field_key, $value, $post_id );		   				

		}

		//IF NO MISC ENTRIES EXIST, GENERATE FROM RESEARCH
		if($writings_field['value'] == false || $misc_count == 0) {   
			//get LOC citation text 
			$misc_entries = get_field('bib_misc_entries', $post_id );

			$citationList = Array();

			if(!empty($misc_entries)) {
				foreach($misc_entries as $misc) {
					$citation = new misc_citation();
					$citation->title = $misc['misc_title'];
					$citation->pubYear = $misc['misc_publication_year'];
					$citation->publisher = $misc['misc_publisher'];
					$citation->pubLocation = $misc['misc_location'];
		
					array_push($citationList, $citation);
				}
		
				//format citation entry text and add to array
				foreach ($citationList as $citation) {
					//$entry = $citation->title . " " . $citation->pubInfo;
					if(!empty($citation->title)) {
						$content = array( "misc_writing_title" => $citation->title,
							"misc_writing_publisher" => $citation->publisher,
							"misc_writing_location" => $citation->pubLocation,
							"misc_writing_year" => $citation->pubYear,
							"acf_fc_layout" => "misc_writing" );
						array_push($writing_entries, $content);
					}
				}		
			}
		}

		//insert generated entries into Writings repeater
		if(count($writing_entries) > 0) {
			//can't append new entries to existing ones using update function, have to retrieve and pass back existing entries as well		
			$existing_entries = $writings_field['value'];
			if($existing_entries == false) {
				update_field( $field_key, $writing_entries, $post_id );
			} else {
				foreach($writing_entries as $new_entry) {
					array_push($existing_entries, $new_entry);
				}			
				update_field( $field_key, $existing_entries, $post_id );
			}		
		}
	}
}

// FORMATS AND INSERTS BIOCRIT CITATIONS ON POST SAVE WHEN
// GALE EXPORT DATA IS PASTED INTO RESEARCH "BIOCRIT" FIELD
// SIMILAR TO LOC ENTRIES BUT MORE COMPLICATED TEXT PARSING TO GENERATE THESE
function generate_biocrit ( $post_id ) {

	class Biocrit {
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

	$post_status = get_post_status( $post_id );
	if($post_status == 'publish' || $post_status == 'pending') {

		//get details for Biocrit entries repeater field
		$biocrit_field = get_field_object("biocrit_entries", $post_id);

		//only generate entries on first save or if # of entries = 0 so as not to override updates to existing entries
		if($biocrit_field['value'] == false || count($biocrit_field['value']) == 0) {
		//get Biocrit field's unique ID
		$field_key = $biocrit_field['key'];		

		//get biocrit citation text 
		$loc_text = get_field('biocrit_text', $post_id );

		//change all line breaks and spaces to a caret
		$loc_text = str_replace("\r", "", $loc_text);
		$loc_text = str_replace("\n", "^", $loc_text);
		$loc_text = str_replace(" ", "^", $loc_text);
		$citationList = Array();

		//explode on carets
		$exploded = explode("^", $loc_text);

		//create array index for each distinct entry
		//(as determined by occurences of "OneFile")
		foreach($exploded as $key => $val) {
			if(strpos($val, 'OneFile,') !== false) {
				$citation = new Biocrit();
				array_push($citationList, $citation);
			}
		}
		//for each citation, gather start and end positions of review title
		//determine cutoff point for information we need to build entries
		if(count($citationList) > 0){		
			$i = 0;
			foreach($exploded as $key => $val) {
				if($val == "OneFile,"){
					$citationList[$i]->fieldsCutoffPos = $key - 2;
					//get rough position of where first entry starts to ignore any text above it
					if($i == 0) {
						$firstEntryPos = $citationList[$i]->fieldsCutoffPos;
					}
					$i++;
				}
			}
			//determine ending index for each citation (relative to "Accessed")
			$i = 0;
			foreach($exploded as $key => $val) {
				if($val == "Accessed"){
					if($key > $firstEntryPos) {
						$citationList[$i]->fieldsEndPos = $key + 3;
						$i++;
					}
				}
			}
			//determine start index for next citation relative to prev entry's ending index
			$i = 0;
			while ($i < count($citationList)) {
				if($i == 0) {
					$citationList[$i]->fieldsStartPos = 0;
				} else {
					$citationList[$i]->fieldsStartPos = ($citationList[$i - 1]->fieldsEndPos + 1);
				}
				$i++;
			}
			//splice each citation into separate arrays
			$i = 0;
			foreach($citationList as $citation){
				$output = array_slice($exploded, $citation->fieldsStartPos, $citation->fieldsCutoffPos - $citation->fieldsStartPos+ 1);
				$citationList[$i]->explodedData = $output;
				$i++;
			}
			//var_dump($citationList);
			//determine start and end points of title (relative to quotes)
			$i = 0;
			foreach($citationList as $citation) {
				foreach($citation->explodedData as $key=>$val) {
					if(startsWith($val, '&quot;')) {
						$citationList[$i]->titleStartPos = $key;
					}
					if(endsWith($val, '&quot;')) {
						$citationList[$i]->titleEndPos = $key;
					}
				}
				$i++;
			}
			// splice title of review together from array chunks
			foreach($citationList as $citation) {
				$title = array_slice($citation->explodedData, $citation->titleStartPos, $citation->titleEndPos - $citation->titleStartPos+ 1);
				$titleString = implode(' ', $title);

				$titleString = str_replace('"','', $titleString);
				$text = convert_apostrophes($titleString);

				$citation->reviewTitle = $text;
			}

			//determine if review incudes author name. if so, grab + parse it
			foreach($citationList as $citation) {
				if($citation->titleStartPos == 0) {
					$citation->includesAuthor = false;
				} else {
					$citation->includesAuthor = true;
					$author = array_slice($citation->explodedData, 0, $citation->titleStartPos);

					//reorder review author name formatting
					// strip comma from last name 				
					$author[0] = str_replace(",", "",$author[0]);
					// strip period from first name
					$author[1] = str_replace(".", "",$author[1]);
					//move last name to end position
					$item = $author[0];
					unset($author[0]);
					array_push($author, $item); 

					$citation->authorName = implode(' ', $author);
				}
			}

			//determine pub details
			foreach($citationList as $citation) {
				$pubDetails = array_slice($citation->explodedData, $citation->titleEndPos + 1, $citation->fieldsCutoffPos - $citation->titleEndPos + 1);
				//get index of pub month
				$pubMonthIndex = getMonthIndex($pubDetails);
				$citation->pubMonth = $pubDetails[$pubMonthIndex];
				$year = $pubDetails[$pubMonthIndex +1];
				$year = str_replace(",","",$year);
				$year = str_replace(".","",$year);
				$citation->pubYear = $year;
				foreach($pubDetails as $key=>$val) {
					if($val == "p.") {
						$pageNumberIndex = $key + 1;
						$citation->pageNumber = $pubDetails[$pageNumberIndex];
					}
				}
				$title = array_slice($pubDetails, 0, $pubMonthIndex);
				$lastItem = end($title);
				if(is_numeric($lastItem)) { //last item in array is numeric
					$pubDay = array_pop($title);
					$citation->pubDay = $pubDay;
				}
				$pubTitle = implode(' ', $title);
				$citation->pubTitle = str_replace(",","",$pubTitle);
			}
		}	

		$count = count($citationList);

		$biocrit_entries = Array();

		//format entry text and add to array
		foreach ($citationList as $citation) {
			$entry = "";

			$entry .= '<i>' . $citation->pubTitle . '</i> ';
			if($citation->pubDay) {
				$entry .= $citation->pubMonth . ' ' . $citation->pubDay . ', ' . $citation->pubYear . ', ';
			} else {
				$entry .= $citation->pubMonth . ', ' . $citation->pubYear . '. ';
			}
			if($citation->authorName) {
				$entry .= $citation->authorName . ', "' . $citation->reviewTitle . '". ';
			} else {
				$entry .= 'review of <i>' . $citation->reviewTitle . '</i>';
			}
			if($citation->pageNumber) {
				$entry .= ' p. ' . $citation->pageNumber;
			}			
			$content = array( "biocrit_entry" => $entry);
			array_push($biocrit_entries, $content);
		}

		//insert generated entries into Biocrit repeater
		if(count($biocrit_entries) > 0) {
			update_field( $field_key, $biocrit_entries, $post_id );	
		}
			

		}
	}	
}

// FORMATS AND INSERTS ONLINE BIOCRIT CITATIONS ON POST SAVE WHEN
// WEB REVIEW ENTRIES EXIST IN RESEARCH

function generate_online ( $post_id ) {

	class online_source {
		public $name;
		public $urlFull;
		public $urlHost;
		public $reviewOf;
		public $date;
	}

	$post_status = get_post_status( $post_id );
	if($post_status == 'publish' || $post_status == 'pending') {
		//get details for online biocrit entries repeater field
		$online_biocrit_field = get_field_object("online_biocrit_entries", $post_id);

		//only generate entries on first save or if # of entries = 0 so as not to override updates to existing entries
		if($online_biocrit_field['value'] == false || count($online_biocrit_field['value']) == 0) {
			//get Biocrit field's unique ID
			$field_key = $online_biocrit_field['key'];		

			//get online reviews from research 
			$web_reviews = get_field('web_reviews', $post_id );

			$online_sources = Array();
			$online_biocrit_entries = Array();

			//build review source object
			if($web_reviews) {
				foreach ($web_reviews as $review) {
					$source = new online_source();
					$source->name = $review['web_review_source_name'];
					$source->urlFull = $review['web_review_source_url'];	
					$source->reviewOf = $review['web_review_of'];
					//if no date entered, use today's
					if($review['web_review_date']) {
						$source->date = $review['web_review_date'];
					} else {
						$source->date = date("F j, Y");
					}
					//parse URL, only need hostname 	
					$parsedUrl = parse_url($source->urlFull);
					if($parsedUrl['scheme']) {
						$source->urlHost = $parsedUrl['scheme'] . "://" . $parsedUrl['host'];
					} else {
						$source->urlHost = $parsedUrl['host'];
					}			

					array_push($online_sources, $source);
				}

					//build citation entries
				foreach ($online_sources as $source) {
					if ($source->reviewOf) {
						$entry = "<i>" . $source->name . "</i>, " . $source->urlHost . " (" . $source->date . "),  review of <i>" . $source->reviewOf . "</i>";
					} else {
						$entry = "<i>" . $source->name . "</i>, " . $source->urlHost . " (" . $source->date . ").";
					}					

					$content = array( "online_biocrit_entry" => $entry);
					array_push($online_biocrit_entries, $content);
				}

				//insert generated entries into Biocrit repeater
				if((count($online_biocrit_entries) > 0) && (count($online_biocrit_entries) == count($online_sources))) {
					update_field( $field_key, $online_biocrit_entries, $post_id );	
				}							
			}
		}
	}
}

function assign_to_admin ( $post ) {
	$post_id = $post->ID;
	$arg = array(
    'ID' => $post_id,
    'post_author' => 1,
);
    wp_update_post( $arg );
}

function startsWith($haystack, $needle)
{
	 $haystack = htmlentities($haystack);
     $length = strlen($needle);
     return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle)
{
	$haystack = htmlentities($haystack);
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}

function convert_apostrophes($text) {
	$text = wptexturize($text);

	$text = str_replace('&#8220;', '&ldquo;', $text);
	$text = str_replace('&#8221;', '&rdquo;', $text);	

	$text = str_replace('&#8216;', '&lsquo;', $text);
	$text = str_replace('&#8217;', '&rsquo;', $text);

	$text = str_replace('&#039;', '&apos;', $text);	

	return $text;
}

function getMonthIndex($fields) {
$months = array('Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec');

	foreach($fields as $key=>$val) {
		foreach($months as $month) {
			if(startsWith($val, $month)) {
				return $key;
			}
		}
	}
}


add_action('acf/save_post', 'generate_writings', 20);
add_action('acf/save_post', 'generate_biocrit', 20);
add_action('acf/save_post', 'generate_online', 20);
add_action( 'draft_to_pending', 'assign_to_admin' );
add_action( 'draft_to_publish', 'assign_to_admin' );

//displays pub year and title (if available) in heading of Writings section 
    
function my_acf_flexible_content_layout_title( $title, $field, $layout, $i ) {
	
	$layout = get_row_layout();
	if($layout == "imported_subhead") {
		$text = get_sub_field('imported_subhead_title');
	//	$title .= '<h4>' . $text . '</h4>';
		$title .= " - <b>" . $text . "</b>";
	} elseif ($layout == "writings_subhead") {
		$text = get_sub_field('writing_subhead_title');
		$title .= " - <b>" . $text . "</b>";
	} elseif ($layout == "imported_writing") {
		$text = get_sub_field('imported_pub_year');
		$title .= " - <b>" . $text . "</b>";
	} elseif ($layout == "loc_writing") {
		$text = get_sub_field('loc_writing_title');
		if(strlen($text) > 30) {
			$trimmed = substr($text, 0, 30);
			$text = $trimmed . "...";
		}	
		$year = get_sub_field('loc_writing_year');
		$title .= " - <b>" . $year . " - " . $text . "</b>";
	} elseif ($layout == "misc_writing") {
		$text = get_sub_field('misc_writing_title');
		if(strlen($text) > 30) {
			$trimmed = substr($text, 0, 30);
			$text = $trimmed . "...";
		}		
		$year = get_sub_field('misc_writing_year');
		$title .= " - <b>" . $year . " - " . $text . "</b>";
	}		
	
	// return
	return $title;
	
}

// name
add_filter('acf/fields/flexible_content/layout_title/name=collected_writings', 'my_acf_flexible_content_layout_title', 10, 4);


    ?>