<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register meta box
 */
add_action('add_meta_boxes', 'smdp_tailwind_meta_box');

function smdp_tailwind_meta_box()
{
    $post_types = [
        'post',
        'page',
        'product',
    ];

    foreach ($post_types as $post_type) {

        add_meta_box(
            'smdp_tailwind_meta',
            'TailwindCSS Browser CDN',
            'smdp_tailwind_meta_box_callback',
            $post_type,
            'side',
            'default'
        );
    }
}

/**
 * Meta box UI
 */
function smdp_tailwind_meta_box_callback($post)
{
    wp_nonce_field('smdp_tailwind_meta_nonce', 'smdp_tailwind_meta_nonce');

    $enabled = get_post_meta($post->ID, '_smdp_enable_tailwind_browser', true);

    $fallback_css = get_post_meta(
        $post->ID,
        '_smdp_tailwind_fallback_css',
        true
    );

    ?>

    <p>
        Enable TailwindCSS Browser CDN for this content.
    </p>

    <label style="display:flex; align-items:center; gap:8px; margin-bottom:16px;">
        <input
            type="checkbox"
            name="smdp_enable_tailwind_browser"
            value="1"
            <?php checked($enabled, '1'); ?>
        >
        Enable
    </label>

    <p style="margin-bottom:6px;">
        <strong>Fallback CSS</strong>
    </p>

    <textarea
        name="smdp_tailwind_fallback_css"
        style="width:100%; min-height:180px; font-family:monospace;"
        placeholder=".my-class { padding:20px; }"
    ><?php echo esc_textarea($fallback_css); ?></textarea>

    <p style="margin-top:8px; font-size:12px; opacity:.7;">
        Injected only when Tailwind CDN is enabled.
    </p>

    <?php
}

/**
 * Save meta
 */
add_action('save_post', 'smdp_save_tailwind_meta');

function smdp_save_tailwind_meta($post_id)
{
    /**
     * Security checks
     */
    if (!isset($_POST['smdp_tailwind_meta_nonce'])) {
        return;
    }

    if (!wp_verify_nonce($_POST['smdp_tailwind_meta_nonce'], 'smdp_tailwind_meta_nonce')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    /**
     * Save enable toggle
     */
    $enabled = isset($_POST['smdp_enable_tailwind_browser']) ? '1' : '0';

    update_post_meta(
        $post_id,
        '_smdp_enable_tailwind_browser',
        $enabled
    );

    /**
     * Save fallback CSS
     */
    $fallback_css = '';

    if (isset($_POST['smdp_tailwind_fallback_css'])) {
        $fallback_css = wp_unslash($_POST['smdp_tailwind_fallback_css']);
    }

    update_post_meta(
        $post_id,
        '_smdp_tailwind_fallback_css',
        $fallback_css
    );
}
/**
 * add_action('wp_footer', function () {
  *  ?>
  *  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
   * <?php
*}, 9999999999999999999999);
 * 
 */



 
/**
 * Inject Tailwind Browser CDN
 */
add_action('wp_enqueue_scripts', 'smdp_enqueue_tailwind_browser_cdn', PHP_INT_MAX);

function smdp_enqueue_tailwind_browser_cdn()
{
    if (!is_singular()) {
        return;
    }

    $post_id = get_queried_object_id();

    if (!$post_id) {
        return;
    }

    $enabled = get_post_meta(
        $post_id,
        '_smdp_enable_tailwind_browser',
        true
    );

    if ($enabled !== '1') {
        return;
    }

    wp_enqueue_script(
        'tailwind-browser',
        'https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4',
        [],
        null,
        false
    );

    /**
     * Add fallback CSS
     */
    $fallback_css = get_post_meta(
        $post_id,
        '_smdp_tailwind_fallback_css',
        true
    );

    if (!empty($fallback_css)) {

        wp_register_style('smdp-tailwind-fallbacks', false);

        wp_enqueue_style('smdp-tailwind-fallbacks');

        wp_add_inline_style(
            'smdp-tailwind-fallbacks',
            $fallback_css
        );
        
    }
}


add_filter('the_content', 'smdp_disable_wpautop_for_tailwind', 0);

function smdp_disable_wpautop_for_tailwind($content)
{
    if (!is_singular()) {
        return $content;
    }

    $post_id = get_the_ID();

    if (!$post_id) {
        return $content;
    }

    if (get_post_meta($post_id, '_smdp_enable_tailwind_browser', true) !== '1') {
        return $content;
    }

    // Remove wpautop only for this render
    remove_filter('the_content', 'wpautop');

    return $content;
}