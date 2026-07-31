<?php
/**
 * Plugin Name: IC Pinout Elementor Widget (PoP Style)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Register Widget ───────────────────────────── */
add_action('elementor/widgets/register', function($widgets_manager){
	$widgets_manager->register( new IC_Pinout_Widget_PoP() );
});

/* ── Minimal Wrapper Class (Required by Elementor) ───────── */
class IC_Pinout_Widget_PoP extends \Elementor\Widget_Base {

	public function get_name(){ return 'ic_pinout'; }
	public function get_title(){ return 'IC Pinout Diagram'; }
	public function get_icon(){ return 'eicon-code'; }
	public function get_categories(){ return ['general']; }

	protected function register_controls(){
		icpw_register_controls($this);
	}

	protected function render(){
		icpw_render($this->get_settings_for_display());
	}
}

/* =========================================================
   🔧 PROCEDURAL CORE (REAL LOGIC STARTS HERE)
========================================================= */

/* ── Packages ───────────────────────────── */
function icpw_get_packages(){
	return [
		
		'SOT-23-6' => [ 'pins'=>6, 'pad_count'=>[3,3], 'w'=>1.4, 'h'=>3, 'pad_pitch'=>[1], 'pad_w'=>0.5, 'pad_h'=>1, 'pad-offset'=>0, 'defaults'=>['GND','SW','VIN','FB','EN','BOOT'] ],
		'SOP-8'    => [ 'pins'=>8, 'w'=>6.4, 'h'=>10, 'pad_count'=>[4,4], 'pad_w'=>0.5, 'pad_h'=>1,'pad_pitch'=>[1.27],  'pad-offset'=>0,  'defaults'=>['VIN','GND','SW','FB','EN','COMP','SS','BOOT'] ],

		// Future ready
		'QFN-64_8'   => [ 'pins'=>64,  'pad_count'=>[16,16,16,16], 'w'=>8.0, 'h'=>8, 'pad_w'=>0.5, 'pad_h'=>0.5,'pad_pitch'=>[0.4], 'pad-offset'=>0.25,  'defaults'=>array_fill(0,64,'IO') ],
	];
}

/* ── Controls ───────────────────────────── */
function icpw_register_controls($w){

	/* Component */
	$w->start_controls_section('section_component', [
		'label' => 'Component'
	]);

	$w->add_control('part_number', [
		'label' => 'Part Number',
		'type'  => \Elementor\Controls_Manager::TEXT,
		'default' => 'TPS54202',
	]);

	$w->add_control('package_type', [
		'label' => 'Package',
		'type'  => \Elementor\Controls_Manager::SELECT,
		'default' => 'SOT-23-6',
		'options' => array_combine(
			array_keys(icpw_get_packages()),
			array_keys(icpw_get_packages())
		),
	]);

	$w->end_controls_section();

	/* Pins */
	$w->start_controls_section('section_pins', [
		'label' => 'Pins'
	]);

	$rep = new \Elementor\Repeater();

	$rep->add_control('pin_name', [
		'label' => 'Pin Name',
		'type'  => \Elementor\Controls_Manager::TEXT,
	]);

	$rep->add_control('pin_desc', [
		'label' => 'Description',
		'type'  => \Elementor\Controls_Manager::TEXTAREA,
	]);

	$w->add_control('pins', [
		'label' => 'Pin List',
		'type'  => \Elementor\Controls_Manager::REPEATER,
		'fields'=> $rep->get_controls(),
		'title_field' => '{{{ pin_name }}}',
	]);

	$w->end_controls_section();
}

/* ── Resolve Pins ───────────────────────── */
function icpw_resolve_pins($settings){

	$packages = icpw_get_packages();
	$pkg = $packages[ $settings['package_type'] ];

	$count = $pkg['pins'];
	$defaults = $pkg['defaults'];
	$user = $settings['pins'] ?? [];

	$pins = [];

	for($i=0; $i<$count; $i++){

		$u = $user[$i] ?? [];

		$pins[] = [
			'name' => $u['pin_name'] ?? $defaults[$i] ?? 'NC',
			'desc' => $u['pin_desc'] ?? '',
		];
	}

	return $pins;
}

