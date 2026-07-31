<?php

defined('ABSPATH') || exit;


require_once SMDP_COURIER_DIR . 'pathao/web-hook-updater.php';




// Add a simple test endpoint first
add_action('rest_api_init', function () {
    // error_log(' ');
    // error_log('-----------------' . date('Y-m-d H:i:s') . '----------------------');
    // error_log('REST API Init: Registering route for Pathao Webhook');

    register_rest_route('shipper/webhook', '/pathao', [
        'methods' => ['POST'],
        'callback' => 'pathao_webhook_callback',
        'permission_callback' => function ($request) {
            error_log('Permission callback called for: ' . $request->get_method());
            return true;
        },
    ]);
});

function pathao_webhook_callback(WP_REST_Request $request)
{


    $pathaw_signature = $request->get_header('x-smdp-signature') ?? '';
    $webhook_secret = get_option('pathao_webhook_secret');
    $response_header_secret = get_option('pathao_response_header_secret');
    $body = $request->get_params();

    error_log('===== CALLBACK FIRED =====');
    error_log('Method: ' . $request->get_method());
    error_log('Route: ' . $request->get_route());

    if (empty($webhook_secret)) {
        return new WP_REST_Response(['message' => 'You are not ready yet!'], 401);
    }

    if (!hash_equals($webhook_secret, $pathaw_signature)) {
        error_log('Signature mismatch!');
        return new WP_REST_Response(['message' => 'you are not okz!'], 401);
    }

    if (empty($body)) {
        return new WP_REST_Response(['message' => 'Invalid payload'], 400);
    }

    $response_headers = [
        'X-Pathao-Merchant-Webhook-Integration-Secret' => $response_header_secret,
    ];


    if (empty($body['consignment_id']) && empty($body['event'])) {
        return new WP_REST_Response([
            'message' => 'Either consignment_id or event must be provided.'
        ], 400, $response_headers);
    }

    update_pathaw_order_status($body);

    return new WP_REST_Response(['message' => 'Thank you for the Update of ' . $body['event']], 202, $response_headers);
}



function update_pathaw_order_status($payload)
{

    $consignment_id = $payload['consignment_id'] ?? null;

    // error_log('Pathao Webhook Event: ' . ($payload['event'] ?? '-') . ' for Order ID: ' . ($payload['merchant_order_id'] ?? '-') . ' with Consignment ID: ' . ($consignment_id ?? '-'));

    if (empty($consignment_id)) {
        error_log('Consignment ID is missing in the payload.' . ' Payload: ' . json_encode($payload));
        return;
    }

    error_log('Payload: ' . print_r($payload, true));
    $order_id = $payload['merchant_order_id'] ?? $payload['order_id'] ?? '-';
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    // order current status
    $current_status = $order->get_status();
    error_log('Current Order Status: ' . $current_status);
    if ($current_status == 'completed') {
        error_log('Order is already in completed status. Skipping update.');
        return;
    }

    $event  = str_replace('order.', '', $payload['event'] ?? '-');
    $reason = $payload['reason'] ?? null;
    $fee = $payload['delivery_fee'] ?? 0;
    // Load existing log
    $existing_log = $order->get_meta('pathaw_log');
    $log_array = [];

    if (!empty($existing_log)) {
        $decoded = json_decode($existing_log, true);
        if (is_array($decoded)) {
            $log_array = $decoded;
        }
    }

    // Append raw payload
    $log_array[] = $payload;

    // Save updated log
    $order->update_meta_data('pathaw_log', wp_json_encode($log_array));
    $order->save();

    // Optional: update order metadata/status
    update_order($order_id, $consignment_id, $event, $fee, $reason);
}



/**
 * Handle Pathao webhook payload and update WooCommerce order accordingly.
 *
 * This function:
 * 1. Validates the incoming webhook payload
 * 2. Locates the WooCommerce order using merchant_order_id
 * 3. Logs the raw webhook payload into order meta for audit/debugging
 * 4. Delegates order status and metadata updates to update_order()
 *
 * @param array $payload Raw webhook payload sent by Pathao
 *
 * @return void
 */
