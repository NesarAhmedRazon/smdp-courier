<?php
// Metabox content
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

// Add a custom metabox
add_action('add_meta_boxes', 'admin_order_custom_metabox');
function admin_order_custom_metabox()
{
    $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') && wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
        ? wc_get_page_screen_id('shop-order')
        : 'shop_order';

    add_meta_box(
        'custom',
        'Pathao Log',
        'custom_metabox_content',
        $screen,
        'normal',
        'low'
    );
}

// Metabox content
function custom_metabox_content($object)
{
    // Get the WC_Order object
    $order = is_a($object, 'WP_Post') ? wc_get_order($object->ID) : $object;
    get_pathaw_log_table_for_order($order->get_order_number());
}

function smdp_get_shipping_provider_for_instance($instance_id)
{
    $instance_settings = get_option("woocommerce_flat_rate_{$instance_id}_settings", []);
    return $instance_settings['shipping_provider'] ?? '';
}

// Hook to add custom field to the order edit page 
if(!function_exists('consignment_metas')){
    add_action('woocommerce_admin_order_data_after_billing_address', 'consignment_metas');
    function consignment_metas($order){
        $pkg_weight = get_post_meta($order->get_id(), '_pkg_weight', true);
        $pkg_weight = empty($pkg_weight) ? "0.2" : $pkg_weight;
        $pkg_qty = get_post_meta($order->get_id(), '_pkg_qty', true);
        $pkg_qty = empty($pkg_qty) ? 1 : $pkg_qty;
        $package_desc = get_post_meta($order->get_id(), '_pkg_desc', true) == "" ? "Electronics Parts and ICs" : get_post_meta($order->get_id(), '_pkg_desc', true);

        ?> 
        <div class="meta_item_grid" style="display:grid; grid-template-columns:repeat(6,1fr); gap:10px;">
            <div class="form-field form-field-wide s"> 
                <label for="pathao_pkg_weight"><?= esc_html('Weight', SMDP_TEXTDOMAIN);?>:</label> 
                <input type="text" id="pkg_weight" name="pkg_weight" value="<?= esc_attr($pkg_weight, SMDP_TEXTDOMAIN);?>" class="pathao-auto-save">
            </div>
            <div class="form-field form-field-wide"> 
                <label for="pathao_pkg_qty"><?= esc_html('Quantity', SMDP_TEXTDOMAIN);?>:</label> 
                <input type="text" id="pkg_qty" name="pkg_qty" value="<?= esc_attr($pkg_qty, SMDP_TEXTDOMAIN);?>" class="pathao-auto-save"> 
            </div>
            <div class="form-field form-field-wide" style="grid-column:span 4;"> 
                <label for="pathao_pkg_desc"><?= esc_html('Package Description', SMDP_TEXTDOMAIN);?>:</label>
                <input type="text" id="pkg_desc" name="pkg_desc" value="<?= esc_attr($package_desc, SMDP_TEXTDOMAIN);?>" class="pathao-auto-save"> 
            </div>
        </div>
        
    <?php }
}




function get_pathaw_log_table_for_order($order_id)
{
    $order = wc_get_order($order_id);
    if (!$order) return;

    $log_json = $order->get_meta('pathaw_log');
    if (empty($log_json)) {
        echo '<p><em>No delivery logs found.</em></p>';
        return;
    }

    $log_array = json_decode($log_json, true);
    if (!is_array($log_array)) {
        echo '<p><em>Invalid log data format.</em></p>';
        return;
    }

    echo "<table class='pathaw-log-table' style='border: 1px solid #ccc; border-collapse: collapse; width: 100%;'>";
    echo "<thead><tr style='background: #f9f9f9;'>";
    echo "<th style='border: 1px solid #ccc; padding: 8px; text-align: left;'>Event</th>";
    echo "<th style='border: 1px solid #ccc; padding: 8px; text-align: left;'>Updated At</th>";
    echo "</tr></thead><tbody>";

    foreach ($log_array as $entry) {
        $event = $entry['event'] ?? ($entry['payload']['event'] ?? '—');
        $event = str_replace('order.', '', $event ?? '-');
        $updated_at_raw = $entry['updated_at'] ?? ($entry['payload']['updated_at'] ?? null);

        // Convert to WordPress timezone if a valid timestamp exists
        if ($updated_at_raw) {
            try {
                $datetime = new DateTime($updated_at_raw, new DateTimeZone('Asia/Dhaka'));
                $datetime->setTimezone(wp_timezone());
                $updated_at = esc_html($datetime->format('j M, Y h:i A'));
            } catch (Exception $e) {
                $updated_at = esc_html($updated_at_raw);
            }
        } else {
            $updated_at = '—';
        }

        echo "<tr>";
        echo "<td style='border: 1px solid #ccc; padding: 8px;'>" . esc_html(ucfirst(str_replace('_', ' ', $event))) . "</td>";
        echo "<td style='border: 1px solid #ccc; padding: 8px;'>" . $updated_at . "</td>";
        echo "</tr>";
    }

    echo "</tbody></table>";
}

// Add High-Performance Order Storage (HPOS) compatibility
// add_action('woocommerce_process_shop_order_meta', 'save_pathao_hpos_meta_fields', 10, 2);
function save_pathao_hpos_meta_fields($order_id, $order)
{
    // This ensures compatibility with HPOS
    // The AJAX handlers already handle the saving, but this is a fallback
    if (isset($_POST['pkg_weight'])) {
        $order->update_meta_data('_pkg_weight', sanitize_text_field($_POST['pkg_weight']));
    }
    if (isset($_POST['pkg_qty'])) {
        $order->update_meta_data('_pkg_qty', sanitize_text_field($_POST['pkg_qty']));
    }
    if (isset($_POST['pkg_desc'])) {
        $order->update_meta_data('_pkg_desc', sanitize_text_field($_POST['pkg_desc']));
    }
    
    $order->save_meta_data();
}

