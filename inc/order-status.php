<?php 

defined('ABSPATH') || exit;

// Add custom order status to WooCommerce

// Register Custom Order Status
add_action('init', 'register_custom_order_status');
function register_custom_order_status() {
    register_post_status('wc-order-placed', array(
        'label'                     => _x('Order Placed', 'Order status', 'woocommerce'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Order Placed (%s)', 'Order Placed (%s)', 'woocommerce')
    ));

    register_post_status('wc-order-confirmed', array(
        'label'                     => _x('Order Confirmed', 'Order status', 'woocommerce'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Order Confirmed (%s)', 'Order Confirmed (%s)', 'woocommerce')
    ));
    register_post_status('wc-order-processing', array(
        'label'                     => _x('Order Processing', 'Order status', 'woocommerce'),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Order Processing (%s)', 'Order Processing (%s)', 'woocommerce')
    ));
}

// Add Custom Order Status to WooCommerce
add_filter('wc_order_statuses', 'add_custom_order_status_to_wc');
function add_custom_order_status_to_wc($order_statuses) {
    $new_order_statuses = array();

    // Add new order status after processing
    foreach ($order_statuses as $key => $status) {
        $new_order_statuses[$key] = $status;
        if ('wc-processing' === $key) {
            $new_order_statuses['wc-order-placed'] = _x('Order Placed', 'Order status', 'woocommerce');                 
            $new_order_statuses['wc-order-confirmed'] = _x('Confirmed ', 'Order status', 'woocommerce'); // Frontend label
            $new_order_statuses['wc-order-processing'] = _x('Preparing', 'Order status', 'woocommerce'); // Frontend label
        }
    }
    

    return $new_order_statuses;
}

// Add custom order notes for "Order Confirmed" status
add_action('woocommerce_order_status_changed', 'add_custom_admin_note_on_status_change', 10, 4);

function add_custom_admin_note_on_status_change($order_id, $old_status, $new_status, $order) {
    // Define your custom status (without the "wc-" prefix).
    $order_confirmed = 'order-confirmed'; // Replace with your custom status slug.
    $order_processing = 'order-processing'; // Replace with your custom status slug.
    // get the admin user name
    $user = wp_get_current_user();
    $user_name = ($user && $user->ID) ? $user->display_name : 'System';

    // Old status Label
    $old_status = wc_get_order_status_name($old_status);

    // Check if the new status matches your custom status.
    if ($new_status === $order_confirmed) {
        $new_status_label = wc_get_order_status_name($new_status);
        // Add a custom admin note to the order.
        $custom_note = __('Order ' . $new_status_label . ' by <strong>' . $user_name.'</strong>', 'woocommerce');
        $order->add_order_note($custom_note, false); // `false` makes it an admin note.
    }
    if ($new_status === $order_processing) {
        $new_status_label = wc_get_order_status_name($new_status);
        // Add a custom admin note to the order.
        $custom_note = __('<strong>' . $user_name.'</strong> is Preparing the order', 'woocommerce');
        $order->add_order_note($custom_note, false); // `false` makes it an admin note.
    }
    
}



// Automatically set new orders to "Order Placed" status
add_action('woocommerce_thankyou', 'set_order_placed_status');
function set_order_placed_status($order_id) {
    if (!$order_id) return;

    $order = wc_get_order($order_id);
    if ($order->get_status() !== 'order-placed') {
        $order->update_status('wc-order-placed');
    }
}

// Allow customers to edit orders in "Order Placed" status with a modal 
add_filter('woocommerce_account_orders_columns', 'add_edit_link_to_orders'); 

function add_edit_link_to_orders($columns) { 
    $columns['order-actions'] = __('Actions', 'woocommerce'); return $columns; 
}

add_filter('woocommerce_my_account_my_orders_actions', 'add_edit_button_to_orders', 10, 2); 

function add_edit_button_to_orders($actions, $order) { 
    if ($order->has_status('order-placed')) { 
        $actions['edit'] = array( 
            'url' => '#edit-order_'.$order->get_id(), 
            'name' => __('Edit Order', 'woocommerce'), 
            'custom_attr' => array( 
                'class' => 'trigger-edit-order', 
                'data-order-id' => $order->get_id() 
            ), 
        ); 
    } 
    return $actions; 
}

// Enqueue custom JavaScript and CSS for the modal 
add_action('wp_enqueue_scripts', 'enqueue_custom_scripts'); 
function enqueue_custom_scripts() {
    if (is_account_page() && is_user_logged_in()) {
        wp_enqueue_script('smdpicker-order-edit', SMD_PICKER_PLUGIN_URL . 'assets/js/order-edit.js', ['jquery'], time(), true);
        wp_enqueue_style('smdpicker-order-edit', SMD_PICKER_PLUGIN_URL . 'assets/style/order-edit.css', [], time());
    }
}

add_action('wp_footer', 'add_edit_order_modal');
function add_edit_order_modal() {
    if (!is_account_page() || !is_user_logged_in()) return;
    ?>
    <div id="editOrderModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2><?php _e('Edit Order', 'woocommerce'); ?></h2>
            <div id="edit-order-form-content"></div>
        </div>
    </div>
    <?php
}