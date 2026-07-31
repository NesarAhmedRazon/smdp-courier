<?php

if (!function_exists('extractContent')) {

    function extractContent($post_id)
    {
        $raw_json = get_post_meta($post_id, '_elementor_data', true);

        if (empty($raw_json)) {
            return [
                'is_elementor' => false,
                'normalized'   => smdp_parse_wp_content($post_id),
            ];
        }

        $raw = json_decode($raw_json, true) ?? [];

        return [
            'is_elementor' => true,
            'elementor'    => [
                'normalized' => smdp_normalize_elements($raw),
                'widgets'    => smdp_collect_widgets($raw),
                'css'        => smdp_get_post_css($post_id),
            ]
        ];
    }
}


// ─────────────────────────────────────────────────────────────
// WP content → Elementor-style nodes
// ─────────────────────────────────────────────────────────────

function smdp_parse_wp_content(int $post_id): array
{
    $html = apply_filters('the_content', get_post_field('post_content', $post_id));

    if (empty(trim($html))) return [];

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $nodes = [];
    $index = 0;

    foreach ($doc->childNodes as $node) {
        if ($node->nodeType !== XML_ELEMENT_NODE) continue;

        $parsed = smdp_parse_wp_node($node, $index++);
        if ($parsed !== null) {
            $nodes[] = $parsed;
        }
    }

    return $nodes;
}

function smdp_parse_wp_node(DOMElement $node, int $index): ?array
{
    $tag  = strtolower($node->tagName);
    $id   = 'wp-' . $index;

    switch (true) {

        // ── Headings ────────────────────────────────────────
        case in_array($tag, ['h1','h2','h3','h4','h5','h6'], true):
            return [
                'type'  => 'heading',
                'id'    => $id,
                'props' => [
                    'text'  => trim($node->textContent),
                    'tag'   => $tag,
                    'align' => 'left',
                ],
            ];

        // ── Paragraph ───────────────────────────────────────
        case $tag === 'p':
            // A <p> that contains only an <img> → image node
            $children = smdp_element_children($node);
            if (count($children) === 1 && strtolower($children[0]->tagName) === 'img') {
                return smdp_parse_img_element($children[0], $id);
            }

            // A <p> that contains only an <a><img></a> → image node with link
            if (count($children) === 1 && strtolower($children[0]->tagName) === 'a') {
                $aChildren = smdp_element_children($children[0]);
                if (count($aChildren) === 1 && strtolower($aChildren[0]->tagName) === 'img') {
                    $imgNode = smdp_parse_img_element($aChildren[0], $id);
                    $imgNode['props']['link'] = $children[0]->getAttribute('href');
                    return $imgNode;
                }
            }

            $inner = smdp_inner_html($node);
            if (trim(strip_tags($inner)) === '') return null;   // empty paragraph

            return [
                'type'  => 'text',
                'id'    => $id,
                'props' => ['html' => $inner],
            ];

        // ── Standalone image ────────────────────────────────
        case $tag === 'img':
            return smdp_parse_img_element($node, $id);

        // ── Figure (gutenberg image block) ──────────────────
        case $tag === 'figure':
            $imgs = $node->getElementsByTagName('img');
            if ($imgs->length === 0) return null;

            $img     = smdp_parse_img_element($imgs->item(0), $id);
            $caption = $node->getElementsByTagName('figcaption');
            if ($caption->length > 0) {
                $img['props']['caption'] = trim($caption->item(0)->textContent);
            }
            return $img;

        // ── Lists ────────────────────────────────────────────
        case in_array($tag, ['ul', 'ol'], true):
            $items = [];
            foreach ($node->getElementsByTagName('li') as $li) {
                $items[] = trim($li->textContent);
            }
            return [
                'type'  => 'list',
                'id'    => $id,
                'props' => [
                    'ordered' => $tag === 'ol',
                    'items'   => $items,
                ],
            ];

        // ── Blockquote ───────────────────────────────────────
        case $tag === 'blockquote':
            return [
                'type'  => 'quote',
                'id'    => $id,
                'props' => ['html' => smdp_inner_html($node)],
            ];

        // ── Divider ──────────────────────────────────────────
        case $tag === 'hr':
            return ['type' => 'divider', 'id' => $id];

        // ── Table ────────────────────────────────────────────
        case $tag === 'table':
            return [
                'type'  => 'table',
                'id'    => $id,
                'props' => ['html' => smdp_inner_html($node)],
            ];

        // ── Embeds / iframes ─────────────────────────────────
        case $tag === 'iframe':
            return [
                'type'  => 'embed',
                'id'    => $id,
                'props' => [
                    'src'    => $node->getAttribute('src'),
                    'width'  => $node->getAttribute('width')  ?: null,
                    'height' => $node->getAttribute('height') ?: null,
                ],
            ];

        // ── Anything else → raw html ─────────────────────────
        default:
            return [
                'type'  => 'raw_html',
                'id'    => $id,
                'props' => ['html' => smdp_outer_html($node)],
            ];
    }
}

