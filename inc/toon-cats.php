<?php
/**
 * Description: Exports WooCommerce product categories with full hierarchy via REST API
 * Version: 1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', 'wc_categories_api_register_routes_toon');

function wc_categories_api_register_routes_toon() {
    register_rest_route('toon-cats/v1', '/all', array(
        'methods' => 'GET',
        'callback' => 'wc_categories_api_get_all',
        'permission_callback' => '__return_true'
    ));
    
    // Optional: Get single category by ID with its children
    register_rest_route('toon-cats/v1', '/category/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'wc_categories_api_get_single',
        'permission_callback' => '__return_true'
    ));
}

function wc_categories_api_get_all() {
    if (!class_exists('WooCommerce')) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'WooCommerce is not active'
        ), 500);
    }

    // // Get all product categories
    // $categories = get_terms(array(
    //     'taxonomy' => 'product_cat',
    //     'hide_empty' => false,
    //     'orderby' => 'name',
    //     'order' => 'ASC'
    // ));

    // if (is_wp_error($categories)) {
    //     return new WP_REST_Response(array(
    //         'success' => false,
    //         'message' => $categories->get_error_message()
    //     ), 500);
    // }

    // // Build hierarchy
    // $hierarchy = build_category_tree($categories);

    return new WP_REST_Response(smdp_get_category_tree(), 200);
    // return new WP_REST_Response(array(
    //     'success' => true,
    //     'total_categories' => count($categories),
    //     'categories' => $hierarchy
    // ), 200);
}

function wc_categories_api_get_single($request) {
    $category_id = $request['id'];
    
    $category = get_term($category_id, 'product_cat');
    
    if (!$category || is_wp_error($category)) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Category not found'
        ), 404);
    }
    
    // Get children of this category
    $children = get_terms(array(
        'taxonomy' => 'product_cat',
        'parent' => $category_id,
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    ));
    
    $category_data = format_category_data($category);
    $category_data['children'] = array();
    
    foreach ($children as $child) {
        $category_data['children'][] = format_category_data($child);
    }
    $category_data['children_count'] = count($children);
    
    return new WP_REST_Response(array(
        'success' => true,
        'category' => $category_data
    ), 200);
}

function build_category_tree($categories, $parent_id = 0) {
    $tree = array();
    
    foreach ($categories as $category) {
        if ($category->parent == $parent_id) {
            $node = format_category_data($category);
            $node['children'] = build_category_tree($categories, $category->term_id);
            $node['children_count'] = count($node['children']);
            $tree[] = $node;
        }
    }
    
    return $tree;
}

function format_category_data($category) {
    
    
    return array(
        'id' => $category->term_id,
        'name' => $category->name,
        'slug' => $category->slug,
        'description' => $category->description,
        'parent_id' => (int)$category->parent,
        'product_count' => $category->count,
        // 'level' => get_category_depth($category->term_id)
    );
}

function get_category_depth($category_id, $depth = 0) {
    $category = get_term($category_id, 'product_cat');
    if ($category && $category->parent != 0) {
        $depth++;
        return get_category_depth($category->parent, $depth);
    }
    return $depth;
}


function smdp_get_all_categories()
{
    // Get all product categories
    $terms = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
    ]);

    if (is_wp_error($terms)) {
        return [
            'status' => 'error',
            'total'  => 0,
            'categories' => []
        ];
    }

    // Index terms by ID for fast lookup
    $term_map = [];
    foreach ($terms as $term) {
        $term_map[$term->term_id] = $term;
    }

    // Helper: build permalink path (parent/child)
    $build_path = function ($term_id) use (&$term_map) {
        $path = [];
        $current = $term_map[$term_id] ?? null;

        while ($current) {
            array_unshift($path, $current->slug);

            if (!$current->parent) break;

            $current = $term_map[$current->parent] ?? null;
        }

        return implode('/', $path);
    };

    // Build response
    $categories = [];

    foreach ($terms as $term) {

        $categories[] = [
            'id'          => (string) $term->term_id,
            'name'        => $term->name,
            'slug'        => $term->slug,
            'description' => $term->description ?: null,
            'parent_id'   => $term->parent ? (string) $term->parent : null,
            'updated_at'  => $term->term_id 
                ? date('Y-m-d H:i:s', strtotime($term->term_group ?: 'now')) // fallback
                : null,
            'permalink'   => $build_path($term->term_id),
        ];
    }

    return [
        'status'     => 'ok',
        'total'      => count($categories),
        'categories' => $categories
    ];
}

function smdp_get_category_tree()
{
    $terms = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
    ]);

    if (is_wp_error($terms)) {
        return [
            'status' => 'error',
            'total'  => 0,
            'categories' => []
        ];
    }

    // Step 1: Index terms
    $term_map = [];
    foreach ($terms as $term) {
        $term_map[$term->term_id] = $term;
    }

    // Step 2: Build permalink path
    $build_path = function ($term_id) use (&$term_map) {
        $path = [];
        $current = $term_map[$term_id] ?? null;

        while ($current) {
            array_unshift($path, $current->slug);
            if (!$current->parent) break;
            $current = $term_map[$current->parent] ?? null;
        }

        return implode('/', $path);
    };

    // Step 3: Prepare nodes
    $nodes = [];
    foreach ($terms as $term) {
        $nodes[$term->term_id] = [
            'id'                  => (string) $term->term_id,
            'name'                => $term->name,
            'slug'                => $term->slug,
            'description'         => $term->description ?: null,
            'parent_id'           => $term->parent ? (string) $term->parent : null,
            'product_count'       => (int) $term->count, // direct count
            'product_count_total' => 0, // will compute later
            'updated_at'          => null,
            'permalink'           => $build_path($term->term_id),
            'children'            => []
        ];
    }

    // Step 4: Build tree
    $tree = [];

    foreach ($nodes as $id => &$node) {
        if ($node['parent_id']) {
            $parent_id = (int) $node['parent_id'];

            if (isset($nodes[$parent_id])) {
                $nodes[$parent_id]['children'][] = &$node;
            }
        } else {
            $tree[] = &$node;
        }
    }
    unset($node);

    // Step 5: Calculate TRUE COUNT (recursive)
    $calculate_total = function (&$node) use (&$calculate_total) {
        $total = $node['product_count'];

        foreach ($node['children'] as &$child) {
            $total += $calculate_total($child);
        }

        $node['product_count_total'] = $total;

        return $total;
    };

    foreach ($tree as &$root) {
        $calculate_total($root);
    }
    unset($root);

    // Step 6: Sort (optional but recommended)
    $sort_tree = function (&$items) use (&$sort_tree) {
        usort($items, fn($a, $b) => strcmp($a['name'], $b['name']));
        foreach ($items as &$item) {
            if (!empty($item['children'])) {
                $sort_tree($item['children']);
            }
        }
    };
    $sort_tree($tree);

    return [
        'status'     => 'ok',
        'total'      => count($nodes),
        'categories' => $tree
    ];
}