<?php 

defined('ABSPATH') || exit;


// Add a custom endpoint for the webhook
add_action('rest_api_init', function () {
    register_rest_route('pathao/v1', '/webhook', [
        'methods' => 'POST',
        'callback' => 'pathao_webhook_callback',
        'permission_callback' => '__return_true', // Allow public access
        
    ]);
});


function pathao_webhook_callback(WP_REST_Request $request) {
    $logger = SMDP_Logger::get_instance( SMDP_COURIER_DIR );
    $pathaw_signature = $request->get_header('X-PATHAO-Signature');
    $webhook_secret = get_option('pathao_webhook_secret');
    $response_header_secret = get_option('pathao_response_header_secret');
    $body = $request->get_params();

    // Log the request regardless of validation
   $logger->write([
        'headers' => $request->get_headers(),
        'body' => $body,
    ], 'pathao_webhook_requests_log.json');

    if (empty($webhook_secret)) {
        return new WP_REST_Response(['message' => 'You are not ready yet!'], 401);
    }

    if (!hash_equals($webhook_secret, $pathaw_signature)) {
        return new WP_REST_Response(['message' => 'you are not ok!'], 401);
    }

    if (empty($body)) {
        return new WP_REST_Response(['message' => 'Invalid payload'], 400);
    } else {

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
}




function update_pathaw_order_status($payload) {

$consignment_id = $payload['consignment_id'] ?? null;
$logger = SMDP_Logger::get_instance( SMDP_COURIER_DIR );

$logger->write([
    'event' => $payload['event'] ?? '-',
    'consignment_id' => $consignment_id,
    'merchant_order_id' => $payload['merchant_order_id'] ?? '-',
    'status' => 'received'
], 'update_pathaw_order_status_log.json');

if (empty($consignment_id)) {
    $logger->write([
        'payload' => $payload,
    ], 'update_pathaw_order_status_error_log.json');        
    return;
}


$order_id = $payload['merchant_order_id'] ?? '-';
$order = wc_get_order($order_id);
if (!$order) {
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
update_order($order_id, $consignment_id, $event,$fee, $reason);
}


function update_order($order_id,$consignment_id, $status, $fee=0, $reason = null) {


    // transform $order_id to int
    $order_id = (int) $order_id;
    // Get the order object
    $order = wc_get_order($order_id);
    
    if (!$order) {
        return;
    }
    

    // Set if order status is 'created' consignment_id as order meta
    if($status == 'created'){
        // check if consignment_id is already set
        $existing_consignment_id = get_post_meta($order_id, '_consignment_id', true);
       
        if($existing_consignment_id){
            return;
        }
        $data = $order->get_data();
        // get shipping phone number
        $phone = $data['billing']['phone'];
        // grab last 11 digits
        $phone = substr($phone, -11);
        

        $order->update_status('wc-ready-to-shipping');
        $order->add_order_note(__('Parcel Tracking: <a href="https://merchant.pathao.com/tracking?consignment_id='.$consignment_id.'&phone='.$phone.'">'.$consignment_id.'</a>'), true);
        update_post_meta($order_id, '_consignment_id', $consignment_id); 
        
        $fee = (float) $fee;
        update_post_meta($order_id, '_consignment_fee', $fee);
         
        
           
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
            $order->add_order_note(__('Parcel arrived at your city'),true);
            break;
        case 'assigned-for-delivery':
            $order->update_status('wc-ready_to_delivery');
            $order->add_order_note(__('On the way to Delivery'),false);
            break;
        case 'partial-delivery':
            $order->add_order_note(__('Partially deliverted: '. $reason ), false);
            break;
        case 'delivered':
            $order->update_status('wc-delivery_success');
            $order->add_order_note(__('পার্সেলটি সফলভাবে ডেলিভারি করা হয়েছে!'),true);
            break;
        case 'delivery-failed':
            $order->add_order_note(__('Delivery Failed: '. $reason ), true);
            $order->update_status('wc-delivery-failed');
            break;
        case 'returned':
            $order->add_order_note(__('Returned: '. $reason ), true);
            $order->update_status('wc-returned');
            break;
        case 'on-hold':
            $order->add_order_note(__('On Hold: '. $reason ), true);
            $order->update_status('wc-delivery_hold');
            break;
        case 'paid':
            $order->add_order_note(__('Paid' ), false); 
            $order->update_status('wc-completed');           
            break;
        case 'paid-return':
            $order->add_order_note(__('Paid Returned: '. $reason ), false);            
            break;
        case 'exchanged':
            $order->add_order_note(__('Exchanged: '. $reason ), false);            
            break;
        default:            
            break;
    }   
}