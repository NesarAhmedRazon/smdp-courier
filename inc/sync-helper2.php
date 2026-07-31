<?php
/**
 * Extract complete Elementor content with full hierarchy preservation
 * Supports: page, post, product, category, and any custom post type
 * Procedural approach - no OOP
 */

/**
 * Main function to get Elementor content with preserved hierarchy
 * 
 * @param int $object_id The ID of the page/post/product/category
 * @param string $object_type Type of object: 'post', 'term', 'product'
 * @return array Complete hierarchical content structure
 */
function smdp_get_elementor_content($object_id, $object_type = 'post') {
    $elementor_data = null;
    $title = '';
    $type_label = '';
    
    // Get Elementor data based on object type
    switch ($object_type) {
        case 'post':
        case 'page':
        case 'product':
            $post = get_post($object_id);
            if (!$post) {
                return ['error' => 'Post not found'];
            }
            $elementor_data = get_post_meta($object_id, '_elementor_data', true);
            $title = $post->post_title;
            $type_label = get_post_type_object($post->post_type)->labels->singular_name ?? $post->post_type;
            break;
            
        case 'term':
        case 'category':
        case 'tag':
            $term = get_term($object_id);
            if (!$term || is_wp_error($term)) {
                return ['error' => 'Term not found'];
            }
            // For taxonomy archives, Elementor stores data in meta
            $elementor_data = get_term_meta($object_id, '_elementor_data', true);
            $title = $term->name;
            $type_label = $term->taxonomy;
            break;
            
        default:
            return ['error' => 'Invalid object type'];
    }
    
    if (!$elementor_data) {
        return [
            'object_id' => $object_id,
            'object_type' => $object_type,
            'title' => $title,
            'type_label' => $type_label,
            'has_elementor' => false,
            'content' => null
        ];
    }
    
    $elementor_data = json_decode($elementor_data, true);
    
    if (!is_array($elementor_data)) {
        return ['error' => 'Invalid Elementor data structure'];
    }
    
    // Process full hierarchy
    $hierarchy = smdp_process_elementor_hierarchy($elementor_data);
    
    return [
        // 'object_id' => $object_id,
        // 'object_type' => $object_type,
        'title' => $title,
        'type' => $object_type,
        'has_elementor' => true,
        'content' => $hierarchy,
        // 'global_settings' => smdp_get_global_settings(),
        // 'page_settings' => smdp_get_page_settings($object_id, $object_type),
        // 'metadata' => [
        //     'elementor_version' => get_metadata($object_type === 'term' ? 'term' : 'post', $object_id, '_elementor_version', true),
        //     'template_type' => get_metadata($object_type === 'term' ? 'term' : 'post', $object_id, '_elementor_template_type', true),
        //     'edit_mode' => get_metadata($object_type === 'term' ? 'term' : 'post', $object_id, '_elementor_edit_mode', true),
        // ]
    ];
}

/**
 * Process Elementor hierarchy preserving all structural levels
 * 
 * @param array $elements Elementor data array
 * @return array Hierarchical structure
 */
function smdp_process_elementor_hierarchy($elements) {
    $result = [];
    
    if (!is_array($elements)) {
        return $result;
    }
    
    foreach ($elements as $index => $element) {
        $processed = [
            'index' => $index,
            'id' => $element['id'] ?? '',
            'elType' => $element['elType'] ?? '',
            'settings' => $element['settings'] ?? [],
        ];
        
        // Add element type specific data
        if (isset($element['elType'])) {
            switch ($element['elType']) {
                case 'section':
                    $processed['type'] = 'section';
                    $processed['structure'] = [
                        'columns' => $element['settings']['structure'] ?? 'full',
                        'content_width' => $element['settings']['content_width'] ?? 'boxed',
                        'height' => $element['settings']['min_height'] ?? 'auto',
                        'gap' => $element['settings']['gap'] ?? 'default',
                    ];
                    break;
                    
                case 'column':
                    $processed['type'] = 'column';
                    $processed['structure'] = [
                        'width' => $element['settings']['_column_size'] ?? 100,
                        'space_between_widgets' => $element['settings']['space_between_widgets'] ?? '',
                    ];
                    break;
                    
                case 'widget':
                    $processed['type'] = 'widget';
                    $processed['widgetType'] = $element['widgetType'] ?? '';
                    $processed['widget_data'] = smdp_extract_widget_data($element, $element['settings'] ?? []);
                    break;
                    
                case 'container':
                    $processed['type'] = 'container';
                    $processed['structure'] = [
                        'flex_direction' => $element['settings']['flex_direction'] ?? 'row',
                        'justify_content' => $element['settings']['justify_content'] ?? 'flex-start',
                        'align_items' => $element['settings']['align_items'] ?? 'stretch',
                        'gap' => $element['settings']['gap'] ?? 'default',
                    ];
                    break;
            }
        }
        
        // Add responsive settings
        $processed['responsive'] = smdp_extract_responsive_settings($element['settings'] ?? []);
        
        // Add animations
        $processed['animation'] = smdp_extract_animation($element['settings'] ?? []);
        
        // Add custom CSS
        $processed['custom_css'] = $element['settings']['_element_custom_css'] ?? '';
        
        // Add CSS classes
        $processed['css_classes'] = $element['settings']['css_classes'] ?? [];
        
        // Recursively process child elements (preserving hierarchy)
        if (isset($element['elements']) && is_array($element['elements']) && !empty($element['elements'])) {
            $processed['elements'] = smdp_process_elementor_hierarchy($element['elements']);
        }
        
        $result[] = $processed;
    }
    
    return $result;
}

