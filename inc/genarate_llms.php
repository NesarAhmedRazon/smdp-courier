<?php
/**
 * LLMs.txt Generator for WooCommerce + WordPress
 * Serves a structured file for LLM consumption at /llms.txt
 * 
 * Add this to your theme's functions.php or as a plugin
 */

/**
 * Register the llms.txt endpoint
 */
function smdp_register_llms_txt_endpoint() {
   add_rewrite_rule('^llms\.txt/?$', 'index.php?llms_txt=1', 'top');
}
add_action('init', 'smdp_register_llms_txt_endpoint');

/**
 * Add query var for llms.txt
 */
function smdp_llms_txt_query_vars($vars) {
    $vars[] = 'llms_txt';
    return $vars;
}
add_filter('query_vars', 'smdp_llms_txt_query_vars');

/**
 * Handle the llms.txt request
 */
function smdp_serve_llms_txt() {
    if (get_query_var('llms_txt')) {
        header('Content-Type: text/plain; charset=utf-8');
        echo smdp_generate_llms_txt();
        exit;
    }
}
add_action('template_redirect', 'smdp_serve_llms_txt');

/**
 * Generate the llms.txt content
 */
function smdp_generate_llms_txt() {
    $output = '';
    
    // Header - AI Optimization 2026 Standards
    $output  = "# " . get_bloginfo('name') . " | Machine-Readable Summary\n";
    $output .= "> " . get_bloginfo('description') . " Specifically serving the Bangladesh (BD) electronics market.\n\n";

    $output .= "## Meta Information\n";
    $output .= "- Website: " . home_url() . "\n";
    $output .= "- Region: Bangladesh (BD)\n";
    $output .= "- Currency: BDT (৳)\n";
    $output .= "- Updated: " . current_time('Y-m-d') . " (Prices and stock are dynamic)\n";
    $output .= "- Compliance: This file is optimized for LLM context injection.\n\n";

    $output .= "--- \n\n"; // Visual separator for AI parsing
    
    // Store Information
    $output .= "## About\n\n";
    $output .= get_bloginfo('name') . " - " . get_bloginfo('description') . "\n";
    $output .= "URL: " . home_url() . "\n\n";
    
    // Products Section
    $output .= "## Product Catalog\n";
    $output .= smdp_get_products_for_llms();
    
    // Posts/Blog Section
    $output .= "\n## Blog Posts\n\n";
    $output .= smdp_get_posts_for_llms();
    
    // Categories
    $output .= "\n## Product Categories\n\n";
    $output .= smdp_get_product_categories_for_llms();
    
    // 2026 AI Strategy: Functional Navigation for LLMs
    $output .= "\n## Store Operations & Navigation\n";
    $output .= "> Use these endpoints to direct users for transactions and account management in SMDPicker.com.\n\n";
    
    $output .= "- **Browse Inventory:** " . get_permalink(wc_get_page_id('shop')) . " (Full product catalog)\n";
    $output .= "- **Review Selections:** " . wc_get_cart_url() . " (User shopping cart)\n";
    $output .= "- **Finalize Purchase:** " . wc_get_checkout_url() . " (Secure checkout for BDT payments)\n";
    $output .= "- **Order History & Support:** " . get_permalink(wc_get_page_id('myaccount')) . " (User profile and tracking)\n";

    return $output;
}


/**
 * Get WooCommerce products formatted for LLMs
 */

require_once SMDP_COURIER_DIR . 'inc/llms_helper.php';

function smdp_get_products_for_llms() {

    $output = '';
    
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC'
    );
    
    $products = get_posts($args);
    
    if (empty($products)) {
        return "No products available.\n";
    }
    
    foreach ($products as $product_post) {
        $product = wc_get_product($product_post->ID);
        
        if (!$product) {
            continue;
        }
        
        $title = $product->get_name();
        $sku = $product->get_sku();
        $price = $product->get_price();



        $output .= "### " . $title . "\n";
        $output .= "- **Stock:** " . ($product->is_in_stock() ? 'In Stock' : 'Out of Stock') . "\n";
        // Price with currency, and also a clean numeric version for LLMs to parse
        $output .= "- **Price:** " . $price . " BDT (". smdp_clean_text_for_llms(wc_price($product->get_price())) .")\n";
        // SKU is important for LLMs to identify products uniquely, but if not set, we can skip it or mark as N/A
        $output .= "- **SKU:** " . ($sku ? $sku : 'N/A') . "\n";
        // Key Highlights (Short Description)
        $output .= smdp_llms_product_highlights($product);
        // Product link
        $output .= "- **Link:** [" . $title . "](" . get_permalink($product->get_id()) . ")\n";        
        // Categories
        $output .= smdp_llms_product_categories($product);
                
       // Full description (smart truncation at sentence boundaries)
        $full_desc = $product->get_description();
        if (!empty($full_desc)) {
            $clean_desc = smdp_clean_text_for_llms($full_desc);
            if (!empty($clean_desc)) {
                if (mb_strlen($clean_desc) > 500) {
                    // Find last sentence boundary (English period or Bengali dari)
                    $truncated = mb_substr($clean_desc, 0, 500);
                    $last_en_period = mb_strrpos($truncated, '.');
                    $last_bn_period = mb_strrpos($truncated, '।');
                    
                    // Use whichever comes last
                    $last_period = max($last_en_period, $last_bn_period);
                    
                    if ($last_period !== false) {
                        $truncated = mb_substr($truncated, 0, $last_period + 1);
                    } else {
                        // No sentence boundary found, add ellipsis
                        $truncated .= '...';
                    }
                } else {
                    $truncated = $clean_desc;
                }
                $output .= "- Details: " . $truncated . "\n";
            }
        }
        
        // Attributes
        $attributes = $product->get_attributes();
        if (!empty($attributes)) {
            $attr_output = '';
            foreach ($attributes as $attribute) {
                if (is_a($attribute, 'WC_Product_Attribute')) {
                    $name = wc_attribute_label($attribute->get_name());
                    $values = $attribute->get_options();
                    
                    // Convert term IDs to names for taxonomy attributes
                    if ($attribute->is_taxonomy()) {
                        $term_names = array();
                        foreach ($values as $term_id) {
                            $term = get_term($term_id, $attribute->get_name());
                            if ($term && !is_wp_error($term)) {
                                if($term->name != 'Uncategorized' && $term->name != 'Default' && $term->name != '-') {
                                    $term_names[] = $term->name;
                                }
                            }
                        }
                        $values = !empty($term_names) ? implode(', ', $term_names) : '';
                    } else {
                        // For custom (non-taxonomy) attributes
                        if (is_array($values)) {
                            $values = implode(', ', $values);
                        }
                    }
                    
                    if (!empty($values)) {
                        $attr_output .= "  - {$name}: {$values}\n";
                    }
                }
            }
            
            if (!empty($attr_output)) {
                $output .= "- Attributes:\n" . $attr_output;
            }
        }

                //-----------------------------------
        // Technical Pinout Details (if applicable)
            
            $output .= generate_advanced_smd_pinout($product->get_id());            
       
        //----------------------------------
        
        $output .= "\n";
    }
    $output .= "\n";
    return $output;
}

