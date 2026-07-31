<?php

/**
 * Description:       Syncs WooCommerce customers to the MiddleMan CI4 webhook.
 *                    Triggers on new registration, manual sync from user edit page,
 *                    and bulk action from the users list.
 */

if (! defined('ABSPATH')) {
    exit;
}
require_once SMDP_COURIER_DIR . 'inc/sync-product-debug.php';
require_once SMDP_COURIER_DIR . 'inc/sync-helper.php';
// ─────────────────────────────────────────────
// 1.  Settings (General Settings page)
// ─────────────────────────────────────────────



// ─────────────────────────────────────────────
// 2.  Auto-sync on new WooCommerce customer registration
//     Fires for both email+password and social login (via woocommerce_created_customer)
// ─────────────────────────────────────────────

add_action('woocommerce_created_customer', 'cs_on_new_customer', 10, 3);
function cs_on_new_customer(int $customer_id, array $new_customer_data, bool $password_generated): void
{
    // Only sync customers — not shop managers, admins, etc.
    if (! cs_is_wc_customer($customer_id)) {
        return;
    }

    $result = cs_send_customer($customer_id);

    if ($result !== true) {
        error_log('Customer Sync: auto-sync failed for user #' . $customer_id . ' — ' . $result);
    }
}

// ─────────────────────────────────────────────
// 3.  "Sync to MiddleMan" button on user edit page
//     Only shown for WooCommerce customers
// ─────────────────────────────────────────────

add_action('edit_user_profile', 'cs_add_sync_button_to_user_edit');
add_action('show_user_profile', 'cs_add_sync_button_to_user_edit');
function cs_add_sync_button_to_user_edit(WP_User $user): void
{
    if (! cs_is_wc_customer($user->ID)) {
        return;
    }

    if (! current_user_can('edit_users')) {
        return;
    }

    $url = wp_nonce_url(
        add_query_arg([
            'action'  => 'cs_sync_customer',
            'user_id' => $user->ID,
        ], admin_url('admin-post.php')),
        'cs_sync_' . $user->ID
    );
?>
    <h2><?php esc_html_e('MiddleMan Sync', 'customer-sync'); ?></h2>
    <table class="form-table">
        <tr>
            <th><?php esc_html_e('Sync to MiddleMan', 'customer-sync'); ?></th>
            <td>
                <a href="<?php echo esc_url($url); ?>" class="button button-secondary">
                    &#x21BA; <?php esc_html_e('Sync this customer', 'customer-sync'); ?>
                </a>
                <p class="description">
                    <?php esc_html_e('Manually push this customer\'s data to the MiddleMan endpoint.', 'customer-sync'); ?>
                </p>
            </td>
        </tr>
    </table>
<?php
}

// ─────────────────────────────────────────────
// 4.  Handle individual sync action (from user edit page button)
// ─────────────────────────────────────────────

add_action('admin_post_cs_sync_customer', 'cs_handle_sync_action');
function cs_handle_sync_action(): void
{
    if (! current_user_can('edit_users')) {
        wp_die(esc_html__('You do not have permission to do this.', 'customer-sync'));
    }

    $user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;

    if (! $user_id) {
        wp_die(esc_html__('Invalid user ID.', 'customer-sync'));
    }

    check_admin_referer('cs_sync_' . $user_id);

    if (! cs_is_wc_customer($user_id)) {
        wp_die(esc_html__('This user is not a WooCommerce customer.', 'customer-sync'));
    }

    $result  = cs_send_customer($user_id);
    $referer = wp_get_referer() ?: admin_url('users.php');

    $referer = add_query_arg(
        'cs_sync',
        $result === true ? 'success' : rawurlencode($result),
        remove_query_arg('cs_sync', $referer)
    );

    wp_safe_redirect($referer);
    exit;
}

// ─────────────────────────────────────────────
// 5.  Bulk action — "Sync to MiddleMan" on users list
//     Only processes users with the 'customer' role
// ─────────────────────────────────────────────

// Register the bulk action
add_filter('bulk_actions-users', 'cs_register_bulk_action');
function cs_register_bulk_action(array $actions): array
{
    $actions['cs_bulk_sync'] = __('Sync to MiddleMan', 'customer-sync');
    return $actions;
}

