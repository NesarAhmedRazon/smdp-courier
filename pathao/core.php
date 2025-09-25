<?php 

defined('ABSPATH') || exit;

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

include_once('order-create.php');
include_once('order-status.php');
include_once('web-hook.php');
include_once('pathao.php');
include_once('order-meta.php');
include_once('wc_tab.php');





/**
 * Automatically refresh token if expired
 * @param bool $is_sandbox Whether to refresh sandbox token
 * @return bool True if token is valid/refreshed, false otherwise
 */
function pathao_ensure_valid_token($is_sandbox = false) {
    $prefix = $is_sandbox ? 'pathao_sandbox_' : 'pathao_';
    
    $token = get_option($prefix . 'access_token');
    $expiry = get_option($prefix . 'access_expires_in', 0);
    $refresh_token = get_option($prefix . 'access_refresh_token');
    
    // Check if token exists
    if (empty($token)) {
        return false;
    }
    
    // Check if token is expired (with 5 minute buffer)
    $current_time = time();
    $buffer_time = 300; // 5 minutes
    
    if ($current_time >= ($expiry - $buffer_time)) {
        // Token is expired or about to expire, try to refresh
        if (empty($refresh_token)) {
            // No refresh token available, need manual authentication
            return false;
        }
        
        $base_url = get_option($prefix . 'base_url');
        $client_id = get_option($prefix . 'client_id');
        $client_secret = get_option($prefix . 'client_secret');
        
        $refresh_data = [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token,
            'base_url' => $base_url
        ];
        
        $new_token = pathao_auth_get_token($refresh_data);
        
        if ($new_token) {
            // Store new token with proper expiry time
            $new_expiry = time() + $new_token['expires_in'];
            update_option($prefix . 'access_token', $new_token['access_token']);
            update_option($prefix . 'access_expires_in', $new_expiry);
            update_option($prefix . 'access_refresh_token', $new_token['refresh_token']);
            
            // Log successful refresh
            error_log('Pathao token refreshed successfully for ' . ($is_sandbox ? 'sandbox' : 'live'));
            return true;
        } else {
            // Refresh failed
            error_log('Pathao token refresh failed for ' . ($is_sandbox ? 'sandbox' : 'live'));
            return false;
        }
    }
    
    return true; // Token is still valid
}

/**
 * Get valid access token (with auto-refresh)
 * @param bool $is_sandbox Whether to get sandbox token
 * @return string|false Valid access token or false if unavailable
 */
function pathao_get_valid_token($is_sandbox = false) {
    if (pathao_ensure_valid_token($is_sandbox)) {
        $prefix = $is_sandbox ? 'pathao_sandbox_' : 'pathao_';
        return get_option($prefix . 'access_token');
    }
    return false;
}

