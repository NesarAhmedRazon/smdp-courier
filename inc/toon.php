<?php


// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Hook REST API initialization
add_action('rest_api_init', 'wc_attributes_api_register_routes');

function wc_attributes_api_register_routes() {
    register_rest_route('wc-attributes/v1', '/all', array(
        'methods' => 'GET',
        'callback' => 'wc_attributes_api_get_all',
        'permission_callback' => '__return_true'
    ));
}

function wc_attributes_api_permission() {
    // Only logged-in users with manage_woocommerce capability
    return current_user_can('manage_woocommerce');
}

function wc_attributes_api_get_all() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'WooCommerce is not active'
        ), 500);
    }

    $attributes_data = array();

    // Get all attribute taxonomies
    $attribute_taxonomies = wc_get_attribute_taxonomies();

    foreach ($attribute_taxonomies as $attribute) {
        $taxonomy_name = 'pa_' . $attribute->attribute_name;
        
        // Get all terms for this attribute
        $terms = get_terms(array(
            'taxonomy' => $taxonomy_name,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ));

        $values = array();
        
        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                $values[] = array(
                    'id' => $term->term_id,
                    'slug' => $term->slug,
                    'name' => $term->name,
                    'description' => $term->description,
                    'count' => $term->count
                );
            }
        }

        $attributes_data[] = array(
            'id' => $attribute->attribute_id,
            'name' => $attribute->attribute_name,
            'label' => $attribute->attribute_label,
            'type' => $attribute->attribute_type,
            'order_by' => $attribute->attribute_orderby,
            'is_public' => (bool)$attribute->attribute_public,
            'values' => $values,
            'values_count' => count($values)
        );
    }

    return new WP_REST_Response(array(
        'success' => true,
        'total_attributes' => count($attributes_data),
        'attributes' => $attributes_data
    ), 200);
}