<?php
/**
 * Plugin Name: WooCommerce API Product Importer
 * Description: Imports WooCommerce products from the DummyJSON API and updates existing ones by ID, including full product details.
 * Version: 1.1
 * Author: rk
 */

if (! defined('ABSPATH')) {
    exit;
}

//enqueue js file
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'toplevel_page_wc-api-importer') return;
    wp_enqueue_script('wc-api-importer-js', plugin_dir_url(__FILE__) . 'importer.js', ['jquery'], '1.0', true);
    wp_localize_script('wc-api-importer-js', 'wcApiImporter', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wc_api_import_nonce'),
    ]);
});

//Check if WooCommerce is Active
register_activation_hook(__FILE__, 'wc_api_importer_check_woocommerce');
function wc_api_importer_check_woocommerce() {
    if (! is_plugin_active('woocommerce/woocommerce.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('This plugin requires WooCommerce.');
    }
}

//Add Admin Menu Page
add_action('admin_menu', function () {
    add_menu_page('Product Importer', 'Product Importer', 'manage_woocommerce', 'wc-api-importer', 'wc_api_importer_page');
});


//Admin Page HTML
function wc_api_importer_page() {
    echo '<div class="wrap"><h1>WooCommerce Product Importer</h1>';
    echo '<button id="import-products-btn" class="button button-primary">Import Products</button>';
    echo '<div id="import-loader" style="display:none;margin-top:10px;">⏳ Importing... Please wait.</div>';
    echo '<div id="import-result" style="margin-top:20px;"></div>';
    echo '</div>';
}

//image handling
function wc_api_importer_download_image($url, $post_id) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $tmp = download_url($url);
    if (is_wp_error($tmp)) return false;
    $file_array = ['name' => basename($url), 'tmp_name' => $tmp];
    $img_id = media_handle_sideload($file_array, $post_id);
    if (is_wp_error($img_id)) { @unlink($tmp); return false; }
    return $img_id;
}

//Main Import Function
function wc_api_import_products() {
    $api_url = 'https://dummyjson.com/products';
    $response = wp_remote_get($api_url);
    if (is_wp_error($response)) {
        echo '<p style="color:red;">Error fetching products.</p>'; return;
    }
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if (empty($data['products'])) {
        echo '<p>No products found.</p>'; return;
    }

    foreach ($data['products'] as $product) {
        $api_id = intval($product['id']);
        $title  = sanitize_text_field($product['title']);

        $existing = new WP_Query([
            'post_type'  => 'product',
            'meta_query' => [['key'=>'_dummyjson_id','value'=>$api_id,'compare'=>'=']],
            'posts_per_page'=>1
        ]);

        if ($existing->have_posts()) {
            $post_id = $existing->posts[0]->ID;
            echo '<p>Updated: '.esc_html($title).'</p>';
        } else {
            $post_id = wp_insert_post([
                'post_title'   => $title,
                'post_content' => sanitize_textarea_field($product['description']),
                'post_status'  => 'publish',
                'post_type'    => 'product',
            ]);
            if (! $post_id || is_wp_error($post_id)) {
                echo '<p style="color:red;">Failed to import: '.esc_html($title).'</p>';
                wp_reset_postdata();
                continue;
            }
            echo '<p>🆕 Imported: '.esc_html($title).'</p>';
        }

        // Prices and stock
        update_post_meta($post_id, '_regular_price', floatval($product['price']));
        update_post_meta($post_id, '_price', floatval($product['price']));
        update_post_meta($post_id, '_stock', intval($product['stock']));
        update_post_meta($post_id, '_stock_status', $product['stock'] > 0 ? 'instock' : 'outofstock');
        update_post_meta($post_id, '_manage_stock', 'yes');
        update_post_meta($post_id, '_dummyjson_id', $api_id);

        // Other meta
        update_post_meta($post_id, '_dummyjson_category', sanitize_text_field($product['category']));
        update_post_meta($post_id, '_dummyjson_tags', implode(', ', array_map('sanitize_text_field', $product['tags'])));
       update_post_meta($post_id, '_dummyjson_brand', sanitize_text_field($product['brand'] ?? ''));
        update_post_meta($post_id, '_sku', sanitize_text_field($product['sku']));
        update_post_meta($post_id, '_weight', floatval($product['weight']));
        update_post_meta($post_id, '_length', floatval($product['dimensions']['depth']));
        update_post_meta($post_id, '_width', floatval($product['dimensions']['width']));
        update_post_meta($post_id, '_height', floatval($product['dimensions']['height']));
        update_post_meta($post_id, '_dummyjson_warranty', sanitize_text_field($product['warrantyInformation']));
        update_post_meta($post_id, '_dummyjson_shipping_info', sanitize_text_field($product['shippingInformation']));
        update_post_meta($post_id, '_dummyjson_return_policy', sanitize_text_field($product['returnPolicy']));
        update_post_meta($post_id, '_dummyjson_minimum_order_quantity', intval($product['minimumOrderQuantity']));
        update_post_meta($post_id, '_dummyjson_rating', floatval($product['rating']));
        update_post_meta($post_id, '_dummyjson_discount_percentage', floatval($product['discountPercentage']));
        update_post_meta($post_id, '_dummyjson_barcode', sanitize_text_field($product['meta']['barcode']));
        update_post_meta($post_id, '_dummyjson_qr_code', esc_url_raw($product['meta']['qrCode']));
        update_post_meta($post_id, '_dummyjson_created_at', sanitize_text_field($product['meta']['createdAt']));
        update_post_meta($post_id, '_dummyjson_updated_at', sanitize_text_field($product['meta']['updatedAt']));
        update_post_meta($post_id, '_dummyjson_full_data', wp_json_encode($product));

        // Taxonomies grouping
        if (!empty($product['category'])) {
            wp_set_object_terms($post_id, sanitize_text_field($product['category']), 'product_cat', true);
        }
        if (!empty($product['tags'])) {
            wp_set_object_terms($post_id, array_map('sanitize_text_field', $product['tags']), 'product_tag', true);
        }

        // Images
        if (! empty($product['images'][0])) {
            $img_id = wc_api_importer_download_image($product['images'][0], $post_id);
            if ($img_id) set_post_thumbnail($post_id, $img_id);
        }

        // Insert reviews as comments
        if (!empty($product['reviews'])) {
            foreach ($product['reviews'] as $rev) {
                wp_insert_comment([
                    'comment_post_ID'   => $post_id,
                    'comment_author'    => sanitize_text_field($rev['reviewerName']),
                    'comment_author_email'=> sanitize_email($rev['reviewerEmail']),
                    'comment_content'   => sanitize_textarea_field($rev['comment']),
                    'comment_type'      => 'review',
                    'comment_approved'  => 1,
                    'comment_date'      => date('Y-m-d H:i:s', strtotime($rev['date'])),
                    'meta_input'        => ['rating'=>intval($rev['rating'])]
                ]);
            }
        }

        wp_reset_postdata();
    }

    echo '<p><strong>Import completed.</strong></p>';
}



//  7. AJAX handler — ADD HERE AT THE BOTTOM
add_action('wp_ajax_wc_api_import_products', 'wc_api_import_products_ajax');
function wc_api_import_products_ajax() {
    check_ajax_referer('wc_api_import_nonce', 'security');
    ob_start();
    wc_api_import_products();
    $output = ob_get_clean();
    wp_send_json_success($output);
}