<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create smdp_areas table (only if not exists)
 */
// Database table creation (with 'fetched' field)

    function smdp_create_locations_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'smdp_locations';
        $table_exists = $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $table_name) ); 
        if ($table_exists === $table_name) 
        { 
            return; // already exists 
        }

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            label VARCHAR(255) NOT NULL,
            parent_id BIGINT(20) UNSIGNED DEFAULT 0,
            provider_name VARCHAR(255) NOT NULL,
            fetched TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY parent_idx (parent_id),
            KEY provider_namex (provider_name)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        error_log('db created');
        dbDelta($sql);
    }

    register_activation_hook(__FILE__, 'smdp_create_locations_table');



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
        'Create Areas Tablex',
        'smdp_create_table_button_html',
        'general',
        'smdp_section'
    );
});

// Add button to settings page
function smdp_create_table_button_html() {
    $url = wp_nonce_url(
        admin_url('admin-post.php?action=smdp_create_table'),
        'smdp_create_table_nonce'
    );
    echo '<a href="' . esc_url($url) . '" class="button button-primary">Create SMDP Loc Table</a>';
}

// Handle button click
add_action('admin_post_smdp_create_table', function() {
    error_log('=== admin_post_smdp_create_table triggered ===');

    if ( ! current_user_can('manage_options') ) {
        wp_die('Permission denied');
    }

    check_admin_referer('smdp_create_table_nonce');

    if ( function_exists('smdp_create_locations_table') ) {
        error_log('Running smdp_create_locations_table()');
        smdp_create_locations_table();
    } else {
        error_log('Function smdp_create_locations_table() not found!');
    }

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
