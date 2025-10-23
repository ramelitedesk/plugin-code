<?php
add_action('wpcf7_before_send_mail', 'save_job_application_to_post_with_resume');
function save_job_application_to_post_with_resume($contact_form)
{
    $submission = WPCF7_Submission::get_instance();
    if (!$submission) return;

    $data = $submission->get_posted_data();
    $uploaded_files = $submission->uploaded_files();
    // ✅ Include upload/media functions here
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    // Extract form fields safely
    $name         = sanitize_text_field($data['user-name']);
    $email        = sanitize_email($data['email']);
    $phone        = sanitize_text_field($data['phone']);
    $job_category = isset($data['jobCategory']) ? (is_array($data['jobCategory']) ? implode(', ', $data['jobCategory']) : $data['jobCategory']) : '';
    $gender       = isset($data['gender']) ? (is_array($data['gender']) ? implode(', ', $data['gender']) : $data['gender']) : '';
    $message      = sanitize_textarea_field($data['message']);

    $resume_url = '';
    $attach_id  = '';

    // Handle uploaded resume
    // ✅ Handle uploaded resume safely
    if (!empty($uploaded_files['resume'])) {
        $file_entry = $uploaded_files['resume'];

        // Handle array of files (CF7 may return an array)
        $file = is_array($file_entry) ? $file_entry[0] : $file_entry;

        if (file_exists($file)) {
            $upload_dir = wp_upload_dir();
            $upload_path = $upload_dir['path'];
            $upload_url  = $upload_dir['url'];

            $filename = basename($file);
            $new_path = trailingslashit($upload_path) . $filename;

            if (copy($file, $new_path)) {
                $resume_url = trailingslashit($upload_url) . $filename;

                $filetype = wp_check_filetype($filename, null);
                $attachment = array(
                    'guid'           => $resume_url,
                    'post_mime_type' => $filetype['type'],
                    'post_title'     => preg_replace('/\.[^.]+$/', '', $filename),
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                );

                $attach_id = wp_insert_attachment($attachment, $new_path);
                $attach_data = wp_generate_attachment_metadata($attach_id, $new_path);
                wp_update_attachment_metadata($attach_id, $attach_data);
            }
        }
    }

    $post_content = "
<strong>Name:</strong> {$name}<br>
<strong>Email:</strong> {$email}<br>
<strong>Phone:</strong> {$phone}<br>
<strong>Job Category:</strong> {$job_category}<br>
<strong>Gender:</strong> {$gender}<br>
<strong>Message:</strong> {$message}<br>
<strong>Resume:</strong> <a href='{$resume_url}' download>Download Resume</a>
";

    // Create job application post
    $post_id = wp_insert_post(array(
        'post_title'   => $name . ' - ' . $job_category,
        'post_content' => $post_content,
        'post_type'    => 'job_application',
        'post_status'  => 'publish',
        'meta_input'   => array(
            'email'        => $email,
            'phone'        => $phone,
            'jobCategory' => $job_category,
            'gender'       => $gender,
            'resume_url'   => $resume_url,
        ),
    ));

    // Attach uploaded resume to the post
    if (!empty($attach_id) && $post_id) {
        update_post_meta($post_id, '_resume_attachment_id', $attach_id);
        wp_update_post(array(
            'ID' => $attach_id,
            'post_parent' => $post_id,
        ));
    }
}

// Add Resume column
add_filter('manage_job_application_posts_columns', function ($columns) {
    $new_columns = [];
    foreach ($columns as $key => $label) {
        $new_columns[$key] = $label;
        if ($key === 'title') {
            $new_columns['resume'] = 'Resume';
        }
    }
    return $new_columns;
});

// Add Download button
add_action('manage_job_application_posts_custom_column', function ($column, $post_id) {
    if ($column === 'resume') {
        $resume_url = get_post_meta($post_id, 'resume_url', true);
        if ($resume_url) {
            echo '<a href="' . esc_url($resume_url) . '" class="button button-primary" download>Download Resume</a>';
        } else {
            echo '—';
        }
    }
}, 10, 2);