// Handle the bulk action
add_filter('handle_bulk_actions-users', 'cs_handle_bulk_action', 10, 3);
function cs_handle_bulk_action(string $redirect_to, string $action, array $user_ids): string
{
    if ($action !== 'cs_bulk_sync') {
        return $redirect_to;
    }

    $synced  = 0;
    $skipped = 0;
    $failed  = 0;

    foreach ($user_ids as $user_id) {
        $user_id = (int) $user_id;

        // Skip non-customers silently
        if (! cs_is_wc_customer($user_id)) {
            $skipped++;
            continue;
        }

        $result = cs_send_customer($user_id);

        if ($result === true) {
            $synced++;
        } else {
            $failed++;
            error_log('Customer Sync bulk: failed for user #' . $user_id . ' — ' . $result);
        }
    }

    return add_query_arg([
        'cs_bulk_synced'  => $synced,
        'cs_bulk_skipped' => $skipped,
        'cs_bulk_failed'  => $failed,
    ], $redirect_to);
}

// ─────────────────────────────────────────────
// 6.  Admin notices — individual + bulk feedback
// ─────────────────────────────────────────────

add_action('admin_notices', 'cs_admin_notices');
function cs_admin_notices(): void
{

    // Individual sync result
    if (isset($_GET['cs_sync'])) {
        $status = sanitize_text_field(wp_unslash($_GET['cs_sync']));

        if ($status === 'success') {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo '<strong>' . esc_html__('Customer Sync', 'customer-sync') . ':</strong> ';
            echo esc_html__('Customer synced successfully.', 'customer-sync');
            echo '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>';
            echo '<strong>' . esc_html__('Customer Sync', 'customer-sync') . ':</strong> ';
            echo esc_html(urldecode($status));
            echo '</p></div>';
        }
    }

    // Bulk sync result
    if (isset($_GET['cs_bulk_synced'])) {
        $synced  = (int) $_GET['cs_bulk_synced'];
        $skipped = (int) $_GET['cs_bulk_skipped'];
        $failed  = (int) $_GET['cs_bulk_failed'];

        echo '<div class="notice notice-success is-dismissible"><p>';
        echo '<strong>' . esc_html__('Customer Sync', 'customer-sync') . ':</strong> ';
        printf(
            /* translators: 1: synced count 2: skipped count 3: failed count */
            esc_html__('%1$d synced, %2$d skipped (non-customers), %3$d failed.', 'customer-sync'),
            $synced,
            $skipped,
            $failed
        );
        echo '</p></div>';
    }
}

// ─────────────────────────────────────────────
// 7.  Admin notice if WooCommerce is inactive
// ─────────────────────────────────────────────

add_action('admin_notices', 'cs_woocommerce_missing_notice');
function cs_woocommerce_missing_notice(): void
{
    if (class_exists('WooCommerce')) {
        return;
    }
    echo '<div class="notice notice-warning is-dismissible"><p>';
    echo '<strong>' . esc_html__('Customer Sync', 'customer-sync') . ':</strong> ';
    echo esc_html__('WooCommerce must be installed and active for this plugin to work.', 'customer-sync');
    echo '</p></div>';
}

// ─────────────────────────────────────────────
// 8.  Core send function
// ─────────────────────────────────────────────

/**
 * Build the customer payload and POST it to the configured endpoint.
 *
 * Payload shape (matches CI4 CustomerController::receive()):
 * {
 *   "wp_user_id":       123,
 *   "email":            "john@example.com",
 *   "name":             "John Doe",
 *   "phone":            "+880...",
 *   "avatar_url":       "https://...",
 *   "billing_address":  { line1, line2, city, state, postcode, country },
 *   "google_id":        null,
 *   "facebook_id":      null,
 *   "source":           "email" | "google" | "facebook" | "wp_import"
 * }
 *
 * @param  int         $user_id
 * @return true|string true on success, error message on failure.
 */
