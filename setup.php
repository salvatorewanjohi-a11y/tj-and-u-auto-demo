<?php
/**
 * Builds the TJ & U Auto Garage (Rabai Road, Buruburu) booking store inside WordPress Playground.
 * Run by the blueprint after WooCommerce and the theme are installed.
 * Service photos are read from /wordpress/tj-images.
 */
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// The photos are already web-sized, so each one is copied straight into uploads and registered with its
// size. (media_handle_sideload opens every image, which made the demo slow to build.)
function tj_sideload( $tmp, $name, $parent, $title ) {
	$up   = wp_upload_dir();
	$file = wp_unique_filename( $up['path'], $name );
	$dest = trailingslashit( $up['path'] ) . $file;
	if ( ! @rename( $tmp, $dest ) && ! copy( $tmp, $dest ) ) {
		return new WP_Error( 'tj_copy', 'Could not copy ' . $name );
	}
	$type = wp_check_filetype( $file );
	$size = @getimagesize( $dest ) ?: [ 0, 0 ];
	$id   = wp_insert_attachment( [ 'post_mime_type' => $type['type'], 'post_title' => $title, 'post_status' => 'inherit', 'guid' => trailingslashit( $up['url'] ) . $file ], $dest, $parent, true, false );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	wp_update_attachment_metadata( $id, [ 'width' => $size[0], 'height' => $size[1], 'file' => _wp_relative_upload_path( $dest ), 'sizes' => [], 'image_meta' => [] ] );
	return $id;
}

// One transaction for the whole import: SQLite otherwise commits (and syncs to disk) after every query.
wp_defer_term_counting( true );
wp_suspend_cache_invalidation( true );
$wpdb->query( 'START TRANSACTION' );

// Fast demo build: the photos are already web-sized, so skip making thumbnails of each one.
if ( defined( 'TJ_FAST' ) && TJ_FAST ) {
	add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );
	add_filter( 'big_image_size_threshold', '__return_false' );
	add_filter( 'woocommerce_background_image_regeneration', '__return_false' );
	add_filter( 'woocommerce_resize_images', '__return_false' );
}

// Store settings.
foreach ( [
	'blogname'                               => 'TJ & U Auto Garage',
	'blogdescription'                        => 'It\'s all about you. Mechanical repairs, colour craft and body repair, ceramic coating and insurance claim repairs on Rabai Road, Buruburu, Nairobi.',
	'timezone_string'                        => 'Africa/Nairobi',
	'woocommerce_currency'                   => 'KES',
	'woocommerce_currency_pos'               => 'left_space',
	'woocommerce_price_num_decimals'         => '0',
	'woocommerce_price_thousand_sep'         => ',',
	'woocommerce_default_country'            => 'KE:KE30',
	'woocommerce_store_address'              => 'Rabai Road, Uhuru Estate',
	'woocommerce_store_city'                 => 'Nairobi',
	'woocommerce_manage_stock'               => 'no',
	'woocommerce_allowed_countries'          => 'specific',
	'woocommerce_specific_allowed_countries' => [ 'KE' ],
	'woocommerce_ship_to_countries'          => 'disabled',
	'woocommerce_enable_reviews'             => 'yes',
	'woocommerce_review_rating_verification_label' => 'yes',
	'woocommerce_onboarding_profile'         => [ 'skipped' => true ],
	'woocommerce_task_list_hidden'           => 'yes',
	'woocommerce_coming_soon'                => 'no',
	'woocommerce_checkout_phone_field'       => 'required',
	'woocommerce_checkout_company_field'     => 'hidden',
	'woocommerce_checkout_address_2_field'   => 'hidden',
	'woocommerce_enable_coupons'             => 'no',
	'woocommerce_calc_discounts_sequentially' => 'no',
] as $k => $v ) {
	update_option( $k, $v );
}

// Remove sample content.
foreach ( get_posts( [ 'post_type' => [ 'post', 'page' ], 'name' => 'hello-world', 'numberposts' => 1 ] ) as $p ) wp_delete_post( $p->ID, true );
$sample = get_page_by_path( 'sample-page' );
if ( $sample ) wp_delete_post( $sample->ID, true );

// Service lines (order = order on the home page, in the menu and in the filters). They follow the garage's own
// Instagram highlights (Mechanical, Color craft, Ceramic coating) and the work in their posts.
$cat = [];
$i   = 0;
foreach ( [
	'mechanical'       => [ 'Mechanical Repairs & Servicing', 'Minor and major services, computer diagnostics, brakes, suspension and steering, and airbag (SRS) and module programming.' ],
	'color-craft'      => [ 'Color Craft & Body Repair', 'Accident and collision repair, nose cuts and frame straightening, panel beating, colour-matched repaints, full resprays and rust repair, in our own paint booth.' ],
	'ceramic-coating'  => [ 'Ceramic Coating & Paint Care', '10H ceramic coating, full body buffing and paint correction, and headlight restoration.' ],
	'insurance-claims' => [ 'Insurance Claim Repairs', 'Accident repairs for insured cars: we assess the damage, prepare the estimate for your insurer and repair the car to their approval.' ],
	'interior'         => [ 'Interior, Upholstery & Sound Proofing', 'Seat upholstery, dashboard and steering restoration, and sound proofing.' ],
	'custom-mods'      => [ 'Body Kits, Rims & Custom Mods', 'Prado 150 skirting with daytime lights, and rim refurbishment and styling.' ],
] as $slug => [ $name, $desc ] ) {
	$t = term_exists( $slug, 'product_cat' ) ?: wp_insert_term( $name, 'product_cat', [ 'slug' => $slug, 'description' => $desc ] );
	$cat[ $slug ] = (int) $t['term_id'];
	update_term_meta( $cat[ $slug ], 'order', $i++ );
}

