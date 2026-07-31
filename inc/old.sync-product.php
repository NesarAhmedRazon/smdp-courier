<?php
/**
 * File Name:       WP->MiddleMan Product Sync
 * Description:     Adds a manual "Sync" button to WooCommerce products that sends
 *                  product data (including attributes) as JSON to a configured endpoint.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once SMDP_COURIER_DIR . 'inc/sync-product-debug.php';
// ─────────────────────────────────────────────
// 1.  Settings (General Settings page)
// ─────────────────────────────────────────────

add_action( 'admin_init', 'pn_register_settings' );
function pn_register_settings() {
    register_setting( 'general', 'pn_endpoint_url', [
        'type'              => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default'           => '',
    ] );

    register_setting( 'general', 'pn_auth_token', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ] );

    add_settings_section(
        'pn_settings_section',
        __( 'Product Notifier', 'product-notifier' ),
        function() {
            echo '<p>' . esc_html__( 'Configure the endpoint that receives product data when the Sync button is clicked on a product.', 'product-notifier' ) . '</p>';
        },
        'general'
    );

    add_settings_field( 'pn_endpoint_url', __( 'Endpoint URL', 'product-notifier' ),  'pn_endpoint_url_callback', 'general', 'pn_settings_section' );
    add_settings_field( 'pn_auth_token',   __( 'Webhook Secret', 'product-notifier' ), 'pn_auth_token_callback',   'general', 'pn_settings_section' );
}

function pn_endpoint_url_callback() {
    $value = get_option( 'pn_endpoint_url', '' );
    printf(
        '<input type="url" id="pn_endpoint_url" name="pn_endpoint_url" value="%s" class="regular-text" placeholder="https://your-service.example.com/posts/product" /><p class="description">%s</p>',
        esc_attr( $value ),
        esc_html__( 'The URL that will receive the POST request with product data.', 'product-notifier' )
    );
}

function pn_auth_token_callback() {
    $value = get_option( 'pn_auth_token', '' );
    printf(
        '<input type="text" id="pn_auth_token" name="pn_auth_token" value="%s" class="regular-text" placeholder="your-webhook-secret" autocomplete="off" /><p class="description">%s</p>',
        esc_attr( $value ),
        esc_html__( 'Sent as X-WC-Webhook-Secret header with every request.', 'product-notifier' )
    );
}

// ─────────────────────────────────────────────
// 2.  "Sync" row-action on the Products list table
// ─────────────────────────────────────────────

add_filter( 'post_row_actions', 'pn_add_sync_row_action', 10, 2 );
function pn_add_sync_row_action( $actions, $post ) {
    if ( $post->post_type !== 'product' ) {
        return $actions;
    }

    $url = wp_nonce_url(
        add_query_arg( [
            'action'     => 'pn_sync_product',
            'product_id' => $post->ID,
        ], admin_url( 'admin-post.php' ) ),
        'pn_sync_' . $post->ID
    );

    $actions['pn_sync'] = sprintf(
        '<a href="%s">%s</a>',
        esc_url( $url ),
        esc_html__( 'Sync', 'product-notifier' )
    );

    return $actions;
}

// ─────────────────────────────────────────────
// 3.  "Sync" button inside the single product edit page
// ─────────────────────────────────────────────

add_action( 'post_submitbox_misc_actions', 'pn_add_sync_button_to_product_editor' );
function pn_add_sync_button_to_product_editor() {
    global $post;
    if ( ! $post || $post->post_type !== 'product' ) {
        return;
    }

    $url = wp_nonce_url(
        add_query_arg( [
            'action'     => 'pn_sync_product',
            'product_id' => $post->ID,
        ], admin_url( 'admin-post.php' ) ),
        'pn_sync_' . $post->ID
    );
    ?>
    <div class="misc-pub-section" style="padding-top:8px;padding-bottom:8px;">
        <a href="<?php echo esc_url( $url ); ?>" class="button button-secondary" style="width:100%;text-align:center;">
            &#x21BA; <?php esc_html_e( 'Sync to Endpoint', 'product-notifier' ); ?>
        </a>
    </div>
    <?php
}

// ─────────────────────────────────────────────
// 4.  Handle the sync action
// ─────────────────────────────────────────────

add_action( 'admin_post_pn_sync_product', 'pn_handle_sync_action' );
function pn_handle_sync_action() {
    if ( ! current_user_can( 'edit_products' ) ) {
        wp_die( esc_html__( 'You do not have permission to do this.', 'product-notifier' ) );
    }

    $product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;

    if ( ! $product_id ) {
        wp_die( esc_html__( 'Invalid product ID.', 'product-notifier' ) );
    }

    check_admin_referer( 'pn_sync_' . $product_id );

    $result  = pn_send_product( $product_id );
    $referer = wp_get_referer() ?: admin_url( 'edit.php?post_type=product' );

    $referer = add_query_arg(
        'pn_sync',
        $result === true ? 'success' : rawurlencode( $result ),
        remove_query_arg( 'pn_sync', $referer )
    );

    wp_safe_redirect( $referer );
    exit;
}

// ─────────────────────────────────────────────
// 5.  Core send function
// ─────────────────────────────────────────────

/**
 * Sends product data to the configured endpoint.
 *
 * Payload fields:
 *   wc_id, permalink, title, sku, stock_quantity, stock_status,
 *   sale_price, regular_price, thumbnail, wc_cog, created_at,
 *   attributes[]
 *     └─ wc_id, name, label, is_public,
 *        values[] → wc_id, slug, name
 *
 * @param  int         $product_id
 * @return true|string true on success, error message string on failure.
 */