function update_pathaw_order_statusx($payload)
{

    /**
     * Extract consignment ID from payload
     * This is Pathao's internal shipment identifier
     */
    $consignment_id = $payload['consignment_id'] ?? null;

    /**
     * Log incoming webhook event for debugging and traceability
     * Useful when dealing with asynchronous webhook failures
     */
    error_log(
        'Pathao Webhook Event: ' . ($payload['event'] ?? '-') .
            ' | Order ID: ' . ($payload['merchant_order_id'] ?? '-') .
            ' | Consignment ID: ' . ($consignment_id ?? '-')
    );

    /**
     * Consignment ID is mandatory for any meaningful update.
     * If missing, log full payload and stop execution.
     */
    if (empty($consignment_id)) {
        error_log(
            'Pathao Webhook Error: Consignment ID missing. Payload: ' .
                json_encode($payload)
        );
        return;
    }

    /**
     * Retrieve WooCommerce order using merchant_order_id
     * Pathao sends this ID exactly as provided during order creation
     */
    $order_id = $payload['merchant_order_id'] ?? $payload['order_id'] ?? null;
    $order    = wc_get_order($order_id);

    /**
     * If order does not exist, silently exit
     * (Webhook retries may occur after order deletion or migration)
     */
    if (!$order) {
        return;
    }

    /**
     * Normalize event name
     * Pathao sends events like: "order.delivered"
     * We store it as: "delivered"
     */
    $event = str_replace('order.', '', $payload['event'] ?? '-');

    /**
     * Optional fields sent by Pathao
     */
    $reason = $payload['reason'] ?? null;
    $fee    = $payload['delivery_fee'] ?? 0;

    /**
     * Retrieve existing webhook log from order meta
     * Stored as JSON string to keep full history of webhook events
     */
    $existing_log = $order->get_meta('pathaw_log');
    $log_array    = [];

    /**
     * Decode existing log if present and valid
     */
    if (!empty($existing_log)) {
        $decoded = json_decode($existing_log, true);
        if (is_array($decoded)) {
            $log_array = $decoded;
        }
    }

    /**
     * Append current raw payload to log
     * This ensures full chronological event history is preserved
     */
    $log_array[] = $payload;

    /**
     * Save updated webhook log back to order meta
     */
    $order->update_meta_data('pathaw_log', wp_json_encode($log_array));
    $order->save();

    /**
     * Perform actual business logic update:
     * - Update order status
     * - Store consignment ID
     * - Apply delivery fee
     * - Record failure/cancellation reason (if any)
     *
     * This function should contain all status mapping rules.
     */
    update_order(
        $order_id,
        $consignment_id,
        $event,
        $fee,
        $reason
    );
}


