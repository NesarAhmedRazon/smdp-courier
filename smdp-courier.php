<?php

/**
 * Plugin Name: SMDP: Courier
 * Plugin URI: https://github.com/NesarAhmedRazon/SMDP-Courier
 * Description: A plugin for Courier Services.
 * Version: 0.0.1
 * Author: Nesar Ahmed
 * Author URI: https://nesarahmed.dev/
 * License: GPLv2 or later
 * Text Domain: smdp-courier
 * Domain Path: /languages/
 */


// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}


if (!defined('SMDP_COURIER_DOMAIN')) {
    define('SMDP_COURIER_DOMAIN', 'smdp-courier');
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

require_once SMDP_COURIER_DIR . 'inc/logger.php';
add_action('woocommerce_init', 'smdpc_wooReady');
function smdpc_wooReady()
{
    include_once('inc/order-status.php');
    require_once SMDP_COURIER_DIR . 'inc/shippingProvider.php';
    require_once SMDP_COURIER_DIR . 'inc/shippingArea.php';
    include_once('pathao/core.php');
}