function pn_send_product( $product_id, $debug=false) {
    $endpoint = get_option( 'pn_endpoint_url', '' );
    $secret   = get_option( 'pn_auth_token', '' );

    if ( empty( $endpoint ) ) {
        return __( 'No endpoint URL configured. Please set it under Settings → General.', 'product-notifier' );
    }

    $product = wc_get_product( $product_id );
    if ( ! $product ) {
        return __( 'Product not found.', 'product-notifier' );
    }

    $sale_price     = $product->get_sale_price();
    $regular_price  = $product->get_regular_price();
    $featured_image_id = $product->get_image_id();
    $featured_image_url = $featured_image_id ? wp_get_attachment_url($featured_image_id) : null;
    $cost           = get_product_cogs( $product_id );

    // Get gallery image IDs
    $gallery_ids = $product->get_gallery_image_ids();

    // Build gallery images array
    $gallery_images = [];
    foreach ($gallery_ids as $image_id) {
        $image_url = wp_get_attachment_url($image_id);
        if ($image_url) {
            $gallery_images[] = $image_url;
        }
    }
    // ── Build attributes array ──────────────────────────────────────────
    $attributes    = [];
    $wc_attributes = $product->get_attributes();
    

    foreach ( $wc_attributes as $taxonomy => $attribute ) {

        // Only taxonomy-based attributes (pa_*) — skip custom text fields
        if ( ! $attribute->is_taxonomy() ) {
            continue;
        }

        $tax_obj = get_taxonomy( $taxonomy );
        if ( ! $tax_obj ) {
            continue;
        }

        // Strip "pa_" prefix for clean name
        $clean_name = str_starts_with( $taxonomy, 'pa_' )
            ? substr( $taxonomy, 3 )
            : $taxonomy;

        $wc_attr_id = wc_attribute_taxonomy_id_by_name( $clean_name );

        // Get all term objects assigned to this product for this taxonomy
        $terms = wc_get_product_terms( $product_id, $taxonomy, [ 'fields' => 'all' ] );

        if ( empty( $terms ) ) {
            continue;
        }

        $values = [];
        foreach ( $terms as $term ) {
            $values[] = [
                'wc_id' => $term->term_id,
                'slug'  => $term->slug,
                'name'  => $term->name,
            ];
        }

        $position = $attribute->get_position() ?? null;
        $attributes[] = [
            'wc_id'     => $wc_attr_id,
            'name'      => $clean_name,
            'label'     => $tax_obj->labels->singular_name ?? $clean_name,
            'is_public' => (bool) $attribute->get_visible(),
            'values'    => $values,
            'order'     => $position,
        ];
    }

    $categories = get_product_cats($product_id);
    
    // ── Build and send payload ──────────────────────────────────────────
    $payload = wp_json_encode( [
        'wc_id'          => $product->get_id(),
        'permalink'      => wp_make_link_relative( get_permalink( $product_id ) ),
        'title'          => $product->get_name(),
        'sku'            => $product->get_sku() ?: null,
        'stock_quantity' => $product->get_stock_quantity(),
        'stock_status'   => $product->get_stock_status(),
        'sale_price'     => $sale_price !== '' ? (float) $sale_price : null,
        'regular_price'  => $regular_price !== '' ? (float) $regular_price : 0,
        'thumbnail'      => $featured_image_url ?: null,
        'gallery'        => $gallery_images ?: [],
        'wc_cog'         => $cost,
        'created_at'     => $product->get_date_created()?->date( 'Y-m-d H:i:s' ),
        'attributes'     => $attributes,
        'categories'     => $categories,
    ] );
    
        if($debug){
            return $payload;  
        }

    $headers = [
        'Content-Type'        => 'application/json',
        'X-WC-Webhook-Secret' => $secret,
    ];

    $response = wp_remote_post( $endpoint, [
        'method'      => 'POST',
        'timeout'     => 15,
        'blocking'    => true,
        'headers'     => $headers,
        'body'        => $payload,
        'data_format' => 'body',
    ] );

    if ( is_wp_error( $response ) ) {
        return $response->get_error_message();
    }

    $code = wp_remote_retrieve_response_code( $response );
    if ( $code < 200 || $code >= 300 ) {
        /* translators: %d: HTTP status code */
        return sprintf( __( 'Endpoint returned HTTP %d.', 'product-notifier' ), $code );
    }

    return true;
}

