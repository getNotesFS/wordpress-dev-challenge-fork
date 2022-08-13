<?php

if (!defined('ABSPATH')) {
    die('Direct access not permitted.');
}



/**
 * Citation shortcode to get the citation of each post by id or current post.
 *
 * @param [type] $atts
 * @param [type] $content
 * @return void
 */
function get_citation_shortcode($atts)
{

    global $post;

    $attributes = shortcode_atts(array(
        'post_id' => ''
    ), $atts);


    /**
     * Get citation according to the ID value in the shortcode. 
     * If the ID does not exist in the shortcode, obtain the current post citation.
     */
    if (!empty($attributes['post_id'])) { //Exist id, get citation by ID

        return get_post_meta($attributes['post_id'], '_post_citation_key', true);
    } else { //No exist id, get the current post citation

        return get_post_meta($post->ID, '_post_citation_key', true);
    }
}
add_shortcode('citation', 'get_citation_shortcode');
