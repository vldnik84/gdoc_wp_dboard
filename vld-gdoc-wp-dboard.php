<?php
/*
Plugin Name: GDoc WP Dashboard
Plugin URI: http://github.com/vldnik84
Description: Plugin takes data from Google Doc and shows it in Wordpress dashboard.
Version: 0.1
Author: vldnik84
Author URI: http://github.com/vldnik84
*/

/**
 * Creates a custom post type
 */
function vld_cpt_function() {

	$labels = array(
		'name'               => __( 'Products', 'post type general name' ),
		'singular_name'      => __( 'Product', 'post type singular name' ),
		'add_new'            => __( 'Add New', 'book' ),
		'add_new_item'       => __( 'Add New Product' ),
		'edit_item'          => __( 'Edit Product' ),
		'new_item'           => __( 'New Product' ),
		'all_items'          => __( 'All Products' ),
		'view_item'          => __( 'View Product' ),
		'search_items'       => __( 'Search Products' ),
		'not_found'          => __( 'No products found' ),
		'not_found_in_trash' => __( 'No products found in the Trash' ),
		'parent_item_colon'  =>     '',
		'menu_name'          =>     'Products'
	);
	$args = array(
		'labels'        => $labels,
		'description'   => 'Holds our products and product specific data',
		'public'        => true,
		'menu_position' => 5,
		'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'comments' ),
		'has_archive'   => true,
	);
	register_post_type( 'product', $args );
}
add_action( 'init', 'vld_cpt_function' );

/**
 * Adds a widget to the dashboard
 */
function vld_gdoc_wp_dboard_add_widget() {

	wp_add_dashboard_widget(
		'vld-gdoc-wp-dboard',
		'GDoc WP Dashboard',
		'vld_gdoc_wp_dboard_function'
	);
}
add_action( 'wp_dashboard_setup', 'vld_gdoc_wp_dboard_add_widget' );

/**
 * Gets data from Google Doc Spreadsheet
 */
function vld_gdoc_wp_dboard_function() {

	require_once __DIR__ . '/google-api-php-client-2.2.2/vendor/autoload.php';

	$service = new Google_Service_Sheets( google_sheet_auth() );

	$sheet_id = file_get_contents(__DIR__ . '/access-data/sheet_id.txt');
	$sheet_range = 'A1:F12';
	//$sheet_range = 'A1:F12';

	$result = $service->spreadsheets_values->get($sheet_id, $sheet_range)->getValues();
	array_shift($result);
	echo "<pre>";
	print_r($result);
}

/**
 * Performs server to server authorization with a service account
 */
function google_sheet_auth() {

	$SERVICE_KEY = __DIR__ . '/access-data/service_key.json';
	$SCOPES = array( Google_Service_Sheets::SPREADSHEETS_READONLY );

	putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $SERVICE_KEY);

	$client = new Google_Client();
	$client->useApplicationDefaultCredentials();
	$client->setIncludeGrantedScopes(true);
	$client->addScope($SCOPES);

	return $client;
}