function cs_send_customer(int $user_id, $debug = false)
{
    $endpoint = get_option('pn_endpoint_url', '');
    $secret   = get_option('pn_auth_token', '');

    if (empty($endpoint)) {
        return __('No endpoint URL configured. Please set it under Settings → General.', 'customer-sync');
    }

    $user = get_userdata($user_id);
    if (! $user) {
        return __('User not found.', 'customer-sync');
    }

    // ── Detect login source ─────────────────────────────────────────────
    // Check for social login meta keys set by common WP social login plugins
    // (NextendSocialLogin, WooCommerce Social Login, etc.)
    $google_id   = get_user_meta($user_id, 'nsl_google_id', true)
        ?: get_user_meta($user_id, 'wc_social_login_google_id', true)
        ?: null;

    $facebook_id = get_user_meta($user_id, 'nsl_facebook_id', true)
        ?: get_user_meta($user_id, 'wc_social_login_facebook_id', true)
        ?: null;

    // Derive source
    if ($google_id) {
        $source = 'google';
    } elseif ($facebook_id) {
        $source = 'facebook';
    } else {
        $source = 'wp_import';
    }

    // ── Avatar ──────────────────────────────────────────────────────────
    $avatar_url = get_avatar_url($user_id, ['size' => 200, 'default' => '404']);
    // Avoid sending the WP mystery person placeholder
    if (str_contains($avatar_url, 'd=404') && ! get_user_meta($user_id, 'nsl_google_picture', true)) {
        $avatar_url = get_user_meta($user_id, 'nsl_google_picture', true)
            ?: get_user_meta($user_id, 'nsl_facebook_picture', true)
            ?: null;
    }

    // ── Billing address ─────────────────────────────────────────────────
    $billing = [
        'address_1'    => get_user_meta($user_id, 'billing_address_1', true) ?: '',
        'address_2'    => get_user_meta($user_id, 'billing_address_2', true) ?: '',
        'city'     => get_user_meta($user_id, 'billing_city', true)      ?: '',
        'state'    => get_user_meta($user_id, 'billing_state', true)     ?: '',
        'postcode' => get_user_meta($user_id, 'billing_postcode', true)  ?: '',
        'country'  => get_user_meta($user_id, 'billing_country', true)   ?: '',
    ];

    $billing_state_name = $billing['state']; // fallback to code if lookup fails

    if ($billing['country'] && $billing['state'] && class_exists('WC_Countries')) {
        $wc_countries = new WC_Countries();
        $states       = $wc_countries->get_states($billing['country']);
        if (! empty($states[$billing['state']])) {
            $billing_state_name = $states[$billing['state']]; // "BD-63" → "Tangail"
        }
    }
    $billing['state'] = $billing_state_name;

    // Only include billing if at least one field is filled
    $has_billing = array_filter($billing);

    // ── Full name ────────────────────────────────────────────────────────
    $name = trim(
        (get_user_meta($user_id, 'billing_first_name', true) ?: $user->first_name)
            . ' '
            . (get_user_meta($user_id, 'billing_last_name', true) ?: $user->last_name)
    ) ?: $user->display_name;

    // ── Build payload ────────────────────────────────────────────────────
    $payload = [
        'wp_user_id'  => $user_id,
        'email'       => strtolower($user->user_email),
        'password'   => $user->user_pass,
        'name'        => html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'phone'       => get_bdPhoneDigits(get_user_meta($user_id, 'billing_phone', true)) ?: null,
        'avatar_url'  => $avatar_url,
        'source'      => $source,
        'google_id'   => $google_id   ?: null,
        'facebook_id' => $facebook_id ?: null,
    ];

    if ($has_billing) {
        $payload['billing_address'] = $billing;
    }

    $headers = [
        'Content-Type'        => 'application/json',
        'X-WC-Webhook-Secret' => $secret,
    ];


    if ($debug) {
        error_log(print_r($headers, true));
        return wp_json_encode($payload);
    }

    // ── Send ─────────────────────────────────────────────────────────────
    $response = wp_remote_post($endpoint . 'customer', [
        'method'      => 'POST',
        'timeout'     => 15,
        'blocking'    => true,
        'headers'     => $headers,
        'body'        => wp_json_encode($payload),
        'data_format' => 'body',
    ]);

    if (is_wp_error($response)) {
        return $response->get_error_message();
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) {
        /* translators: %d: HTTP status code */
        return sprintf(__('Endpoint returned HTTP %d.', 'customer-sync'), $code);
    }

    return true;
}

// ─────────────────────────────────────────────
// 9.  Helper — is this user a WC customer?
//     Only syncs users with the 'customer' role.
//     Excludes admins, editors, shop managers, etc.
// ─────────────────────────────────────────────

function cs_is_wc_customer(int $user_id): bool
{
    $user = get_userdata($user_id);
    if (! $user) {
        return false;
    }

    return in_array('customer', (array) $user->roles, true);
}

// ─────────────────────────────────────────────
// 10. Minimal WP error log helper
// ─────────────────────────────────────────────

function log_message_to_wp(string $message): void
{
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('[customer-sync] ' . $message);
    }
}
