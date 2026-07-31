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

        $sql = "CREATE TABLE IF NOT EXISTS `$table_name` (
            `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `label` VARCHAR(255) NOT NULL,
            `parent_id` BIGINT(20) UNSIGNED DEFAULT 0,
            `sys_id` BIGINT(20) UNSIGNED DEFAULT 0,
            `provider_name` VARCHAR(255) NOT NULL,
            `fetched` BOOLEAN DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `parent_idx` (`parent_id`),
            UNIQUE KEY `unique_sys_provider` (`provider_name`, `sys_id`)
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



// Render Fields

if(!function_exists('consignment_metas_shipping')){
    add_action('woocommerce_admin_order_data_after_shipping_address', 'consignment_metas_shipping');
    function consignment_metas_shipping($order)
    {
        if (!$order instanceof WC_Order) return;

        $order_id = $order->get_id();

        // Consignment and customer data
        $consignment_id    = $order->get_meta('_consignment_id', true);
        
        // ✅ Try structured address first (HPOS safe)
        $address_data = $order->get_meta('_customer_address', true); // Fixed: was '_pathao_address'
        $city = $zone = $area = null;

        if (is_array($address_data) && !empty($address_data)) {
            $city = $address_data['city'] ?? null;
            $zone = $address_data['zone'] ?? null;
            $area = $address_data['area'] ?? null;
        }

        // ✅ Fallback to old single meta keys (legacy support)
        if (empty($city)) {
            $city = $order->get_meta('_pathao_city', true);
            if (empty($city)) {
                $city = $order->get_meta('_consignment_city', true);
            }
        }

        if (empty($zone)) {
            $zone = $order->get_meta('_pathao_zone', true);
            if (empty($zone)) {
                $zone = $order->get_meta('_consignment_zone', true);
            }
        }

        if (empty($area)) {
            $area = $order->get_meta('_pathao_area', true);
            if (empty($area)) {
                $area = $order->get_meta('_consignment_area', true);
            }
        }

        $order_zone = $order->get_shipping_city();
        $order_city = smdp_get_shipping_state_label( $order );
        $provider = smdp_get_order_shipping_provider($order); 
       
        $cities_list = smdp_get_parent_locations($provider,true);
         
        $zone_list = [];
        $area_list = [];

        if(!empty($city)){
            $zone_list = smdp_get_child_locations($city, $provider);
        }
        
        if(!empty($zone)){
            $area_list = smdp_get_child_locations($zone, $provider);
        }
        
        wp_nonce_field('locations_save', 'meta_locations_nonce');
        
        ?>
        <div class="address-section">
            <h4 style="margin-top:0;"><?php _e('Delivery Information', SMDP_TEXTDOMAIN); ?></h4>
            <div class="meta_item_grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap:10px;">
                <!-- City Selection -->
                <div class="form-field form-field-wide" data-provider="<?=$provider;?>" >
                    <label for="consignment_city"><?php _e('City', SMDP_TEXTDOMAIN); ?>:</label>
                    <select id="consignment_city" name="consignment_city" class="location-select" data-order-id="<?php echo $order_id; ?>" data-level="city" data-loaded="<?=count($cities_list);?>" data-current="<?=$city;?>">
                        <option value=""><?php _e('Select City', SMDP_TEXTDOMAIN); ?></option>
                    <?php                
                        foreach ($cities_list as $item) {                                     
                            $selected = ($city == $item['sys_id']) ? ' selected' : '';
                            // Auto-select if order city matches and no city is set
                            
                            if(empty($city) && $order_city == $item['label']){
                                $selected = ' selected';
                            }                        
                            echo '<option value="' . esc_attr($item['sys_id']) . '"' . $selected . '>' . esc_html($item['label']) . '</option>';
                        }
                    ?>                
                    </select>
                    <span class="loading" id="city-loading" style="display:none;">⟳</span>
                </div>

                <!-- Zone Selection -->
                <div class="form-field form-field-wide">
                    <label for="consignment_zone"><?php _e('Zone', SMDP_TEXTDOMAIN); ?>:</label>
                    <select id="consignment_zone" name="consignment_zone" class="location-select" data-order-id="<?php echo $order_id; ?>" data-level="zone" data-loaded="<?=count($zone_list);?>" data-current="<?=$zone;?>">
                        <option value=""><?php _e('Select Zone', SMDP_TEXTDOMAIN); ?></option>
                    <?php
                        if(!empty($zone_list)){
                            foreach ($zone_list as $item) {
                                $selected = ($zone == $item['sys_id']) ? ' selected' : '';
                                echo '<option value="' . esc_attr($item['sys_id']) . '"' . $selected . '>' . esc_html($item['label']) . '</option>';
                            }
                        } else{
                            echo '<option value="">' . __('No Zone Found', SMDP_TEXTDOMAIN) . '</option>';
                        }?>                
                    </select>
                    <span class="loading" id="zone-loading" style="display:none;">⟳</span>
                </div>

                <!-- Area Selection -->
                <div class="form-field form-field-wide">
                    <label for="consignment_area"><?php _e('Area', SMDP_TEXTDOMAIN); ?>:</label>
                    <select id="consignment_area" name="consignment_area" class="location-select" data-order-id="<?php echo $order_id; ?>" data-level="area" data-loaded="<?=count($area_list);?>" data-current="<?=$area;?>">
                        <option value=""><?php _e('Select Area', SMDP_TEXTDOMAIN); ?></option>
                    <?php
                        if(!empty($area_list)){
                            foreach ($area_list as $item) {
                                $selected = ($area == $item['sys_id']) ? ' selected' : '';
                                echo '<option value="' . esc_attr($item['sys_id']) . '"' . $selected . '>' . esc_html($item['label']) . '</option>';
                            }
                        } else{
                            echo '<option value="">' . __('No Area Found', SMDP_TEXTDOMAIN) . '</option>';
                        }?>                
                    </select>
                    <span class="loading" id="area-loading" style="display:none;">⟳</span>
                </div>

                <!-- Consignment ID -->
                <div class="form-field form-field-wide">
                    <label for="consignment_id"><?php _e('Consignment ID', SMDP_TEXTDOMAIN); ?>:</label>
                    <input type="text" id="consignment_id" name="consignment_id" value="<?php echo esc_attr($consignment_id); ?>" readonly>
                </div>
            </div>
        </div>        
    <?php
    
    }
}


/**
 * Enqueue admin JS for consignment location handling
 */
add_action('admin_enqueue_scripts', 'smdp_enqueue_consignment_script');
function smdp_enqueue_consignment_script($hook) {
    // Ensure WooCommerce is active
    if (!class_exists('WooCommerce')) {
        return;
    }

    // Check if we are on the HPOS order edit screen or classic one
    $screen = get_current_screen();
    if (empty($screen)) {
        return;
    }

    // Allow both classic and HPOS order edit pages
    $is_order_edit_screen =
        (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']) && $screen->id === 'woocommerce_page_wc-orders') ||
        ($screen->id === 'shop_order' && isset($_GET['post']));

    if (!$is_order_edit_screen) {
        return;
    }

    // Enqueue CSS file
    wp_enqueue_style(
        'smdp-consignment',
        SMDP_COURIER_URL . 'assets/css/smdp-consignment.css',
        [],
        time()
    );

    // Register + enqueue your script
    wp_register_script(
        'smdp-consignment-js',
        SMDP_COURIER_URL . 'assets/js/smdp-consignment.js', // adjust path as needed
        ['jquery'],
        time(),
        true
    );

    wp_localize_script('smdp-consignment-js', 'smdp_admin', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce_pathao_locations' => wp_create_nonce('locations_nonce'),
    ]);

    wp_enqueue_script('smdp-consignment-js');
}


// Save the meta keys
if(!function_exists('location_save_meta_fields_hpos')){
    add_action('woocommerce_process_shop_order_meta', 'location_save_meta_fields_hpos', 10, 2);
    function location_save_meta_fields_hpos($order_id, $order) {
        // Check nonce first
        if (empty($_POST['meta_locations_nonce']) || !wp_verify_nonce($_POST['meta_locations_nonce'], 'locations_save')) {
            return; // Don't send JSON in admin context
        }

        if (!current_user_can('edit_shop_orders')) {
            return;
        }

        // ✅ Sanitize and collect location data
        $city_id = isset($_POST['consignment_city']) ? sanitize_text_field($_POST['consignment_city']) : '';
        $zone_id = isset($_POST['consignment_zone']) ? sanitize_text_field($_POST['consignment_zone']) : '';
        $area_id = isset($_POST['consignment_area']) ? sanitize_text_field($_POST['consignment_area']) : '';

        // ✅ Structured address format
        $address_data = [
            'city' => $city_id,
            'zone' => $zone_id,
            'area' => $area_id,
        ];

        // ✅ Remove old individual meta data
        // $old_meta_keys = [
        //     '_consignment_city',
        //     '_consignment_zone', 
        //     '_consignment_area',
        //     '_pathao_city',
        //     '_pathao_zone',
        //     '_pathao_area'
        // ];
        
        // foreach ($old_meta_keys as $meta_key) {
        //     $order->delete_meta_data($meta_key);
        // }
        // ✅ Use HPOS update_meta_data()
        
        $order->update_meta_data('_customer_address', $address_data);

        // ✅ Save the order (HPOS-safe)
        $order->save();
    }
}


// AJAX handler for getting cities / zones / areas
add_action('wp_ajax_get_locations', 'get_locations_list');
function get_locations_list() {
    check_ajax_referer('locations_nonce', 'nonce');

    if (!current_user_can('edit_shop_orders')) {
        wp_send_json_error('Insufficient permissions');
    }

    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $pid = isset($_POST['parent']) ? intval($_POST['parent']) : 0;
    $find = isset($_POST['find']) ? sanitize_text_field($_POST['find']) : 'city';
    $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : 'pathaw'; 

    // ✅ Try to get cached locations first
    if ($find === 'city') {
        $list = smdp_get_parent_locations($provider);
    } else {
        
        $list = smdp_get_child_locations($pid,$provider);
    }

    if (!empty($list)) {
        error_log('data from DB list');
        wp_send_json_success($list);
    }

    // ✅ Otherwise, fetch from API
    $is_sandbox = get_option('pathao_sandbox') === 'yes';
    $token = pathao_get_valid_token($is_sandbox);
    if (!$token) {
        wp_send_json_error('No valid access token available');
    }

    $prefix = $is_sandbox ? 'pathao_sandbox_' : 'pathao_';
    $base_url = get_option($prefix . 'base_url');

    switch ($find) {
        case 'zone':
            $list = get_pathao_zones($token, $base_url, $pid);
            break;
        case 'area':
            $list = get_pathao_area($token, $base_url, $pid);
            break;
        default:
            $list = get_pathao_cities($token, $base_url);
            break;
    }

    if (empty($list) || !is_array($list)) {
        wp_send_json_error("Failed to fetch {$find}s from API");
    }

    $loc_data = [];
    foreach ($list as $item) {
        $name_key = "{$find}_name";
        $id_key   = "{$find}_id";

        if (!empty($item[$name_key])) {
            $loc_data[] = [
                'label'     => $item[$name_key],
                'sys_id'    => $item[$id_key],
                'parent_id' => $pid,
            ];
        }
    }

    if (!empty($loc_data)) {
        // ✅ Save to local DB for caching
        smdp_add_locations_bulk_safe($loc_data, $provider);
    }

    // ✅ Return the data we already have (no need to re-query DB)
    error_log('data after Fetch+DB');
    wp_send_json_success($loc_data);
}