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