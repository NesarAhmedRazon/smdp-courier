# EL Content Extractor — Full Architecture & Senior Dev Guide

> Elementor **4.0.5** · WordPress 6.6+

---

## What the plugin does

Exposes a clean PHP API, REST endpoint, and shortcode to get the **exact same HTML,
CSS, and JS that Elementor would render on the frontend** — without visiting the page.

```php
$el = elx_get_elementor_content( $post_id );
// $el = [
//   'html' => '<div data-elementor-id="..." class="elementor ..."> ... </div>',
//   'css'  => '.elementor-123 { ... }',
//   'js'   => '<script id="elementor-frontend-js-extra">...</script> <script src="...frontend.min.js"></script> ...',
// ]
```

---

## Elementor 4.0.5 — Deep Architecture Analysis

### Plugin entry point

```
elementor.php
  → defines ELEMENTOR_VERSION (4.0.5), ELEMENTOR_PATH, ELEMENTOR_URL, etc.
  → requires includes/plugin.php
```

### Core singleton — `Plugin` class (`includes/plugin.php`)

```
Plugin::$instance          (global singleton, accessed everywhere as Plugin::$instance)
  ├── ->frontend            (Elementor\Frontend)           ← THE rendering engine
  ├── ->documents           (Core\Documents_Manager)       ← loads Document objects per post
  ├── ->db                  (DB)                           ← stores/reads _elementor_data meta
  ├── ->editor              (Editor)                       ← edit mode controls
  ├── ->preview             (Core\Preview\Manager)
  ├── ->breakpoints         (Core\Breakpoints\Manager)
  ├── ->experiments         (Core\Experiments\Manager)     ← feature flags (container, etc.)
  └── ...more managers
```

---

## How `the_content` works with Elementor

When WordPress calls `the_content()` or `apply_filters('the_content', $content)`,
Elementor intercepts it at **priority 9**:

```
the_content filter (priority 9)
  → Frontend::apply_builder_in_content($content)
      → checks: not preview mode, not excerpt
      → removes the filter from itself (prevent recursion)
      → calls get_builder_content($post_id)
          ┌─────────────────────────────────────────────────────────────┐
          │ 1. documents->get_doc_for_frontend($post_id)                │
          │    Returns a Document object (Post, Page, Product, etc.)    │
          │                                                             │
          │ 2. document->is_built_with_elementor()                      │
          │    Checks _elementor_edit_mode = 'builder'                  │
          │                                                             │
          │ 3. document->get_elements_data()                            │
          │    Reads _elementor_data (JSON) from post meta              │
          │    Decodes to PHP array of sections/columns/widgets         │
          │                                                             │
          │ 4. apply_filters('elementor/document/load/data', $data)     │
          │    apply_filters('elementor/frontend/builder_content_data') │
          │                                                             │
          │ 5. Post_CSS::create($post_id)->enqueue()                    │
          │    Generates CSS file if not cached, registers stylesheet   │
          │                                                             │
          │ 6. ob_start()                                               │
          │      $css_file->print_css() (if $with_css = true)          │
          │      document->print_elements_with_wrapper($data)           │
          │        <div data-elementor-id="..." class="elementor ...">  │
          │          foreach section → column → widget:                 │
          │            $element->before_render()                        │
          │            $element->render_content()         ← widget HTML │
          │            $element->after_render()                         │
          │        </div>                                               │
          │ 7. $content = ob_get_clean()                                │
          │                                                             │
          │ 8. apply_filters('elementor/frontend/the_content', $content)│
          └─────────────────────────────────────────────────────────────┘
      → re-adds itself to the_content filter
      → returns $content
```

---

## CSS — How it's generated and stored

```
Core\Files\CSS\Post extends Core\Files\CSS\Base extends Core\Files\Base
```

**Storage path:**
```
wp-content/uploads/elementor/css/post-{ID}.css
```

