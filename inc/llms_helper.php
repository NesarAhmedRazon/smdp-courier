<?php

function smdp_get_elementor_table_by_class($post_id, $target_class = 'pinout') {

    // Get Elementor raw JSON
    $raw_data = get_post_meta($post_id, '_elementor_data', true);
    if (empty($raw_data)) {
        return false;
    }

    $elements = json_decode($raw_data, true);
    if (!is_array($elements)) {
        return false;
    }
    
    return smdp_find_table_by_class_recursive($elements, $target_class);
}


/**
 * Recursively search Elementor elements for table widget
 * with specific CSS class.
 */
function smdp_find_table_by_class_recursive($elements, $target_class) {
$el = [];
    foreach ($elements as $element) {

        // Check if this is a widget
        if (isset($element['widgetType'])) {

            // Confirm it's a table widget
            if ($element['widgetType'] === 'wd_table') {

                $settings = $element['settings'] ?? [];

                // Elementor stores custom CSS classes here
                $css_classes = $settings['_css_classes'] ?? '';

                if (!empty($css_classes) && strpos($css_classes, $target_class) !== false) {
                    $el['heading_items']=$element['settings']['heading_items'];
                    $el['body_items']=$element['settings']['body_items'];
                    return $el;
                }
            }
        }

        // Traverse children (sections / columns)
        if (!empty($element['elements'])) {
            $found = smdp_find_table_by_class_recursive($element['elements'], $target_class);
            if ($found) {
                return $found;
            }
        }
    }

    return false;
}

// Product Key Highlights
function smdp_llms_product_highlights($product) {
    // AI-কে বোঝানোর জন্য এটি 'Key Highlights' হিসেবে কাজ করবে
        $short_desc = $product->get_short_description();
        if (!empty($short_desc)) {
            $clean_short = smdp_clean_text_for_llms($short_desc);
            if (!empty($clean_short)) {
                $output .= "- **Key Highlights:** " . $clean_short . "\n";
            }
        }
        return $output;
}

// Product Categories

function smdp_llms_product_categories($product) {
    // Categories - AI-এর জন্য শ্রেণীবিন্যাস স্পষ্ট করা
    $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
    $output = '';
    if (!empty($categories)) {
        // ক্যাটাগরিগুলোকে বোল্ড করে দিলে AI দ্রুত স্ক্যান করতে পারে
        $output .= "- **Product Categories:** " . implode(' | ', $categories) . "\n";
    }
        return $output;
}

// Helper to clean text for LLM output
/**
 * Elementor JSON থেকে টেকনিক্যাল পিনআউট অ্যাপেন্ডিক্স জেনারেট করার উন্নত ফাংশন
 */
function generate_advanced_smd_pinout($id) {
    $output = "\n- Technical Pinout Details\n";
    $output .= "> এই ডাটাটি সরাসরি আমাদের টেকনিক্যাল স্পেসিফিকেশন টেবিল থেকে সংগৃহীত।\n\n";
    
    $data = smdp_get_elementor_table_by_class($id, 'pinout') ?? false;
    if (!$data || !isset($data['body_items'])) return '';

    
    $pin_data = [];
    $row_count = -1;

    foreach ($data['body_items'] as $item) {
        if ($item['body_content_type'] === 'row') {
            $row_count++;
            continue;
        }

        $cell_text = trim(wp_strip_all_tags($item['body_cell_text']));
        if (!empty($cell_text)) {
            $pin_data[$row_count][] = $cell_text;
        }
    }

    foreach ($pin_data as $row) {
        if (count($row) < 2) continue;

        $pin_no = $row[0]; // প্রথম এলিমেন্ট পিন নাম্বার
        $pin_name = $row[1]; // ২য় এলিমেন্ট পিন নাম
        
        // শেষেরটি বিস্তারিত বর্ণনা, আর মাঝেরগুলো অতিরিক্ত ফিচার
        $description = array_pop($row); 
        
        // পিন নম্বর ও নাম বাদে বাকি অংশগুলো ফাংশন হিসেবে থাকবে
        $extra_features = array_slice($row, 2); 
        $features_text = !empty($extra_features) ? implode(", ", $extra_features) . " - " : "";

        $output .= "  - **Pin $pin_no:** " . ($pin_name !== $pin_no ? "$pin_name, " : "") . $features_text . $description . "\n";
    }

    return $output;
}


/**
 * Clean text for LLM consumption
 * Removes HTML, entities, extra whitespace
 */
function smdp_clean_text_for_llms($text) {
    // Strip all HTML tags
    $text = wp_strip_all_tags($text);
    
    // Decode HTML entities (&nbsp;, &amp;, etc.)
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Remove extra whitespace, newlines, tabs
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Trim
    $text = trim($text);
    
    return $text;
}