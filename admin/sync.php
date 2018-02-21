<?php
/**
 * BGG Collection – Sync
 *
 */

$username = (get_option('bgg_username')) ? sanitize_text_field( get_option('bgg_username') ) : '';
$bgg_url  = 'https://www.boardgamegeek.com/xmlapi2/collection?username='.$username.'&stats=1';

$xml = simplexml_load_file( $bgg_url );

// set defaults
$avg_rating = '';
$per_rating = '';
$min_player = '';
$max_player = '';

if( !empty( $xml ) ):

    foreach( $xml as $item ):
        $bgg_id = intval( $item['objectid']->__toString() );
        $title  = $item->name[0]->__toString();
        $image  = $item->image[0]->__toString();
        
        // if stats are accessible
        if( !empty($item->stats) ):
            $avg_rating = number_format( $item->stats->rating->average[0]['value']->__toString(), 5 );

            $per_rating = $item->stats->rating['value'][0]->__toString();

            $min_player = intval( $item->stats['minplayers'][0]->__toString() );
            $max_player = intval( $item->stats['maxplayers'][0]->__toString() );

            $playingtime = intval( $item->stats['playingtime']->__toString() );
        endif;


        echo '<pre>';
        print_r( $item );
        echo '</pre>';

        global $wpdb;

        $exist_check = $wpdb->get_results("SELECT * FROM $wpdb->postmeta WHERE meta_key = 'bgg_id' AND meta_value = ".$bgg_id." LIMIT 1", ARRAY_A);

        // generate player terms
        if( $min_player && $max_player ):
            $players = array();

            for($i = $min_player; $i <= $max_player; $i++):
                $term_check = term_exists( $i, 'players' );

                if( $term_check === 0 && $term_check === null ):
                    $title = $i.' Players';
                    if( $i == 1 ):
                        $title = 'Solo'; 
                    endif;

                    $args = array(
                        'slug' => $i
                    );
                    wp_insert_term( $title, 'players', $args );
                endif;

                $term = get_term_by('slug', $i, 'players');
                $players[] = intval( $term->term_id );
            endfor;
        endif;

        if( empty($exist_check) ):
            $new_post = array(
                'post_title' => $title,
                'post_status' => 'publish',
                'post_type' => 'collection',
            );

            $post_id = wp_insert_post($new_post);
            add_post_meta($post_id, 'bgg_id', $bgg_id);

            // meta
                // average rating
                if( $avg_rating ):
                    add_post_meta($post_id, 'avg_rating', $avg_rating);
                endif;

                // personal rating
                if( $per_rating && $per_rating != 'N/A' ):
                    add_post_meta($post_id, 'per_rating', number_format($per_rating, 1) );
                endif;

                // playing time
                if( playingtime ):
                    add_post_meta($post_id, 'playingtime', $playingtime);
                endif;
        else:

            $post_id = $exist_check[0]['post_id'];

            $update_details = array(
                'ID' => $post_id,
                'post_title' => $title,
            );

            //meta
                // average rating
                if( $avg_rating ):
                    update_post_meta($post_id, 'avg_rating', $avg_rating);
                endif;

                // personal rating
                if( $per_rating && $per_rating != 'N/A' ):
                    update_post_meta($post_id, 'per_rating', number_format($per_rating, 1) );
                endif;

                // playing time
                if( playingtime ):
                    update_post_meta($post_id, 'playingtime', $playingtime);
                endif;
        endif;

        // add player choices
        if( !empty($players) ):
            wp_set_object_terms( $post_id, $players, 'players' );
        endif;

    endforeach;
endif;



?>