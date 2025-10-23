<?php
include get_template_directory() . '/inc/admin/post-type.php';
include get_template_directory() . '/inc/admin/save-data.php';
function custom_disable_block_editor( $use_block_editor, $post_id ) {
    // Get the post object
    $post = get_post( $post_id );

    // Option 1: Disable for all post types (uncomment the line below)
    // return false;

    // Option 2: Disable for specific post types (e.g., 'post' and 'page')
    if ( 'post' === $post->post_type || 'page' === $post->post_type ) {
        return false; // Return false to enable Classic Editor
    }

    // Option 3: Disable for a specific post ID
    // if ( 123 === $post_id ) { // Replace 123 with your post ID
    //     return false;
    // }

    // For other post types or conditions, keep the default behavior
    return $use_block_editor;
}
add_filter( 'use_block_editor_for_post', 'custom_disable_block_editor', 10, 2 );

//hide the  post menu 
function remove_post_menu() {
    remove_menu_page('edit.php');
}
add_action('admin_menu', 'remove_post_menu');

// remove extra p tag from the cf7 form
function remove_cf7_br_and_p_tags($form) {
  return str_replace(['<p>', '</p>', '<br />'], '', $form);
}
add_filter('wpcf7_form_elements', 'remove_cf7_br_and_p_tags');