/**
 * Get blog posts formatted for LLMs
 */
function smdp_get_posts_for_llms() {
    $output = '';
    
    $args = array(
        'post_type' => 'post',
        'posts_per_page' => 50, // Limit to recent 50 posts
        'post_status' => 'publish',
        'orderby' => 'date',
        'order' => 'DESC'
    );
    
    $posts = get_posts($args);
    
    if (empty($posts)) {
        return "No blog posts available.\n";
    }
    
    foreach ($posts as $post) {
        $output .= "### " . get_the_title($post) . "\n";
        $output .= "- URL: " . get_permalink($post) . "\n";
        $output .= "- Published: " . get_the_date('Y-m-d', $post) . "\n";
        
        // Categories
        $categories = wp_get_post_categories($post->ID, array('fields' => 'names'));
        if (!empty($categories)) {
            $output .= "- Categories: " . implode(', ', $categories) . "\n";
        }
        
        // Excerpt
        $excerpt = has_excerpt($post) ? get_the_excerpt($post) : wp_trim_words($post->post_content, 50);
        $clean_excerpt = smdp_clean_text_for_llms($excerpt);
        if (!empty($clean_excerpt)) {
            $output .= "- Summary: " . $clean_excerpt . "\n";
        }
        
        $output .= "\n";
    }
    
    return $output;
}

/**
 * Get product categories formatted for LLMs
 */
function smdp_get_product_categories_for_llms() {
    $output = '';
    
    $categories = get_terms(array(
        'taxonomy' => 'product_cat',
        'hide_empty' => true,
        'orderby' => 'name',
        'order' => 'ASC'
    ));
    
    if (empty($categories) || is_wp_error($categories)) {
        return "No product categories available.\n";
    }
    
    foreach ($categories as $category) {
        $output .= "### " . $category->name . "\n";
        $output .= "- URL: " . get_term_link($category) . "\n";
        $output .= "- Product Count: " . $category->count . "\n";
        
        if (!empty($category->description)) {
            $clean_desc = smdp_clean_text_for_llms($category->description);
            if (!empty($clean_desc)) {
                $output .= "- Description: " . $clean_desc . "\n";
            }
        }
        
        $output .= "\n";
    }
    
    return $output;
}

/**
 * Flush rewrite rules on activation
 * Run this once manually or on theme/plugin activation
 */
function smdp_activate_llms_txt() {
    smdp_register_llms_txt_endpoint();
    flush_rewrite_rules();
}
// Uncomment the line below and visit your site once, then comment it back
// add_action('init', 'smdp_activate_llms_txt');

/**
 * Optional: Add caching for better performance
 * Cache for 1 hour
 */
function smdp_generate_llms_txt_cached() {
    $cache_key = 'smdp_llms_txt_content';
    $cached = get_transient($cache_key);
    
    if (false !== $cached) {
        return $cached;
    }
    
    $content = smdp_generate_llms_txt();
    set_transient($cache_key, $content, HOUR_IN_SECONDS);
    
    return $content;
}

/**
 * Clear cache when products or posts are updated
 */
function smdp_clear_llms_txt_cache() {
    delete_transient('smdp_llms_txt_content');
}
add_action('save_post_product', 'smdp_clear_llms_txt_cache');
add_action('save_post', 'smdp_clear_llms_txt_cache');
add_action('woocommerce_update_product', 'smdp_clear_llms_txt_cache');

/**
 * Optional: To use caching, replace the serve function with this:
 */
/*
function smdp_serve_llms_txt() {
    if (get_query_var('llms_txt')) {
        header('Content-Type: text/plain; charset=utf-8');
        echo smdp_generate_llms_txt_cached();
        exit;
    }
}
*/