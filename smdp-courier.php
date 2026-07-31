<?php

/**
 * Plugin Name: SMDP: Courier
 * Plugin URI: https://github.com/NesarAhmedRazon/SMDP-Courier
 * Description: A plugin for Courier Services.
 * Version: 0.0.1
 * Author: Nesar Ahmed
 * Author URI: https://nesarahmed.dev/
 * License: GPLv2 or later
 * Text Domain: smdp-text-domain
 * Domain Path: /languages/
 */


// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}


if (!defined('SMDP_TEXTDOMAIN')) {
    define('SMDP_TEXTDOMAIN', 'smdp-text-domain');
}

if (!defined('SMDP_COURIER_DOMAIN')) { // Remove it ASAP
    define('SMDP_COURIER_DOMAIN', 'smdp-text-domain');
}

if (!defined('SMDP_COURIER_DIR')) {
    define('SMDP_COURIER_DIR', plugin_dir_path(__FILE__));
}

if (!defined('SMDP_COURIER_URL')) {
    define('SMDP_COURIER_URL', plugin_dir_url(__FILE__));
}

if (!defined('SMDP_COURIER_FILE')) {
    define('SMDP_COURIER_FILE', __FILE__);
}

error_log('====== Courier ======');
//   error_log("payload: ");
// error_log(print_r($payload,true));



add_action('woocommerce_init', 'smdpc_wooReady');
function smdpc_wooReady()
{

    include_once('inc/order-status.php');
    require_once SMDP_COURIER_DIR . 'inc/shippingProvider.php';
    require_once SMDP_COURIER_DIR . 'inc/shippingArea.php';
    require_once SMDP_COURIER_DIR . 'inc/genarate_llms.php';
    require_once SMDP_COURIER_DIR . 'inc/sync-product.php';
    require_once SMDP_COURIER_DIR . 'inc/sync-customer.php';
    require_once SMDP_COURIER_DIR . 'inc/product-blocker_modal.php';
    
    require_once SMDP_COURIER_DIR . 'inc/add-tailwind.php';
    require_once SMDP_COURIER_DIR . 'inc/sync/helper-attachment.php';
    // require_once SMDP_COURIER_DIR . 'inc/toon-cats.php';
    // require_once SMDP_COURIER_DIR . 'inc/toon.php';

    include_once('pathao/core.php');
    if (!function_exists('smdp_add_locations_bulk_safe')) {
        require_once SMDP_COURIER_DIR . 'inc/db_localtion-table.php';
    }
}


// // Place this in a temporary plugin or theme functions.php
// add_action('init', function() {
//     if(current_user_can('administrator')) {
//         error_log('Crons');
//         do_action('action_scheduler_run_queue');
//     }
// });


// HPOS-compatible meta save
if (!function_exists('update_order_hpos_meta')) {
    function update_order_hpos_meta($order, $meta_key, $meta_value)
    {
        $order->update_meta_data($meta_key, $meta_value);
        $order->save();
    }
}