// Vehicle type: a global attribute with its own pages (/vehicle/suv/). The brief's five types.
if ( ! wc_attribute_taxonomy_id_by_name( 'vehicle' ) ) {
	wc_create_attribute( [ 'name' => 'Vehicle type', 'slug' => 'vehicle', 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => true ] );
}
if ( ! taxonomy_exists( 'pa_vehicle' ) ) {
	register_taxonomy( 'pa_vehicle', [ 'product' ], [ 'hierarchical' => false, 'show_ui' => false, 'query_var' => true, 'rewrite' => false ] );
}
$sizes = [
	'hatchback' => [ 'Hatchback', 'Small cars: Vitz, Fit, Note, Demio, Ractis, Golf.' ],
	'sedan'     => [ 'Sedan / saloon', 'Saloons: Premio, Axio, Axela, Mercedes C- and E-Class, Audi A4.' ],
	'suv'       => [ 'SUV / crossover', 'Crossovers and SUVs: CX-5, Harrier, X-Trail, Vezel, Forester, Mercedes GLA and GLC.' ],
	'4x4'       => [ '4x4 / pickup', 'Big 4x4s and pickups: Prado, Land Cruiser V8, Lexus LX, Range Rover, Hilux, D-Max, Ranger.' ],
	'van'       => [ 'Commercial van', 'Vans and light commercials: Hiace, Caravan, Noah, Voxy, ToyoAce.' ],
];
$size = [];
foreach ( $sizes as $slug => [ $name, $desc ] ) {
	$t = term_exists( $slug, 'pa_vehicle' ) ?: wp_insert_term( $name, 'pa_vehicle', [ 'slug' => $slug, 'description' => $desc ] );
	$size[ $slug ] = (int) $t['term_id'];
	update_term_meta( $size[ $slug ], 'order', count( $size ) );
}
delete_transient( 'wc_attribute_taxonomies' );

// Payment: nothing is paid online. The M-Pesa till isn't public, so it is sent on confirmation (PLACEHOLDER).
update_option( 'woocommerce_cod_settings', [
	'enabled'            => 'yes',
	'title'              => 'Pay at the garage when you collect (M-Pesa or cash)',
	'description'        => 'Check the work when your car is ready, then pay at the garage on Rabai Road by M-Pesa or cash.',
	'instructions'       => 'We will call to confirm your booking. Pay by M-Pesa or cash when you collect your car.',
	'enable_for_methods' => [],
	'enable_for_virtual' => 'yes',
] );
update_option( 'woocommerce_bacs_settings', [
	'enabled'         => 'yes',
	'title'           => 'M-Pesa deposit to hold my slot',
	'description'     => 'For body work, resprays, ceramic coating and upholstery. We send our M-Pesa details when we call to confirm; the deposit comes off your bill.',
	'instructions'    => 'We will call to confirm your booking and send our M-Pesa details for the deposit. The balance is paid when you collect your car.',
	'account_details' => '',
] );
update_option( 'woocommerce_bacs_accounts', [] );
update_option( 'woocommerce_cheque_settings', [ 'enabled' => 'no' ] );
update_option( 'woocommerce_gateway_order', [ 'cod' => 0, 'bacs' => 1 ] );

