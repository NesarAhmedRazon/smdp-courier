<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

register_activation_hook(__FILE__, 'smdp_create_areas_table');

/**
 * Create smdp_areas table (only if not exists)
 */
function smdp_create_areas_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'smdp_areas';
    $table_exists = $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $table_name) );

    if ($table_exists === $table_name) {
        return; // already exists
    }

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        shipper varchar(255) DEFAULT NULL,
        parent bigint(20) unsigned DEFAULT 0,
        wc_id bigint(20) unsigned DEFAULT 0,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Add the button in Settings → General
 */
add_action('admin_init', function() {
    add_settings_section(
        'smdp_section',
        'SMDP Database Tools',
        '__return_false',
        'general'
    );

    add_settings_field(
        'smdp_create_table_btn',
        'Create Areas Table',
        'smdp_create_table_button_html',
        'general',
        'smdp_section'
    );
});

/**
 * HTML for the button
 */
function smdp_create_table_button_html() {
    $url = wp_nonce_url(
        admin_url('admin-post.php?action=smdp_create_table'),
        'smdp_create_table_nonce'
    );
    echo '<a href="' . esc_url($url) . '" class="button button-primary">Create SMDP Areas Table</a>';
}

/**
 * Handle button click
 */
add_action('admin_post_smdp_create_table', function() {
    if ( ! current_user_can('manage_options') ) {
        wp_die('Permission denied');
    }

    check_admin_referer('smdp_create_table_nonce');

    smdp_create_areas_table();

    wp_safe_redirect( add_query_arg('smdp_table_created', '1', wp_get_referer()) );
    exit;
});

/**
 * Show success message after creation
 */
add_action('admin_notices', function() {
    if ( isset($_GET['smdp_table_created']) ) {
        echo '<div class="notice notice-success is-dismissible"><p><strong>SMDP Areas Table</strong> checked/created successfully!</p></div>';
    }
});
