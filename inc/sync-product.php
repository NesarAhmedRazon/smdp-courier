<?php

/**
 * File Name:       WP->MiddleMan Product Sync
 * Description:     Adds a manual "Sync" button to WooCommerce products that sends
 *                  product data (including attributes) as JSON to a configured endpoint.
 */

if (! defined('ABSPATH')) {
    exit;
}

require_once SMDP_COURIER_DIR . 'inc/sync-product-debug.php';
require_once SMDP_COURIER_DIR . 'inc/sync-helper.php';
require_once SMDP_COURIER_DIR . 'inc/sync-helper2.php';
require_once SMDP_COURIER_DIR . 'inc/el-content-extractor/el-content-extractor.php';
// ─────────────────────────────────────────────
// 1.  Settings (General Settings page)
// ─────────────────────────────────────────────

add_action('admin_init', 'pn_register_settings');
if (!function_exists('pn_register_settings')) {
    function pn_register_settings()
    {
        register_setting('general', 'pn_endpoint_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ]);

        register_setting('general', 'pn_auth_token', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        add_settings_section(
            'pn_settings_section',
            __('Product Notifier', 'product-notifier'),
            function () {
                echo '<p>' . esc_html__('Configure the endpoint that receives product data when the Sync button is clicked on a product.', 'product-notifier') . '</p>';
            },
            'general'
        );

        add_settings_field('pn_endpoint_url', __('Endpoint URL', 'product-notifier'),  'pn_endpoint_url_callback', 'general', 'pn_settings_section');
        add_settings_field('pn_auth_token',   __('Webhook Secret', 'product-notifier'), 'pn_auth_token_callback',   'general', 'pn_settings_section');
    }
}

if (!function_exists('pn_endpoint_url_callback')) {
    function pn_endpoint_url_callback()
    {
        $value = get_option('pn_endpoint_url', '');
        printf(
            '<input type="url" id="pn_endpoint_url" name="pn_endpoint_url" value="%s" class="regular-text" placeholder="https://your-service.example.com/api/post/product/sync" /><p class="description">%s</p>',
            esc_attr($value),
            esc_html__('The URL that will receive the POST request with product data.', 'product-notifier')
        );
    }
}
if (!function_exists('pn_auth_token_callback')) {
    function pn_auth_token_callback()
    {
        $value = get_option('pn_auth_token', '');
        printf(
            '<input type="text" id="pn_auth_token" name="pn_auth_token" value="%s" class="regular-text" placeholder="your-webhook-secret" autocomplete="off" /><p class="description">%s</p>',
            esc_attr($value),
            esc_html__('Sent as X-WC-Webhook-Secret header with every request.', 'product-notifier')
        );
    }
}
// ─────────────────────────────────────────────
// 2.  "Sync" row-action on the Products list table
// ─────────────────────────────────────────────

add_filter('post_row_actions', 'pn_add_sync_row_action', 10, 2);
function pn_add_sync_row_action($actions, $post)
{
    if ($post->post_type !== 'product') {
        return $actions;
    }

    $url = wp_nonce_url(
        add_query_arg([
            'action'     => 'pn_sync_product',
            'product_id' => $post->ID,
        ], admin_url('admin-post.php')),
        'pn_sync_' . $post->ID
    );

    $actions['pn_sync'] = sprintf(
        '<a href="%s">%s</a>',
        esc_url($url),
        esc_html__('Sync', 'product-notifier')
    );

    return $actions;
}

// ─────────────────────────────────────────────
// 3.  Bulk action: "Sync to Endpoint"
// ─────────────────────────────────────────────

// Register the bulk action in the dropdown
add_filter('bulk_actions-edit-product', 'pn_register_bulk_sync_action');
function pn_register_bulk_sync_action($bulk_actions)
{
    $bulk_actions['pn_bulk_sync'] = __('Sync to Endpoint', 'product-notifier');
    return $bulk_actions;
}

// Handle the bulk action
add_filter('handle_bulk_actions-edit-product', 'pn_handle_bulk_sync_action', 10, 3);
function pn_handle_bulk_sync_action($redirect_url, $action, $post_ids)
{
    if ($action !== 'pn_bulk_sync') {
        return $redirect_url;
    }

    if (! current_user_can('edit_products')) {
        wp_die(esc_html__('You do not have permission to do this.', 'product-notifier'));
    }

    $synced  = 0;
    $failed  = 0;

    foreach ($post_ids as $product_id) {
        $result = pn_send_product(absint($product_id));
        if ($result === true) {
            $synced++;
        } else {
            $failed++;
        }
    }

    $redirect_url = remove_query_arg(['pn_bulk_synced', 'pn_bulk_failed'], $redirect_url);
    $redirect_url = add_query_arg([
        'pn_bulk_synced' => $synced,
        'pn_bulk_failed' => $failed,
    ], $redirect_url);

    return $redirect_url;
}

// Notice for bulk action result
add_action('admin_notices', 'pn_bulk_sync_admin_notice');
function pn_bulk_sync_admin_notice()
{
    if (! isset($_GET['pn_bulk_synced']) && ! isset($_GET['pn_bulk_failed'])) {
        return;
    }

    $synced = absint($_GET['pn_bulk_synced'] ?? 0);
    $failed = absint($_GET['pn_bulk_failed'] ?? 0);

    if ($synced > 0) {
        printf(
            '<div class="notice notice-success is-dismissible"><p><strong>%s</strong> %s</p></div>',
            esc_html__('Product Notifier:', 'product-notifier'),
            /* translators: %d: number of products */
            esc_html(sprintf(_n('%d product synced successfully.', '%d products synced successfully.', $synced, 'product-notifier'), $synced))
        );
    }

    if ($failed > 0) {
        printf(
            '<div class="notice notice-error is-dismissible"><p><strong>%s</strong> %s</p></div>',
            esc_html__('Product Notifier:', 'product-notifier'),
            /* translators: %d: number of products */
            esc_html(sprintf(_n('%d product failed to sync.', '%d products failed to sync.', $failed, 'product-notifier'), $failed))
        );
    }
}

// ─────────────────────────────────────────────
// 4.  "Save & Sync" button inside the single product edit page
// ─────────────────────────────────────────────





// ─────────────────────────────────────────────
// 5.  Handle single row-action sync
// ─────────────────────────────────────────────

add_action('admin_post_pn_sync_product', 'pn_handle_sync_action');
function pn_handle_sync_action()
{
    if (! current_user_can('edit_products')) {
        wp_die(esc_html__('You do not have permission to do this.', 'product-notifier'));
    }

    $product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;

    if (! $product_id) {
        wp_die(esc_html__('Invalid product ID.', 'product-notifier'));
    }

    check_admin_referer('pn_sync_' . $product_id);

    $result  = pn_send_product($product_id);

    $referer = wp_get_referer() ?: admin_url('edit.php?post_type=product');

    $referer = add_query_arg(
        'pn_sync',
        $result === true ? 'success' : rawurlencode($result),
        remove_query_arg('pn_sync', $referer)
    );

    wp_safe_redirect($referer);
    exit;
}



// ─────────────────────────────────────────────
// 7.  Admin notice for single row-action result
// ─────────────────────────────────────────────

add_action('admin_notices', 'pn_sync_admin_notice');
function pn_sync_admin_notice()
{
    if (! isset($_GET['pn_sync'])) {
        return;
    }

    $status = sanitize_text_field(wp_unslash($_GET['pn_sync']));

    if ($status === 'success') {
        echo '<div class="notice notice-success is-dismissible"><p>';
        echo '<strong>' . esc_html__('Product Notifier', 'product-notifier') . ':</strong> ';
        echo esc_html__('Product synced successfully.', 'product-notifier');
        echo '</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>';
        echo '<strong>' . esc_html__('Product Notifier', 'product-notifier') . ':</strong> ';
        echo esc_html(urldecode($status));
        echo '</p></div>';
    }
}



// ─────────────────────────────────────────────
// 9.  COGS helper
// ─────────────────────────────────────────────






function get_metas($post_id)
{
    // Get all meta data
    $all_meta = get_post_meta($post_id);

    // Define excluded prefixes (normalized)
    $exclude_prefixes = array_map(
        function ($p) {
            return ltrim($p, '_');
        },
        [
            '_fixed_price_rules',
            'cld_replacements',
            'wd_page_css_files',
            'cloudinary_transformations_terms',
            '_thumbnail_id',
            '_invoice',
            '_product_360_image_gallery',
            '_wp_old_date',
            '_download',
            '_ame_cpe_post_policy',
            // '_woodmart_product_custom',
            '_edit_last',
            '_tax_status',
            '_sku',
            '_price',
            '_tax_class',
            '_virtual',
            '_product_version',
            '_edit_lock',
            '_product_attributes',
            'cloudinary',
            'elementor',
            'elementor_data',
            'elementor_css',
            'elementor_element_cache',
            'rank_math_facebook_image_id',
            'wc_gla',
            'woodmart_history_of_visits',
            'stock_status',
            'wp_page_template',
            'woodmart_sguide_select',
            'woodmart_product-background',
            'backorders',
            'sold_individually',
            'stock',
            'rank_math_internal_links_processed',
            'smdp_faqs'
        ]
    );

    // Define SEO field mappings (use normalized keys)
    $seo_field_mappings = [
        'rank_math_description'        => 'description',
        'rank_math_focus_keyword'     => 'keywords',
        'rank_math_title'             => 'title',
        'rank_math_robots'            => 'robots',
        'rank_math_canonical_url'     => 'canonical_url',
        'rank_math_facebook_title'    => 'og_title',
        'rank_math_facebook_description' => 'og_description',
        'rank_math_facebook_image'    => 'og_image',
        'rank_math_twitter_title'     => 'twitter_title',
        'rank_math_twitter_description' => 'twitter_description',
        'rank_math_twitter_image'     => 'twitter_image',
        'wc_gla_brand'                => 'gla_brand',
        'smdp_smdp_custom_seo_keywords' => 'keywords_x'
    ];



    $seo_data = [];

    $clean_meta = [];

    // Helper: process value
    $processValue = function ($value) {
        if (is_array($value) && count($value) === 1) {
            $value = $value[0];
            $value = is_serialized($value) ? maybe_unserialize($value) : $value;
        }
        return $value;
    };

    // 1. Normalize + collect everything (NO exclusion yet)
    foreach ($all_meta as $key => $value) {

        $normalizedKey = ltrim($key, '_');
        $value = $processValue($value);

        // 2. Handle SEO FIRST
        if (isset($seo_field_mappings[$normalizedKey])) {
            $seo_key = $seo_field_mappings[$normalizedKey];

            if ($seo_key === 'robots' && is_array($value)) {
                $seo_data[$seo_key] = $value;
            } elseif (!empty($value)) {
                $seo_data[$seo_key] = $value;
            }

            continue;
        }


        // Store everything temporarily
        $clean_meta[$normalizedKey] = $value;
    }

    // 3. Apply exclusion AFTER processing
    $clean_meta = array_filter(
        $clean_meta,
        function ($value, $key) use ($exclude_prefixes) {
            foreach ($exclude_prefixes as $prefix) {
                if (
                    str_starts_with($key, $prefix) ||
                    str_contains($key, $prefix)
                ) {
                    return false;
                }
            }
            return true;
        },
        ARRAY_FILTER_USE_BOTH
    );

    // 4. Attach SEO
    if (!empty($seo_data)) {
        $clean_meta['seo'] = $seo_data;
    }

    // 5. Add transformed price rules
    $tp = transformPriceRules($all_meta);
    if (!empty($tp)) {
        $clean_meta['trd_price'] = $tp;
    }

    return $clean_meta;
}
function transformPriceRules($data)
{
    $result = [];

    // Get price rules from meta
    $priceRules = isset($data['_fixed_price_rules']) ? $data['_fixed_price_rules'] : null;

    if ($priceRules) {
        // Unserialize if needed
        if (is_array($priceRules) && count($priceRules) === 1) {
            $priceRules = maybe_unserialize($priceRules[0]);
        }

        // Process price rules
        if (is_array($priceRules)) {
            foreach ($priceRules as $qty => $price) {
                $result[] = [
                    'qty' => (int)$qty,
                    'price' => (float)$price
                ];
            }

            // Sort by qty
            usort($result, fn($a, $b) => $a['qty'] <=> $b['qty']);
        }
    }

    return $result;
}