/**
 * Extract widget data with full context
 * 
 * @param array $element The widget element
 * @param array $settings Widget settings
 * @return array Widget data
 */
function smdp_extract_widget_data($element, $settings) {
    $widget_type = $element['widgetType'] ?? '';
    
    $base_data = [
        'id' => $element['id'] ?? '',
        'widgetType' => $widget_type,
        'name' => smdp_get_widget_name($widget_type),
        'category' => smdp_get_widget_category($widget_type),
    ];
    
    switch ($widget_type) {
        case 'image':
            return array_merge($base_data, [
                'type' => 'image',
                'props' => [
                    'src' => $settings['image']['url'] ?? '',
                    'alt' => $settings['image']['alt'] ?? '',
                    'title' => $settings['image']['title'] ?? '',
                    'caption' => $settings['caption'] ?? '',
                    'link' => $settings['link']['url'] ?? '',
                    'link_target' => ($settings['link']['is_external'] ?? false) ? '_blank' : '_self',
                    'image_size' => $settings['image_size'] ?? 'full',
                    'width' => $settings['width'] ?? '',
                    'height' => $settings['height'] ?? '',
                ],
                'style' => smdp_extract_style($settings),
                'responsive' => smdp_extract_responsive_settings($settings),
                'animation' => smdp_extract_animation($settings)
            ]);
            
        case 'image_or_svg':
            return array_merge($base_data, [
                'type' => 'image',
                'props' => [
                    'src' => $settings['image']['url'] ?? '',
                    'alt' => $settings['image']['alt'] ?? '',
                    'title' => $settings['image']['title'] ?? '',
                    'is_svg' => smdp_is_svg($settings['image']['url'] ?? ''),
                    'svg_code' => smdp_extract_svg_code($settings),
                    'caption' => $settings['caption'] ?? '',
                    'link' => $settings['link']['url'] ?? '',
                    'link_target' => ($settings['link']['is_external'] ?? false) ? '_blank' : '_self',
                    'image_size' => $settings['image_size'] ?? 'full',
                ],
                'style' => smdp_extract_style($settings),
                'responsive' => smdp_extract_responsive_settings($settings),
                'animation' => smdp_extract_animation($settings)
            ]);
            
        case 'heading':
            return array_merge($base_data, [
                'type' => 'heading',
                'props' => [
                    'text' => $settings['title'] ?? '',
                    'size' => $settings['size'] ?? 'default',
                    'html_tag' => $settings['header_size'] ?? 'h2',
                    'link' => $settings['link']['url'] ?? '',
                ],
                'style' => smdp_extract_style($settings),
                'responsive' => smdp_extract_responsive_settings($settings),
                'animation' => smdp_extract_animation($settings)
            ]);
            
        case 'text-editor':
            return array_merge($base_data, [
                'type' => 'text',
                'props' => [
                    'content' => $settings['editor'] ?? '',
                    'drop_cap' => $settings['drop_cap'] ?? 'no',
                ],
                'style' => smdp_extract_style($settings),
                'responsive' => smdp_extract_responsive_settings($settings),
                'animation' => smdp_extract_animation($settings)
            ]);
            
        case 'button':
            return array_merge($base_data, [
                'type' => 'button',
                'props' => [
                    'text' => $settings['text'] ?? '',
                    'link' => $settings['link']['url'] ?? '',
                    'link_target' => ($settings['link']['is_external'] ?? false) ? '_blank' : '_self',
                    'icon' => $settings['icon'] ?? '',
                    'icon_position' => $settings['icon_align'] ?? 'left',
                    'size' => $settings['size'] ?? 'md',
                ],
                'style' => smdp_extract_style($settings),
                'responsive' => smdp_extract_responsive_settings($settings),
                'animation' => smdp_extract_animation($settings)
            ]);
            
        case 'video':
            return array_merge($base_data, [
                'type' => 'video',
                'props' => [
                    'video_type' => $settings['video_type'] ?? 'youtube',
                    'youtube_url' => $settings['youtube_url'] ?? '',
                    'vimeo_url' => $settings['vimeo_url'] ?? '',
                    'self_hosted_url' => $settings['self_hosted_url'] ?? '',
                    'autoplay' => $settings['autoplay'] ?? false,
                    'mute' => $settings['mute'] ?? false,
                    'loop' => $settings['loop'] ?? false,
                    'controls' => $settings['controls'] ?? true,
                ],
                'style' => smdp_extract_style($settings),
                'responsive' => smdp_extract_responsive_settings($settings),
                'animation' => smdp_extract_animation($settings)
            ]);
            
        case 'icon':
            return array_merge($base_data, [
                'type' => 'icon',
                'props' => [
                    'icon' => $settings['icon'] ?? '',
                    'view' => $settings['view'] ?? 'default',
                    'link' => $settings['link']['url'] ?? '',
                    'link_target' => ($settings['link']['is_external'] ?? false) ? '_blank' : '_self',
                ],
                'style' => smdp_extract_style($settings),
                'responsive' => smdp_extract_responsive_settings($settings),
                'animation' => smdp_extract_animation($settings)
            ]);
            
        default:
            return array_merge($base_data, [
                'type' => $widget_type,
                'props' => $settings,
                'style' => smdp_extract_style($settings),
                'responsive' => smdp_extract_responsive_settings($settings),
                'animation' => smdp_extract_animation($settings)
            ]);
    }
}

