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

// Hook to add custom field to the order edit page 
add_action('woocommerce_admin_order_data_after_billing_address', 'add_consignment_id_field');
function add_consignment_id_field($order)
{
    $pkg_weight = get_post_meta($order->get_id(), '_pkg_weight', true);
    $pkg_weight = empty($pkg_weight) ? "0.5" : $pkg_weight;
    $pkg_qty = get_post_meta($order->get_id(), '_pkg_qty', true);
    $pkg_qty = empty($pkg_qty) ? 1 : $pkg_qty;
    $package_desc = get_post_meta($order->get_id(), '_pkg_desc', true) == "" ? "Electronics Parts and ICs" : get_post_meta($order->get_id(), '_pkg_desc', true);

    echo '<div class="form-field form-field-wide"> <label for="pathao_pkg_weight">' . __('Package Weight', SMDP_COURIER_DOMAIN) . ':</label> <input type="text" id="pkg_weight" name="pkg_weight" value="' . esc_attr($pkg_weight) . '" class="pathao-auto-save"> </div>';
    echo '<div class="form-field form-field-wide"> <label for="pathao_pkg_qty">' . __('Package Quantity', SMDP_COURIER_DOMAIN) . ':</label> <input type="text" id="pkg_qty" name="pkg_qty" value="' . esc_attr($pkg_qty) . '" class="pathao-auto-save"> </div>';
    echo '<div class="form-field form-field-wide"> <label for="pathao_pkg_desc">' . __('Package Description', SMDP_COURIER_DOMAIN) . ':</label> <input type="text" id="pkg_desc" name="pkg_desc" value="' . esc_attr($package_desc) . '" class="pathao-auto-save"> </div>';
}

