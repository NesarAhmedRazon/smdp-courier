<?php 

defined('ABSPATH') || exit;

// Hook to add settings tab in WooCommerce 
add_filter('woocommerce_settings_tabs_array', 'add_pathao_settings_tab', 50); 
function add_pathao_settings_tab($tabs) { 
    $tabs['pathao'] = __('Pathao Auth', SMDP_COURIER_DOMAIN); 
    return $tabs; 
}

// Settings fields
add_action('woocommerce_settings_tabs_pathao', 'pathao_settings_tab'); 
function pathao_settings_tab() { 
    woocommerce_admin_fields(get_pathao_settings()); 
    pathao_auth_add_token_link();
}

// Update settings
add_action('woocommerce_update_options_pathao', 'update_pathao_settings'); 
function update_pathao_settings() { 
    woocommerce_update_options(get_pathao_settings()); 
}