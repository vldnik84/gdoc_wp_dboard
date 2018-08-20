<?php
/*
Plugin Name: GDoc Casino Dashboard
Plugin URI: http://github.com/vldnik84
Description: Plugin takes data from Google Doc and shows it in Wordpress dashboard.
Version: 0.1
Author: vldnik84
Author URI: http://github.com/vldnik84
*/

/**
 * Creates a custom post type
 */
function vld_gdoc_casino_add_cpt() {

	$labels = array(
		'name'               => __( 'Casinos', 'post type general name' ),
		'singular_name'      => __( 'Casino', 'post type singular name' ),
		'add_new'            => __( 'Add New', 'book' ),
		'add_new_item'       => __( 'Add New Casino' ),
		'edit_item'          => __( 'Edit Casino' ),
		'new_item'           => __( 'New Casino' ),
		'all_items'          => __( 'All Casinos' ),
		'view_item'          => __( 'View Casino' ),
		'search_items'       => __( 'Search Casinos' ),
		'not_found'          => __( 'No casinos found' ),
		'not_found_in_trash' => __( 'No casinos found in the Trash' ),
		'parent_item_colon'  =>     '',
		'menu_name'          =>     'Casinos'
	);
	$supports = array(
		'title',
		'editor',
		'thumbnail',
		'excerpt',
		'comments'
	);
	$args = array(
		'labels'        => $labels,
		'description'   => 'Holds casinos info and their specific data',
		'public'        => true,
		'menu_position' => 5,
		'supports'      => $supports,
		'has_archive'   => true
	);
	register_post_type( 'vld_gdoc_casino_cpt', $args );
}
add_action( 'init', 'vld_gdoc_casino_add_cpt');

/**
 * Adds a widget to the dashboard
 */
function vld_gdoc_casino_add_widget() {

	wp_add_dashboard_widget(
		'vld_gdoc_casino',
		'GDoc Casino Widget',
		'vld_gdoc_casino_function'
	);
}
add_action( 'wp_dashboard_setup', 'vld_gdoc_casino_add_widget' );

/**
 * Gets data from Google Doc Spreadsheet
 */
function vld_gdoc_casino_function() {

	require_once __DIR__ . '/google-api-php-client-2.2.2/vendor/autoload.php';

	$service = new Google_Service_Sheets( vld_gdoc_casino_auth() );

	$sheet_id    = json_decode( file_get_contents( __DIR__ . '/access-data/sheet_id.json' ) );
	$sheet_range = 'A1:F12'; // full range is A1:F12

	$result = $service->spreadsheets_values->get( $sheet_id, $sheet_range )->getValues();

	$key = array();
	$sort_res = array();

	// parsing the retrieved data
	for ($i = 0; $i < sizeof($result); $i++) {
		if ($i > 0) {
			for ($j = 0; $j < sizeof($result[$i]); $j++) {
				$sort_res[$i][$key[$j]] = $result[$i][$j];
			}
		} else {
			foreach ($result[$i] as $value) {
				array_push( $key, str_replace(' ', '_', strtolower($value)) );
			}
		}
	}

	// sorting the retrieved data
	usort($sort_res, function($a, $b) {
		return $a['sort_order'] - $b['sort_order'];
	});

	//file_put_contents( __DIR__ . '/access-data/cache.json', json_encode($sort_res) );

	for ($i = 0; $i < sizeof( $sort_res ); $i++ ) {

		$post_title = $sort_res[$i]['casino_name'];

		// post delete function
//		wp_delete_post(post_exists($post_title), true);

		if (post_exists($post_title) === 0) {

			set_time_limit(0);

			$basename = basename( $sort_res[$i]['casino_image'] );
			$filename = $sort_res[$i]['casino_name'] . substr( $basename, strpos($basename, '.') );
			// TODO add check for similar names, not only here btw
			$upload_file = strtolower( str_replace( ' ', '_', $filename ) );

			$upload_path = wp_upload_dir()['basedir'] . '/casinos/' . $upload_file;
			$upload = fopen( $upload_path, 'w+' );

			$url = str_replace(' ','%20', $sort_res[3]['casino_image']);
			$curl = curl_init($url);

			curl_setopt($curl, CURLOPT_TIMEOUT, 50);
			curl_setopt($curl, CURLOPT_FILE, $upload);
			curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

			curl_exec($curl);
			curl_close($curl);
			$post_args = array(
				'post_type'    => 'vld_gdoc_casino_cpt',
				'post_title'   => $post_title,
				'post_status'  => $sort_res[$i]['display_status'] === 'y' ? 'publish' : 'private',
				'post_content' => $sort_res[$i]['description'],
				'menu_order'   => $i,
				'meta_input'   => array(
					'casino_link'  => $sort_res[$i]['casino_link'],
					'casino_image' => $sort_res[$i]['casino_image'],
					'sort_order'   => $sort_res[$i]['sort_order'] )
			);
			// TODO check if not 0 and not an object
			$post_id = wp_insert_post($post_args);

			$filetype = wp_check_filetype( $upload_path, null );

			// Prepare an array of post data for the attachment.
			$attachment = array(
				'guid'           => wp_upload_dir()['baseurl'] . '/casinos/' . $upload_file,
				'post_mime_type' => $filetype['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', '', $upload_file ),
				'post_content'   => '',
				'post_status'    => 'inherit'
			);

			// Insert the attachment.
			$attach_id = wp_insert_attachment( $attachment, $upload_path, $post_id );

			// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
			require_once( ABSPATH . 'wp-admin/includes/image.php' );

			// Generate the metadata for the attachment, and update the database record.
			$attach_data = wp_generate_attachment_metadata( $attach_id, $upload_path );
			wp_update_attachment_metadata( $attach_id, $attach_data );

			set_post_thumbnail( $post_id, $attach_id );
		}
	}

	echo "TEST TEST TEST";
}

