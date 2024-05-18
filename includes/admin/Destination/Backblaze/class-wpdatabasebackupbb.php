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
public static function bb_upload_to_backblaze($file_path, $file_name, $key_id, $app_key, $bucket_id) {
    $b2_authorize_url = "https://api.backblazeb2.com/b2api/v2/b2_authorize_account";
    $credentials = base64_encode($key_id . ":" . $app_key);
    $chunk_size = 10000000; // 100MB chunks


    // Authorize account
    $response = wp_remote_get($b2_authorize_url, array(
        'headers' => array(
            'Authorization' => 'Basic ' . $credentials
        ),
        'timeout' => 60 // Extend timeout
    ));


    if (is_wp_error($response)) {
        return array('success' => false, 'message' => 'Authorization request failed: ' . $response->get_error_message());
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if (empty($data->authorizationToken)) {
        return array('success' => false, 'message' => 'Failed to authorize with Backblaze.');
    }

    $auth_token = $data->authorizationToken;
    $upload_url = $data->apiUrl . '/b2api/v2/b2_get_upload_url';

    // Get upload URL
    $response = wp_remote_post($upload_url, array(
        'body' => json_encode(array('bucketId' => $bucket_id)),
        'headers' => array(
            'Authorization' => $auth_token,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 60 // Extend timeout
    ));

    if (is_wp_error($response)) {
        return array('success' => false, 'message' => 'Failed to get upload URL: ' . $response->get_error_message());
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if(isset($data->status) &&  $data->status!= 200) return array('success' => false, 'message' => 'Failed to get upload URL: ' . $data->message);

    if (empty($data->uploadUrl)) {
        return array('success' => false, 'message' => 'Failed to get upload URL from Backblaze.');
    }

    $upload_url = $data->uploadUrl;
    $upload_auth_token = $data->authorizationToken;

    if (!file_exists($file_path)) {
        return array('success' => false, 'message' => 'File does not exist: ' . $file_path);
    }

    $file_size = filesize($file_path);
    $file = fopen($file_path, 'r');
    if ($file === false) {
        return array('success' => false, 'message' => 'Failed to open file: ' . $file_path);
    }

    $offset = 0;
    $part_number = 1;

    while ($offset < $file_size) {
        $file_contents = fread($file, $chunk_size);
        if ($file_contents === false) {
            fclose($file);
            return array('success' => false, 'message' => 'Failed to read file chunk: ' . $file_path);
        }

        $sha1_of_file_data = sha1($file_contents);
        $file_name = preg_replace('/[^a-zA-Z0-9_.]/', '', basename($file_name));
        $response = wp_remote_post($upload_url, array(
            'body' => $file_contents,
            'headers' => array(
                'Authorization' => $upload_auth_token,
                'X-Bz-File-Name' => $file_name,
                'Content-Type' => 'b2/x-auto',
                'X-Bz-Content-Sha1' => $sha1_of_file_data,
                'X-Bz-Part-Number' => $part_number
            ),
            'timeout' => 60 // Extend timeout
        ));

        if (is_wp_error($response)) {
            fclose($file);
            return array('success' => false, 'message' => 'Upload request failed: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code != 200) {
            $response_body = wp_remote_retrieve_body($response);
            fclose($file);
            return array('success' => false, 'message' => 'Failed to upload chunk ' . $part_number . ' of ' . $file_name . ' to Backblaze. Response: ' . $response_body);
        }

        $offset += $chunk_size;
        $part_number++;
    }

    fclose($file);

    return array('success' => true, 'message' => '<br> File ' . $file_name . ' uploaded successfully to Backblaze in ' . ($part_number - 1) . ' parts.');
}

	/**
	 * Run after complete backup.
	 *
	 * @param array $args - backup details.
	 */

	public static function wp_db_backup_completed( &$args ) {
		$destination_s3 = get_option( 'wp_db_backup_destination_bb' );
		if ( isset( $destination_s3 ) && 1 == $destination_s3 && get_option( 'wpdb_dest_bb_s3_bucket_host' ) && get_option( 'wpdb_dest_bb_s3_bucket' ) && get_option( 'wpdb_dest_bb_s3_bucket_key' ) && get_option( 'wpdb_dest_bb_s3_bucket_secret' ) ) {
			update_option('wpdbbkp_backupcron_current','Processing Backblaze S3 Backup', false);
			try {
		

				$wpdb_dest_bb_s3_bucket_host = get_option( 'wpdb_dest_bb_s3_bucket_host',null);
				$wpdb_dest_bb_s3_bucket = get_option( 'wpdb_dest_bb_s3_bucket',null);
				$wpdb_dest_bb_s3_bucket_key = get_option( 'wpdb_dest_bb_s3_bucket_key',null);
				$wpdb_dest_bb_s3_bucket_secret = get_option( 'wpdb_dest_bb_s3_bucket_secret',null);

				// Check for CURL.
				if ( ! extension_loaded( 'curl' ) && ! dl( 'so' === PHP_SHLIB_SUFFIX ? 'curl.so' : 'php_curl.dll' ) ) { // phpcs:ignore
					$message_error = 'No Curl';
				}
				preg_match('/s3\.([a-zA-Z0-9-]+)\.backblazeb2\.com/', $wpdb_dest_bb_s3_bucket_host, $matches);
					if (isset($matches[1])) {
						$region = $matches[1];
					} 
                $ret = WPDatabaseBackupBB::bb_upload_to_backblaze($args[1], $args[1], $wpdb_dest_bb_s3_bucket_key, $wpdb_dest_bb_s3_bucket_secret, $wpdb_dest_bb_s3_bucket);
				$args[2] = $args[2] .$ret['message'];
                if ($ret['success']) {
                    $args[4] = $args[4] .= 'Backblaze, ';
                }	
			} catch ( Exception $e ) {
				$args[2] = $args[2] . "<br>Failed to upload Database Backup on s3 bucket";
			}
		}
	}
	
}
