<?php 

defined('ABSPATH') || exit;


function pathaw_create_new_delivery($order_id,$old_status){
    $sandbox = get_option('pathao_sandbox');
    $prefix = $sandbox == 'yes' ? 'Sandbox -> ' : '';
    $order = wc_get_order($order_id);

    if($sandbox == 'yes'){
        $store_id = get_option('pathao_sandbox_store_id');
        $base_url = get_option('pathao_sandbox_base_url');
        $token = get_option('pathao_sandbox_access_token');
    }else{
        $store_id = get_option('pathao_store_id');
        $base_url = get_option('pathao_base_url');
        $token = get_option('pathao_access_token');
    }
    

    
    $order_data = $order->get_data();
    $order_total = $order->get_total();

    $client_info = get_client_info($order);
    $name = $client_info['name'];
    $phone = $client_info['phone'];    
    $order_address = $client_info['address'];
    
    $client_city = get_post_meta($order_id, '_pathao_city', true);
    $client_zone = get_post_meta($order_id, '_pathao_zone', true);
    $client_area = get_post_meta($order_id, '_pathao_area', true);
    $order_note = $order->get_customer_note();
    $package_weight = get_post_meta($order_id, '_pkg_weight', true);
    $package_qty = get_post_meta($order_id, '_pkg_qty', true);
    $package_desc = get_post_meta($order_id, '_pkg_desc', true);

    $data = [
        'store_id' => $store_id,
        'merchant_order_id' => $order_id,
        'recipient_name' => $name,
        'recipient_phone' => $phone,
        'recipient_address' => $order_address,
        'recipient_city' => $client_city,
        'recipient_zone' => $client_zone,
        'delivery_type' => 48,
        'item_type' => 2,
        'item_quantity' => $package_qty,
        'item_weight' => $package_weight,
        'item_description' => $package_desc,
        'amount_to_collect' => ceil($order_total),
    ];

    if(!empty($client_area)){
        $data['recipient_area'] = $client_area;
    }
    if(!empty($order_note)){
        $data['special_instruction'] = $order_note;
    }

    $payload = json_encode( $data );
// API Endpoint
    $url = $base_url."aladdin/api/v1/orders";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $data = json_decode($response, true);
        $consignment_id = $data['data']['consignment_id'];
        $fee = $data['data']['delivery_fee'];
        $order->add_order_note(__($prefix.'Pathao Message: '.$response), false);
        //$order->add_order_note(__($prefix.'Parcel Tracking: <a href="https://merchant.pathao.com/tracking?consignment_id='.$consignment_id.'&phone='.$phone.'">'.$consignment_id.'</a>'), true);
        //update_post_meta($order_id, '_consignment_id', $consignment_id);
        update_post_meta($order_id, '_consignment_fee', $fee);
        update_order($order_id,$consignment_id,'created');
    } else {

        //$order->add_order_note(__($prefix.'Pathao Error: Unable to create delivery. HTTP Status Code: '.$http_code), false);
        $order->add_order_note(__($prefix.'Pathao Error: Unable to create delivery. HTTP Status Code: '.$response), false);
        $order->update_status($old_status);
    }

   
}


// fire the function when the order updated
add_action('woocommerce_order_status_changed', 'pathaw_order_status_changed', 10, 4);
function pathaw_order_status_changed($order_id, $old_status, $new_status, $order) {
    
    $provider = $order->get_meta('_shipping_provider');
    if ('ready-to-shipping' === $new_status && 'order-processing' === $old_status) {  
             
        
        // switch case on $provider value
        switch ($provider) {
            case 'pathaw':
                pathaw_create_new_delivery($order_id,$old_status);
                break;
            default: 
                $order->add_order_note(__('No Shipping'),false);           
                break;
        }
    }
    
}

// formatting Client info for Pathaw
function get_client_info($order){
    $client_name = $order->get_formatted_shipping_full_name();
    $phone = $order->get_billing_phone();
    $phone = substr($phone, -11);

    $shipping = $order->get_address('shipping');

    // Fallback to billing if shipping is empty
    if (empty($shipping['address_1']) && empty($shipping['city'])) {
        $shipping = $order->get_address('billing');
    }

    $states = WC()->countries->get_states($shipping['country']);
    $state_name = isset($states[$shipping['state']]) ? $states[$shipping['state']] : $shipping['state'];
    // Format address manually
    $order_address = implode(', ', array_filter([
        ucfirst($shipping['address_1']),
        ucfirst($shipping['address_2']),
        // ucfirst($shipping['city']),
        // $states ? ucfirst($state_name) : ucfirst($shipping['state']),
    ]));
    
    if (strtolower($shipping['city']) === strtolower($state_name)) {
        $order_address .= ', ' . ucfirst($shipping['city']);
    } else {
        $order_address .= ', ' . ucfirst($shipping['city']) . ', ' . ucfirst($state_name);
    }

    if (!empty($shipping['postcode'])) {
        $order_address .= '-' . $shipping['postcode'];
    }
    $data = [
        'name'=> $client_name,
        'address'=> $order_address,
        'phone'=>$phone,
    ];
    return $data;
}