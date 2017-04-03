<?php
/**
 * Template for displaying all single posts
 *
 * @package Bootstrap Canvas WP
 * @since Bootstrap Canvas WP 1.0
 */

	get_header(); ?>

      <div class="row">

        <div class="col-sm-10 blog-main">

          <?php get_template_part( 'loop', 'single' ); ?>

            <ul class="nav nav-pills" role="tablist">
              <li role="presentation" class="active"><a href="#preview" role="tab" data-toggle="tab">Entry Preview</a></li>
              <li role="presentation"><a href="#loc" role="tab" data-toggle="tab">LOC</a></li>
              <li role="presentation"><a href="#misc" role="tab" data-toggle="tab">Misc Entries</a></li>
              <li role="presentation"><a href="#bio" role="tab" data-toggle="tab">Bio</a></li>
              <li role="presentation"><a href="#gale" role="tab" data-toggle="tab">Gale</a></li>
              <li role="presentation"><a href="#biocrit" role="tab" data-toggle="tab">Biocrit</a></li>              
              <li role="presentation"><a href="#web" role="tab" data-toggle="tab">Web</a></li>
              <!--<li role="presentation"><a href="#messages" aria-controls="messages" role="tab" data-toggle="tab">Messages</a></li>-->
              <!--<li role="presentation"><a href="#settings" aria-controls="settings" role="tab" data-toggle="tab">Settings</a></li>-->
        </ul>

         <div class="tab-content">

         <div role="tabpanel" class="tab-pane fade in active" id="preview">

          <p>
          <b>PERSONAL</b>
          <?php the_field("personal"); ?>
          <?php $value = get_field( "education" );
            if( $value ) { 
              echo "EDUCATION: " . $value;
              }
           ?>        
          </p>

          <p>
          <b>ADDRESS</b>
          <?php if( have_rows('author_address') ): ?>
            <ul>
              <?php while( have_rows('author_address') ): the_row(); ?>
                <li><?php the_sub_field('author_address_type');
                  echo " - ";
                  the_sub_field('author_address_text'); ?></li>      
              <?php endwhile; ?> 
            </ul>
          <?php endif; ?>     
          </p>   
          <p>
          <b>CAREER</b>
          <?php the_field("work_history"); ?>
          </p>

          <p>
          <b>WRITINGS</b>
          <?php if( have_rows('collected_writings') ): ?>
            <ul>
              <?php while( have_rows('collected_writings') ): the_row(); ?>
              <li>
                <?php if( get_row_layout() == 'loc_writing' ): ?>
                        <?php $role = get_sub_field( "loc_writing_role" );
                            if( $role ) { 
                               echo "(" . $role . ")";
                            }
                        ?>
                      	<?php $title = get_sub_field( "loc_writing_title" );
                              $type = get_sub_field( "loc_writing_type" );
                              if ($type) {
                                 echo "<u> " . $title . "</u> (" . $type . "), ";
                              } else {
                                 echo "<u> " . $title . "</u>, ";
                              }                           
                        ?>  
                        <?php the_sub_field('loc_writing_publisher'); ?>
                        <?php $location = get_sub_field( "loc_writing_location" );
                            if( $location ) { 
                               echo "(" . $location . "),";
                            }
                        ?>
                        <?php the_sub_field('loc_writing_year'); ?>
                  <?php elseif( get_row_layout() == 'misc_writing' ): ?>
                        <?php $role = get_sub_field( "misc_writing_role" );
                            if( $role ) { 
                               echo "(" . $role . ")";
                            }
                        ?>                  
                        <?php $title = get_sub_field( "misc_writing_title" );
                              $type = get_sub_field( "misc_writing_type" );
                              if ($type) {
                                 echo "<b><i> " . $title . "</i></b> (" . $type . "), ";
                              } else {
                                 echo "<b><i> " . $title . "</i></b>, ";
                              }    
                        ?>
                        <?php the_sub_field('misc_writing_publisher'); ?>
                        <?php $location = get_sub_field( "misc_writing_location" );
                            if( $location ) { 
                               echo "(" . $location . "),";
                            }
                        ?>
                        <?php the_sub_field('misc_writing_year'); ?>
                  <?php elseif( get_row_layout() == 'imported_writing' ): ?>              
                        <?php $imported_text = get_sub_field( "imported_writing_text" );
                              $imported_text = str_replace("<p>", "", $imported_text);
                              $imported_text = str_replace("</p>", "", $imported_text);
                              $imported_text = trim($imported_text);
                              $imported_year = get_sub_field('imported_pub_year'); 
                              echo $imported_text . ", " . $imported_year;
                              ?>                    
                  <?php elseif( get_row_layout() == 'writings_subhead' ): ?>
                        	<?php echo "<b>";
                            the_sub_field('writing_subhead_title'); 
                            echo "</b>";
                            ?>       
                   <?php endif; ?>             
              </li>    
              <?php endwhile; ?> 
            </ul>
          <?php endif; ?>
          </p>
          <p>
            <?php the_field("secondary_writings"); ?>
          </p>            
          <p>
            <?php the_field("adaptations"); ?>
          </p>              

          <p>
          <b>SIDELIGHTS</b>
          <?php the_field("narrative"); ?>
          </p> 

          <p>
          <b>BIOCRIT</b><br />
          <?php if( have_rows('biocrit_entries') ): ?>
          <u>PERIODICALS</u>
            <ul>
              <?php while( have_rows('biocrit_entries') ): the_row(); ?>
                <li><?php 
                  the_sub_field('biocrit_entry');              
                  ?></li>    
              <?php endwhile; ?> 
            </ul>
          <?php endif; ?>          
          </p>
          <?php if( have_rows('online_biocrit_entries') ): ?>
          <u>ONLINE</u>
            <ul>
              <?php while( have_rows('online_biocrit_entries') ): the_row(); ?>
                <li><?php 
                  the_sub_field('online_biocrit_entry');              
                  ?></li>    
              <?php endwhile; ?> 
            </ul>
          <?php endif; ?>          
          </p>

          </div>
          <!--LOC-->
           <div role="tabpanel" class="tab-pane fade" id="loc">
          <?php if( have_rows('collected_writings') ): ?>
            <ul>
              <?php while( have_rows('collected_writings') ): the_row(); ?>              
                <?php if( get_row_layout() == 'loc_writing' ): ?>
                <li>
                      	<?php $test = get_sub_field( "loc_writing_title" );
                            echo "<u> " . $test . "</u>";
                        ?>
                        <?php $value = get_sub_field( "loc_writing_type" );
                            if( $value ) { 
                               echo "( " . $value . ")";
                            }
                        ?>
                        <?php the_sub_field('loc_writing_publisher'); ?>
                        <?php $value2 = get_sub_field( "loc_writing_location" );
                            if( $value2 ) { 
                               echo "(" . $value2 . "),";
                            }
                        ?>
                        <?php the_sub_field('loc_writing_year'); ?>
                   </li>      
                   <?php endif; ?>                                
              <?php endwhile; ?> 
            </ul>
          <?php endif; ?> 
            <?php the_field("bib_loc_entries"); ?>
          </div>
          <!--MISC-->
          <div role="tabpanel" class="tab-pane fade" id="misc">
          <?php if( have_rows('bib_misc_entries') ): ?>
            <ul>
              <?php while( have_rows('bib_misc_entries') ): the_row(); ?>
                <li><?php the_sub_field('misc_title');
                  echo " - ";
                  the_sub_field('misc_publication_year');
                  echo " "; 
                  the_sub_field('misc_publisher'); 
                  echo ", ";
                  the_sub_field('misc_location');                   
                  ?></li>      
              <?php endwhile; ?> 
            </ul>
          <?php endif; ?>
          </div>
          <!--BIO-->
          <div role="tabpanel" class="tab-pane fade" id="bio">
          <?php if( have_rows('biographical_sources') ): ?>
            <ul>
              <?php while( have_rows('biographical_sources') ): the_row(); ?>
                <li><?php 
                  echo "<b>";
                  the_sub_field('bio_source_name');
                  echo " - ";
                  the_sub_field('bio_source_url');
                  echo "</b>";
                  // echo "<br />";
                  the_sub_field('bio_text');                
                  ?></li>      
              <?php endwhile; ?> 
            </ul>
          <?php endif; ?>
          </div>
          <!--GALE                 -->
          <div role="tabpanel" class="tab-pane fade" id="gale">
            <?php the_field("gale_reviews"); ?>
          </div>
          <!--BIOCRIT                 -->
          <div role="tabpanel" class="tab-pane fade" id="biocrit">
            <?php the_field("biocrit_text"); ?>
          </div>          
          <!--WEB-->
          <div role="tabpanel" class="tab-pane fade" id="web">
          <?php if( have_rows('web_reviews') ): ?>
            <ul>
              <?php while( have_rows('web_reviews') ): the_row(); ?>
                <li><?php 
                  echo "<b>";
                  the_sub_field('web_review_source_name');
                  echo " - ";
                  the_sub_field('web_review_source_url');
                  echo "</b>";
                  // echo "<br />";
                  the_sub_field('web_review_text');                
                  ?></li>    
              <?php endwhile; ?> 
            </ul>
          <?php endif; ?>
          </div>          


          </div>                                      

        </div><!-- /.blog-main -->

      </div><!-- /.row -->
      
	<?php get_footer(); ?>