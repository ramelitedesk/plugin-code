<?php 
function register_job_application_post_type() {
    register_post_type('job_application', array(
        'labels' => array(
            'name' => __('Job Applications'),
            'singular_name' => __('Job Application')
        ),
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'custom-fields'),
    ));
}
add_action('init', 'register_job_application_post_type');

?>