**Generation pipeline:**
```
Post_CSS::create($post_id)         // factory method, sets filename = "post-{ID}.css"
  → get_content()
      → parse_content()
          → Performance::set_use_style_controls(true)
          → render_css()
              foreach elements_data as element:
                  $element->get_raw_data()
                  foreach controls as control:
                      add_rules_for_control()  → Stylesheet::add_rules()
          → do_action("elementor/css-file/post/parse", $css_file)
          → return $this->get_stylesheet()->__toString()
```

**The CSS file contains:**
- Per-element custom CSS (from the "Custom CSS" panel in each widget)
- Typography rules (font-family, font-size, etc.) from controls
- Color and spacing overrides
- Responsive breakpoint variations

**The CSS file does NOT contain:**
- Global CSS (kit-level: design tokens, global colors) — that's in `global.css`
- Frontend framework CSS (elementor-frontend.css, flexbox.css) — always enqueued separately

---

## JS — How it's structured

### Registered handles (in order of dependency):

| Handle | File | Purpose |
|--------|------|---------|
| `elementor-webpack-runtime` | webpack.runtime.min.js | Module loading runtime |
| `elementor-frontend-modules` | frontend-modules.min.js | All widget handler modules |
| `elementor-frontend` | frontend.min.js | Core frontend JS class |
| `swiper` | swiper/v8/swiper.min.js | Carousel widgets |
| `flatpickr` | flatpickr/flatpickr.min.js | Date pickers |
| `imagesloaded` | imagesloaded/imagesloaded.pkgd.min.js | Masonry layout |

### Per-page config (the important part for your use case):

```js
// Injected via wp_localize_script('elementor-frontend', 'elementorFrontendConfig', [...])
// Built in Frontend::get_init_settings()

window.elementorFrontendConfig = {
    environmentMode: { edit: false, wpPreview: false, isScriptDebug: false },
    i18n: { ... },
    is_rtl: false,
    breakpoints: { xs: 0, sm: 480, md: 768, lg: 1025, xl: 1440, xxl: 1600 },
    responsive: { ... },
    version: "4.0.5",
    is_static: false,
    experimentalFeatures: { container: true, ... },
    urls: { assets: "...elementor/assets/" },
    nonces: { ... },
    post: { id: 123, title: "...", excerpt: "...", featuredImage: "..." },
    kit: { ... },  // design tokens from active Elementor Kit
    settings: { page: { ... } }  // page-level settings
};
```

---

## Usage in your plugin

### 1. Direct PHP function call

```php
// In your plugin — after plugins_loaded at priority >= 20

add_action('template_redirect', function() {
    $post_id = get_the_ID();

    $el = elx_get_elementor_content($post_id);

    if (is_wp_error($el)) {
        // Not an Elementor post, or post not found
        return;
    }

    // $el['html'] — the raw Elementor markup string
    // $el['css']  — the compiled CSS for this post
    // $el['js']   — <script> tags for Elementor runtime + per-page config
});
```

### 2. REST API

```
GET /wp-json/elx/v1/content/{post_id}
```

**Response:**
```json
{
  "html": "<div data-elementor-id=\"123\" class=\"elementor elementor-123\">...</div>",
  "css":  ".elementor-123 { ... }",
  "js":   "<script id=\"elementor-frontend-js-extra\">var elementorFrontendConfig = {...}</script>\n<script src=\".../frontend.min.js?ver=4.0.5\"></script>"
}
```

**To restrict to logged-in users**, change `rest_permission_check()`:
```php
public function rest_permission_check(): bool {
    return current_user_can('edit_posts');
}
```

### 3. Shortcode

```
[elx_content post_id="123"]
```

Outputs `<style>`, HTML, and `<script>` inline in the page.

---

## Integrating into your own plugin (without installing this one)

Copy only the `ELX_Extractor` class and use it directly:

