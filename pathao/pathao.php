<?php

/**
 * Get Pathao stores using WordPress HTTP API
 */
function get_pathao_stores($token, $base_url)
{
    $store_list = get_option('pathao_store_list');

    if (empty($store_list)) {
        $url = trailingslashit($base_url) . 'aladdin/api/v1/stores';

        error_log('Pathao: Fetching store list from: ' . $url);

        $response = wp_remote_get($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json'
            ],
            'timeout' => 30,
            'sslverify' => true
        ]);

        if (is_wp_error($response)) {
            error_log('Pathao: Store list WP_Error - ' . $response->get_error_message());
            return [];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        error_log('Pathao: Store list response - HTTP Code: ' . $http_code);

        if ($http_code === 200) {
            $data = json_decode($body, true);

            if (isset($data['data']['data'])) {
                $store_list = $data['data']['data'];
                update_option('pathao_store_list', $store_list);
                error_log('Pathao: Store list retrieved successfully. Count: ' . count($store_list));
            } else {
                error_log('Pathao: Invalid store list response structure: ' . $body);
                return [];
            }
        } else {
            error_log('Pathao: Store list error - HTTP ' . $http_code . ': ' . $body);
            return [];
        }
    }

    return $store_list;
}

/**
 * Get Pathao cities using WordPress HTTP API
 */
function get_pathao_cities($token, $base_url)
{
    $url = trailingslashit($base_url) . 'aladdin/api/v1/city-list';

    error_log('Pathao: Fetching city list from: ' . $url);

    $response = wp_remote_get($url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ],
        'timeout' => 30,
        'sslverify' => true
    ]);

    if (is_wp_error($response)) {
        error_log('Pathao: City list WP_Error - ' . $response->get_error_message());
        return [];
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    error_log('Pathao: City list response - HTTP Code: ' . $http_code);

    if ($http_code === 200) {
        $data = json_decode($body, true);

        if (isset($data['data']['data'])) {
            $cities = $data['data']['data'];
            error_log('Pathao: City list retrieved successfully. Count: ' . count($cities));
            return $cities;
        } else {
            error_log('Pathao: Invalid city list response structure: ' . $body);
            return [];
        }
    } else {
        error_log('Pathao: City list error - HTTP ' . $http_code . ': ' . $body);
        return [];
    }
}

/**
 * Get Pathao zones using WordPress HTTP API
 */
function get_pathao_zones($access_token, $base_url, $city)
{
    $sandbox = get_option('pathao_sandbox');

    // Override with settings if no token provided
    if (empty($access_token)) {
        if ($sandbox == 'yes') {
            $base_url = get_option('pathao_sandbox_base_url');
            $access_token = pathao_get_valid_token(true);
        } else {
            $base_url = get_option('pathao_base_url');
            $access_token = pathao_get_valid_token(false);
        }
    }

    if (empty($access_token)) {
        error_log('Pathao: No valid token for zone list request');
        return [];
    }

    $url = trailingslashit($base_url) . 'aladdin/api/v1/cities/' . $city . '/zone-list';

    error_log('Pathao: Fetching zone list for city ' . $city . ' from: ' . $url);

    $response = wp_remote_get($url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,
            'Accept' => 'application/json'
        ],
        'timeout' => 30,
        'sslverify' => true
    ]);

    if (is_wp_error($response)) {
        error_log('Pathao: Zone list WP_Error - ' . $response->get_error_message());
        return [];
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    error_log('Pathao: Zone list response - HTTP Code: ' . $http_code);

    if ($http_code === 200) {
        $data = json_decode($body, true);

        if (isset($data['data']['data'])) {
            $zones = $data['data']['data'];
            error_log('Pathao: Zone list retrieved successfully. Count: ' . count($zones));
            return $zones;
        } else {
            error_log('Pathao: Invalid zone list response structure: ' . $body);
            return [];
        }
    } else {
        error_log('Pathao: Zone list error - HTTP ' . $http_code . ': ' . $body);
        return [];
    }
}

/**
 * Get Pathao area using WordPress HTTP API
 */
function get_pathao_area($access_token, $base_url, $zone)
{
    $url = trailingslashit($base_url) . 'aladdin/api/v1/zones/' . $zone . '/area-list';

    error_log('Pathao: Fetching area list for zone ' . $zone . ' from: ' . $url);

    $response = wp_remote_get($url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,
            'Accept' => 'application/json'
        ],
        'timeout' => 30,
        'sslverify' => true
    ]);

    if (is_wp_error($response)) {
        error_log('Pathao: Area list WP_Error - ' . $response->get_error_message());
        return [];
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    error_log('Pathao: Area list response - HTTP Code: ' . $http_code);

    if ($http_code === 200) {
        $data = json_decode($body, true);

        if (isset($data['data']['data'])) {
            $result = $data['data']['data'];
            error_log('Pathao: Area list retrieved successfully. Count: ' . count($result));
            return $result;
        } else {
            error_log('Pathao: Invalid area list response structure: ' . $body);
            return [];
        }
    } else {
        error_log('Pathao: Area list error - HTTP ' . $http_code . ': ' . $body);
        return [];
    }
}