add_action('woocommerce_admin_order_data_after_shipping_address', 'add_pathao_address_fields');
function add_pathao_address_fields($order)
{
    $order_id = $order->get_id();
    $consignment_id = get_post_meta($order->get_id(), '_consignment_id', true);
    $pathao_city = get_post_meta($order_id, '_pathao_city', true);
    $pathao_zone = get_post_meta($order_id, '_pathao_zone', true);
    $pathao_area = get_post_meta($order_id, '_pathao_area', true);

    // Get order's shipping city for auto-selection
    $order_city = get_post_meta($order_id, '_shipping_city', true);
?>
    <!-- <div class="pathao-address-section"><h4 style="margin-top:0;"><?php _e('Pathao Delivery Information', SMDP_COURIER_DOMAIN); ?></h4><div class="">s</div></div> -->
    <div class="pathao-address-section">
        <h4 style="margin-top:0;"><?php _e('Pathao Delivery Information', SMDP_COURIER_DOMAIN); ?></h4>

        <!-- City Selection -->
        <div class="form-field form-field-wide">
            <label for="pathao_city"><?php _e('City', SMDP_COURIER_DOMAIN); ?>:</label>
            <select id="pathao_city" name="pathao_city" class="pathao-location-select" data-order-id="<?php echo $order_id; ?>" data-level="city">
                <option value=""><?php _e('Loading cities...', SMDP_COURIER_DOMAIN); ?></option>
            </select>
            <span class="pathao-loading" id="city-loading" style="display:none;">⟳</span>
        </div>

        <!-- Zone Selection -->
        <div class="form-field form-field-wide">
            <label for="pathao_zone"><?php _e('Zone', SMDP_COURIER_DOMAIN); ?>:</label>
            <select id="pathao_zone" name="pathao_zone" class="pathao-location-select" data-order-id="<?php echo $order_id; ?>" data-level="zone" disabled>
                <option value=""><?php _e('Select city first', SMDP_COURIER_DOMAIN); ?></option>
            </select>
            <span class="pathao-loading" id="zone-loading" style="display:none;">⟳</span>
        </div>

        <!-- Area Selection -->
        <div class="form-field form-field-wide">
            <label for="pathao_area"><?php _e('Area', SMDP_COURIER_DOMAIN); ?>:</label>
            <select id="pathao_area" name="pathao_area" class="pathao-location-select" data-order-id="<?php echo $order_id; ?>" data-level="area" disabled>
                <option value=""><?php _e('Select zone first', SMDP_COURIER_DOMAIN); ?></option>
            </select>
            <span class="pathao-loading" id="area-loading" style="display:none;">⟳</span>
        </div>

        <!-- Consignment ID -->
        <div class="form-field form-field-wide">
            <label for="pathao_consignment_id"><?php _e('Pathao Consignment ID', SMDP_COURIER_DOMAIN); ?>:</label>
            <input type="text" id="pathao_consignment_id" name="pathao_consignment_id" value="<?php echo esc_attr($consignment_id); ?>" readonly>
        </div>

        <!-- Save Status -->
        <div class="pathao-save-status" style="margin-top: 10px; font-size: 12px;"></div>
    </div>

    <!-- Hidden fields to store current values -->
    <input type="hidden" id="current_pathao_city" value="<?php echo esc_attr($pathao_city); ?>">
    <input type="hidden" id="current_pathao_zone" value="<?php echo esc_attr($pathao_zone); ?>">
    <input type="hidden" id="current_pathao_area" value="<?php echo esc_attr($pathao_area); ?>">
    <input type="hidden" id="order_shipping_city" value="<?php echo esc_attr($order_city); ?>">

    <style>
        .pathao-address-section {
            background: #f9f9f9;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 10px 0;
        }

        .order_data_column>.pathao-address-section>.form-field.form-field-wide {
            float: none !important;
        }

        .pathao-loading {
            margin-left: 10px;
            color: #0073aa;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .pathao-save-status {
            padding: 5px;
            border-radius: 3px;
            display: none;
        }

        .pathao-save-status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .pathao-save-status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .pathao-auto-save {
            border-left: 3px solid #0073aa;
        }
    </style>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            const orderId = <?php echo $order_id; ?>;
            let saveTimeout;

            // Initialize location dropdowns
            initializeLocations();

            // Auto-save for package fields
            $('.pathao-auto-save').on('input change', function() {
                clearTimeout(saveTimeout);
                const field = $(this);
                const fieldName = field.attr('name');
                const fieldValue = field.val();

                saveTimeout = setTimeout(function() {
                    saveMetaField(fieldName, fieldValue, field);
                }, 2000); // Save 1 second after user stops typing
            });

            // Location dropdowns change handlers
            $('#pathao_city').on('change', function() {
                const cityId = $(this).val();
                const field = $(this);

                if (cityId) {
                    saveMetaField('pathao_city', cityId, field);
                    loadZones(cityId);
                    // Reset dependent dropdowns
                    $('#pathao_zone, #pathao_area').html('<option value="">-</option>').prop('disabled', true);
                    $('#pathao_zone').prop('disabled', false);
                }
            });

            $('#pathao_zone').on('change', function() {
                const zoneId = $(this).val();
                const field = $(this);

                if (zoneId) {
                    saveMetaField('pathao_zone', zoneId, field);
                    loadAreas(zoneId);
                    // Reset area dropdown
                    $('#pathao_area').html('<option value="">-</option>').prop('disabled', false);
                }
            });

            $('#pathao_area').on('change', function() {
                const areaId = $(this).val();
                const field = $(this);

                if (areaId) {
                    saveMetaField('pathao_area', areaId, field);
                }
            });

            function initializeLocations() {
                showLoading('city');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'pathao_get_cities',
                        order_id: orderId,
                        nonce: '<?php echo wp_create_nonce("pathao_locations_nonce"); ?>'
                    },
                    success: function(response) {
                        hideLoading('city');
                        if (response.success) {
                            populateCities(response.data);
                        } else {
                            showError('Failed to load cities: ' + response.data);
                        }
                    },
                    error: function(e) {
                        hideLoading('city');
                        showError('Error loading cities', e);
                        console.log(e);
                    }
                });
            }

            function populateCities(cities) {
                const citySelect = $('#pathao_city');
                const currentCity = $('#current_pathao_city').val();
                const orderCity = $('#order_shipping_city').val();

                citySelect.html('<option value="">Select City</option>');

                let selectedCityId = '';
                cities.forEach(function(city) {
                    const isSelected = city.city_id == currentCity ||
                        (city.city_name == orderCity && !currentCity);
                    if (isSelected) selectedCityId = city.city_id;

                    citySelect.append(new Option(city.city_name, city.city_id, false, isSelected));
                });

                if (selectedCityId) {
                    loadZones(selectedCityId);
                }
            }

            function loadZones(cityId) {
                showLoading('zone');
                $('#pathao_zone').prop('disabled', true);

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'pathao_get_zones',
                        city_id: cityId,
                        order_id: orderId,
                        nonce: '<?php echo wp_create_nonce("pathao_locations_nonce"); ?>'
                    },
                    success: function(response) {
                        hideLoading('zone');
                        if (response.success) {
                            populateZones(response.data);
                        } else {
                            showError('Failed to load zones');
                        }
                    },
                    error: function() {
                        hideLoading('zone');
                        showError('Error loading zones');
                    }
                });
            }

            function populateZones(zones) {
                const zoneSelect = $('#pathao_zone');
                const currentZone = $('#current_pathao_zone').val();

                zoneSelect.html('<option value="">Select Zone</option>').prop('disabled', false);

                let selectedZoneId = '';
                zones.forEach(function(zone) {
                    const isSelected = zone.zone_id == currentZone;
                    if (isSelected) selectedZoneId = zone.zone_id;

                    zoneSelect.append(new Option(zone.zone_name, zone.zone_id, false, isSelected));
                });

                if (selectedZoneId) {
                    loadAreas(selectedZoneId);
                }
            }

            function loadAreas(zoneId) {
                showLoading('area');
                $('#pathao_area').prop('disabled', true);

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'pathao_get_areas',
                        zone_id: zoneId,
                        order_id: orderId,
                        nonce: '<?php echo wp_create_nonce("pathao_locations_nonce"); ?>'
                    },
                    success: function(response) {
                        hideLoading('area');
                        if (response.success) {
                            populateAreas(response.data);
                        } else {
                            showError('Failed to load areas');
                        }
                    },
                    error: function() {
                        hideLoading('area');
                        showError('Error loading areas');
                    }
                });
            }

            function populateAreas(areas) {
                const areaSelect = $('#pathao_area');
                const currentArea = $('#current_pathao_area').val();

                areaSelect.html('<option value="">Select Area</option>').prop('disabled', false);

                areas.forEach(function(area) {
                    const isSelected = area.area_id == currentArea;
                    areaSelect.append(new Option(area.area_name, area.area_id, false, isSelected));
                });
            }

            function saveMetaField(fieldName, fieldValue, field) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'pathao_save_meta_field',
                        order_id: orderId,
                        field_name: fieldName,
                        field_value: fieldValue,
                        nonce: '<?php echo wp_create_nonce("pathao_save_meta_nonce"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            showSuccess('Saved: ' + fieldName);
                            field.css('border-left-color', '#28a745');
                            setTimeout(function() {
                                field.css('border-left-color', '#0073aa');
                            }, 2000);
                        } else {
                            showError('Failed to save: ' + fieldName);
                            field.css('border-left-color', '#dc3545');
                        }
                    },
                    error: function() {
                        showError('Error saving: ' + fieldName);
                        field.css('border-left-color', '#dc3545');
                    }
                });
            }

            function showLoading(level) {
                $('#' + level + '-loading').show();
            }

            function hideLoading(level) {
                $('#' + level + '-loading').hide();
            }

            function showSuccess(message) {
                $('.pathao-save-status')
                    .removeClass('error')
                    .addClass('success')
                    .text(message)
                    .show()
                    .delay(3000)
                    .fadeOut();
            }

            function showError(message) {
                $('.pathao-save-status')
                    .removeClass('success')
                    .addClass('error')
                    .text(message)
                    .show()
                    .delay(5000)
                    .fadeOut();
            }
        });
    </script>
