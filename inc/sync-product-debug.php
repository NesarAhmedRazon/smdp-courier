<?php
/**
 * File Name:       WP->MiddleMan Product Sync
 * Description:     Adds a manual "Sync" button to WooCommerce products that sends
 *                  product data (including attributes) as JSON to a configured endpoint.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('rest_api_init', 'wc_categories_api_register_routes');

function wc_categories_api_register_routes() {
    register_rest_route('webhook/sync', '/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'syncTest',
        'permission_callback' => '__return_true'
    ));
    
}

function syncTest($request){
    $product_id = $request['id'];
    return new WP_REST_Response(array(
        'success' => true,
        'data' => json_decode(pn_send_product( $product_id,true ))
    ), 200);
}


add_action('rest_api_init', 'wc_customer_api_register_routes');

function wc_customer_api_register_routes() {
    register_rest_route('webhook/customer', '/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'syncTestcustomer',
        'permission_callback' => '__return_true'
    ));
    
}

function syncTestcustomer($request){
    $product_id = $request['id'];
    return new WP_REST_Response(array(
        'success' => true,
        'data' => json_decode(cs_send_customer( $product_id,true ))
    ), 200);
}