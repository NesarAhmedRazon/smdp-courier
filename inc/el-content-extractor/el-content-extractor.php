<?php

/**
 * EL Content Extractor - Robust Admin/Frontend Context Edition
 */

if (! defined('ABSPATH')) exit;

// =============================================================================
// Main function
// =============================================================================

function elx_get_elementor_content(int $post_id): array|WP_Error
{

    if (! class_exists('\Elementor\Plugin')) {
        return new WP_Error('elementor_missing', 'Elementor is not active.');
    }

    if (! get_post($post_id)) {
        return new WP_Error('not_found', "Post {$post_id} not found.");
    }

    if (! elx_is_elementor_post($post_id)) {
        return new WP_Error('not_elementor', "Post {$post_id} is not built with Elementor.");
    }

    // ── Core problem: Elementor's frontend renderer is never booted in admin ──
    elx_ensure_frontend_ready($post_id);

    $html = elx_get_html($post_id);

    // If HTML is still empty after bootstrapping, the environment is too broken.
    // Return a WP_Error so the caller can fall back gracefully.
    if (empty(trim($html))) {
        return new WP_Error(
            'render_failed',
            "Elementor rendered empty HTML for post {$post_id}. " .
                "Possible causes: Elementor Pro hook not fired, widget registration missing in admin context."
        );
    }

    return [    // line 41
        'html' => $html,
        'css'  => elx_get_css($post_id),
        'js'   => elx_get_js($post_id),
    ]; // <-- Add closing bracket here
}


// =============================================================================
// The key fix: Bootstrap Elementor frontend in non-frontend contexts
// =============================================================================

/**
 * Elementor's frontend->get_builder_content_for_display() requires:
 *   1. $wp_query to know the queried object (for is_singular(), get_the_ID() etc.)
 *   2. Frontend::_is_initialized to be true
 *   3. The post's Document to be loaded as "current document"
 *
 * None of these are true during admin POST saves or REST requests.
 * We set them up manually here.
 */
function elx_ensure_frontend_ready(int $post_id): void
{
    global $wp_query, $post;

    $elementor = \Elementor\Plugin::$instance;

    // ── 1. Make WP_Query think we are on a singular post page ────────────────
    //    Elementor checks is_singular(), get_queried_object_id(), etc.
    if (! $wp_query->is_singular || (int) $wp_query->get_queried_object_id() !== $post_id) {
        $wp_query->is_singular     = true;
        $wp_query->is_archive      = false;
        $wp_query->is_home         = false;
        $wp_query->queried_object  = get_post($post_id);
        $wp_query->queried_object_id = $post_id;
    }

    // ── 2. Set the global $post so setup_postdata / get_the_ID() work ────────
    $post = get_post($post_id);
    setup_postdata($post);

    // ── 3. Initialize Elementor frontend if it hasn't been yet ───────────────
    //    The frontend object has an internal $_is_initialized flag.
    //    The only public API to force this is init(). It is safe to call
    //    multiple times; Elementor guards with its own flag internally.
    if (method_exists($elementor->frontend, 'init')) {
        $elementor->frontend->init();
    }

    // ── 4. Register Elementor scripts/styles so renderers don't crash ─────────
    //    Some widgets call wp_enqueue_* during render expecting scripts to exist.
    if (method_exists($elementor->frontend, 'register_scripts')) {
        $elementor->frontend->register_scripts();
    }
    if (method_exists($elementor->frontend, 'register_styles')) {
        $elementor->frontend->register_styles();
    }
}


// =============================================================================
// HTML  (two-strategy approach)
// =============================================================================