```php
// After plugins_loaded

if (class_exists('\Elementor\Plugin')) {
    $extractor = new ELX_Extractor();

    $result = $extractor->get($post_id);

    if (!is_wp_error($result)) {
        $html = $result['html'];
        $css  = $result['css'];
        $js   = $result['js'];
    }
}
```

---

## How a Senior WP Developer would approach this

### Step 1 — Always use Elementor's public API, not internal shortcuts

```php
// ✅ CORRECT — public API
Plugin::$instance->frontend->get_builder_content_for_display($post_id, false);

// ❌ WRONG — internal method, not designed for external callers
Plugin::$instance->frontend->get_builder_content($post_id);

// ❌ WRONG — bypasses all of Elementor's render pipeline
get_post_meta($post_id, '_elementor_data', true);
```

### Step 2 — Separate CSS from HTML

Never render with `$with_css = true` if you need to manipulate the CSS separately.
The `$with_css = true` flag inlines the `<style>` block before the HTML — you lose
the ability to extract CSS cleanly.

```php
// Get HTML only (no inlined CSS)
$html = Plugin::$instance->frontend->get_builder_content_for_display($post_id, false);

// Get CSS separately via the CSS file class
$css = \Elementor\Core\Files\CSS\Post::create($post_id)->get_content();
```

### Step 3 — JS is framework-level, not post-level (mostly)

Elementor's JS framework doesn't change per-post. Only `elementorFrontendConfig`
is post-aware. This is why we return `<script src="...">` tags (cacheable) rather
than inlining 200KB of minified JavaScript.

### Step 4 — Context switching

If you're calling the extractor outside of a normal WP page request (e.g., in
an AJAX handler, REST callback, or CLI command), set up the post context properly:

```php
$post = get_post($post_id);
$GLOBALS['post'] = $post;
setup_postdata($post);
query_posts(['p' => $post_id, 'post_type' => 'any']);

// ... extract content ...

wp_reset_query();
wp_reset_postdata();
```

### Step 5 — Cache the output

```php
$cache_key = 'elx_content_' . $post_id . '_' . get_post_modified_time('U', false, $post_id);

$cached = get_transient($cache_key);
if ($cached !== false) {
    return $cached;
}

$result = (new ELX_Extractor())->get($post_id);
set_transient($cache_key, $result, HOUR_IN_SECONDS);
return $result;
```

### Step 6 — Listen for Elementor's save hook to bust the cache

```php
add_action('elementor/editor/after_save', function($post_id) {
    delete_transient('elx_content_' . $post_id . '_*');
    // or use a naming scheme that lets you delete by post ID
});
```

---

## Important meta keys

| Meta Key | Value | Purpose |
|---|---|---|
| `_elementor_edit_mode` | `'builder'` | Marks the post as Elementor-built |
| `_elementor_data` | JSON string | The full page layout (sections/columns/widgets) |
| `_elementor_css` | array | CSS file metadata (version, status) |
| `_elementor_template_type` | `'page'`, `'section'`, etc. | Document type |
| `_elementor_page_settings` | array | Page-level settings (layout, background, etc.) |

---

## Hooks your plugin can use

```php
// Before Elementor renders a document
add_action('elementor/frontend/before_get_builder_content', function($document, $is_excerpt) {}, 10, 2);

// After rendering, filter the HTML output
add_filter('elementor/frontend/the_content', function($content) {
    return $content;
});

// After rendering (action, for side effects)
add_action('elementor/frontend/get_builder_content', function($document, $is_excerpt, $with_css) {}, 10, 3);

// When CSS file is parsed
add_action('elementor/css-file/post/parse', function($css_file) {
    // $css_file->get_stylesheet()->add_rules(...)
});

// After Elementor frontend scripts are enqueued
add_action('elementor/frontend/after_enqueue_scripts', function() {
    wp_enqueue_script('my-script', '...', ['elementor-frontend'], '1.0', true);
});
```