// Services. Every price is a PLACEHOLDER (Nairobi market estimate) to confirm with TJ & U: they publish none.
// The work itself is theirs: each service is something their Instagram posts or highlights show them doing.
// 'by_size' => [ hatchback, sedan, SUV, 4x4, van ] prices; 'attr2' + 'options2' => [ label => multiplier ]
// adds a second choice. 'options' => [ label => price ] is a choice that doesn't depend on the vehicle.
// 'quote' => true: an indicative price, confirmed after the garage inspects the car.
$garage = 'Our garage on Rabai Road, Buruburu';
$products = [
	[
		'slug' => 'minor-service', 'name' => 'Minor Service', 'cats' => [ 'mechanical' ],
		'badge' => 'Most booked', 'perk' => 'Oil & filter + checks', 'perks' => 'Grade-matched oil | New oil filter | Brakes & fluids checked', 'time' => '2–3 hours',
		'clip' => 'service',
		'short' => 'Old oil drained, fresh grade-matched oil and a new oil filter, then the brakes, tyres, fluids, lights and battery checked, so small problems are caught before they get expensive.',
		'desc' => 'The routine service your car needs every 5,000 km or so. We drain every drop of the old oil, fit a new filter and fill with clean oil of the right grade for your engine. While it\'s on the lift we check the brake pads and discs, tyres, fluid levels, belts, lights and battery, and tell you what we found before we touch anything else. No guesswork, no shortcuts.',
		'by_size' => [ 5500, 6500, 8000, 10500, 9000 ],
		'included' => [ 'Engine oil drained and refilled with the right grade', 'New oil filter', 'Brake pads and discs inspected', 'Tyre pressure and condition checked', 'Coolant, brake, power-steering and washer fluids topped up', 'Lights, belts and battery checked', 'Report of anything else that needs attention' ],
		'specs' => [ 'Time' => 'About 2–3 hours; wait in our lounge', 'Where' => $garage, 'Oil' => 'Standard oil included; fully synthetic costs extra', 'Payment' => 'When you collect, M-Pesa or cash' ],
		'upsells' => [ 'computer-diagnostics', 'brake-service', 'headlight-restoration' ],
	],
	[
		'slug' => 'major-service', 'name' => 'Major Service', 'cats' => [ 'mechanical' ],
		'badge' => 'Full service', 'perk' => 'Filters, plugs & fluids', 'perks' => 'Oil, air & fuel filters | Spark plugs | Full inspection', 'time' => '1 day',
		'clip' => 'service',
		'short' => 'Everything in a minor service plus new air and fuel filters, spark plugs, a brake fluid change and a full under-car inspection of suspension, steering and exhaust.',
		'desc' => 'The big service your logbook asks for every 20,000 km or so. On top of the oil and filter change, we replace the air and fuel filters and the spark plugs, change the brake fluid, and inspect the suspension, steering, exhaust and drive belts with the car on the lift. We use genuine parts, show you the old ones, and call before doing any extra work.',
		'by_size' => [ 12000, 14000, 17000, 22000, 19000 ],
		'included' => [ 'Everything in the minor service', 'New air filter and fuel filter', 'New spark plugs (petrol engines)', 'Brake fluid changed', 'Suspension, steering and exhaust inspected on the lift', 'Drive belts and hoses checked', 'Computer scan for stored fault codes' ],
		'specs' => [ 'Time' => 'About a day; drop off in the morning', 'Where' => $garage, 'Parts' => 'Genuine parts; old parts shown to you', 'Payment' => 'When you collect, M-Pesa or cash' ],
		'upsells' => [ 'brake-service', 'suspension-overhaul', 'body-buffing' ],
	],
	[
		'slug' => 'computer-diagnostics', 'name' => 'Computer Diagnostic Check', 'cats' => [ 'mechanical' ],
		'badge' => 'Diagnostics', 'perk' => 'Fault codes read', 'perks' => 'Engine, gearbox & ABS scan | Warning lights explained | Written findings', 'time' => '30–60 min',
		'short' => 'Check-engine, ABS or airbag light on? We plug in, read the fault codes from every module, test the likely causes and explain in plain words what\'s wrong and what it costs to fix.',
		'desc' => 'A warning light is the car telling you something. We scan the engine, gearbox, ABS and airbag modules, read live data, and then test the likely causes rather than guessing from a code. You get the findings and a price for the repair before any work starts, and the scan fee is the only charge if you decide not to go ahead.',
		'price' => 2500,
		'included' => [ 'Full scan of engine, gearbox, ABS and SRS modules', 'Live data checked with the engine running', 'Likely causes tested', 'Findings and a repair price explained to you' ],
		'specs' => [ 'Time' => 'About 30–60 minutes', 'Where' => $garage, 'Best with' => 'Any service or repair', 'Payment' => 'When you collect, M-Pesa or cash' ],
		'upsells' => [ 'minor-service', 'srs-module-programming' ],
	],
	[
		'slug' => 'brake-service', 'name' => 'Brake Service: Pads & Discs', 'cats' => [ 'mechanical' ],
		'badge' => 'Brakes', 'perk' => 'Stop with confidence', 'perks' => 'Wheels off & inspected | Pads or discs fitted | Test-driven', 'time' => '1–3 hours',
		'short' => 'We pull the wheels, measure the pads and discs, and fit fresh pads (and discs where they\'re worn), then bleed the brakes and road-test the car. Price is labour; parts are genuine and billed at cost.',
		'desc' => 'Stopping is just as important as going. We take the wheels off, measure pad thickness and disc wear, clean and lubricate the sliders, fit new pads (and machine or replace the discs if needed), then bleed the system and road-test the car. Parts are quoted before fitting and billed at cost.',
		'by_size' => [ 3000, 3500, 4000, 5000, 4500 ], 'attr2' => 'Axle', 'options2' => [ 'One axle' => 1, 'Front and rear' => 1.8 ],
		'included' => [ 'Wheels removed and brakes inspected', 'Pads (and discs if worn) fitted', 'Sliders cleaned and lubricated', 'Brake fluid level checked and system bled', 'Road test' ],
		'specs' => [ 'Time' => 'About 1–3 hours', 'Price' => 'Labour; genuine pads and discs billed at cost', 'Where' => $garage, 'Payment' => 'When you collect, M-Pesa or cash' ],
		'upsells' => [ 'minor-service', 'suspension-overhaul' ],
	],
	[
		'slug' => 'suspension-overhaul', 'name' => 'Suspension & Steering Overhaul', 'cats' => [ 'mechanical' ],
		'badge' => 'Suspension', 'perk' => 'Smooth & quiet ride', 'perks' => 'Shocks & bushes | Ball joints & links | Wheel alignment advice', 'time' => '1–2 days', 'quote' => true,
		'short' => 'Knocks over bumps, a car that pulls or wanders, or uneven tyre wear: we find the worn shocks, bushes, links and ball joints and replace them with genuine parts.',
		'desc' => 'Nairobi roads are hard on suspension. We inspect the whole front and rear end on the lift, show you what\'s worn and quote the parts before replacing them. The price here is the typical labour and parts for a front-end overhaul; we confirm it after inspection.',
		'by_size' => [ 15000, 18000, 22000, 30000, 25000 ],
		'included' => [ 'Suspension and steering inspected on the lift', 'Worn shocks, bushes, links and ball joints replaced', 'Steering rack ends checked', 'Road test', 'Alignment advice' ],
		'specs' => [ 'Time' => 'About 1–2 days', 'Parts' => 'Genuine parts, quoted before fitting', 'Price' => 'Indicative: confirmed after inspection', 'Payment' => 'Deposit for parts, balance on collection' ],
		'upsells' => [ 'brake-service', 'computer-diagnostics' ],
	],
	[
		'slug' => 'srs-module-programming', 'name' => 'Airbag (SRS) & Module Programming', 'cats' => [ 'mechanical', 'insurance-claims' ],
		'badge' => 'Electronics', 'perk' => 'Back to factory spec', 'perks' => 'SRS sensors programmed | Airbag light cleared | Module coding', 'time' => '1 day', 'quote' => true,
		'short' => 'After an accident, the airbags, sensors and control modules have to be replaced and programmed before the car is safe. We do it in-house, like the Volvo S90 and Mercedes GLA in our posts.',
		'desc' => 'Blown airbags and damaged modules are why many cars get written off. We replace the airbags and crash sensors, program the SRS unit, and code replaced modules (lights, body, engine) to the car so every warning light goes out and the systems work as designed. The price here is for the programming; airbags and modules are quoted separately.',
		'options' => [ 'SRS sensor programming & airbag light reset' => 8000, 'Module coding after a repair' => 10000 ], 'attr' => 'Job',
		'included' => [ 'Fault codes read from every module', 'Airbags and crash sensors fitted (parts quoted)', 'SRS unit programmed and reset', 'Replaced modules coded to the car', 'Final scan: no warning lights' ],
		'specs' => [ 'Time' => 'About a day', 'Parts' => 'Airbags and modules quoted separately', 'Price' => 'Indicative: confirmed after inspection', 'Insurance' => 'Included in insurance repair estimates' ],
		'upsells' => [ 'collision-repair', 'computer-diagnostics' ],
	],
	[
		'slug' => 'collision-repair', 'name' => 'Accident & Collision Repair', 'cats' => [ 'color-craft', 'insurance-claims' ],
		'badge' => 'Body shop', 'perk' => 'Write-offs rebuilt', 'perks' => 'Nose cuts & panels | Frame straightening | Painted back to OEM', 'time' => '3 days–3 weeks', 'quote' => true,
		'clip' => 'rebuild',
		'short' => 'From a crumpled bumper to a car someone else called a write-off: nose cuts, panels, frame straightening, glass and tailgates, all painted back to the factory colour in our own booth.',
		'desc' => 'Rebuilding crashed cars is what our Instagram is full of: a Range Rover Sport after a tree, a GLE 350d rear-ended, a Peugeot 5008 with its dashboard transplanted, a Mazda Axela pulled back straight on our frame equipment. We strip the damage, fit the nose cut or panels dry to check the fit, straighten the frame where needed, then paint and refit everything so the colour matches. The price here is a starting point by how bad the damage is; we confirm it after inspection, and insured cars are repaired to the assessor\'s report.',
		'options' => [ 'Minor: 1–2 panels' => 25000, 'Moderate: front or rear end' => 70000, 'Major: nose cut or frame pull' => 180000 ], 'attr' => 'Damage',
		'included' => [ 'Damage assessed and parts list prepared', 'Damaged panels, bumpers, nose cut or tailgate replaced', 'Frame straightened on professional equipment where needed', 'Dry fit to check alignment before paint', 'Colour-matched paint in our booth', 'Refit, polish and final inspection' ],
		'specs' => [ 'Time' => 'From about 3 days to 3 weeks', 'Parts' => 'Genuine or OEM-quality parts', 'Insurance' => 'We work to your assessor\'s report', 'Price' => 'Indicative: confirmed after inspection' ],
		'upsells' => [ 'srs-module-programming', 'ceramic-coating', 'body-buffing' ],
	],
	[
		'slug' => 'panel-repaint', 'name' => 'Panel Beating & Repaint', 'cats' => [ 'color-craft' ],
		'badge' => 'Color craft', 'perk' => 'Colour-matched', 'perks' => 'Dents pulled | Primed & sanded | Blended & polished', 'time' => '1–3 days', 'quote' => true,
		'clip' => 'paint',
		'short' => 'Dented doors, scraped bumpers and scratched wings beaten out, primed and repainted to the factory colour code, then blended into the next panel so you can\'t tell which one was done.',
		'desc' => 'Like the Golf R bumper we heated back into shape instead of replacing: we pull the dent, skim and sand, prime, lay the base coat and clear in the booth, then blend and polish. No matter how small the dent, the standard is the same. Prices here are per panel and indicative; we confirm after seeing the damage.',
		'options' => [ '1 panel' => 7000, '2 panels' => 13000, '3 panels' => 18000, 'Bumper (front or rear)' => 8000 ], 'attr' => 'Panels',
		'included' => [ 'Dent pulled or panel beaten', 'Filler, primer and sanding', 'Factory colour code matched', 'Base coat and clear coat in the booth', 'Blended into neighbouring panels and polished' ],
		'specs' => [ 'Time' => 'About 1–3 days', 'Where' => 'Our panel-beating bay and paint booth', 'Price' => 'Indicative: confirmed after inspection', 'Payment' => 'When you collect, M-Pesa or cash' ],
		'upsells' => [ 'body-buffing', 'ceramic-coating' ],
	],
	[
		'slug' => 'full-respray', 'name' => 'Full Body Respray', 'cats' => [ 'color-craft' ],
		'badge' => 'Color craft', 'perk' => 'Same colour or new', 'perks' => 'Stripped & prepped | Booth-sprayed | Cut & polished', 'time' => '1–2 weeks', 'quote' => true,
		'clip' => 'booth',
		'short' => 'A full respray in the original colour code or a new one: dents fixed, old filler blown out, primed, sprayed in our booth and polished. Like the C200, W210, Audi A4 and Prado 120 on our page.',
		'desc' => 'Faded, tired or a different colour in your head? We strip down the trims, fix every dent, remove cracked old filler, prime and block-sand, then spray base and clear coat in our booth and finish with a cut and polish. Chassis repainting can be added. The price here is indicative and depends on the car\'s condition; we confirm after inspection.',
		'by_size' => [ 55000, 65000, 80000, 110000, 95000 ], 'attr2' => 'Colour', 'options2' => [ 'Same colour (factory code)' => 1, 'Colour change' => 1.35 ],
		'included' => [ 'Inspection and paint plan', 'Trims, lights and handles removed', 'Dents repaired, old filler removed', 'Primer and block sanding', 'Base coat and clear coat in the booth', 'Cut, polish and refit' ],
		'specs' => [ 'Time' => 'About 1–2 weeks', 'Where' => 'Our paint booth on Rabai Road', 'Colour' => 'Factory colour code or a new colour', 'Price' => 'Indicative: confirmed after inspection' ],
		'upsells' => [ 'ceramic-coating', 'rim-styling', 'headlight-restoration' ],
	],
	[
		'slug' => 'rust-chassis-restoration', 'name' => 'Rust, Chassis & Roof Restoration', 'cats' => [ 'color-craft' ],
		'badge' => 'Restoration', 'perk' => 'Cut out, not covered up', 'perks' => 'Rot cut out | New metal welded | Sealed & painted', 'time' => '3–10 days', 'quote' => true,
		'short' => 'Corroded chassis, rotten roofs and rusty sills cut back to clean metal, new sections welded in, sealed and painted, like the Isuzu D-Max roof and Prado chassis in our posts.',
		'desc' => 'Rust only gets worse under filler. We mark the cut lines, grind out the rotten metal section by section, weld in new metal, seal the joints and paint back to factory standard. Chassis can be cleaned, treated and repainted. The price here is indicative; we confirm after inspection.',
		'options' => [ 'Chassis rust treatment & repaint' => 30000, 'Roof or sill section replaced' => 60000 ], 'attr' => 'Job',
		'included' => [ 'Rust and old repairs inspected', 'Rotten metal cut out', 'New sections welded in', 'Seam sealer and anti-corrosion primer', 'Painted to factory standard' ],
		'specs' => [ 'Time' => 'About 3–10 days', 'Where' => $garage, 'Price' => 'Indicative: confirmed after inspection', 'Payment' => 'Deposit to book, balance on collection' ],
		'upsells' => [ 'full-respray', 'sound-proofing' ],
	],
	[
		'slug' => 'ceramic-coating', 'name' => '10H Ceramic Coating', 'cats' => [ 'ceramic-coating' ],
		'badge' => 'Protection', 'perk' => 'Glass-like gloss', 'perks' => '10H ceramic | Hydrophobic | UV resistant', 'time' => '2 days',
		'clip' => 'ceramic',
		'short' => 'A 10H ceramic coating bonded to freshly polished paint: deeper gloss, water that beads straight off, UV resistance and easier washing.',
		'desc' => 'We wash and decontaminate the paint, buff it to remove swirls and dullness (the coating locks in whatever is underneath), wipe it down, then apply the 10H ceramic coating panel by panel and let it cure indoors. The result: deeper gloss, easier maintenance and long-lasting protection against sun, rain and everyday contaminants.',
		'by_size' => [ 25000, 28000, 32000, 40000, 38000 ],
		'included' => [ 'Hand wash and decontamination', 'One-stage machine buff', 'Panel wipe to remove polishing oils', '10H ceramic coating, panel by panel', 'Indoor curing', 'Aftercare guide' ],
		'specs' => [ 'Time' => 'About 2 days, including curing', 'Coating' => '10H ceramic', 'Aftercare' => 'No washing for 7 days; hand wash after that', 'Payment' => 'Deposit to book, balance on collection' ],
		'upsells' => [ 'headlight-restoration', 'rim-styling' ],
	],
	[
		'slug' => 'body-buffing', 'name' => 'Full Body Buffing & Paint Correction', 'cats' => [ 'ceramic-coating' ],
		'badge' => 'Paint care', 'perk' => 'Shine brought back', 'perks' => 'Swirls & oxidation out | Machine polished | Washed & dressed', 'time' => '4–6 hours',
		'short' => 'Dull, swirled or faded paint machine-buffed back to a deep shine, then the car is washed and the tyres dressed. Book it on its own, or before a ceramic coating.',
		'desc' => 'Most dull paint doesn\'t need a respray. We wash the car, then machine-buff every panel with compound and polish to take out swirls, light scratches and oxidation. Add a ceramic coating to keep the shine.',
		'by_size' => [ 7000, 8000, 10000, 13000, 12000 ],
		'included' => [ 'Hand wash', 'Machine compound and polish on every panel', 'Plastics and tyres dressed', 'Glass cleaned' ],
		'specs' => [ 'Time' => 'About 4–6 hours', 'Where' => $garage, 'Best with' => '10H ceramic coating', 'Payment' => 'When you collect, M-Pesa or cash' ],
		'upsells' => [ 'ceramic-coating', 'headlight-restoration' ],
	],
	[
		'slug' => 'headlight-restoration', 'name' => 'Headlight Restoration', 'cats' => [ 'ceramic-coating' ],
		'badge' => 'Add-on', 'perk' => 'Clear lenses', 'perks' => 'Yellowing removed | Brighter at night | UV sealed', 'time' => '+1–2 hours',
		'short' => 'Yellow, cloudy headlights wet-sanded and polished clear, then sealed against UV so they stay clear and light the road properly again.',
		'desc' => 'Sun-faded headlights cut how far you can see at night and age the whole car. We mask around the lights, wet-sand the oxidised layer, polish the lenses clear and seal them against UV.',
		'price' => 3500,
		'included' => [ 'Masking around the lights', 'Wet-sanding of the oxidised layer', 'Machine polish to clear', 'UV-protective sealant' ],
		'specs' => [ 'Added time' => 'About 1–2 hours', 'Covers' => 'Both headlights' ],
	],
	[
		'slug' => 'insurance-claim-repair', 'name' => 'Insurance Claim Assessment & Repair', 'cats' => [ 'insurance-claims' ],
		'badge' => 'Insurance', 'perk' => 'We handle the paperwork', 'perks' => 'Damage assessed | Estimate for your insurer | Repaired to approval', 'time' => 'Assessment in 1 visit',
		'short' => 'Had an accident and you\'re insured? Book an assessment: we inspect the damage, prepare the repair estimate and photos your insurer needs, liaise with the assessor and repair the car once it\'s approved.',
		'desc' => 'Insurers trust us with cars like the Range Rover Sport that met a tree. Bring the car (or ask us to tow it), your claim form and the police abstract if you have one. We assess the damage, send the estimate and photos to your insurer or their assessor, and start the repair as soon as it\'s approved. We keep you updated by phone through the claim. Booking the assessment costs nothing; the excess and anything your policy doesn\'t cover are agreed with you before work starts.',
		'price' => 0,
		'included' => [ 'Damage inspection and photos', 'Repair estimate prepared for your insurer', 'We liaise with the assessor', 'Repair to the approved report', 'Phone updates through the claim', 'Quality check before release' ],
		'specs' => [ 'Bring' => 'Claim form, logbook copy and police abstract', 'Towing' => 'We can arrange towing to the garage', 'Excess' => 'Agreed with you before work starts', 'Payment' => 'Your insurer pays the approved repair; you pay the excess' ],
		'upsells' => [ 'collision-repair', 'srs-module-programming' ],
	],
	[
		'slug' => 'upholstery', 'name' => 'Seat Upholstery & Interior Refurbishment', 'cats' => [ 'interior' ],
		'badge' => 'Interior', 'perk' => 'Your pattern, your material', 'perks' => 'Seats re-covered | Door cards & roof lining | Custom patterns', 'time' => '2–5 days', 'quote' => true,
		'clip' => 'interior',
		'short' => 'Torn, faded or just boring seats re-covered in leather or fabric, in a pattern you choose, along with door cards and roof lining. Like the Hilux Surf and Audi interiors on our page.',
		'desc' => 'Every journey deserves comfort. We take the seats out, strip the old covers, repair the foam and re-cover them in the material and pattern you pick, then refit them. Door cards and roof lining can be done to match. The price here is indicative and depends on the material; we confirm after you choose it.',
		'options' => [ 'Front seats' => 18000, 'Full set of seats' => 35000, 'Full interior: seats, door cards & roof lining' => 65000 ], 'attr' => 'Job',
		'included' => [ 'Seats removed and stripped', 'Foam repaired where needed', 'Re-covered in leather or fabric of your choice', 'Custom stitching and patterns', 'Refitted and cleaned' ],
		'specs' => [ 'Time' => 'About 2–5 days', 'Material' => 'Leather, leatherette or fabric', 'Price' => 'Indicative: depends on material', 'Payment' => 'Deposit for material, balance on collection' ],
		'upsells' => [ 'dashboard-restoration', 'sound-proofing' ],
	],
	[
		'slug' => 'dashboard-restoration', 'name' => 'Dashboard & Steering Restoration', 'cats' => [ 'interior' ],
		'badge' => 'Interior', 'perk' => 'Cracks gone', 'perks' => 'Dashboard removed & repaired | Steering re-covered | Fitted perfectly', 'time' => '2–3 days', 'quote' => true,
		'short' => 'Cracked or sun-faded dashboards removed, repaired and re-covered, and worn steering wheels restored, then fitted back perfectly. Like the Subaru Outback BR9 and Lancer jobs we posted.',
		'desc' => 'We remove the dashboard and steering wheel, repair the cracks and re-cover them, then fit everything back so there are no squeaks or gaps. The price here is indicative; we confirm after seeing the dashboard.',
		'options' => [ 'Dashboard' => 15000, 'Steering wheel' => 6000, 'Dashboard & steering wheel' => 19000 ], 'attr' => 'Job',
		'included' => [ 'Dashboard and steering wheel removed', 'Cracks repaired', 'Re-covered', 'Refitted and tested' ],
		'specs' => [ 'Time' => 'About 2–3 days', 'Where' => $garage, 'Price' => 'Indicative: confirmed after inspection' ],
		'upsells' => [ 'upholstery' ],
	],
	[
		'slug' => 'sound-proofing', 'name' => 'Sound Proofing', 'cats' => [ 'interior' ],
		'badge' => 'Comfort', 'perk' => 'Quieter cabin', 'perks' => 'Doors, floor & boot | Road noise cut | Better audio', 'time' => '1–2 days', 'quote' => true,
		'short' => 'Damping and insulation fitted to the doors, floor and boot to cut road and engine noise, so the cabin is quieter and the sound system sounds better.',
		'desc' => 'We strip the door cards, carpets and boot lining, fit damping sheets and insulation to the bare metal, and refit everything. Coverage and material decide the price; we confirm it when you book.',
		'by_size' => [ 18000, 22000, 26000, 32000, 30000 ],
		'included' => [ 'Door cards, carpets and boot lining removed', 'Damping sheets on doors, floor and boot', 'Insulation layer', 'Everything refitted' ],
		'specs' => [ 'Time' => 'About 1–2 days', 'Where' => $garage, 'Price' => 'Indicative: depends on coverage' ],
		'upsells' => [ 'upholstery' ],
	],
	[
		'slug' => 'prado-skirting', 'name' => 'Prado 150 Skirting with Daytime Lights', 'cats' => [ 'custom-mods' ],
		'badge' => 'Body kit', 'perk' => 'DRLs & indicators built in', 'perks' => 'Front & rear skirting | Daytime lights & indicators | Painted to match', 'time' => '1–2 days', 'quote' => true,
		'short' => 'The latest front and rear skirting for the Land Cruiser Prado 150, with built-in daytime running lights and indicators, fitted and painted to match your car.',
		'desc' => 'Transform the look of your Prado 150. We supply the skirting, paint it to your colour code, wire the daytime lights and indicators, and fit it. Ask about kits for other models.',
		'options' => [ 'Front skirting' => 28000, 'Front & rear skirting' => 48000 ], 'attr' => 'Kit',
		'included' => [ 'Skirting supplied', 'Painted to your colour code', 'Daytime lights and indicators wired in', 'Fitted and aligned' ],
		'specs' => [ 'Time' => 'About 1–2 days', 'Fits' => 'Toyota Land Cruiser Prado 150', 'Price' => 'Indicative: confirmed when you book' ],
		'upsells' => [ 'rim-styling', 'ceramic-coating' ],
	],
	[
		'slug' => 'rim-styling', 'name' => 'Rim Refurbishment & Styling', 'cats' => [ 'custom-mods' ],
		'badge' => 'Wheels', 'perk' => 'Kerb marks fixed', 'perks' => 'Kerb damage repaired | Any colour | Sealed', 'time' => '1–2 days',
		'short' => 'Kerbed, corroded or tired rims repaired and repainted: back to the original finish, or gloss black, gunmetal or any colour you like.',
		'desc' => 'Old is gold: style up your rims with us. We take the wheels off, strip and repair kerb damage, prime and paint them, then seal them so brake dust washes off.',
		'options' => [ 'Set of 4: original colour' => 12000, 'Set of 4: new colour' => 15000 ], 'attr' => 'Job',
		'included' => [ 'Wheels removed and tyres protected', 'Kerb damage repaired', 'Primer, paint and lacquer', 'Sealed and refitted' ],
		'specs' => [ 'Time' => 'About 1–2 days', 'Covers' => 'All four wheels', 'Where' => $garage ],
		'upsells' => [ 'ceramic-coating' ],
	],
];