/**
 * Get widget display name
 */
function smdp_get_widget_name($widget_type) {
    $names = [
        'heading' => 'Heading',
        'text-editor' => 'Text Editor',
        'image' => 'Image',
        'image_or_svg' => 'Image/SVG',
        'button' => 'Button',
        'video' => 'Video',
        'icon' => 'Icon',
        'image-gallery' => 'Image Gallery',
        'form' => 'Form',
        'posts' => 'Posts',
        'nav-menu' => 'Navigation Menu',
        'social-icons' => 'Social Icons',
        'counter' => 'Counter',
        'progress' => 'Progress Bar',
        'testimonial' => 'Testimonial',
        'tabs' => 'Tabs',
        'accordion' => 'Accordion',
        'toggle' => 'Toggle',
        'divider' => 'Divider',
        'spacer' => 'Spacer',
        'google-maps' => 'Google Maps',
    ];
    
    return $names[$widget_type] ?? ucfirst(str_replace('-', ' ', $widget_type));
}

/**
 * Get widget category
 */
function smdp_get_widget_category($widget_type) {
    $categories = [
        'heading' => 'basic',
        'text-editor' => 'basic',
        'image' => 'basic',
        'image_or_svg' => 'basic',
        'button' => 'basic',
        'video' => 'media',
        'icon' => 'basic',
        'image-gallery' => 'media',
        'form' => 'forms',
        'posts' => 'dynamic',
        'nav-menu' => 'layout',
        'social-icons' => 'social',
        'counter' => 'dynamic',
        'progress' => 'dynamic',
        'testimonial' => 'social',
        'tabs' => 'layout',
        'accordion' => 'layout',
        'toggle' => 'layout',
        'google-maps' => 'maps',
    ];
    
    return $categories[$widget_type] ?? 'general';
}

/**
 * Extract style settings from Elementor widget
 */