function icpw_safe_key( $pkg_key ) {
	return strtolower( preg_replace( '/[^a-zA-Z0-9]/', '_', $pkg_key ) );
}
/* ── Render ───────────────────────────── */
function icpw_render($s){

	$pins = icpw_resolve_pins($s);

	
    $part_number = esc_html( $s['part_number'] ?? 'IC Component' );
    $packages = icpw_get_packages();
	$pkg_key   = $s['package_type'] ?? 'SOT-23-6';
    $pkg      = $packages[ $pkg_key ];
	$ctrl_name = 'pin_list_' . icpw_safe_key( $pkg_key );
    $pin_count = $pkg['pins'];  
    $pads     = $pkg['pad_count'];  
    $defaults  = $pkg['defaults'];
	$rows      = $s[ $ctrl_name ] ?? [];
    $uid = 'icpw_' . uniqid(); // Unique widget ID for JS scoping
    $show_tooltip = true; // Can be made a setting later
    // Geometry calculations (simplified)
    $ic_w     = $pkg['w'] * 10; // Scale up for better visuals
    $ic_h     = $pkg['h'] * 10;
    $pin_w    = $pkg['pad_w'] * 10;
	$pin_h    = $pkg['pad_h'] * 10;
    $margin_x = 20;
    $margin_y = 20;
    $total_h = $ic_h + $margin_y * 2 + 20;
    $total_w = $ic_w + $margin_x * 2 + 20;

    $bx = $margin_x + 10;
    $by = $margin_y + 10;
    $left_pins = $pkg['pad_count'][0] ?? floor($pin_count/2);
    $right_pins = $pkg['pad_count'][1] ?? ceil($pin_count/2);
    $top_pins = $pkg['pad_count'][2] ?? 0;
    $bottom_pins = $pkg['pad_count'][3] ?? 0;
    $body_pad = 0;

    ob_start();
    ?>
    <style>
        /* IC Pinout Widget */
        .icpw-outer  { display: block; }
        .icpw-wrap   { display: inline-block; position: relative; max-width: 300px; width: 100%; }
        .icpw-svg    { display: block; overflow: visible; }

        .icpw-body {
            fill: #f9f9f9;
            stroke: #111;
            stroke-width: 1.5;
        }
        .icpw-pin {
            fill: #ffffff;
            stroke: #444;
            stroke-width: 0.8;
            transition: fill .15s;
        }
        .icpw-pin-group:hover .icpw-pin { fill: #fff8e1; stroke: #b85c00; }
        .icpw-pin-group                 { cursor: default; }
        .icpw-pin-group[data-tip]       { cursor: help; }

        .icpw-label {
            font: bold 11px Arial, sans-serif;
            fill: #222;
        }
        .icpw-num {
            font: bold 9px Arial, sans-serif;
            fill: #666;
        }
        .icpw-part-label {
            font: bold 13px Arial, sans-serif;
            fill: #d32f2f;
        }

        /* Tooltip */
        .icpw-tooltip {
            display: none;
            position: absolute;
            background: #1a1a1a;
            color: #fff;
            font: 12px Arial, sans-serif;
            padding: 5px 10px;
            border-radius: 4px;
            pointer-events: none;
            white-space: nowrap;
            z-index: 99;
            max-width: 200px;
            white-space: normal;
        }
        .icpw-tooltip.visible { display: block; }

        /* Datasheet link */
        .icpw-ds-link {
            text-align: center;
            margin-top: 6px;
            font: 12px Arial, sans-serif;
        }
        .icpw-ds-link a {
            color: #1565c0;
            text-decoration: none;
        }
        .icpw-ds-link a:hover { text-decoration: underline; }
    </style>
        <div class="icpw-outer">
			<div class="icpw-wrap" id="<?php echo esc_attr( $uid ); ?>">
                <?php if ( $show_tooltip ) : ?>
				<div class="icpw-tooltip" id="<?php echo esc_attr( $uid ); ?>_tip"></div>
				<?php endif; ?>
                <svg
					xmlns="http://www.w3.org/2000/svg"
					viewBox="0 0 <?php echo $total_w; ?> <?php echo $total_h; ?>"
					width="100%"
					role="img"
					aria-label="<?php echo esc_attr( $part_number . ' ' . $pkg_key . ' pinout' ); ?>"
					class="icpw-svg"
				>
                <rect
						x="<?php echo $bx; ?>"
						y="<?php echo $by; ?>"
						width="<?php echo $ic_w; ?>"
						height="<?php echo $ic_h; ?>"
						rx="0" ry="0"
						class="icpw-body"
					/>
                    <!-- Pin-1 dot -->
					<circle
						cx="<?php echo $bx + 14; ?>"
						cy="<?php echo $by + 14; ?>"
						r="4"
						fill="none"
						stroke="#333"
						stroke-width="1.2"
					/>
                    <?php

                    foreach($pads as $side => $count){
                        $side_name = ['Left','Right','Top','Bottom'][$side] ?? 'Side';
                        $is_vertical = in_array($side, ['0', '1'], true);
                       
                        switch ($side) {
                            case 0: // Top
                                $px = $bx + $body_pad + $i * ($pin_w + $pin_gap);
                                $py = $by - $pin_h;
                                $pad_w = $pin_h;
                                $pad_h = $pin_w;
                                break;
                            case 1: // Right
                                $pad_h = $pin_h;
                                $pad_w = $pin_w;
                                $px = $bx + $ic_w;
                                $py = $by + $body_pad + $i * ($pin_h + $pin_gap);
                                break;
                            case 2: // Bottom
                                    $pad_h = $pin_w;
                                    $pad_w = $pin_h;
                                $px = $bx + $body_pad + $i * ($pin_w + $pin_gap);
                                $py = $by + $ic_h;
                                break;
                            default :// Left
                                $pad_h = $pin_h;
                                $pad_w = $pin_w;
                                $px = $bx - $pin_w;
                                $py = $by + $body_pad + $i * ($pin_h + $pin_gap);
                        }
                        echo '<g class="icpw-pin-group">';
                            for ( $i = 0; $i < $count; $i++ ){                          

                                    $pad_gap = $pkg['pad_pitch'][ $side ] ?? 0;
                                    $pin_gap = $pad_gap * 10;

                                    if ($side === '0') {

                                        $pad_x = $bx - $pin_w;
                                        $pad_y = $by + $body_pad + $i * ($pin_h + $pin_gap);

                                    } elseif ($side === '1') {

                                        $pad_x = $bx + $ic_w;
                                        $pad_y = $by + $body_pad + $i * ($pin_h + $pin_gap);

                                    } elseif ($side === '2') {

                                        $pad_x = $bx + $body_pad + $i * ($pin_w + $pin_gap);
                                        $pad_y = $by - $pin_h;

                                    } elseif ($side === '3') {

                                        $pad_x = $bx + $body_pad + $i * ($pin_w + $pin_gap);
                                        $pad_y = $by + $body_h;

                                    }




                                echo '<rect x="' . $pad_x . '" y="' . $pad_y . '" width="' . $pad_w . '" height="' . $pad_h . '" rx="2" class="icpw-pin"/>';
                                // echo '<text x="' . ($pad_x - 4) . '" y="' . ($pad_y + 13) . '" text-anchor="end" class="icpw-label">' . $side_name . '</text>';
                                
                                
                            }
                        echo '<text x="' . ($bx + 4) . '" y="' . ($by + $ic_h + 20) . '" class="icpw-part-label">' . $part_number . ' (' . $pkg_key . ')</text>';
                        echo '</g>';
                        
                    }  ?> 

                </svg>
            </div>
        </div>
    <?php
    echo ob_get_clean();

    echo '<pre>';
    print_r($ic_w);
    print_r($ic_h);
    echo '</pre>';
	
	// $pins     = icpw_pins_from_repeater( $rows );
	// $geom     = icpw_geometry( count( $pins ) );
	// $show_tip = ( $s['show_tooltip'] ?? 'yes' ) === 'yes';
	// $part     = esc_html( $s['part_number'] ?? $pkg_key );
	// $ds_url   = $s['datasheet_url']['url'] ?? '';
	// $uid      = 'icpw_' . uniqid();

	// echo icpw_html_wrap_open( $uid, $show_tip );
	// echo icpw_svg( $pins, $geom, $part, $show_tip, $uid );
	// echo icpw_html_wrap_close();
	// echo icpw_datasheet_link( $ds_url );
}


/* ── Auto-fill (Editor UX) ───────────────────────── */
add_action('elementor/preview/enqueue_scripts', function(){

	wp_add_inline_script('jquery', "
		elementor.hooks.addAction('panel/open_editor/widget', function(panel, model){

			if (model.get('widgetType') !== 'ic_pinout') return;

			model.on('change:package_type', function(){

				const map = {
					'SOT-23': ['IN','GND','OUT'],
					'SOT-23-5': ['IN','GND','OUT','EN','NC'],
					'SOT-23-6': ['GND','SW','VIN','FB','EN','BOOT'],
					'SOP-8': ['VIN','GND','SW','FB','EN','COMP','SS','BOOT']
				};

				let pins = (map[model.get('package_type')] || []).map(n => ({
					pin_name: n,
					pin_desc: ''
				}));

				model.set('pins', pins);
			});
		});
	");
});