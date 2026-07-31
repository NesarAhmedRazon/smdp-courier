<?php


/**
 * Get all attachment data by ID and return as JSON
 * 
 * @param int  $attachment_id Attachment Post ID
 * @param bool $pretty_print  Whether to pretty print the JSON
 * @return string|false JSON string or false on failure
 */

 
if (! defined('ABSPATH')) {
    exit;
}


/**
 * Get full attachment data with smart Cloudinary support
 */
function get_attachment_full_data($attachment_id) {
    
    $attachment = get_post($attachment_id);
    if (!$attachment || $attachment->post_type !== 'attachment') {
        return false;
    }

    $raw_meta = get_post_meta($attachment->ID);
    $processed_meta = [];

    foreach ($raw_meta as $key => $values) {
        $value = $values[0] ?? $values;
        $processed_meta[$key] = maybe_unserialize_value($value);
    }

    // Extract Cloudinary data
    $cloudinary_data = $processed_meta['_cloudinary'] ?? [];

    $data = [
        'id'              => (int) $attachment->ID,
        'title'           => $attachment->post_title,
        'caption'         => $attachment->post_excerpt,
        'description'     => $attachment->post_content,
        'alt_text'        => $processed_meta['_wp_attachment_image_alt'] ?? '',
        
        // URLs
        'url'             => wp_get_attachment_url($attachment->ID),
        'cloudinary_url'  => $cloudinary_data['_cloudinary_url'] ?? null,
        'public_id'       => $cloudinary_data['_public_id'] ?? null,
        
        'file'            => get_attached_file($attachment->ID),
        'file_name'       => basename(get_attached_file($attachment->ID)),
        'mime_type'       => $attachment->post_mime_type,
        
        'metadata'        => $processed_meta['_wp_attachment_metadata'] ?? [],
        'sizes'           => [],
        'cloudinary'      => $cloudinary_data,           // Full Cloudinary meta
        'post_meta'       => $processed_meta,
    ];

    // Image sizes
    if (wp_attachment_is_image($attachment->ID)) {
        $sizes = get_intermediate_image_sizes();
        $sizes[] = 'full';

        foreach ($sizes as $size) {
            $image = wp_get_attachment_image_src($attachment->ID, $size);
            if ($image) {
                $data['sizes'][$size] = [
                    'url'    => $image[0],
                    'width'  => (int) $image[1],
                    'height' => (int) $image[2],
                ];
            }
        }
    }

    return $data;
}


/**
 * Smart unserializer - handles serialized strings, JSON strings, and normal values
 */
function maybe_unserialize_value($value) {
    if (empty($value)) {
        return $value;
    }

    // If it's already an array or object, return as is
    if (is_array($value) || is_object($value)) {
        return $value;
    }

    // Try JSON first (newer data)
    $json = json_decode($value, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $json;
    }

    // Try PHP serialize
    if (is_string($value) && str_starts_with(trim($value), 'a:') || str_starts_with(trim($value), 'O:')) {
        $unserialized = @unserialize($value);
        if ($unserialized !== false) {
            return $unserialized;
        }
    }

    // Return original value if nothing worked
    return $value;
}


function get_attachment_cloudinary($attachment_id) {
    $url = wp_get_attachment_url($attachment_id);
    
    $cld = get_post_meta($attachment_id, '_cloudinary', true);
    $cld = maybe_unserialize($cld);

    return [
        'url'       => $url,
        'cloudinary' => !empty($cld) ? [
            'url' => $cld['_cloudinary_url'] ?? null,
            'id'  => $cld['_public_id'] ?? null,
        ] : null
    ];
}