function get_pathao_settings() { 
    $current_time = time();
    
    // Live token info
    $live_token = get_option('pathao_access_token');
    $live_expiry = get_option('pathao_access_expires_in', 0);
    $live_expired = !empty($live_expiry) && $current_time > (int) $live_expiry;
    
    // Sandbox token info
    $sandbox = get_option('pathao_sandbox');
    $sandbox_token = get_option('pathao_sandbox_access_token');
    $sandbox_expiry = get_option('pathao_sandbox_access_expires_in', 0);
    $sandbox_expired = !empty($sandbox_expiry) && $current_time > (int) $sandbox_expiry;
    
    $settings = [
        'section_title' => [
            'name' => __('Pathao Auth Settings', SMDP_COURIER_DOMAIN), 
            'type' => 'title', 
            'desc' => '', 
            'id' => 'pathao_auth_section_title' 
        ],
        
        'client_id' => [
            'name' => __('Client ID', SMDP_COURIER_DOMAIN), 
            'type' => 'text', 
            'desc' => __('Enter your Pathao Client ID.', SMDP_COURIER_DOMAIN), 
            'id' => 'pathao_client_id' 
        ], 
        
        'client_secret' => [
            'name' => __('Client Secret', SMDP_COURIER_DOMAIN), 
            'type' => 'text', 
            'desc' => __('Enter your Pathao Client Secret.', SMDP_COURIER_DOMAIN), 
            'id' => 'pathao_client_secret' 
        ], 
        
        'client_email' => [
            'name' => __('Client Email', SMDP_COURIER_DOMAIN), 
            'type' => 'text', 
            'desc' => __('Enter your Pathao Client Email.', SMDP_COURIER_DOMAIN), 
            'id' => 'pathao_client_email' 
        ], 
        
        'client_password' => [
            'name' => __('Client Password', SMDP_COURIER_DOMAIN), 
            'type' => 'password', 
            'desc' => __('Enter your Pathao Client Password.', SMDP_COURIER_DOMAIN), 
            'id' => 'pathao_client_password' 
        ], 
        
        'base_url' => [
            'name' => __('Base URL', SMDP_COURIER_DOMAIN), 
            'type' => 'text', 
            'desc' => __('Enter the Base URL.', SMDP_COURIER_DOMAIN), 
            'id' => 'pathao_base_url' 
        ],
        
        'access_token' => [
            'name' => __('Access Token', SMDP_COURIER_DOMAIN), 
            'type' => 'textarea', 
            'desc' => __('This is your Pathao Access Token.', SMDP_COURIER_DOMAIN) . 
                     ($live_expired ? ' <span style="color:red;"><strong>(EXPIRED)</strong></span>' : ' <span style="color:green;"><strong>(Valid)</strong></span>') .
                     (!empty($live_expiry) ? '<br><strong>Expires:</strong> ' . date('Y-m-d H:i:s', $live_expiry) : ''),
            'desc_tip' => false,
            'id' => 'pathao_access_token', 
            'custom_attributes' => [ 
                'readonly' => 'readonly' 
            ]
        ], 
        
        'webhook_secret' => [
            'name' => __('Webhook Secret', SMDP_COURIER_DOMAIN), 
            'type' => 'password',    
            'desc' => __('Enter your Pathao Webhook Secret.', SMDP_COURIER_DOMAIN),
            'id' => 'pathao_webhook_secret'                 
        ],
        'response_header_secret' => [
            'name' => __('Response Header Secret', SMDP_COURIER_DOMAIN), 
            'type' => 'password',    
            'desc' => __('Enter your Pathao Response Header Secret.', SMDP_COURIER_DOMAIN),
            'id' => 'pathao_response_header_secret'                 
        ],
    ]; 

    // Add live store selection if token is valid
    if (!empty($live_token) && !$live_expired) {
        $live_base_url = get_option('pathao_base_url');
        $store_list = get_pathao_stores($live_token, $live_base_url);
        $store_options = ['' => __('Select Store', SMDP_COURIER_DOMAIN)];

        if (!empty($store_list)) {
            foreach ($store_list as $store) {
                $store_options[$store['store_id']] = $store['store_name'] . ' (' . $store['store_address'] . ')';
            }
        }
        
        $settings['store_id'] = [
            'name' => __('Store ID', SMDP_COURIER_DOMAIN), 
            'type' => 'select', 
            'options' => $store_options,
            'class' => 'wc-enhanced-select',
            'desc' => __('Select your Pathao Store.', SMDP_COURIER_DOMAIN), 
            'id' => 'pathao_store_id'
        ];
    }

    // Sandbox settings
    $settings = array_merge($settings, [
        'sandbox' => [
            'name' => __('Sandbox Mode', SMDP_COURIER_DOMAIN), 
            'type' => 'radio', 
            'default' => 'no',
            'options' => [ 
                'yes' => __('Yes', SMDP_COURIER_DOMAIN), 
                'no' => __('No', SMDP_COURIER_DOMAIN) 
            ],
            'desc' => __('Enable sandbox mode for testing.', SMDP_COURIER_DOMAIN), 
            'id' => 'pathao_sandbox' 
        ], 
        
        'sandbox_client_id' => [
            'name' => __('Sandbox Client ID', SMDP_COURIER_DOMAIN), 
            'type' => 'text', 
            'desc' => __('Enter your Pathao Sandbox Client ID.', SMDP_COURIER_DOMAIN), 
            'id' => 'pathao_sandbox_client_id' 
        ],
        
        'sandbox_client_secret' => [
            'name' => __('Sandbox Client Secret', SMDP_COURIER_DOMAIN), 
            'type' => 'text', 
            'desc' => __('Enter your Pathao Sandbox Client Secret.', SMDP_COURIER_DOMAIN), 
            'id' => 'pathao_sandbox_client_secret' 
        ],
        
        'sandbox_client_email' => [
            'name' => __('Sandbox Client Email', SMDP_COURIER_DOMAIN), 
            'type' => 'text', 
            'desc' => __('Enter your Pathao Sandbox Client Email.', SMDP_COURIER_DOMAIN), 
            'id' => 'pathao_sandbox_client_email' 
        ],
        
        'sandbox_client_password' => [
            'name' => __('Sandbox Client Password', SMDP_COURIER_DOMAIN), 
            'type' => 'password', 
            'desc' => __('Enter your Pathao Sandbox Client Password.', SMDP_COURIER_DOMAIN), 
            'id' => 'pathao_sandbox_client_password' 
        ],
        
        'sandbox_base_url' => [
            'name' => __('Sandbox Base URL', SMDP_COURIER_DOMAIN), 
            'type' => 'text', 
            'desc' => __('Enter the Sandbox Base URL.', SMDP_COURIER_DOMAIN), 
            'id' => 'pathao_sandbox_base_url' 
        ],
        
        'sandbox_access_token' => [
            'name' => __('Sandbox Access Token', SMDP_COURIER_DOMAIN), 
            'type' => 'textarea', 
            'desc' => __('This is your Pathao Sandbox Access Token.', SMDP_COURIER_DOMAIN) . 
                     ($sandbox_expired ? ' <span style="color:red;"><strong>(EXPIRED)</strong></span>' : ' <span style="color:green;"><strong>(Valid)</strong></span>') .
                     (!empty($sandbox_expiry) ? '<br><strong>Expires:</strong> ' . date('Y-m-d H:i:s', $sandbox_expiry) : ''),
            'desc_tip' => false,
            'id' => 'pathao_sandbox_access_token', 
            'custom_attributes' => [ 
                'readonly' => 'readonly' 
            ],
        ],
    ]);

    // Add sandbox store selection if token is valid
    if ($sandbox == 'yes' && !empty($sandbox_token) && !$sandbox_expired) {
        $sandbox_base_url = get_option('pathao_sandbox_base_url');
        $store_list = get_pathao_stores($sandbox_token, $sandbox_base_url);
        $store_options = ['' => __('Select Store', SMDP_COURIER_DOMAIN)];

        if (!empty($store_list)) {
            foreach ($store_list as $store) {
                $store_options[$store['store_id']] = $store['store_name'] . ' (' . $store['store_address'] . ')';
            }
        }
        
        $settings['sandbox_store_id'] = [
            'name' => __('Sandbox Store ID', SMDP_COURIER_DOMAIN), 
            'type' => 'select', 
            'options' => $store_options,
            'class' => 'wc-enhanced-select',
            'desc' => __('Select your Pathao Sandbox Store.', SMDP_COURIER_DOMAIN), 
            'id' => 'pathao_sandbox_store_id'
        ];
    }

    $settings['section_end'] = [
        'type' => 'sectionend', 
        'id' => 'pathao_auth_section_end'
    ];

    return $settings;
}

