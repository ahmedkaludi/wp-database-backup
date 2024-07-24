<?php
/**
 * Destination BlackBlaze.
 *
 * @package wpdbbkp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}




add_action( 'wp_db_backup_completed', array( 'WPDatabaseBackupBB', 'wp_db_backup_completed' ) );

/**
 * WPDatabaseBackupBB Class.
 *
 * @class WPDatabaseBackupBB
 */
class WPDatabaseBackupBB {

// Function to upload files to Backblaze B2
public static function upload_backup_to_backblaze($file_path, $file_name) {

    global $wp_filesystem;
    if(!function_exists('WP_Filesystem')){
    require_once ( ABSPATH . '/wp-admin/includes/file.php' );
    }
    WP_Filesystem();

    $s3_token = get_transient('b2_authorization_token');
    $api_url = get_transient('b2_api_url');
    $bucket_id = get_option('wpdb_dest_bb_s3_bucket') ? get_option('wpdb_dest_bb_s3_bucket') : '';
    if (!$s3_token) {
        $key_id = get_option('wpdb_dest_bb_s3_bucket_key') ? get_option('wpdb_dest_bb_s3_bucket_key') : '';
        $app_key = get_option('wpdb_dest_bb_s3_bucket_secret') ? get_option('wpdb_dest_bb_s3_bucket_secret') : '';

        $b2_authorize_url = "https://api.backblazeb2.com/b2api/v2/b2_authorize_account";
        $credentials = base64_encode($key_id . ":" . $app_key);

        // Authorize account
        $response = wp_remote_get($b2_authorize_url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . $credentials
            ),
            'timeout' => 60 // Extend timeout
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'message' => esc_html__('Authorization request failed: ', 'wpdbbkp'). $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (empty($data->authorizationToken)) {
            return array('success' => false, 'message' => esc_html__('Failed to authorize with Backblaze.', 'wpdbbkp'));
        }

        $auth_token = $data->authorizationToken;
        $expiration = 1 * HOUR_IN_SECONDS; // 24 hours
        $upload_url = $data->apiUrl . '/b2api/v2/b2_get_upload_url';

        set_transient('b2_authorization_token', $auth_token, $expiration);
        set_transient('b2_api_url', $data->apiUrl, $expiration);
    } else {
        $auth_token = $s3_token;
        $upload_url = $api_url . '/b2api/v2/b2_get_upload_url';
    }

    // Get upload URL
    $response = wp_remote_post($upload_url, array(
        'body' => wp_json_encode(array('bucketId' => $bucket_id)),
        'headers' => array(
            'Authorization' => $auth_token,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 60 // Extend timeout
    ));

    if (is_wp_error($response)) {
        return array('success' => false, 'message' =>  esc_html__('Failed to get upload URL: ', 'wpdbbkp'). $response->get_error_message());
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if (isset($data->status) && $data->status != 200) return array('success' => false, 'message' =>  esc_html__('Failed to get upload URL: ' , 'wpdbbkp'). $data->message);

    if (empty($data->uploadUrl)) {
        return array('success' => false, 'message' => esc_html__('Failed to get upload URL from Backblaze.', 'wpdbbkp'));
    }

    $upload_url = $data->uploadUrl;
    $upload_auth_token = $data->authorizationToken;

    if (!$wp_filesystem) {
        return array('success' => false, 'message' => esc_html__('Unable to initialize wp_filesystem : ' , 'wpdbbkp'). $file_path);
    }

    if (!$wp_filesystem->exists($file_path)) {
        return array('success' => false, 'message' => esc_html__('File does not exist: ' , 'wpdbbkp'). $file_path);
    }

    $file_size = filesize($file_path);

    $file_contents = $wp_filesystem->get_contents( $file_path );

    if ($file_contents === false) {
        return array('success' => false, 'message' => esc_html__('Failed to read file: ', 'wpdbbkp') . $file_path);
    }

    $sha1_of_file_data = sha1($file_contents);
    $root_path = str_replace('\\', '/', ABSPATH); // Normalize to forward slashes for consistency
    $file_path = str_replace($root_path, '', $file_path);
    $file_path = ltrim($file_path, '/'); // Ensure there is no leading slash

    $response = wp_remote_post($upload_url, array(
        'body' => $file_contents,
        'headers' => array(
            'Authorization' => $upload_auth_token,
            'X-Bz-File-Name' => basename($file_path),
            'Content-Type' => 'b2/x-auto',
            'X-Bz-Content-Sha1' => $sha1_of_file_data
        ),
        'timeout' => 900,
    ));

    if (is_wp_error($response)) {
        return array('success' => false, 'message' => esc_html__('Upload request failed: ', 'wpdbbkp') . $response->get_error_message());
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code != 200) {
        $response_body = wp_remote_retrieve_body($response);
        return array('success' => false, 'message' => esc_html__('Failed to upload ' , 'wpdbbkp'). $file_name . ' to Backblaze. Response: ' . $response_body);
    }

    return array('success' => true, 'message' => 'File ' . $file_name . esc_html__(' uploaded successfully to Backblaze.', 'wpdbbkp'));
}


	/**
	 * Run after complete backup.
	 *
	 * @param array $args - backup details.
	 */

	public static function wp_db_backup_completed( &$args ) {
		$destination_s3 = get_option( 'wp_db_backup_destination_bb' );
		if ( isset( $destination_s3 ) && 1 == $destination_s3 && get_option( 'wpdb_dest_bb_s3_bucket_host' ) && get_option( 'wpdb_dest_bb_s3_bucket' ) && get_option( 'wpdb_dest_bb_s3_bucket_key' ) && get_option( 'wpdb_dest_bb_s3_bucket_secret' ) ) {
			
			try {
		
                $ret = WPDatabaseBackupBB::upload_backup_to_backblaze($args[1], $args[1]);
				$args[2] = $args[2] .$ret['message'];
                if ($ret['success']) {
                    $args[4] .= 'Backblaze, ';
                }	
			} catch ( Exception $e ) {
				$args[2] = $args[2] . "<br>".esc_html__("Failed to upload Database Backup on s3 bucket", 'wpdbbkp');
			}
		}
	}
	
}
