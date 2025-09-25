<?php 

function get_pathao_stores($token,$base_url){

   
    
    
     if(empty($store_list)){
        $store_chk_api = $base_url."aladdin/api/v1/stores";
        $ch = curl_init($store_chk_api);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        if ($http_code === 200) {
            $data = json_decode($response, true);
           $store_list = $data['data']['data'];
           update_option('pathao_store_list',$store_list); 
            
        } else {
            echo "<h3>Error</h3>";
            echo "<p>Unable to get store id. HTTP Status Code: {$http_code}</p>";
            echo "<p>Response: {$response}</p>";
            
        }
        
    }
    
    return $store_list;
}

function get_pathao_cities($token,$base_url){
    
        $city_chk_api = $base_url."aladdin/api/v1/city-list";
        $ch = curl_init($city_chk_api);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code === 200) {
            $data = json_decode($response, true);
            $cities = $data['data']['data'];
            
        } else {
            echo "<h3>Error</h3>";
            echo "<p>Unable to get store id. HTTP Status Code: {$http_code}</p>";
            echo "<p>Response: {$response}</p>";
            
        }
        
    

    return $cities;
}

function get_pathao_zones($access_token,$base_url,$city){
    $sandbox = get_option('pathao_sandbox');
    if($sandbox == 'yes'){
        $base_url = get_option('pathao_sandbox_base_url');
        $token = get_option('pathao_sandbox_access_token');
    }else{
        $base_url = get_option('pathao_base_url');
        $token = get_option('pathao_access_token');
    }
    if(empty($access_token)){
        $access_token = $token;
    }
    $zone_api = "aladdin/api/v1/cities/".$city."/zone-list";
    $zone_chk_api = $base_url.$zone_api;
    $ch = curl_init($zone_chk_api);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token,
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code === 200) {
        $data = json_decode($response, true);
        $zones = $data['data']['data'];
    } else {
        echo "<h3>Error</h3>";
        echo "<p>Unable to get zone list. HTTP Status Code: {$http_code}</p>";
        echo "<p>Response: {$response}</p>";
    }
    return $zones;
}

function get_pathao_area($access_token,$base_url,$zone){
    $area_api = "aladdin/api/v1/zones/".$zone."/area-list";
    $area_chk_api = $base_url.$area_api;
    $ch = curl_init($area_chk_api);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token,
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code === 200) {
        $data = json_decode($response, true);
        $result = $data['data']['data'];
    } else {
        echo "<h3>Error</h3>";
        echo "<p>Unable to get area list. HTTP Status Code: {$http_code}</p>";
        echo "<p>Response: {$response}</p>";
    }
    return $result;
}

function update_pathao_order(){
    
}