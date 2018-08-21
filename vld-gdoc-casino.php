<?php
/*
Plugin Name: GDoc to WP Data Parser
Plugin URI: http://github.com/vldnik84/vld_gdoc_casino_wp_plugin
Description: Plugin takes data from Google Doc and places it on the WP dashboard and the front page.
Version: 0.9
Author: vldnik84
Author URI: http://github.com/vldnik84
*/

/**
 * Creates vld_gdoc_casino_cpt custom post type
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
add_action( 'init', 'vld_gdoc_casino_add_cpt' );

/**
 * Main function of plugin
 *
 * @throws Exception
 */
function vld_gdoc_casino_main() {

	$sort_res = vld_gdoc_casino_get_data();
	vld_gdoc_casino_add_posts($sort_res);
	vld_gdoc_casino_add_table();
}
add_action( 'admin_init', 'vld_gdoc_casino_main' );

/**
 * Gets data from Google Doc
 *
 * @return array
 * @throws Exception
 */
function vld_gdoc_casino_get_data() {

	require_once __DIR__ . '/google-api-php-client-2.2.2/vendor/autoload.php';

	$service = new Google_Service_Sheets( vld_gdoc_casino_gauth() );
	$sheet_id    = json_decode( file_get_contents( __DIR__ . '/access-data/sheet_id.json' ) );
	$sheet_range = 'A1:F12'; // full range is A1:F12
	$result = $service->spreadsheets_values->get( $sheet_id, $sheet_range )->getValues();

	$key = array();
	$sort_res = array();

	// parses the retrieved data
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

	// sorts the retrieved data
	usort($sort_res, function($a, $b) {
		return $a['sort_order'] - $b['sort_order'];
	});

	// !!! writes sorted post array to cache - for debug purposes
	//file_put_contents( __DIR__ . '/access-data/cache.json', json_encode($sort_res) );

	if (empty($sort_res)) {
		throw new Exception('Data, retrieved from GDoc, is empty.');
	}

	return $sort_res;
}

/**
 * Performs server to server authorization with a service account
 *
 * @return Google_Client
 */
function vld_gdoc_casino_gauth() {

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
 * Adds custom posts from Google Doc data
 *
 * @param $sort_res
 */
function vld_gdoc_casino_add_posts($sort_res) {

	for ($i = 0; $i < sizeof( $sort_res ); $i++ ) {

		$post_title = $sort_res[$i]['casino_name'];

		// !!! deletes a post by title - for debug purposes
		//wp_delete_post(post_exists($post_title), true);

		if (post_exists($post_title) === 0) {

			set_time_limit(0);

			$basename = basename( $sort_res[$i]['casino_image'] );
			$filename = $sort_res[$i]['casino_name'] . substr( $basename, strpos($basename, '.') );
			$filename = strtolower( str_replace( ' ', '_', $filename ) );

			$upload_path = wp_upload_dir()['basedir'] . '/casinos/' . $filename;
			$url = str_replace( ' ','%20', $sort_res[$i]['casino_image'] );

			vld_gdoc_casino_get_file( $url, $upload_path );

			$post_args = array(
				'post_type'    => 'vld_gdoc_casino_cpt',
				'post_title'   => $post_title,
				'post_status'  => $sort_res[$i]['display_status'] === 'y' ? 'publish' : 'private',
				'post_content' => $sort_res[$i]['description'],
				'meta_input'   => array(
					'casino_link'    => $sort_res[$i]['casino_link'],
					'casino_image'   => $sort_res[$i]['casino_image'],
					'sort_order'     => (int) $sort_res[$i]['sort_order']),
					'is_casino_list' => false
			);
			$post_id = wp_insert_post($post_args);

			$filetype = wp_check_filetype( $upload_path, null );

			// prepares post data array for the attachment
			$attachment = array(
				'guid'           => wp_upload_dir()['baseurl'] . '/casinos/' . $filename,
				'post_mime_type' => $filetype['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', '', $filename ),
				'post_content'   => '',
				'post_status'    => 'inherit'
			);

			// inserts the attachment.
			$attach_id = wp_insert_attachment( $attachment, $upload_path, $post_id );

			// fail-safe measure
			require_once( ABSPATH . 'wp-admin/includes/image.php' );

			// generates metadata for the attachment and updates database record
			$attach_data = wp_generate_attachment_metadata( $attach_id, $upload_path );
			wp_update_attachment_metadata( $attach_id, $attach_data );

			set_post_thumbnail( $post_id, $attach_id );
		}
	}
}

/**
 * Saves casino image to a file using curl
 *
 * @param $url
 * @param $upload_path
 */
function vld_gdoc_casino_get_file( $url, $upload_path ) {

	$upload = fopen( $upload_path, 'w+' );

	$curl = curl_init($url);

	curl_setopt( $curl, CURLOPT_TIMEOUT, 50 );
	curl_setopt( $curl, CURLOPT_FILE, $upload );
	curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, true );

	curl_exec($curl);
	curl_close($curl);
}