// We are deprecating this function soon
function dep_update_order($order_id, $consignment_id, $status, $fee = 0, $reason = null)
{


    // transform $order_id to int
    $order_id = (int) $order_id;
    // Get the order object
    $order = wc_get_order($order_id);

    if (!$order) {
        return;
    }

    error_log('Updating Order ID: ' . $order_id . ' to status: ' . $status);
    // Set if order status is 'created' consignment_id as order meta
    if ($status === 'created') {
        error_log('Updating: ...\smdp-courier\pathao\web-hook.php');
        // '_consignment_id' is LEGACY meta — check-only, never overwrite
        $existing_consignment_id = get_post_meta($order_id, '_consignment_id', true);

        if ($existing_consignment_id) {
            return; // Prevent duplicate processing
        }

        // Validate required values before proceeding
        if (empty($consignment_id)) {
            $order->add_order_note(__('Pathao: Failed to create consignment — missing consignment ID.', 'your-textdomain'));
            return;
        }

        $data = $order->get_data();

        // Extract and sanitize phone number (last 11 digits of billing phone)
        $raw_phone = $data['billing']['phone'] ?? '';
        $phone     = preg_replace('/\D/', '', $raw_phone); // Strip non-numeric characters
        $phone     = substr($phone, -11);

        if (strlen($phone) < 11) {
            $order->add_order_note(__('Pathao: Warning — billing phone number appears invalid.', 'your-textdomain'));
            // Non-fatal: continue processing, tracking link may not work
        }

        // Sanitize consignment_id before use in URL/meta
        $consignment_id_clean = sanitize_text_field($consignment_id);

        // Build tracking URL
        $tracking_url = esc_url(
            'https://merchant.pathao.com/tracking?consignment_id=' . rawurlencode($consignment_id_clean) . '&phone=' . rawurlencode($phone)
        );

        // Update order status
        $order->update_status('wc-ready-to-shipping');

        // Add tracking note (visible to customer)
        $order->add_order_note(
            sprintf(
                __('Parcel Tracking: <a href="%1$s" target="_blank">%2$s</a>', 'your-textdomain'),
                $tracking_url,
                esc_html($consignment_id_clean)
            ),
            true // Notify customer
        );

        // Store consignment ID in LEGACY meta key (kept for backward compatibility)
        update_post_meta($order_id, '_consignment_id', $consignment_id_clean);

        // Store fee — cast to float, fallback to 0.0 if invalid
        $fee_clean = is_numeric($fee) ? (float) $fee : 0.0;
        update_post_meta($order_id, '_consignment_fee', $fee_clean);


        error_log('Updating Order ID: ' . $order_id . ' with consignment: ' . get_post_meta($order_id, '_consignment_fee', true));
    }

    switch ($status) {
        case 'picked':
            $order->update_status('wc-pickup_ok');
            $order->add_order_note(__('Parcel Picked Successfully'), false); // `false` makes it an admin note.
            break;
        case 'updated':
            $order->update_status('wc-pickup_updated');
            break;
        case 'pickup-requested':
            $order->update_status('wc-pickup_requested');
            $order->add_order_note(__('Waitting for Pickup'), true);
            break;
        case 'assigned-for-pickup': // set order note as assigned for pickup
            $order->add_order_note(__('Assigned for Pickup'), false); // `false` makes it an admin note.
            break;
        case 'pickup-failed':
            $order->update_status('wc-pickup_error');
            $order->add_order_note(__('Pickup Failed'), false); // `false` makes it an admin note.
            break;
        case 'pickup-cancelled':
            $order->update_status('wc-pickup_cancelled');
            break;
        case 'at-the-sorting-hub':
            $order->update_status('wc-at_sorting_hub');
            $order->add_order_note(__('At the Sorting Hub'), false);
            break;
        case 'in-transit':
            $order->update_status('wc-on_the_way');
            $order->add_order_note(__('On the way'), true);
            break;
        case 'received-at-last-mile-hub':
            $order->update_status('wc-last_mile_hub');
            $order->add_order_note(__('Parcel arrived at your city'), true);
            break;
        case 'assigned-for-delivery':
            $order->update_status('wc-ready_to_delivery');
            $order->add_order_note(__('On the way to Delivery'), false);
            break;
        case 'partial-delivery':
            $order->add_order_note(__('Partially deliverted: ' . $reason), false);
            break;
        case 'delivered':
            $order->update_status('wc-delivery_success');
            $order->add_order_note(__('পার্সেলটি সফলভাবে ডেলিভারি করা হয়েছে!'), true);
            break;
        case 'delivery-failed':
            $order->add_order_note(__('Delivery Failed: ' . $reason), true);
            $order->update_status('wc-delivery-failed');
            break;
        case 'returned':
            $order->add_order_note(__('Returned: ' . $reason), true);
            $order->update_status('wc-returned');
            break;
        case 'on-hold':
            $order->add_order_note(__('On Hold: ' . $reason), true);
            $order->update_status('wc-delivery_hold');
            break;
        case 'paid':
            $order->add_order_note(__('Paid'), false);
            $order->update_status('wc-completed');
            break;
        case 'paid-return':
            $order->add_order_note(__('Paid Returned: ' . $reason), false);
            break;
        case 'exchanged':
            $order->add_order_note(__('Exchanged: ' . $reason), false);
            break;
        default:
            break;
    }
}