<?php
}

// AJAX handler for getting cities
add_action('wp_ajax_pathao_get_cities', 'pathao_ajax_get_cities');
function pathao_ajax_get_cities()
{
    check_ajax_referer('pathao_locations_nonce', 'nonce');

    if (!current_user_can('edit_shop_orders')) {
        wp_die('Insufficient permissions');
    }

    $order_id = intval($_POST['order_id']);
    $is_sandbox = get_option('pathao_sandbox') === 'yes';
    $token = pathao_get_valid_token($is_sandbox);

    if (!$token) {
        wp_send_json_error('No valid access token available');
        return;
    }

    $prefix = $is_sandbox ? 'pathao_sandbox_' : 'pathao_';
    $base_url = get_option($prefix . 'base_url');

    $cities = get_pathao_cities($token, $base_url);

    if ($cities) {
        wp_send_json_success($cities);
    } else {
        wp_send_json_error('Failed to fetch cities');
    }
}

// AJAX handler for getting zones
add_action('wp_ajax_pathao_get_zones', 'pathao_ajax_get_zones');
function pathao_ajax_get_zones()
{
    check_ajax_referer('pathao_locations_nonce', 'nonce');

    if (!current_user_can('edit_shop_orders')) {
        wp_die('Insufficient permissions');
    }

    $city_id = intval($_POST['city_id']);
    $order_id = intval($_POST['order_id']);

    $is_sandbox = get_option('pathao_sandbox') === 'yes';
    $token = pathao_get_valid_token($is_sandbox);

    if (!$token) {
        wp_send_json_error('No valid access token available');
        return;
    }

    $prefix = $is_sandbox ? 'pathao_sandbox_' : 'pathao_';
    $base_url = get_option($prefix . 'base_url');

    $zones = get_pathao_zones($token, $base_url, $city_id);

    if ($zones) {
        wp_send_json_success($zones);
    } else {
        wp_send_json_error('Failed to fetch zones');
    }
}