/**
 * Creates a table with a list of casinos
 */
function vld_gdoc_casino_add_table() {

	$query = new WP_Query(array(
		'post_type'   => 'vld_gdoc_casino_cpt',
		'post_status' => 'publish',
		'posts_per_page' => -1,
		//'nopaging'    => true,
		'meta_key'    => 'sort_order',
		'orderby'     => 'meta_value_num',
		'order'       => 'ASC'
	));

	$out  = '<table>';
	$out .= '<thead><tr><th>Casino name</th><th>Description</th></tr></thead>';
	$out .= '<tbody>';

	while ($query->have_posts()) {

		$query->the_post();
		$out .= '<tr><td>' . get_the_title() . '</td><td>' . get_the_content() . '</td>' . '</tr>';

		// !!! prints meta data of post by ID - for debug purposes
		//print_r(get_post_meta($query->post->ID));
		wp_reset_postdata();
	}

	$out .= '</tbody>';
	$out .= '</table>';

	//wp_reset_query();

	$post_title = 'List of casinos';
	// !!! deletes a post by title - for debug purposes
	//wp_delete_post(post_exists($post_title), true);

	$post_args = array(
		'ID'           => post_exists($post_title),
		'post_type'    => 'vld_gdoc_casino_cpt',
		'post_title'   => $post_title,
		'post_status'  => 'publish',
		'post_content' => $out,
		'meta_input'   => array( 'is_casino_list' => true )
	);
	wp_insert_post( $post_args );
}

/**
 * Adds a widget to the dashboard
 */
function vld_gdoc_casino_add_widget() {

	wp_add_dashboard_widget(
		'vld_gdoc_casino',
		'GDoc Casino Widget',
		'vld_gdoc_casino_widget'
	);
}
add_action( 'wp_dashboard_setup', 'vld_gdoc_casino_add_widget' );

/**
 * Gets data from Google Doc spreadsheet
 */
function vld_gdoc_casino_widget() {

	$post_title = 'List of casinos';
	$post_id = post_exists($post_title);

	if ($post_id > 0) {

		$content = get_post($post_id)->post_content;
		$content = apply_filters('the_content', $content);
		$content = str_replace(']]>', ']]&gt;', $content);
		echo $content;
	}
}

/**
 * Adds custom meta box with casino link
 */
function vld_gdoc_casino_add_cmb() {

	add_meta_box(
		'vld_gdoc_casino_link',
		'Casino link',
		'vld_gdoc_casino_link',
		'vld_gdoc_casino_cpt',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_vld_gdoc_casino_cpt', 'vld_gdoc_casino_add_cmb' );

/**
 * Outputs HTML for custom meta box
 */
function vld_gdoc_casino_link() {

	global $post;

	// adds nonce field to validate that the form request came from current site
	wp_nonce_field( basename( __FILE__ ), 'vld_gdoc_casino_nonce' );

	// gets the already existing data
	$casino_link = get_post_meta( $post->ID, 'casino_link', true );

	echo '<input type="text" name="casino_link" value="' . esc_textarea( $casino_link )  . '" class="widefat">';
}

/**
 * Saves the meta box data
 *
 * @param $post_id
 */
function vld_gdoc_casino_link_save( $post_id ) {

	// returns if the user doesn't have edit permissions
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// verifies that the save request is connected with this meta box
	if ( ! isset($_POST[ 'vld_gdoc_casino_nonce' ]) || ! wp_verify_nonce($_POST[ 'vld_gdoc_casino_nonce' ],
			basename(__FILE__) ) ) {
		return;
	}

	$casino_meta['casino_link'] = esc_textarea( $_POST['casino_link'] );

	// goes through all possible meta fields
	foreach ( $casino_meta as $key => $value ) {

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( get_post_meta( $post_id, $key, false ) ) {
			// updates the existing value
			update_post_meta( $post_id, $key, $value );
		} else {
			// adds a new value
			add_post_meta( $post_id, $key, $value );
		}

		if ( ! $value ) {
			// deletes the meta key if there is no value
			delete_post_meta( $post_id, $key );
		}
	}
}
add_action( 'save_post', 'vld_gdoc_casino_link_save', 10, 1 );

/**
 * Adds custom posts to the front page
 *
 * @param $query
 */
function vld_gdoc_casino_fp_posts( $query ) {

	if( $query->is_main_query() && $query->is_home() ) {

		// gets already existing post types and meta queries
		$post_types = $query->get('post_type');
		$meta_query = $query->get('meta_query') ?: [];

		// appends new post types and meta queries
		array_push( $post_types, 'vld_gdoc_casino_cpt' );
		$meta_query[] = [
			'key' => 'is_casino_list',
			'value' => true,
			'compare' => '='
		];

		$query->set( 'post_type', $post_types );
		$query->set( 'post_status', 'publish' );
		$query->set( 'meta_query', $meta_query );
	}
}
add_action( 'pre_get_posts', 'vld_gdoc_casino_fp_posts', 10, 1 );