/**
 * Performs server to server authorization with a service account
 */
function vld_gdoc_casino_auth() {

	$SERVICE_KEY = __DIR__ . '/access-data/service_key.json';
	$SCOPES = array( Google_Service_Sheets::SPREADSHEETS_READONLY );

	putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $SERVICE_KEY);

	$client = new Google_Client();
	$client->useApplicationDefaultCredentials();
	$client->setIncludeGrantedScopes(true);
	$client->addScope($SCOPES);

	return $client;
}

/**
 * Adds custom metabox with casino link
 */
function vld_gdoc_casino_add_cmb() {
	add_meta_box(
		'vld_gdoc_casino_link',
		'Casino link',
		'vld_gdoc_casino_link_function',
		'vld_gdoc_casino_cpt',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_vld_gdoc_casino_cpt', 'vld_gdoc_casino_add_cmb' );

/**
 * Outputs the HTML for the metabox
 */
function vld_gdoc_casino_link_function() {

	global $post;

	// Add nonce field to validate form request came from current site
	wp_nonce_field( basename( __FILE__ ), 'vld_gdoc_casino_nonce' );

	// Get the location data if it's already been entered
	$casino_link = get_post_meta( $post->ID, 'casino_link', true );

	// Output the field
	echo '<input type="text" name="casino_link" value="' . esc_textarea( $casino_link )  . '" class="widefat">';
}

/**
 * Save the metabox data
 */
function vld_gdoc_casino_link_save( $post_id ) {

	// Return if the user doesn't have edit permissions.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return $post_id;
	}

	// Verify this came from the our screen and with proper authorization,
	// because save_post can be triggered at other times.
	if ( ! isset( $_POST['vld_gdoc_casino_nonce'] ) || ! wp_verify_nonce( $_POST['vld_gdoc_casino_nonce'],
			basename(__FILE__) ) ) {
		return $post_id;
	}

	// Now that we're authenticated, time to save the data.
	// This sanitizes the data from the field and saves it into an array $events_meta.
	$casino_meta['casino_link'] = esc_textarea( $_POST['casino_link'] );

	// Cycle through the $events_meta array.
	// Note, in this example we just have one item, but this is helpful if you have multiple.
	foreach ( $casino_meta as $key => $value ) {

		// Don't store custom data twice
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( get_post_meta( $post_id, $key, false ) ) {
			// If the custom field already has a value, update it.
			update_post_meta( $post_id, $key, $value );
		} else {
			// If the custom field doesn't have a value, add it.
			add_post_meta( $post_id, $key, $value );
		}

		if ( ! $value ) {
			// Delete the meta key if there's no value
			delete_post_meta( $post_id, $key );
		}
	}
}
add_action( 'save_post', 'vld_gdoc_casino_link_save', 1, 2 );



//function wpa18013_add_pages_to_dropdown( $pages, $r ){
//	print_r($r);
//die();
//	if('page_on_front' == $r['name']) {
//		$args = array(
//			'post_type' => 'vld_gdoc_casino_cpt'
//		);
//		$stacks = get_posts($args);
//		$pages = array_merge($pages, $stacks);
//	}
//
//	return $pages;
//}
//add_filter( 'get_pages', 'wpa18013_add_pages_to_dropdown', 10, 2 );

//add_action( 'pre_get_posts', 'wpse_242473_add_post_type_to_home' );
//
//function wpse_242473_add_post_type_to_home( $query ) {
//
//	if( $query->is_main_query() && $query->is_home() ) {
//		$query->set( 'post_type', array( 'post', 'vld_gdoc_casino_cpt') );
//	}
//}


//function wpse_242473_recent_posts( $atts = null, $content = null, $tag = null ) {
//
//	$out = '';
//
//	$args = array(
//		'numberposts' => '6',
//		'post_status' => 'publish',
//		'post_type' => 'vld_gdoc_casino_cpt' ,
//	);
//
//	$recent = wp_get_recent_posts( $args );
//
//	echo "recent";
//	print_r($recent);
//	die();
//
//	if ( $recent ) {
//
//		$out .= '<section class="overview">';
//
//		$out .= '<h1>Recent Projects</h1>';
//
//		$out .= '<div class="overview">';
//
//		foreach ( $recent as $item ) {
//
//			$out .= '<a href="' . get_permalink( $item['ID'] ) . '">';
//			$out .= get_the_post_thumbnail( $item['ID'] );
//			$out .= '</a>';
//		}
//
//		$out .= '</div></section>';
//	}
//
//	if ( $tag ) {
//		return $out;
//	} else {
//		echo $out;
//	}
//
//}
//
//add_shortcode( 'recentposts', 'wpse_242473_recent_posts' );


function themeslug_filter_front_page_template( $template ) {
	return "123";
		//is_home() ? '' : $template;
}
add_filter( 'frontpage_template', 'themeslug_filter_front_page_template' );