// ─────────────────────────────────────────────
// 6.  Admin notice after redirect
// ─────────────────────────────────────────────

add_action( 'admin_notices', 'pn_sync_admin_notice' );
function pn_sync_admin_notice() {
    if ( ! isset( $_GET['pn_sync'] ) ) {
        return;
    }

    $status = sanitize_text_field( wp_unslash( $_GET['pn_sync'] ) );

    if ( $status === 'success' ) {
        echo '<div class="notice notice-success is-dismissible"><p>';
        echo '<strong>' . esc_html__( 'Product Notifier', 'product-notifier' ) . ':</strong> ';
        echo esc_html__( 'Product synced successfully.', 'product-notifier' );
        echo '</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>';
        echo '<strong>' . esc_html__( 'Product Notifier', 'product-notifier' ) . ':</strong> ';
        echo esc_html( urldecode( $status ) );
        echo '</p></div>';
    }
}

// ─────────────────────────────────────────────
// 7.  Admin notice if WooCommerce is inactive
// ─────────────────────────────────────────────

add_action( 'admin_notices', 'pn_woocommerce_missing_notice' );
function pn_woocommerce_missing_notice() {
    if ( class_exists( 'WooCommerce' ) ) {
        return;
    }
    echo '<div class="notice notice-warning is-dismissible"><p>';
    echo '<strong>' . esc_html__( 'Product Notifier', 'product-notifier' ) . ':</strong> ';
    echo esc_html__( 'WooCommerce must be installed and active for this plugin to work.', 'product-notifier' );
    echo '</p></div>';
}

// ─────────────────────────────────────────────
// 8.  COGS helper
// ─────────────────────────────────────────────

function get_product_cogs( $product_id ) {
    $product = wc_get_product( $product_id );
    if ( ! $product ) {
        return null;
    }

    // Try known COGS meta keys in order of preference
    foreach ( [ '_cogs_total_value', '_cost_of_goods', '_wc_cog_cost' ] as $meta_key ) {
        $value = get_post_meta( $product_id, $meta_key, true );
        if ( $value !== '' && $value !== false ) {
            return (float) $value;
        }
    }

    return null;
}


// ─────────────────────────────────────────────
// 9.  Categories helper
// ─────────────────────────────────────────────
function get_product_cats( $product_id ) {
    $product = wc_get_product( $product_id );
    if ( ! $product ) {
        return null;
    }
    $wc_category_ids = $product->get_category_ids();
    $categories_data = [];
    foreach ($wc_category_ids as $cat_id) {
        $term = get_term($cat_id, 'product_cat');
        
        if ($term && !is_wp_error($term)) {
            $categories_data[] = [
                'wc_id'        => $term->term_id,
                'name'         => $term->name,
                'slug'         => $term->slug,
                'description'  => $term->description ?: null,
                'parent' => $term->parent ?: 0,
            ];
        }
    }
    return $categories_data;
}