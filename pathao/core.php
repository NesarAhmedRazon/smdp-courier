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
function pathao_ensure_valid_token($is_sandbox = false)
{
    $prefix = $is_sandbox ? 'pathao_sandbox_' : 'pathao_';

    $token = get_option($prefix . 'access_token');
    $expiry = get_option($prefix . 'access_expires_in', 0);
    $refresh_token = get_option($prefix . 'access_refresh_token');

    // Check if token exists
    if (empty($token)) {
        error_log('Pathao: No token found for ' . ($is_sandbox ? 'sandbox' : 'live'));
        return false;
    }

    // Check if token is expired (with 5 minute buffer)
    $current_time = time();
    $buffer_time = 300; // 5 minutes

    if ($current_time >= ($expiry - $buffer_time)) {
        error_log('Pathao: Token expired or expiring soon for ' . ($is_sandbox ? 'sandbox' : 'live') . '. Attempting refresh...');

        // Token is expired or about to expire, try to refresh
        if (empty($refresh_token)) {
            error_log('Pathao: No refresh token available for ' . ($is_sandbox ? 'sandbox' : 'live'));
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

            error_log('Pathao: Token refreshed successfully for ' . ($is_sandbox ? 'sandbox' : 'live') . '. Expires: ' . date('Y-m-d H:i:s', $new_expiry));
            return true;
        } else {
            error_log('Pathao: Token refresh FAILED for ' . ($is_sandbox ? 'sandbox' : 'live'));
            return false;
        }
    }

    error_log('Pathao: Token is valid for ' . ($is_sandbox ? 'sandbox' : 'live') . '. Expires: ' . date('Y-m-d H:i:s', $expiry));
    return true; // Token is still valid
}

/**
 * Get valid access token (with auto-refresh)
 * @param bool $is_sandbox Whether to get sandbox token
 * @return string|false Valid access token or false if unavailable
 */
function pathao_get_valid_token($is_sandbox = false)
{
    if (pathao_ensure_valid_token($is_sandbox)) {
        $prefix = $is_sandbox ? 'pathao_sandbox_' : 'pathao_';
        $token = get_option($prefix . 'access_token');
        error_log('Pathao: Retrieved valid token for ' . ($is_sandbox ? 'sandbox' : 'live'));
        return $token;
    }
    error_log('Pathao: Failed to get valid token for ' . ($is_sandbox ? 'sandbox' : 'live'));
    return false;
}