function tj_attach_images( $pid, $slug, $name ) {
	$num   = fn( $f ) => preg_match( '#^' . preg_quote( $slug, '#' ) . '(?:-(\d+))?\.webp$#', basename( $f ), $m ) ? (int) ( $m[1] ?? 1 ) : 0;
	$files = array_values( array_filter( glob( "/wordpress/tj-images/$slug*.webp" ) ?: [], fn( $f ) => $num( $f ) > 0 ) );
	usort( $files, fn( $a, $b ) => $num( $a ) <=> $num( $b ) );
	$ids = [];
	foreach ( $files as $file ) {
		$base = basename( $file );
		$tmp  = wp_tempnam( $base );
		copy( $file, $tmp );
		$n   = $num( $file );
		$att = tj_sideload( $tmp, $base, $pid, $name . ( $n > 1 ? ' – photo ' . $n : '' ) );
		if ( ! is_wp_error( $att ) ) $ids[ $n ] = $att;
	}
	return $ids; // photo number => attachment id
}

function tj_local_attr( $name, array $options, $position ) {
	$a = new WC_Product_Attribute();
	$a->set_name( $name );
	$a->set_options( $options );
	$a->set_position( $position );
	$a->set_visible( true );
	$a->set_variation( true );
	return $a;
}

$ids = [];
foreach ( $products as $order => $d ) {
	$by_size  = $d['by_size'] ?? null;
	$variable = $by_size || ! empty( $d['options'] );
	$p = $variable ? new WC_Product_Variable() : new WC_Product_Simple();
	$p->set_name( $d['name'] );
	$p->set_slug( $d['slug'] );
	$p->set_status( 'publish' );
	$p->set_menu_order( $order );
	$p->set_virtual( true );
	$p->set_description( $d['desc'] );
	$p->set_short_description( $d['short'] );
	$p->set_category_ids( array_map( fn( $c ) => $cat[ $c ], $d['cats'] ) );
	// The home page's packages: the headline service of each line, not the add-ons.
	$p->set_featured( in_array( $d['slug'], [ 'minor-service', 'major-service', 'collision-repair', 'full-respray', 'ceramic-coating', 'insurance-claim-repair', 'upholstery', 'computer-diagnostics' ], true ) );
	foreach ( [ 'badge', 'perk', 'perks', 'time', 'clip' ] as $k ) {
		if ( ! empty( $d[ $k ] ) ) $p->update_meta_data( '_tj_' . $k, $d[ $k ] );
	}
	$p->update_meta_data( '_tj_specs', implode( "\n", array_map( fn( $k, $v ) => "$k: $v", array_keys( $d['specs'] ), $d['specs'] ) ) );
	$p->update_meta_data( '_tj_included', implode( "\n", $d['included'] ) );
	if ( ! empty( $d['quote'] ) ) $p->update_meta_data( '_tj_quote', 'yes' );

	$attrs = [];
	if ( $by_size ) {
		$a = new WC_Product_Attribute();
		$a->set_id( wc_attribute_taxonomy_id_by_name( 'vehicle' ) );
		$a->set_name( 'pa_vehicle' );
		$a->set_options( array_values( $size ) );
		$a->set_position( 0 );
		$a->set_visible( true );
		$a->set_variation( true );
		$attrs[] = $a;
		if ( ! empty( $d['options2'] ) ) $attrs[] = tj_local_attr( $d['attr2'], array_keys( $d['options2'] ), 1 );
	} elseif ( $variable ) {
		$attrs[] = tj_local_attr( $d['attr'], array_keys( $d['options'] ), 0 );
	}
	$p->set_attributes( $attrs );
	if ( $by_size ) {
		$defaults = [ 'pa_vehicle' => 'sedan' ];
		if ( ! empty( $d['options2'] ) ) $defaults[ sanitize_title( $d['attr2'] ) ] = array_key_first( $d['options2'] );
		$p->set_default_attributes( $defaults );
	} elseif ( $variable ) {
		$p->set_default_attributes( [ sanitize_title( $d['attr'] ) => array_key_first( $d['options'] ) ] );
	} else {
		$p->set_regular_price( $d['price'] );
	}
	$pid = $p->save();
	$ids[ $d['slug'] ] = $pid;

	$imgs = tj_attach_images( $pid, $d['slug'], $d['name'] );
	if ( $imgs ) {
		// Written as meta: a second full product save here slowed the import.
		set_post_thumbnail( $pid, $imgs[1] ?? reset( $imgs ) );
		update_post_meta( $pid, '_product_image_gallery', implode( ',', array_values( array_diff_key( $imgs, [ 1 => true ] ) ) ) );
	}

	if ( $variable ) {
		// One variation per combination: vehicle class × second choice, or the plain choices.
		$combos = [];
		if ( $by_size ) {
			foreach ( array_keys( $size ) as $n => $s ) {
				foreach ( $d['options2'] ?? [ null => 1 ] as $o2 => $mult ) {
					$va = [ 'pa_vehicle' => $s ];
					if ( $o2 !== '' && $o2 !== null ) $va[ sanitize_title( $d['attr2'] ) ] = $o2;
					$combos[] = [ $va, round( $by_size[ $n ] * $mult / 500 ) * 500 ];
				}
			}
		} else {
			foreach ( $d['options'] as $label => $price ) {
				$combos[] = [ [ sanitize_title( $d['attr'] ) => $label ], $price ];
			}
		}
		foreach ( $combos as $n => [ $va, $price ] ) {
			$v = new WC_Product_Variation();
			$v->set_parent_id( $pid );
			$v->set_attributes( $va );
			$v->set_virtual( true );
			$v->set_regular_price( (string) $price );
			$v->set_menu_order( $n );
			$v->save();
		}
		WC_Product_Variable::sync( $pid );
	}
}