function pathao_auth_add_token_link() { 
    if (isset($_GET['tab']) && $_GET['tab'] === 'pathao') { 
        echo '<div style="margin: 10px 0;">';
        echo '<a class="button button-primary" href="' . esc_url(add_query_arg('action', 'get_token')) . '">Get/Refresh Access Token</a> ';
        echo '<a class="button button-secondary" href="' . esc_url(add_query_arg('action', 'get_sandbox_token')) . '">Get/Refresh Sandbox Token</a>';
        echo '</div>';
    } 

    // Handle token requests
    if (isset($_GET['action'])) {
        if ($_GET['action'] === 'get_token') { 
            pathao_handle_token_request(false);
        } elseif ($_GET['action'] === 'get_sandbox_token') { 
            pathao_handle_token_request(true);
        }
    }
}

/**
 * Handle token request (initial or refresh)
 * @param bool $is_sandbox Whether this is for sandbox
 */
function pathao_handle_token_request($is_sandbox = false) {
    $prefix = $is_sandbox ? 'pathao_sandbox_' : 'pathao_';
    $label = $is_sandbox ? 'Sandbox' : 'Live';
    
    $client_id = get_option($prefix . 'client_id');
    $client_secret = get_option($prefix . 'client_secret');
    $client_email = get_option($prefix . 'client_email');
    $client_password = get_option($prefix . 'client_password');
    $base_url = get_option($prefix . 'base_url');
    
    // Validate required fields
    if (empty($client_id) || empty($client_secret) || empty($base_url)) {
        add_action('admin_notices', function() use ($label) {
            echo '<div class="notice notice-error"><p>' . sprintf(__('%s credentials are incomplete. Please fill all required fields.', SMDP_COURIER_DOMAIN), $label) . '</p></div>';
        });
        return;
    }
    
    // Check if we should try refresh first
    $current_token = get_option($prefix . 'access_token');
    $refresh_token = get_option($prefix . 'access_refresh_token');
    $expiry = get_option($prefix . 'access_expires_in', 0);
    $current_time = time();
    $expired = $current_time > (int) $expiry;
    
    $data = [];
    
    if (!empty($current_token) && !empty($refresh_token) && $expired) {
        // Try refresh token first
        $data = [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token,
            'base_url' => $base_url
        ];
    } else {
        // Use password grant
        if (empty($client_email) || empty($client_password)) {
            add_action('admin_notices', function() use ($label) {
                echo '<div class="notice notice-error"><p>' . sprintf(__('%s email and password are required for initial authentication.', SMDP_COURIER_DOMAIN), $label) . '</p></div>';
            });
            return;
        }
        
        $data = [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'username' => $client_email,
            'password' => $client_password,
            'base_url' => $base_url,
            'grant_type' => 'password'
        ];
    }
    
    $token = pathao_auth_get_token($data);
    
    if ($token) {
        // Store token with proper expiry time (current time + expires_in seconds)
        $new_expiry = time() + $token['expires_in'];
        update_option($prefix . 'access_token', $token['access_token']);
        update_option($prefix . 'access_expires_in', $new_expiry);
        update_option($prefix . 'access_refresh_token', $token['refresh_token']);
        
        add_action('admin_notices', function() use ($label, $new_expiry) {
            echo '<div class="notice notice-success"><p>' . 
                 sprintf(__('%s token obtained successfully! Expires: %s', SMDP_COURIER_DOMAIN), $label, date('Y-m-d H:i:s', $new_expiry)) . 
                 '</p></div>';
        });
    } else {
        add_action('admin_notices', function() use ($label) {
            echo '<div class="notice notice-error"><p>' . sprintf(__('Failed to obtain %s token. Please check your credentials.', SMDP_COURIER_DOMAIN), $label) . '</p></div>';
        });
    }
}