function smdp_extract_style($settings) {
    $style = [
        'desktop' => [],
        'tablet' => [],
        'mobile' => [],
        'hover' => [],
        'custom_css' => $settings['_element_custom_css'] ?? ''
    ];
    
    // Extract common style properties
    $style_properties = [
        'width', 'height', 'max_width', 'max_height',
        'margin', 'padding',
        'background_color', 'background_image',
        'text_color', 'text_align',
        'border_radius', 'border_width', 'border_color',
        'box_shadow', 'text_shadow',
        'opacity', 'visibility',
        'position', 'top', 'right', 'bottom', 'left',
        'z_index',
        'font_size', 'font_weight', 'font_family',
        'line_height', 'letter_spacing',
        'transition_duration', 'transition_delay'
    ];
    
    foreach ($style_properties as $prop) {
        if (isset($settings[$prop])) {
            $style['desktop'][$prop] = $settings[$prop];
        }
    }
    
    // Extract hover styles
    $hover_properties = [
        'background_color_hover', 'text_color_hover',
        'border_color_hover', 'box_shadow_hover',
        'opacity_hover', 'transform_hover'
    ];
    
    foreach ($hover_properties as $prop) {
        $key = str_replace('_hover', '', $prop);
        if (isset($settings[$prop])) {
            $style['hover'][$key] = $settings[$prop];
        }
    }
    
    // Extract responsive styles
    if (isset($settings['_mobile'])) {
        $style['mobile'] = smdp_parse_responsive_styles($settings['_mobile']);
    }
    
    if (isset($settings['_tablet'])) {
        $style['tablet'] = smdp_parse_responsive_styles($settings['_tablet']);
    }
    
    // Extract alignment
    if (isset($settings['align'])) {
        $style['desktop']['text_align'] = $settings['align'];
    }
    
    if (isset($settings['align_tablet'])) {
        $style['tablet']['text_align'] = $settings['align_tablet'];
    }
    
    if (isset($settings['align_mobile'])) {
        $style['mobile']['text_align'] = $settings['align_mobile'];
    }
    
    // Extract spacing
    $spacing_props = ['margin', 'padding'];
    foreach ($spacing_props as $spacing) {
        if (isset($settings[$spacing])) {
            $style['desktop'][$spacing] = $settings[$spacing];
        }
        if (isset($settings[$spacing . '_tablet'])) {
            $style['tablet'][$spacing] = $settings[$spacing . '_tablet'];
        }
        if (isset($settings[$spacing . '_mobile'])) {
            $style['mobile'][$spacing] = $settings[$spacing . '_mobile'];
        }
    }
    
    return array_filter($style);
}

/**
 * Parse responsive styles from Elementor settings
 */
function smdp_parse_responsive_styles($responsive_settings) {
    $styles = [];
    
    if (!is_array($responsive_settings)) {
        return $styles;
    }
    
    $responsive_props = [
        'width', 'height', 'margin', 'padding',
        'font_size', 'line_height', 'text_align'
    ];
    
    foreach ($responsive_props as $prop) {
        if (isset($responsive_settings[$prop])) {
            $styles[$prop] = $responsive_settings[$prop];
        }
    }
    
    return $styles;
}

/**
 * Extract responsive settings
 */
function smdp_extract_responsive_settings($settings) {
    return [
        'hide_on_desktop' => isset($settings['hide_desktop']) ? $settings['hide_desktop'] === 'yes' : false,
        'hide_on_tablet' => isset($settings['hide_tablet']) ? $settings['hide_tablet'] === 'yes' : false,
        'hide_on_mobile' => isset($settings['hide_mobile']) ? $settings['hide_mobile'] === 'yes' : false,
        'custom_breakpoint' => $settings['custom_breakpoint'] ?? null,
        'reverse_order_tablet' => $settings['reverse_order_tablet'] ?? false,
        'reverse_order_mobile' => $settings['reverse_order_mobile'] ?? false,
    ];
}

/**
 * Extract animation settings
 */
function smdp_extract_animation($settings) {
    return [
        'entrance_animation' => $settings['entrance_animation'] ?? null,
        'entrance_animation_duration' => $settings['entrance_animation_duration'] ?? 'normal',
        'entrance_animation_delay' => $settings['entrance_animation_delay'] ?? 0,
        'exit_animation' => $settings['exit_animation'] ?? null,
        'mouse_animation' => $settings['mouse_animation'] ?? null,
        'css_animation' => $settings['css_animation'] ?? null,
    ];
}

/**
 * Check if image URL is an SVG
 */
function smdp_is_svg($url) {
    if (empty($url)) {
        return false;
    }
    
    $extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));
    return $extension === 'svg';
}

/**
 * Extract SVG code from settings
 */