function get_pathao_settings()
{
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
            'name' => __('Pathao Auth Settings', SMDP_TEXTDOMAIN),
            'type' => 'title',
            'desc' => '',
            'id' => 'pathao_auth_section_title'
        ],

        'client_id' => [
            'name' => __('Client ID', SMDP_TEXTDOMAIN),
            'type' => 'text',
            'desc' => __('Enter your Pathao Client ID.', SMDP_TEXTDOMAIN),
            'id' => 'pathao_client_id'
        ],

        'client_secret' => [
            'name' => __('Client Secret', SMDP_TEXTDOMAIN),
            'type' => 'text',
            'desc' => __('Enter your Pathao Client Secret.', SMDP_TEXTDOMAIN),
            'id' => 'pathao_client_secret'
        ],

        'client_email' => [
            'name' => __('Client Email', SMDP_TEXTDOMAIN),
            'type' => 'text',
            'desc' => __('Enter your Pathao Client Email.', SMDP_TEXTDOMAIN),
            'id' => 'pathao_client_email'
        ],

        'client_password' => [
            'name' => __('Client Password', SMDP_TEXTDOMAIN),
            'type' => 'password',
            'desc' => __('Enter your Pathao Client Password.', SMDP_TEXTDOMAIN),
            'id' => 'pathao_client_password'
        ],

        'base_url' => [
            'name' => __('Base URL', SMDP_TEXTDOMAIN),
            'type' => 'text',
            'desc' => __('Enter the Base URL.', SMDP_TEXTDOMAIN),
            'id' => 'pathao_base_url'
        ],

        'access_token' => [
            'name' => __('Access Token', SMDP_TEXTDOMAIN),
            'type' => 'textarea',
            'desc' => __('This is your Pathao Access Token.', SMDP_TEXTDOMAIN) .
                ($live_expired ? ' <span style="color:red;"><strong>(EXPIRED)</strong></span>' : ' <span style="color:green;"><strong>(Valid)</strong></span>') .
                (!empty($live_expiry) ? '<br><strong>Expires:</strong> ' . date('Y-m-d H:i:s', $live_expiry) : ''),
            'desc_tip' => false,
            'id' => 'pathao_access_token',
            'custom_attributes' => [
                'readonly' => 'readonly'
            ]
        ],

        'webhook_secret' => [
            'name' => __('Webhook Secret', SMDP_TEXTDOMAIN),
            'type' => 'password',
            'desc' => __('Enter your Pathao Webhook Secret.', SMDP_TEXTDOMAIN),
            'id' => 'pathao_webhook_secret'
        ],
        'response_header_secret' => [
            'name' => __('Response Header Secret', SMDP_TEXTDOMAIN),
            'type' => 'password',
            'desc' => __('Enter your Pathao Response Header Secret.', SMDP_TEXTDOMAIN),
            'id' => 'pathao_response_header_secret'
        ],
    ];

    // Add live store selection if token is valid
    if (!empty($live_token) && !$live_expired) {
        $live_base_url = get_option('pathao_base_url');
        $store_list = get_pathao_stores($live_token, $live_base_url);
        $store_options = ['' => __('Select Store', SMDP_TEXTDOMAIN)];

        if (!empty($store_list)) {
            foreach ($store_list as $store) {
                $store_options[$store['store_id']] = $store['store_name'] . ' (' . $store['store_address'] . ')';
            }
        }

        $settings['store_id'] = [
            'name' => __('Store ID', SMDP_TEXTDOMAIN),
            'type' => 'select',
            'options' => $store_options,
            'class' => 'wc-enhanced-select',
            'desc' => __('Select your Pathao Store.', SMDP_TEXTDOMAIN),
            'id' => 'pathao_store_id'
        ];
    }

    // Sandbox settings
    $settings = array_merge($settings, [
        'sandbox' => [
            'name' => __('Sandbox Mode', SMDP_TEXTDOMAIN),
            'type' => 'radio',
            'default' => 'no',
            'options' => [
                'yes' => __('Yes', SMDP_TEXTDOMAIN),
                'no' => __('No', SMDP_TEXTDOMAIN)
            ],
            'desc' => __('Enable sandbox mode for testing.', SMDP_TEXTDOMAIN),
            'id' => 'pathao_sandbox'
        ],

        'sandbox_client_id' => [
            'name' => __('Sandbox Client ID', SMDP_TEXTDOMAIN),
            'type' => 'text',
            'desc' => __('Enter your Pathao Sandbox Client ID.', SMDP_TEXTDOMAIN),
            'id' => 'pathao_sandbox_client_id'
        ],

        'sandbox_client_secret' => [
            'name' => __('Sandbox Client Secret', SMDP_TEXTDOMAIN),
            'type' => 'text',
            'desc' => __('Enter your Pathao Sandbox Client Secret.', SMDP_TEXTDOMAIN),
            'id' => 'pathao_sandbox_client_secret'
        ],

        'sandbox_client_email' => [
            'name' => __('Sandbox Client Email', SMDP_TEXTDOMAIN),
            'type' => 'text',
            'desc' => __('Enter your Pathao Sandbox Client Email.', SMDP_TEXTDOMAIN),
            'id' => 'pathao_sandbox_client_email'
        ],

        'sandbox_client_password' => [
            'name' => __('Sandbox Client Password', SMDP_TEXTDOMAIN),
            'type' => 'password',
            'desc' => __('Enter your Pathao Sandbox Client Password.', SMDP_TEXTDOMAIN),
            'id' => 'pathao_sandbox_client_password'
        ],

        'sandbox_base_url' => [
            'name' => __('Sandbox Base URL', SMDP_TEXTDOMAIN),
            'type' => 'text',
            'desc' => __('Enter the Sandbox Base URL.', SMDP_TEXTDOMAIN),
            'id' => 'pathao_sandbox_base_url'
        ],

        'sandbox_access_token' => [
            'name' => __('Sandbox Access Token', SMDP_TEXTDOMAIN),
            'type' => 'textarea',
            'desc' => __('This is your Pathao Sandbox Access Token.', SMDP_TEXTDOMAIN) .
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
        $store_options = ['' => __('Select Store', SMDP_TEXTDOMAIN)];

        if (!empty($store_list)) {
            foreach ($store_list as $store) {
                $store_options[$store['store_id']] = $store['store_name'] . ' (' . $store['store_address'] . ')';
            }
        }

        $settings['sandbox_store_id'] = [
            'name' => __('Sandbox Store ID', SMDP_TEXTDOMAIN),
            'type' => 'select',
            'options' => $store_options,
            'class' => 'wc-enhanced-select',
            'desc' => __('Select your Pathao Sandbox Store.', SMDP_TEXTDOMAIN),
            'id' => 'pathao_sandbox_store_id'
        ];
    }

    $settings['section_end'] = [
        'type' => 'sectionend',
        'id' => 'pathao_auth_section_end'
    ];

    return $settings;
}

function pathao_auth_add_token_link()
{
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
function pathao_handle_token_request($is_sandbox = false)
{
    $prefix = $is_sandbox ? 'pathao_sandbox_' : 'pathao_';
    $label = $is_sandbox ? 'Sandbox' : 'Live';

    $client_id = get_option($prefix . 'client_id');
    $client_secret = get_option($prefix . 'client_secret');
    $client_email = get_option($prefix . 'client_email');
    $client_password = get_option($prefix . 'client_password');
    $base_url = get_option($prefix . 'base_url');

    // Validate required fields
    if (empty($client_id) || empty($client_secret) || empty($base_url)) {
        add_action('admin_notices', function () use ($label) {
            echo '<div class="notice notice-error"><p>' . sprintf(__('%s credentials are incomplete. Please fill all required fields.', SMDP_TEXTDOMAIN), $label) . '</p></div>';
        });
        error_log('Pathao: ' . $label . ' credentials incomplete');
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
        error_log('Pathao: Attempting token refresh for ' . $label);
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
            add_action('admin_notices', function () use ($label) {
                echo '<div class="notice notice-error"><p>' . sprintf(__('%s email and password are required for initial authentication.', SMDP_TEXTDOMAIN), $label) . '</p></div>';
            });
            error_log('Pathao: ' . $label . ' email/password missing');
            return;
        }

        error_log('Pathao: Attempting password grant for ' . $label);
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

        add_action('admin_notices', function () use ($label, $new_expiry) {
            echo '<div class="notice notice-success"><p>' .
                sprintf(__('%s token obtained successfully! Expires: %s', SMDP_TEXTDOMAIN), $label, date('Y-m-d H:i:s', $new_expiry)) .
                '</p></div>';
        });
        error_log('Pathao: ' . $label . ' token obtained successfully. Expires: ' . date('Y-m-d H:i:s', $new_expiry));
    } else {
        add_action('admin_notices', function () use ($label) {
            echo '<div class="notice notice-error"><p>' . sprintf(__('Failed to obtain %s token. Please check your credentials.', SMDP_TEXTDOMAIN), $label) . '</p></div>';
        });
        error_log('Pathao: Failed to obtain ' . $label . ' token');
    }
}

/**
 * Get token using WordPress HTTP API (replaces cURL)
 */
function pathao_auth_get_token($data)
{
    $url = trailingslashit($data['base_url']) . 'aladdin/api/v1/issue-token';

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

    error_log('Pathao: Requesting token with grant_type: ' . $data['grant_type']);

    $response = wp_remote_post($url, [
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ],
        'body' => wp_json_encode($post_data),
        'timeout' => 30,
        'sslverify' => true // Enable SSL verification for production
    ]);

    if (is_wp_error($response)) {
        error_log('Pathao: WP_Error - ' . $response->get_error_message());
        return false;
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    error_log('Pathao: Token request - HTTP Code: ' . $http_code);
    error_log('Pathao: Token response: ' . $body);

    if ($http_code !== 200) {
        error_log('Pathao: HTTP Error ' . $http_code . ' - ' . $body);
        return false;
    }

    $result = json_decode($body, true);

    if (isset($result['access_token'])) {
        error_log('Pathao: Token received successfully. Expires in: ' . $result['expires_in'] . ' seconds');
        return [
            'access_token' => $result['access_token'],
            'expires_in' => $result['expires_in'],
            'refresh_token' => $result['refresh_token'] ?? '',
        ];
    } else {
        error_log('Pathao: Token Error - Invalid response structure: ' . $body);
        return false;
    }
}

/**
 * Get Pathao order info using WordPress HTTP API
 */
function get_pathao_order_info($consignment_id)
{
    $is_sandbox = get_option('pathao_sandbox') === 'yes';
    $access_token = pathao_get_valid_token($is_sandbox);

    if (!$access_token) {
        error_log('Pathao: No valid access token available for order info request');
        return array('error' => 'No valid access token available. Please authenticate first.');
    }

    $prefix = $is_sandbox ? 'pathao_sandbox_' : 'pathao_';
    $base_url = get_option($prefix . 'base_url');
    $url = trailingslashit($base_url) . 'aladdin/api/v1/orders/' . $consignment_id . '/info';

    error_log('Pathao: Requesting order info for consignment: ' . $consignment_id);

    $response = wp_remote_get($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ),
        'timeout' => 30,
        'sslverify' => true
    ));

    if (is_wp_error($response)) {
        error_log('Pathao: Order info WP_Error - ' . $response->get_error_message());
        return array('error' => $response->get_error_message());
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    error_log('Pathao: Order info response - HTTP Code: ' . $http_code);

    // If unauthorized, try to refresh token once
    if ($http_code === 401) {
        error_log('Pathao: 401 Unauthorized - attempting token refresh');

        if (pathao_ensure_valid_token($is_sandbox)) {
            // Token refreshed, retry the request
            $access_token = pathao_get_valid_token($is_sandbox);
            error_log('Pathao: Retrying order info request with refreshed token');

            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ),
                'timeout' => 30,
                'sslverify' => true
            ));

            if (!is_wp_error($response)) {
                $http_code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);
                error_log('Pathao: Retry response - HTTP Code: ' . $http_code);
            }
        } else {
            error_log('Pathao: Token refresh failed during order info request');
        }
    }

    if (isset($data['data'])) {
        error_log('Pathao: Order info retrieved successfully');
        return $data['data'];
    } else {
        $error_msg = isset($data['message']) ? $data['message'] : 'Failed to retrieve order info';
        error_log('Pathao: Order info error - ' . $error_msg);
        return array('error' => $error_msg);
    }
}

// Database table creation
if (!function_exists('create_pathaw_order_table')) {
    function create_pathaw_order_table()
    {
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

// Cron job to refresh tokens periodically
add_action('wp', 'pathao_schedule_token_refresh');
function pathao_schedule_token_refresh()
{
    if (!wp_next_scheduled('pathao_refresh_tokens')) {
        wp_schedule_event(time(), 'daily', 'pathao_refresh_tokens');
    }
}

add_action('pathao_refresh_tokens', 'pathao_daily_token_refresh');
function pathao_daily_token_refresh()
{
    error_log('Pathao: Running daily token refresh cron');

    // Refresh live token if needed
    pathao_ensure_valid_token(false);

    // Refresh sandbox token if needed
    if (get_option('pathao_sandbox') === 'yes') {
        pathao_ensure_valid_token(true);
    }
}

function pathao_admin_styles()
{
    if (isset($_GET['tab']) && $_GET['tab'] === 'pathao') {
        echo '<style>
        .pathao-token-valid { color: #00a32a; font-weight: bold; }
        .pathao-token-expired { color: #d63638; font-weight: bold; }
        </style>';
    }
}
add_action('admin_head', 'pathao_admin_styles');