function pathao_auth_get_token($data) { 
    $url = $data['base_url'] . 'aladdin/api/v1/issue-token'; 
    
    $post_data = [
        'client_id' => $data['client_id'], 
        'client_secret' => $data['client_secret'], 
        'grant_type' => $data['grant_type']
    ];
    
    // Add username/password for password grant, refresh_token for refresh grant
    if ($data['grant_type'] === 'password') {
        $post_data['username'] = $data['username'];
        $post_data['password'] = $data['password'];
    } elseif ($data['grant_type'] === 'refresh_token') {
        $post_data['refresh_token'] = $data['refresh_token'];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
    curl_setopt($ch, CURLOPT_POST, 1); 
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [ 
        'Accept: application/json', 
        'Content-Type: application/json'
    ]); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Only for testing

    $result = curl_exec($ch); 
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch); 

    if ($curl_error) {
        error_log('Pathao cURL Error: ' . $curl_error);
        return false;
    }

    if ($http_code !== 200) {
        error_log('Pathao HTTP Error: ' . $http_code . ' - ' . $result);
        return false;
    }

    $response = json_decode($result, true); 

    if (isset($response['access_token'])) {
        return [
            'access_token' => $response['access_token'],
            'expires_in' => $response['expires_in'],
            'refresh_token' => $response['refresh_token'] ?? '',
        ];
    } else { 
        error_log('Pathao Token Error: ' . $result);
        return false; 
    } 
}