function smdp_extract_svg_code($settings) {
    if (!isset($settings['image']['url']) || !smdp_is_svg($settings['image']['url'])) {
        return null;
    }
    
    $svg_data = [
        'url' => $settings['image']['url'],
        'alt' => $settings['image']['alt'] ?? '',
        'width' => $settings['width'] ?? null,
        'height' => $settings['height'] ?? null,
    ];
    
    // Try to fetch SVG content if local file
    $local_path = str_replace(content_url(), WP_CONTENT_DIR, $settings['image']['url']);
    if (file_exists($local_path) && smdp_is_svg($local_path)) {
        $svg_content = file_get_contents($local_path);
        if ($svg_content) {
            $svg_data['content'] = $svg_content;
            if (preg_match('/viewBox="([^"]+)"/', $svg_content, $matches)) {
                $svg_data['viewBox'] = $matches[1];
            }
        }
    }
    
    return $svg_data;
}

/**
 * Get URL for object
 */
function smdp_get_object_url($object_id, $object_type) {
    switch ($object_type) {
        case 'post':
        case 'page':
        case 'product':
            return get_permalink($object_id);
        case 'term':
        case 'category':
        case 'tag':
            $term = get_term($object_id);
            return $term ? get_term_link($term) : '';
        default:
            return '';
    }
}

/**
 * Get global Elementor settings
 */
function smdp_get_global_settings() {
    $global_settings = [];
    
    if (class_exists('\Elementor\Plugin')) {
        $kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
        if ($kit) {
            $global_settings = $kit->get_settings();
        }
    }
    
    return $global_settings;
}

/**
 * Get page settings for specific object
 */
function smdp_get_page_settings($object_id, $object_type) {
    $settings = [];
    $meta_key = '_elementor_page_settings';
    
    switch ($object_type) {
        case 'post':
        case 'page':
        case 'product':
            $settings = get_post_meta($object_id, $meta_key, true);
            break;
        case 'term':
        case 'category':
        case 'tag':
            $settings = get_term_meta($object_id, $meta_key, true);
            break;
    }
    
    return is_array($settings) ? $settings : [];
}

/**
 * Flatten the hierarchy for easy searching (optional)
 */
function smdp_flatten_hierarchy($hierarchy, &$result = []) {
    foreach ($hierarchy as $item) {
        $result[] = $item;
        if (isset($item['elements']) && is_array($item['elements'])) {
            smdp_flatten_hierarchy($item['elements'], $result);
        }
    }
    return $result;
}

/**
 * Search for specific widget type in hierarchy
 */
function smdp_find_widgets_by_type($hierarchy, $widget_type) {
    $found = [];
    $flattened = smdp_flatten_hierarchy($hierarchy);
    
    foreach ($flattened as $item) {
        if (isset($item['widgetType']) && $item['widgetType'] === $widget_type) {
            $found[] = $item;
        }
        if (isset($item['widget_data']['widgetType']) && $item['widget_data']['widgetType'] === $widget_type) {
            $found[] = $item['widget_data'];
        }
    }
    
    return $found;
}

/**
 * Convert array to JSON for output
 */
function smdp_to_json($data, $pretty = false) {
    if ($pretty) {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

// ========== USAGE EXAMPLES ==========

// // Example 1: Get Elementor content from a page
// $page_content = smdp_get_elementor_content(42, 'page');
// echo smdp_to_json($page_content, true);

// // Example 2: Get from a product
// $product_content = smdp_get_elementor_content(123, 'product');
// echo smdp_to_json($product_content, true);

// // Example 3: Get from a category archive
// $category_content = smdp_get_elementor_content(5, 'category');
// echo smdp_to_json($category_content, true);

// // Example 4: Get from a custom post type
// $custom_content = smdp_get_elementor_content(456, 'post');
// echo smdp_to_json($custom_content, true);

// // Example 5: Search for all heading widgets in hierarchy
// $content = smdp_get_elementor_content(42, 'page');
// if ($content['has_elementor']) {
//     $headings = smdp_find_widgets_by_type($content['content'], 'heading');
//     foreach ($headings as $heading) {
//         echo "Found heading: " . ($heading['props']['text'] ?? '') . "\n";
//     }
// }

// // Example 6: Process multiple post types
// $posts_to_process = [
//     ['id' => 42, 'type' => 'page'],
//     ['id' => 123, 'type' => 'product'],
//     ['id' => 5, 'type' => 'category'],
// ];

// $all_content = [];
// foreach ($posts_to_process as $item) {
//     $all_content[] = smdp_get_elementor_content($item['id'], $item['type']);
// }
// echo smdp_to_json($all_content, true);