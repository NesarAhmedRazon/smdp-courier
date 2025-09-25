<?php 

defined('ABSPATH') || exit;

// Register Custom Order Status for Pathaw Courier
add_action('init', 'register_pathaw_order_status');
function register_pathaw_order_status() {
    register_post_status('wc-ready-to-shipping', array(
        'label'                     => _x('Ready For Shipping', 'Order status', 'woocommerce'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Ready For Shipping (%s)', 'Ready For Shipping (%s)', 'woocommerce')
    ));
    register_post_status('wc-pickup_requested', array(
        'label'                     => _x('Picked Requested', 'Order status', 'woocommerce'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Picked Requested (%s)', 'Picked Requested (%s)', 'woocommerce')
    ));
    register_post_status('wc-pickup_updated', array(
        'label'                     => _x('Shipping Updated', 'Order status', 'woocommerce'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Shipping Updated (%s)', 'Shipping Updated (%s)', 'woocommerce')
    ));
    register_post_status('wc-pickup_error', array(
        'label'                     => _x('PickUp Failed', 'Order status', 'woocommerce'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('PickUp Failed (%s)', 'PickUp Failed (%s)', 'woocommerce')
    ));
    register_post_status('wc-pickup_ok', array(
        'label'                     => _x('Picked Successfully', 'Order status', 'woocommerce'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Picked Successfully (%s)', 'Picked Successfully (%s)', 'woocommerce')
    ));
    
    register_post_status('wc-pickup_cancelled', array(
        'label'                     => _x('Pickup Cancelled', 'Order status', 'woocommerce'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Pickup Cancelled (%s)', 'Pickup Cancelled (%s)', 'woocommerce')
    ));
    register_post_status('wc-at_sorting_hub', array(
        'label'                     => _x('At Sorting Hub', 'Order status', 'woocommerce'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('At Sorting Hub (%s)', 'At Sorting Hub (%s)', 'woocommerce')
    ));
    register_post_status('wc-on_the_way', array(
        'label'                     => _x('In Transit', 'Order status', 'woocommerce'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('In Transit (%s)', 'In Transit (%s)', 'woocommerce')
    ));
    register_post_status('wc-last_mile_hub', array(
        'label'                     => _x('At Last Mile Hub', 'Order status', 'woocommerce'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('At Last Mile Hub (%s)', 'At Last Mile Hub (%s)', 'woocommerce')
    ));
    register_post_status('wc-ready_to_delivery', array(
        'label'                     => _x('Assigned for Delivery', 'Order status', 'woocommerce'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Assigned for Delivery (%s)', 'Assigned for Delivery (%s)', 'woocommerce')
    ));
    register_post_status('wc-delivery_success', array(
        'label'                     => _x('Delivered', 'Order status', 'woocommerce'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Delivered (%s)', 'Delivered (%s)', 'woocommerce')
    ));
    register_post_status('wc-delivery-failed', array(
        'label'                     => _x('Delivery Failed', 'Order status', 'woocommerce'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Delivery Failed (%s)', 'Delivery Failed (%s)', 'woocommerce')
    ));
    register_post_status('wc-delivery_hold', array(
        'label'                     => _x('Delivery on Hold', 'Order status', 'woocommerce'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Delivery on Hold (%s)', 'Delivery on Hold (%s)', 'woocommerce')
    ));
    register_post_status('wc-returned', array(
        'label'                     => _x('Returnd', 'Order status', 'woocommerce'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Returnd (%s)', 'Returnd (%s)', 'woocommerce')
    ));
    
}


// Add Custom Order Status to WooCommerce
add_filter('wc_order_statuses', 'add_pathaw_order_status_to_wc');
function add_pathaw_order_status_to_wc($order_statuses) {
    $new_order_statuses = array();

    // Add new order status after processing
    foreach ($order_statuses as $key => $status) {
        $new_order_statuses[$key] = $status;
        // if the order status is wc-order-processing then only then add the custom order statuses
        if ('wc-order-processing' === $key || 'wc-completed' === $key) {
            $new_order_statuses['wc-ready-to-shipping'] = _x('Ready For Shipping', 'Order status', 'woocommerce');               
            $new_order_statuses['wc-pickup_requested'] = _x('Pickup Requested', 'Order status', 'woocommerce');      
            $new_order_statuses['wc-pickup_updated'] = _x('Shipping Updated', 'Order status', 'woocommerce');       
            $new_order_statuses['wc-pickup_ok'] = _x('Parcel Picked', 'Order status', 'woocommerce'); // Frontend label
            $new_order_statuses['wc-pickup_error'] = _x('Pickup Failed', 'Order status', 'woocommerce'); // Frontend label
            $new_order_statuses['wc-pickup_cancelled'] = _x('Pickup Cancelled', 'Order status', 'woocommerce'); // Frontend label
            $new_order_statuses['wc-at_sorting_hub'] = _x('At Sorting Hub', 'Order status', 'woocommerce'); // Frontend label
            $new_order_statuses['wc-on_the_way'] = _x('In Transit', 'Order status', 'woocommerce'); // Frontend label
            $new_order_statuses['wc-last_mile_hub'] = _x('At Last Mile Hub', 'Order status', 'woocommerce'); // Frontend label
            $new_order_statuses['wc-ready_to_delivery'] = _x('Assigned for Delivery', 'Order status', 'woocommerce'); // Frontend label
            $new_order_statuses['wc-delivery_success'] = _x('Delivered', 'Order status', 'woocommerce'); // Frontend label
            $new_order_statuses['wc-delivery-failed'] = _x('Delivery Failed', 'Order status', 'woocommerce'); // Frontend label
            $new_order_statuses['wc-delivery_hold'] = _x('Delivery on Hold', 'Order status', 'woocommerce'); // Frontend label
            $new_order_statuses['wc-returned'] = _x('Returnd', 'Order status', 'woocommerce'); // Frontend label
            

        }
        
    }
    

    return $new_order_statuses;
}

