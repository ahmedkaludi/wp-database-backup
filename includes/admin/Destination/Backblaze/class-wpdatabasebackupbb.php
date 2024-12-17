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

    if (!function_exists('WP_Filesystem')) {
        require_once(ABSPATH . '/wp-admin/includes/file.php');
    }
    WP_Filesystem();

    $s3_token = get_transient('b2_authorization_token');
    $api_url = get_transient('b2_api_url');
    $bucket_id = get_option('wpdb_dest_bb_s3_bucket') ?: '';

    if (!$s3_token) {
        $key_id = get_option('wpdb_dest_bb_s3_bucket_key') ?: '';
        $app_key = get_option('wpdb_dest_bb_s3_bucket_secret') ?: '';
        $b2_authorize_url = "https://api.backblazeb2.com/b2api/v2/b2_authorize_account";
        $credentials = base64_encode($key_id . ":" . $app_key);

        // Authorize account
        $response = wp_remote_get($b2_authorize_url, array(
            'headers' => array('Authorization' => 'Basic ' . $credentials),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'message' => esc_html__('Authorization request failed: ', 'wpdbbkp') . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (empty($data->authorizationToken)) {
            return array('success' => false, 'message' => esc_html__('Failed to authorize with Backblaze.', 'wpdbbkp'));
        }

        $auth_token = $data->authorizationToken;
        set_transient('b2_authorization_token', $auth_token, 1 * HOUR_IN_SECONDS);
        set_transient('b2_api_url', $data->apiUrl, 1 * HOUR_IN_SECONDS);
    } else {
        $auth_token = $s3_token;
    }

    // Handle large files via multipart upload
    $file_size = filesize($file_path);
    $max_part_size = 100 * 1024 * 1024; // 50MB max part size
    $is_large_file = $file_size > $max_part_size;

    if ($is_large_file) {
        return self::handle_large_file_upload($file_path, $file_name, $auth_token, $bucket_id, $max_part_size);
    }

    // If it's not a large file, proceed with single file upload
    return self::upload_single_file($file_path, $file_name, $auth_token, $bucket_id);
}

// Function to handle large file multipart upload
public static function handle_large_file_upload($file_path, $file_name, $auth_token, $bucket_id, $max_part_size) {
    global $wp_filesystem;
    if (!function_exists('WP_Filesystem')) {
        require_once(ABSPATH . '/wp-admin/includes/file.php');
    }
    WP_Filesystem();

    if (!$wp_filesystem->exists($file_path)) {
        return array('success' => false, 'message' => esc_html__('File does not exist: ', 'wpdbbkp') . $file_path);
    }

    $root_path = str_replace('\\', '/', ABSPATH); // Normalize to forward slashes for consistency
    $file_name = str_replace($root_path, '', $file_name);
    $file_name = ltrim($file_name, '/'); // Ensure there is no leading slash
    $file_name = 'wpdbbkp/' . $file_name;

    // Start large file upload
    $start_large_file_url = get_transient('b2_api_url') . '/b2api/v2/b2_start_large_file';
    $response = wp_remote_post($start_large_file_url, array(
        'body' => wp_json_encode(array(
            'bucketId' => $bucket_id,
            'fileName' => $file_name,
            'contentType' => 'b2/x-auto'
        )),
        'headers' => array(
            'Authorization' => $auth_token,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 60
    ));
    if (is_wp_error($response)) {
        return array('success' => false, 'message' => esc_html__('Failed to start large file upload: ', 'wpdbbkp') . $response->get_error_message());
    }

    $data = json_decode(wp_remote_retrieve_body($response));
    $file_id = $data->fileId;

    $file_size = filesize($file_path);
    $part_size = 100 * 1024 * 1024; // 100MB per part
    $num_parts = ceil($file_size / $part_size); // Calculate the number of parts

    $handle = fopen($file_path, 'rb'); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen --required for large files
    $part_sha1_array = array(); 

    for ($i = 0; $i < $num_parts; $i++) {
        // Get a new upload part URL for each part
        $get_upload_part_url = get_transient('b2_api_url') . '/b2api/v2/b2_get_upload_part_url';
        $response_2 = wp_remote_post($get_upload_part_url, array(
            'body' => wp_json_encode(array(
                'fileId' => $file_id
            )),
            'headers' => array(
                'Authorization' => $auth_token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 60
        ));

        if (is_wp_error($response_2)) {
            fclose($handle); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose --required for large files
            return array('success' => false, 'message' => esc_html__('Failed to get upload part URL: ', 'wpdbbkp') . $response_2->get_error_message());
        }

        $data_2 = json_decode(wp_remote_retrieve_body($response_2));
        $upload_part_url = $data_2->uploadUrl;
        $upload_part_auth_token = $data_2->authorizationToken;

        // Read the part from the file
        $file_part = fread($handle, $part_size); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread --required for large files
        if ($file_part === false) {
            fclose($handle); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose --required for large files
            return array('success' => false, 'message' => esc_html__('Failed to read part ', 'wpdbbkp') . $i . ' from file.');
        }

        $sha1_of_part = sha1($file_part);
        $part_sha1_array[] = $sha1_of_part;

        // Upload each part to Backblaze
        $response = wp_remote_post($upload_part_url, array(
            'body' => $file_part,
            'headers' => array(
                'Authorization' => $upload_part_auth_token,
                'X-Bz-Part-Number' => ($i + 1),
                'X-Bz-Content-Sha1' => $sha1_of_part,
                'Content-Length' => strlen($file_part)
            ),
            'timeout' => 1800 // 15-minute timeout for large file uploads
        ));

        if (is_wp_error($response)) {
            fclose($handle); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose --required for large files
            return array('success' => false, 'message' => esc_html__('Upload request failed for part ', 'wpdbbkp') . $i . ': ' . $response->get_error_message());
        }

        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code != 200) {
            fclose($handle); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose --required for large files
            return array('success' => false, 'message' => esc_html__('Failed to upload part ', 'wpdbbkp') . $i);
        }
    }

    fclose($handle); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose --required for large files

    // Finalize large file upload
    $finish_large_file_url = get_transient('b2_api_url') . '/b2api/v2/b2_finish_large_file';
    $response = wp_remote_post($finish_large_file_url, array(
        'body' => wp_json_encode(array(
            'fileId' => $file_id,
            'partSha1Array' =>  $part_sha1_array
        )),
        'headers' => array(
            'Authorization' => $auth_token,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 60
    ));

    if (is_wp_error($response)) {
        return array('success' => false, 'message' => esc_html__('Failed to finalize large file upload: ', 'wpdbbkp') . $response->get_error_message());
    }

    return array('success' => true, 'message' => 'Large file ' . $file_name . esc_html__(' uploaded successfully to Backblaze.', 'wpdbbkp'));
}


public static function upload_single_file($file_path, $file_name, $auth_token, $bucket_id) {
    global $wp_filesystem;
    $root_path = str_replace('\\', '/', ABSPATH); // Normalize to forward slashes for consistency
    $file_name = str_replace($root_path, '', $file_name);
    $file_name = ltrim($file_name, '/'); // Ensure there is no leading slash
    $file_name = 'wpdbbkp/'.$file_name;
    // Get upload URL
    $upload_url = get_transient('b2_api_url') . '/b2api/v2/b2_get_upload_url';
    $response = wp_remote_post($upload_url, array(
        'body' => wp_json_encode(array('bucketId' => $bucket_id)),
        'headers' => array(
            'Authorization' => $auth_token,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 60
    ));

    if (is_wp_error($response)) {
        return array('success' => false, 'message' => esc_html__('Failed to get upload URL: ', 'wpdbbkp') . $response->get_error_message());
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);
    if (empty($data->uploadUrl)) {
        return array('success' => false, 'message' => esc_html__('Failed to get upload URL from Backblaze.', 'wpdbbkp'));
    }

    $upload_url = $data->uploadUrl;
    $upload_auth_token = $data->authorizationToken;

    // Check if file exists
    if (!$wp_filesystem->exists($file_path)) {
        return array('success' => false, 'message' => esc_html__('File does not exist: ', 'wpdbbkp') . $file_path);
    }

    $file_contents = $wp_filesystem->get_contents($file_path);
    if ($file_contents === false) {
        return array('success' => false, 'message' => esc_html__('Failed to read file: ', 'wpdbbkp') . $file_path);
    }

    $sha1_of_file_data = sha1($file_contents);

    // Upload the file
    $response = wp_remote_post($upload_url, array(
        'body' => $file_contents,
        'headers' => array(
            'Authorization' => $upload_auth_token,
            'X-Bz-File-Name' => $file_name,
            'Content-Type' => 'b2/x-auto',
            'X-Bz-Content-Sha1' => $sha1_of_file_data
        ),
        'timeout' => 900
    ));

    if (is_wp_error($response)) {
        return array('success' => false, 'message' => esc_html__('Upload request failed: ', 'wpdbbkp') . $response->get_error_message());
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code != 200) {
        $response_body = wp_remote_retrieve_body($response);
        return array('success' => false, 'message' => esc_html__('Failed to upload ', 'wpdbbkp') . $file_name . '. Response: ' . $response_body);
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
