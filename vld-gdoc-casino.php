<?php
/*
Plugin Name: GDoc Casino Dashboard Plugin
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
//		'register_meta_box_cb' => 'vld_gdoc_casino_add_cmb'
	);
	register_post_type( 'vld_gdoc_casino_cpt', $args );
}
add_action( 'init', 'vld_add_cpt');

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
	//file_put_contents( __DIR__ . '/access-data/cache.json', json_encode($result) );

	$key = array();
	$sort_res = array();

	for ($i = 0; $i < sizeof($result); $i++) {
		if ($i > 0) {
			for ($j = 0; $j < sizeof($result[$i]); $j++) {
				$sort_res[$i][$key[$j]] = $result[$i][$j];
			}
		} else {
			foreach ($result[$i] as $value) {
				array_push($key, $value);
			}
		}
	}

	uasort($sort_res, function($a, $b) {
		return $a['Sort order'] - $b['Sort order'];
	});

	foreach ( $sort_res as $value ) {
		$post_args = array(
			'post_type' => 'vld_casinos',
			'post_title' => $value['Casino name'],
			'post_status' => $value['Display status'] === 'y' ? 'publish' : 'private',
			'post_content' => $value['Description']
		);
		wp_insert_post($post_args);
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

function vld_gdoc_casino_add_cmb( $post ) {
	add_meta_box(
		'vld_gdoc_casino_link',
		'Casino link',
		'vld_gdoc_casino_link_function',
		'events',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_vld_gdoc_casino_cpt', 'vld_gdoc_casino_add_cmb' );

/**
 * Output the HTML for the metabox.
 */
function vld_gdoc_casino_link_function() {

	global $post;

	// Nonce field to validate form request came from current site
	wp_nonce_field( basename( __FILE__ ), 'event_fields' );

	// Get the location data if it's already been entered
	$casino_link = get_post_meta( $post->ID, 'Casino link', true );

	// Output the field
	echo '<input type="text" name="Casino link" value="' . esc_textarea( $casino_link )  . '" class="widefat">';

}

/**
 * Save the metabox data
 */
function vld_gdoc_casino_link_save( $post_id, $post ) {

	// Return if the user doesn't have edit permissions.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return $post_id;
	}

	// Verify this came from the our screen and with proper authorization,
	// because save_post can be triggered at other times.
	if ( ! isset( $_POST['Casino link'] ) || ! wp_verify_nonce( $_POST['event_fields'], basename(__FILE__) ) ) {
		return $post_id;
	}

	// Now that we're authenticated, time to save the data.
	// This sanitizes the data from the field and saves it into an array $events_meta.
	$events_meta['Casino link'] = esc_textarea( $_POST['Casino link'] );

	// Cycle through the $events_meta array.
	// Note, in this example we just have one item, but this is helpful if you have multiple.
	foreach ( $events_meta as $key => $value ) {

		// Don't store custom data twice
		if ( 'revision' === $post->post_type ) {
			return $post_id;
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