// Updated function to use auto-refresh
function get_pathao_order_info($consignment_id) { 
    $is_sandbox = get_option('pathao_sandbox') === 'yes';
    $access_token = pathao_get_valid_token($is_sandbox);
    
    if (!$access_token) {
        return array('error' => 'No valid access token available. Please authenticate first.');
    }
    
    $prefix = $is_sandbox ? 'pathao_sandbox_' : 'pathao_';
    $base_url = get_option($prefix . 'base_url');
    $url = $base_url . 'aladdin/api/v1/orders/' . $consignment_id . '/info'; 
    
    $response = wp_remote_get($url, array( 
        'headers' => array( 
            'Authorization' => 'Bearer ' . $access_token, 
            'Content-Type' => 'application/json', 
            'Accept' => 'application/json' 
        ),
        'timeout' => 30
    )); 
    
    if (is_wp_error($response)) { 
        return array('error' => $response->get_error_message()); 
    } 
    
    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response); 
    $data = json_decode($body, true); 
    
    // If unauthorized, try to refresh token once
    if ($http_code === 401) {
        if (pathao_ensure_valid_token($is_sandbox)) {
            // Token refreshed, retry the request
            $access_token = pathao_get_valid_token($is_sandbox);
            $response = wp_remote_get($url, array( 
                'headers' => array( 
                    'Authorization' => 'Bearer ' . $access_token, 
                    'Content-Type' => 'application/json', 
                    'Accept' => 'application/json' 
                ),
                'timeout' => 30
            )); 
            
            if (!is_wp_error($response)) {
                $body = wp_remote_retrieve_body($response); 
                $data = json_decode($body, true);
            }
        }
    }
    
    if (isset($data['data'])) { 
        return $data['data']; 
    } else { 
        return array('error' => isset($data['message']) ? $data['message'] : 'Failed to retrieve order info'); 
    } 
}

// Database table creation (unchanged)
if (!function_exists('create_pathaw_order_table')) {
    function create_pathaw_order_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pathaw_order';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id varchar(255) DEFAULT NULL, 
            consignment_id varchar(255) NOT NULL,                
            store_id varchar(255) NOT NULL,
            event varchar(255) NOT NULL,
            consignment_fee varchar(255) DEFAULT NULL,
            collected_amount varchar(255) DEFAULT NULL,
            reason varchar(255) DEFAULT NULL,
            invoice_id varchar(255) DEFAULT NULL,
            updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    register_activation_hook(__FILE__, 'create_pathaw_order_table');
}

// Cron job to refresh tokens periodically (optional)
add_action('wp', 'pathao_schedule_token_refresh');
function pathao_schedule_token_refresh() {
    if (!wp_next_scheduled('pathao_refresh_tokens')) {
        wp_schedule_event(time(), 'daily', 'pathao_refresh_tokens');
    }
}

add_action('pathao_refresh_tokens', 'pathao_daily_token_refresh');
function pathao_daily_token_refresh() {
    // Refresh live token if needed
    pathao_ensure_valid_token(false);
    
    // Refresh sandbox token if needed
    if (get_option('pathao_sandbox') === 'yes') {
        pathao_ensure_valid_token(true);
    }
}


// Add this function after the settings
function pathao_admin_styles() {
    if (isset($_GET['tab']) && $_GET['tab'] === 'pathao') {
        echo '<style>
        .pathao-token-valid { color: #00a32a; font-weight: bold; }
        .pathao-token-expired { color: #d63638; font-weight: bold; }
        </style>';
    }
}
add_action('admin_head', 'pathao_admin_styles');