function smdp_parse_img_element(DOMElement $img, string $id): array
{
    // Prefer largest srcset candidate over src when available
    $src = $img->getAttribute('src');
    if ($srcset = $img->getAttribute('srcset')) {
        $candidates = array_map('trim', explode(',', $srcset));
        $largest    = '';
        $largestW   = 0;
        foreach ($candidates as $candidate) {
            [$url, $descriptor] = array_pad(explode(' ', trim($candidate), 2), 2, '');
            $w = (int) rtrim($descriptor, 'w');
            if ($w > $largestW) { $largestW = $w; $largest = $url; }
        }
        if ($largest) $src = $largest;
    }

    return [
        'type'  => 'image',
        'id'    => $id,
        'props' => array_filter([
            'src'    => $src,
            'alt'    => $img->getAttribute('alt')    ?: '',
            'width'  => $img->getAttribute('width')  ?: null,
            'height' => $img->getAttribute('height') ?: null,
            'class'  => $img->getAttribute('class')  ?: null,
        ]),
    ];
}

/** Returns only element-node children (skips text/comment nodes) */
function smdp_element_children(DOMElement $node): array
{
    $out = [];
    foreach ($node->childNodes as $child) {
        if ($child->nodeType === XML_ELEMENT_NODE) $out[] = $child;
    }
    return $out;
}

function smdp_inner_html(DOMElement $node): string
{
    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $node->ownerDocument->saveHTML($child);
    }
    return $html;
}

function smdp_outer_html(DOMElement $node): string
{
    return $node->ownerDocument->saveHTML($node);
}


// ─────────────────────────────────────────────────────────────
// Tree normaliser
// ─────────────────────────────────────────────────────────────

function smdp_normalize_elements(array $elements): array
{
    return array_values(array_filter(array_map('smdp_map_node', $elements)));
}

function smdp_map_node(array $el): ?array
{
    return match ($el['elType'] ?? '') {
        'section'             => smdp_map_section($el),
        'column', 'container' => smdp_map_container($el),
        'widget'              => smdp_map_widget($el),
        default               => null,
    };
}

function smdp_map_section(array $el): array
{
    $s = $el['settings'] ?? [];
    return [
        'type'     => 'section',
        'id'       => $el['id'],
        'layout'   => [
            'width' => $s['content_width'] ?? 'boxed',
            'gap'   => $s['gap']           ?? 'default',
        ],
        'style'    => smdp_extract_styles($s),
        'children' => smdp_normalize_elements($el['elements'] ?? []),
    ];
}

function smdp_map_container(array $el): array
{
    $s = $el['settings'] ?? [];
    return [
        'type'     => 'container',
        'id'       => $el['id'],
        'style'    => smdp_extract_styles($s),
        'children' => smdp_normalize_elements($el['elements'] ?? []),
    ];
}


// ─────────────────────────────────────────────────────────────
// Widget mapper
// ─────────────────────────────────────────────────────────────