// "Popular add-ons" on each service page.
foreach ( $products as $d ) {
	if ( ! empty( $d['upsells'] ) ) {
		update_post_meta( $ids[ $d['slug'] ], '_upsell_ids', array_values( array_filter( array_map( fn( $s ) => $ids[ $s ] ?? 0, $d['upsells'] ) ) ) );
	}
}

// Pages.
function tj_page( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug );
	if ( $existing ) return $existing->ID;
	return wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content ] );
}
tj_page( 'estimator', 'Instant estimate & quote builder', "<!-- wp:paragraph -->\n<p>Choose your vehicle type and the service, add photos of any damage, and see the usual price range and how long it takes. Book a slot in a few taps, or send it to us on WhatsApp for an exact quote.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[tj_estimator]\n<!-- /wp:shortcode -->" );
tj_page( 'insurance-claims', 'Insurance claim centre', "<!-- wp:shortcode -->\n[tj_claim_guide]\n<!-- /wp:shortcode -->\n\n<!-- wp:shortcode -->\n[tj_claim_form]\n<!-- /wp:shortcode -->" );
tj_page( 'fleet-commercial', 'Fleets, trucks & commercial vehicles', "<!-- wp:paragraph -->\n<p>School buses, vans, pickups, trucks and company cars: servicing, accident repair and repainting on a schedule that keeps your vehicles on the road. Tell us about your fleet and we reply on WhatsApp with a per-vehicle price.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[tj_fleet_form]\n<!-- /wp:shortcode -->" );

// Cart and checkout read as a booking.
foreach ( [ 'shop' => 'All services', 'cart' => 'Your booking', 'checkout' => 'Book your workshop slot' ] as $page => $title ) {
	$id = wc_get_page_id( $page );
	if ( $id > 0 ) wp_update_post( [ 'ID' => $id, 'post_title' => $title ] );
}

update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();

// Skip WooCommerce's first-run redirect and setup checklist so the admin opens on the store itself.
delete_transient( '_wc_activation_redirect' );
update_option( 'woocommerce_task_list_hidden_lists', [ 'setup', 'extended' ] );
update_option( 'woocommerce_task_list_complete', 'yes' );
update_option( 'woocommerce_show_marketplace_suggestions', 'no' );
update_option( 'woocommerce_admin_install_timestamp', time() - WEEK_IN_SECONDS );

$wpdb->query( 'COMMIT' );
wp_suspend_cache_invalidation( false );
wp_defer_term_counting( false );
