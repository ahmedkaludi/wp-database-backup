<?php
/**
 * Destination CloudDrive.
 *
 * @package wpdbbkp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}




add_action( 'wp_db_backup_completed', array( 'WPDatabaseBackupCD', 'wp_db_backup_completed' ) );

/**
 * WPDatabaseBackupCD Class.
 *
 * @class WPDatabaseBackupCD
 */
class WPDatabaseBackupCD {

// Function to upload files to Backblaze B2
public static function upload_backup_to_clouddrive($file_path, $file_name) {

    global $wp_filesystem;
    if(!function_exists('WP_Filesystem')){
    require_once ( ABSPATH . '/wp-admin/includes/file.php' );
    }
    WP_Filesystem();

    $api_url ="https://app.backupforwp.com/public";

    $token = get_option('wpdb_clouddrive_token') ? get_option('wpdb_clouddrive_token') : '';

    if(!$token){
        return array('success' => false, 'message' => esc_html__('Cloud Backup token not found. Please enter your Cloud Backup token in the settings.', 'wpdbbkp'));
    }
  
    $upload_auth_token = 'Bearer '.$token;
    $upload_url = $api_url . '/api/v1/file/upload';
    

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

    $root_path = str_replace('\\', '/', ABSPATH); // Normalize to forward slashes for consistency
    $file_path = str_replace($root_path, '', $file_path);
    $file_path = ltrim($file_path, '/'); // Ensure there is no leading slash


    $file_data = $file_contents;

    $boundary = wp_generate_password( 24 );

    $headers = array(
        'Authorization' => $upload_auth_token,
        'domain'=> parse_url(get_site_url(), PHP_URL_HOST),
        'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
    );

    $body = "--{$boundary}\r\n";
    $body .= 'Content-Disposition: form-data; name="file"; filename="' . $file_name . '"' . "\r\n";
    $body .= "file_contents-Type: application/octet-stream\r\n\r\n";
    $body .= $file_data . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= 'Content-Disposition: form-data; name="filename"' . "\r\n\r\n";
    $body .= $file_path . "\r\n";
    $body .= "--{$boundary}\r\n";

    $response = wp_remote_post( $upload_url, array(
        'headers' => $headers,
        'body'    => $body,
        'timeout' => 300,
    ) );


    if (is_wp_error($response)) {
        return array('success' => false, 'message' => esc_html__('Upload request failed: ', 'wpdbbkp') . $response->get_error_message());
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code != 200) {
        $response_body = wp_remote_retrieve_body($response);
        return array('success' => false, 'message' => esc_html__('Failed to upload ' , 'wpdbbkp'). $file_name . ' to Cloud Backup. Response: ' . $response_body);
    }

    return array('success' => true, 'message' => 'File ' . $file_name . esc_html__(' uploaded successfully to Cloud Backup.', 'wpdbbkp'));
}


	/**
	 * Run after complete backup.
	 *
	 * @param array $args - backup details.
	 */

	public static function wp_db_backup_completed( &$args ) {
		$destination_cd = get_option( 'wpdb_clouddrive_token' , false);
		if ( $destination_cd && !empty($destination_cd)) {
			
			try {
		
                $ret = WPDatabaseBackupCD::upload_backup_to_clouddrive($args[1], $args[1]);
				$args[2] = $args[2] .$ret['message'];
                if ($ret['success']) {
                    $args[4] .= 'CloudDrive, ';
                }
                return $ret;	
			} catch ( Exception $e ) {
				$args[2] = $args[2] . "<br>".esc_html__("Failed to upload Database Backup on Cloud Backup", 'wpdbbkp');
                return $e;	
			}
		}
	}

    public // Function to handle the file upload
    function wp_db_handle_file_upload($file_path) {
        // Prepare file array for sideload
        $file_array = array(
            'name'     => basename($file_path),
            'tmp_name' => $file_path,
        );
    
        // Include WordPress file handling functions
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
    
        // Handle the file upload
        $uploaded_file = media_handle_sideload($file_array, 0);
    
        if (is_wp_error($uploaded_file)) {
            return $uploaded_file; // Return the WP_Error object
        }
    
        return get_attached_file($uploaded_file);
    }
	
}
