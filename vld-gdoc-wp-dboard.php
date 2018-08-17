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
 * Adds a widget to the dashboard
 */
function vld_gdoc_wp_dboard_add_widget() {

	wp_add_dashboard_widget(
		'vld-gdoc-wp-dboard',
		'GDoc WP Dashboard',
		'vld_gdoc_wp_dboard_function'
	);
}

/**
 * Adds action on wp_dashboard_setup hook
 */
add_action( 'wp_dashboard_setup', 'vld_gdoc_wp_dboard_add_widget' );

/**
 * Returns info from Google Doc
 */
function vld_gdoc_wp_dboard_function() {

	require_once 'google-api-php-client-2.2.2/vendor/autoload.php';

	$client = new Google_Client();
	$client->setApplicationName("Client_Library_Examples");
	$client->setDeveloperKey("YOUR_APP_KEY");

	$service = new Google_Service_Books($client);
	$optParams = array('filter' => 'free-ebooks');
	$results = $service->volumes->listVolumes('Henry David Thoreau', $optParams);

	foreach ($results as $item) {
		echo $item['volumeInfo']['title'], "<br /> \n";
	}

	echo "Test Test Test";
}