function elx_get_html(int $post_id): string
{

    // ── Strategy A: Public frontend API ──────────────────────────────────────
    //    Second arg `true` forces fresh render (skips internal post-level cache
    //    that may have been set to '' during the admin page load).
    $html = \Elementor\Plugin::$instance->frontend
        ->get_builder_content_for_display($post_id, true);

    if (! empty(trim($html))) {
        return smdp_clean_html($html);
    }

    // ── Strategy B: Document API (no frontend context required) ──────────────
    //    This is what Elementor uses internally when previewing in the editor.
    //    It walks _elementor_data JSON → widgets → render() → collect output.
    $document = \Elementor\Plugin::$instance->documents->get_doc_for_frontend($post_id);

    if (! $document) {
        // Last resort: get() instead of get_doc_for_frontend()
        $document = \Elementor\Plugin::$instance->documents->get($post_id);
    }

    if ($document && method_exists($document, 'get_content')) {
        $html = $document->get_content(true); // true = render, not raw JSON
        if (! empty(trim($html))) {
            $html = preg_replace(
                '#<style\b[^>]*>.*?</style>|<script\b[^>]*>.*?</script>#is',
                '',
                $html
            );
            return smdp_clean_html($html);
        }
    }

    // ── Strategy C: Manual widget-tree walk ──────────────────────────────────
    //    If both APIs fail, build HTML by walking the raw _elementor_data JSON.
    //    This is the nuclear option — works even if the frontend is totally dead.
    if ($document) {
        $html = elx_render_elements_manually($document);
        if (! empty(trim($html))) {
            return smdp_clean_html($html);
        }
    }

    return '';
}

/**
 * Walk the element tree and call each element's print_content() individually.
 * This mimics what document->print_elements_with_wrapper() does internally,
 * but without the wrapper markup and without requiring frontend context.
 */
function elx_render_elements_manually(\Elementor\Core\Base\Document $document): string
{
    $elements_data = $document->get_elements_data();

    if (empty($elements_data)) {
        return '';
    }

    $elements_manager = \Elementor\Plugin::$instance->elements_manager;

    ob_start();

    \Elementor\Plugin::$instance->frontend->add_content_filter();

    $document->print_elements($elements_data);

    \Elementor\Plugin::$instance->frontend->remove_content_filter();

    return ob_get_clean() ?: '';
}


// =============================================================================
// CSS
// =============================================================================

function elx_get_css(int $post_id): string
{
    if (! class_exists('\Elementor\Core\Files\CSS\Post')) {
        return '';
    }

    $post_css = \Elementor\Core\Files\CSS\Post::create($post_id);

    // Regenerate if the file is missing or stale — avoids returning ''
    // when the static file was deleted/never generated in this environment.
    if (method_exists($post_css, 'update')) {
        $post_css->update();
    }

    return $post_css->get_content() ?: '';
}


// =============================================================================
// JS  (unchanged — was already correct)
// =============================================================================

function elx_get_js(int $post_id): string
{
    global $wp_scripts;

    if (! isset($wp_scripts)) {
        return '';
    }

    $handles = [
        'elementor-webpack-runtime',
        'elementor-frontend-modules',
        'elementor-frontend',
    ];

    $output = '';

    foreach ($handles as $handle) {
        if (! isset($wp_scripts->registered[$handle])) {
            continue;
        }

        $script = $wp_scripts->registered[$handle];

        foreach (['before', 'data', 'after'] as $position) {
            $chunk = $wp_scripts->get_data($handle, $position);
            if ($chunk) {
                $output .= '<script id="' . esc_attr($handle) . '-js-' . $position . '">'
                    . implode("\n", (array) $chunk)
                    . "</script>\n";
            }
        }

        if ($script->src) {
            $src     = add_query_arg('ver', $script->ver ?: get_bloginfo('version'), $script->src);
            $output .= '<script src="' . esc_url($src) . '" id="' . esc_attr($handle) . '-js"></script>' . "\n";
        }
    }

    return $output;
}


// =============================================================================
// Helper
// =============================================================================

function elx_is_elementor_post(int $post_id): bool
{
    return 'builder' === get_post_meta($post_id, '_elementor_edit_mode', true)
        && ! empty(get_post_meta($post_id, '_elementor_data', true));
}