// AJAX handler for getting areas
add_action('wp_ajax_pathao_get_areas', 'pathao_ajax_get_areas');
function pathao_ajax_get_areas()
{
    check_ajax_referer('pathao_locations_nonce', 'nonce');

    if (!current_user_can('edit_shop_orders')) {
        wp_die('Insufficient permissions');
    }

    $zone_id = intval($_POST['zone_id']);
    $order_id = intval($_POST['order_id']);

    $is_sandbox = get_option('pathao_sandbox') === 'yes';
    $token = pathao_get_valid_token($is_sandbox);

    if (!$token) {
        wp_send_json_error('No valid access token available');
        return;
    }

    $prefix = $is_sandbox ? 'pathao_sandbox_' : 'pathao_';
    $base_url = get_option($prefix . 'base_url');

    $areas = get_pathao_area($token, $base_url, $zone_id);

    if ($areas) {
        wp_send_json_success($areas);
    } else {
        wp_send_json_error('Failed to fetch areas');
    }
}

// AJAX handler for saving meta fields
add_action('wp_ajax_pathao_save_meta_field', 'pathao_ajax_save_meta_field');
function pathao_ajax_save_meta_field()
{
    check_ajax_referer('pathao_save_meta_nonce', 'nonce');

    if (!current_user_can('edit_shop_orders')) {
        wp_die('Insufficient permissions');
    }

    $order_id = intval($_POST['order_id']);
    $field_name = sanitize_text_field($_POST['field_name']);
    $field_value = sanitize_text_field($_POST['field_value']);

    // Map field names to meta keys
    $field_mapping = [
        'pkg_weight' => '_pkg_weight',
        'pkg_qty' => '_pkg_qty',
        'pkg_desc' => '_pkg_desc',
        'pathao_city' => '_pathao_city',
        'pathao_zone' => '_pathao_zone',
        'pathao_area' => '_pathao_area'
    ];

    if (!isset($field_mapping[$field_name])) {
        wp_send_json_error('Invalid field name');
        return;
    }

    $meta_key = $field_mapping[$field_name];
    $updated = update_post_meta($order_id, $meta_key, $field_value);

    if ($updated !== false) {
        // Log the change
        $order = wc_get_order($order_id);
        if ($order) {
            $order->add_order_note(
                sprintf('Pathao field updated: %s = %s', $field_name, $field_value),
                false,
                true
            );
        }

        wp_send_json_success('Field saved successfully');
    } else {
        wp_send_json_error('Failed to save field');
    }
}

// Remove the old save hook since we're now saving via AJAX
// add_action('save_post_shop_order', 'save_pathao_meta_fields');

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
add_action('woocommerce_process_shop_order_meta', 'save_pathao_hpos_meta_fields', 10, 2);
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
    if (isset($_POST['pathao_city'])) {
        $order->update_meta_data('_pathao_city', sanitize_text_field($_POST['pathao_city']));
    }
    if (isset($_POST['pathao_zone'])) {
        $order->update_meta_data('_pathao_zone', sanitize_text_field($_POST['pathao_zone']));
    }
    if (isset($_POST['pathao_area'])) {
        $order->update_meta_data('_pathao_area', sanitize_text_field($_POST['pathao_area']));
    }
    $order->save_meta_data();
}
