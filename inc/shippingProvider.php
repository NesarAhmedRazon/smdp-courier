<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
function smdp_get_shipping_providers()
{

    // Define the available shipping providers in Bangladesh
    return [
        ''         => __('Select a Provider', SMDP_COURIER_DOMAIN),
        'pathaw'   => 'পাঠাও',
        'redx'     => 'রেডএক্স',
        'ecourier' => 'ই-কুরিয়ার',
        'paperfly' => 'পেপারফ্লাই',
        'sundorbon' => 'সুন্দরবন',
        'steadfast' => 'স্টেডফাস্ট',
        'local'    => 'লোকাল',

    ];
}

// Add a “Provider” dropdown to each Flat Rate shipping method
add_filter('woocommerce_shipping_instance_form_fields_flat_rate', 'smdp_add_provider_to_method');
function smdp_add_provider_to_method($fields)
{
    $fields['shipping_provider'] = [
        'title'       => __('Shipping Provider', SMDP_COURIER_DOMAIN),
        'type'        => 'select',
        'class'       => 'wc-enhanced-select',
        'description' => __('Select the shipping provider for this method.', SMDP_COURIER_DOMAIN),
        'desc_tip'    => true,
        'default'     => '',
        'options'     => smdp_get_shipping_providers(),
    ];
    return $fields;
}

// Get the shipping provider for a given shipping method object or ID
function smdp_get_shipping_method_provider($method_obj)
{
    if (is_numeric($method_obj)) {
        $method_obj = WC_Shipping_Zones::get_shipping_method($method_obj);
    }

    return $method_obj instanceof WC_Shipping_Method
        ? $method_obj->get_option('shipping_provider')
        : null;
}


add_action('woocommerce_checkout_create_order', 'smdp_save_shipping_provider_to_order', 20, 2);
function smdp_save_shipping_provider_to_order($order, $data)
{
    foreach ($order->get_items('shipping') as $shipping_item) {
        $instance_id = $shipping_item->get_instance_id();

        // Get shipping method instance from zone
        $shipping_method = WC_Shipping_Zones::get_shipping_method($instance_id);
        if (! $shipping_method || ! method_exists($shipping_method, 'get_option')) {
            continue;
        }

        $provider = $shipping_method->get_option('shipping_provider');
        if ($provider) {
            $order->update_meta_data('_shipping_provider', $provider); // save to order meta
            $shipping_item->add_meta_data('_shipping_provider', $provider, false); // optional
            $shipping_item->save();
        }
    }
}



// Display the shipping provider in the order items table
add_action('woocommerce_after_order_itemmeta', function ($item_id, $item, $product) {
    if ($item->is_type('shipping')) {
        $provider = $item->get_meta('_shipping_provider');
        if ($provider) {
            echo '<p><strong>Provider:</strong> ' . esc_html(ucwords($provider)) . '</p>';
        }
    }
}, 10, 3);

// Get the shipping provider for a specific order
function smdp_get_order_shipping_provider($order_id)
{
    $order = wc_get_order($order_id);
    if (!$order) return null;

    foreach ($order->get_items('shipping') as $shipping_item) {

        $provider = $shipping_item->get_meta('_shipping_provider');
        if (!empty($provider)) {

            return $provider; // e.g. 'pathao'
        }
    }

    return null;
}