function smdp_map_widget(array $el): array
{
    $s    = $el['settings'] ?? [];
    $type = $el['widgetType'] ?? 'unknown';
    $id   = $el['id'];

    return match ($type) {

        'heading', 'wd_title' => [
            'type'  => 'heading',
            'id'    => $id,
            'props' => [
                'text'  => $s['title']       ?? '',
                'tag'   => $s['header_size'] ?? 'h2',
                'align' => $s['align']       ?? 'left',
            ],
            'style' => smdp_extract_styles($s),
        ],

        'text-editor' => [
            'type'  => 'text',
            'id'    => $id,
            'props' => ['html' => $s['editor'] ?? ''],
            'style' => smdp_extract_styles($s),
        ],

        'image', 'image_or_svg' => [
            'type'  => 'image',
            'id'    => $id,
            'props' => [
                'src' => $s['image']['url'] ?? '',
                'alt' => $s['image']['alt'] ?? '',
            ],
            'style' => smdp_extract_styles($s),
        ],

        'button' => [
            'type'  => 'button',
            'id'    => $id,
            'props' => [
                'text'   => $s['text']               ?? '',
                'link'   => $s['link']['url']         ?? '',
                'target' => $s['link']['is_external'] ?? false,
            ],
            'style' => smdp_extract_styles($s),
        ],

        'divider' => [
            'type'  => 'divider',
            'id'    => $id,
            'style' => smdp_extract_styles($s),
        ],

        'spacer' => [
            'type'  => 'spacer',
            'id'    => $id,
            'props' => ['height' => $s['space']['size'] ?? 20],
        ],

        default => [
            'type'   => 'unknown',
            'id'     => $id,
            'widget' => $type,
            'raw'    => $s,
        ],
    };
}


// ─────────────────────────────────────────────────────────────
// Style extractor
// ─────────────────────────────────────────────────────────────

function smdp_extract_styles(array $s): array
{
    return array_filter([
        'padding' => $s['padding']          ?? null,
        'margin'  => $s['margin']           ?? null,
        'bg'      => $s['background_color'] ?? null,
        'color'   => $s['text_color']       ?? null,
        'align'   => $s['align']            ?? null,
    ]);
}


// ─────────────────────────────────────────────────────────────
// Flat widget list collector  (iterative — no by-ref recursion)
// ─────────────────────────────────────────────────────────────

function smdp_collect_widgets(array $elements): array
{
    $widgets = [];
    $stack   = $elements;

    while ($stack) {
        $el = array_shift($stack);

        if (($el['elType'] ?? '') === 'widget') {
            $widgets[] = ['id' => $el['id'], 'type' => $el['widgetType']];
        }

        if (!empty($el['elements'])) {
            array_unshift($stack, ...$el['elements']);
        }
    }

    return $widgets;
}


// ─────────────────────────────────────────────────────────────
// CSS helper
// ─────────────────────────────────────────────────────────────

function smdp_get_post_css(int $post_id): string
{
    if (!class_exists('\Elementor\Core\Files\CSS\Post')) return '';

    $css_file = \Elementor\Core\Files\CSS\Post::create($post_id);
    $css_file->update();

    return $css_file->get_content();
}

function smdp_clean_html($html) {
    // Remove tabs, newlines, extra spaces between tags
    $html = preg_replace('/\s+/', ' ', $html);          // collapse whitespace
    $html = preg_replace('/>\s+</', '><', $html);       // remove space between tags
    return trim($html);
}

function smdp_get_elementor_css_inline($post_id) {
    if (!class_exists('\Elementor\Plugin')) return '';

    // Ensure CSS file is generated
    \Elementor\Plugin::instance()->files_manager->clear_cache();

    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['basedir'] . "/elementor/css/post-{$post_id}.css";

    if (!file_exists($file_path)) {
        return ''; // fail silently
    }

    return file_get_contents($file_path);
}
function smdp_simplify_elementor_css($css) {
    if (empty($css)) return $css;

    // 1. Convert CSS vars like --display:flex; → display:flex;
    $css = preg_replace('/--([a-zA-Z0-9\-]+)\s*:\s*([^;]+);/', '$1:$2;', $css);

    // 2. Remove var(...) usages (dangerous but controlled)
    $css = preg_replace('/var\([^)]+\)/', 'initial', $css);

    return $css;
}