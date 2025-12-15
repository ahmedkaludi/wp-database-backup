<?php
/**
 * Destination Generic S3.
 *
 * @package wpdbbkp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'wp_db_backup_completed', array( 'WPDatabaseBackupGenericS3', 'wp_db_backup_completed' ) );
add_action( 'wp_ajax_test_generics3_connection', array( 'WPDatabaseBackupGenericS3', 'ajax_test_connection' ) );

/**
 * WPDatabaseBackupGenericS3 Class.
 *
 * @class WPDatabaseBackupGenericS3
 */
class WPDatabaseBackupGenericS3 {

	/**
	 * AJAX handler for testing Generic S3 connection
	 */
	public static function ajax_test_connection() {
		// Check nonce for security
		if ( ! wp_verify_nonce( $_POST['nonce'], 'test_generics3_connection' ) ) {
			wp_die( 'Security check failed' );
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions' );
		}

		$results = self::testConnection();

		if ( $results['success'] ) {
			wp_send_json_success( $results );
		} else {
			wp_send_json_error( $results );
		}
	}

	/**
	 * Test AWS4 signature generation (for debugging)
	 *
	 * @param string $access_key Access key.
	 * @param string $secret_key Secret key.
	 * @param string $endpoint   Endpoint.
	 * @return array Test signature results
	 */
	public static function testSignature( $access_key, $secret_key, $endpoint ) {
		$client = new self( $access_key, $secret_key, $endpoint );

		// Test basic signature generation
		$test_headers = array(
			'host' => parse_url( $endpoint, PHP_URL_HOST ),
			'x-amz-date' => gmdate( 'Ymd\THis\Z' ),
			'x-amz-content-sha256' => hash( 'sha256', '' ),
		);

		$canonical_headers = $client->getCanonicalHeaders( $test_headers );
		$signed_headers = $client->getSignedHeaders( $test_headers );

		return array(
			'canonical_headers' => $canonical_headers,
			'signed_headers' => $signed_headers,
			'region' => $client->getRegionFromEndpoint(),
		);
	}

	/**
	 * Test connection to Generic S3
	 *
	 * @return array Test results
	 */
	public static function testConnection() {
		$results = array(
			'success' => false,
			'messages' => array(),
			'endpoint' => get_option( 'wpdb_dest_generics3_endpoint' ),
			'bucket' => get_option( 'wpdb_dest_generics3_bucket' ),
		);

		try {
			$access_key = get_option( 'wpdb_dest_generics3_bucket_key' );
			$secret_key = get_option( 'wpdb_dest_generics3_bucket_secret' );
			$endpoint   = get_option( 'wpdb_dest_generics3_endpoint' );
			$bucket_name = get_option( 'wpdb_dest_generics3_bucket' );

			if ( empty( $access_key ) || empty( $secret_key ) || empty( $endpoint ) || empty( $bucket_name ) ) {
				$results['messages'][] = 'Missing configuration: Access Key, Secret Key, Endpoint, or Bucket Name';
				return $results;
			}

			$results['messages'][] = 'Initializing S3 client with endpoint: ' . $endpoint;

			$s3 = new WPDatabaseBackupGenericS3Client( $access_key, $secret_key, $endpoint );

			$results['messages'][] = 'Testing bucket listing...';
			$buckets = $s3->listBuckets();

			if ( $buckets !== false ) {
				$results['messages'][] = 'Successfully listed buckets: ' . count( $buckets ) . ' found';

				$bucket_exists = false;
				foreach ( $buckets as $bucket ) {
					if ( isset( $bucket['name'] ) && $bucket['name'] === $bucket_name ) {
						$bucket_exists = true;
						break;
					}
				}

				if ( $bucket_exists ) {
					$results['messages'][] = 'Bucket exists: ' . $bucket_name;
					$results['success'] = true;
				} else {
					$results['messages'][] = 'Bucket does not exist: ' . $bucket_name . ' - attempting to create it';
					if ( $s3->putBucket( $bucket_name ) ) {
						$results['messages'][] = 'Bucket is ready for use: ' . $bucket_name . ' (created or already exists)';
						$results['success'] = true;
					} else {
						$results['messages'][] = 'Failed to create bucket: ' . $bucket_name;
					}
				}
			} else {
				$results['messages'][] = 'Failed to list buckets - check credentials and endpoint';
			}

		} catch ( Exception $e ) {
			$results['messages'][] = 'Error: ' . $e->getMessage();
		}

		return $results;
	}

	/**
	 * Run after complete backup.
	 *
	 * @param array $args - backup details.
	 */
	public static function wp_db_backup_completed( &$args ) {
		$destination_generics3 = get_option( 'wp_db_backup_destination_generics3' );
		if ( isset( $destination_generics3 ) && 1 == $destination_generics3 &&
		     get_option( 'wpdb_dest_generics3_bucket' ) &&
		     get_option( 'wpdb_dest_generics3_bucket_key' ) &&
		     get_option( 'wpdb_dest_generics3_bucket_secret' ) &&
		     get_option( 'wpdb_dest_generics3_endpoint' ) ) {

			update_option('wpdbbkp_backupcron_current','Processing Generic S3 Backup', false);
			try {
				// Generic S3 access info.
				$access_key = get_option( 'wpdb_dest_generics3_bucket_key' );
				$secret_key = get_option( 'wpdb_dest_generics3_bucket_secret' );
				$endpoint   = get_option( 'wpdb_dest_generics3_endpoint' );
				$bucket_name = get_option( 'wpdb_dest_generics3_bucket' );

				// Check for CURL.
				if ( ! extension_loaded( 'curl' ) && ! @dl( 'so' === PHP_SHLIB_SUFFIX ? 'curl.so' : 'php_curl.dll' ) ) { // phpcs:ignore
					$message_error = 'No Curl';
					$args[2] = $args[2] . '<br>'.esc_html__('Failed to upload Database Backup on Generic S3 - CURL extension not loaded','wpdbbkp');
					return;
				}

				// Initialize S3 client with custom endpoint
				$s3 = new WPDatabaseBackupGenericS3Client( $access_key, $secret_key, $endpoint );

				// Verify bucket exists or try to create it
				$bucket_exists = false;
				try {
					$buckets = $s3->listBuckets();
					if ( ! empty( $buckets ) ) {
						foreach ( $buckets as $bucket ) {
							if ( isset( $bucket['name'] ) && $bucket['name'] === $bucket_name ) {
								$bucket_exists = true;
								break;
							}
						}
					}
				} catch ( Exception $bucket_error ) {
					// If listing fails, try to create bucket directly
					error_log( 'GenericS3: Failed to list buckets: ' . $bucket_error->getMessage() );
				}

				if ( ! $bucket_exists ) {
					// Try to create the bucket
					try {
						if ( $s3->putBucket( $bucket_name ) ) {
							$bucket_exists = true;
							error_log( 'GenericS3: Bucket is ready for use: ' . $bucket_name );
						} else {
							error_log( 'GenericS3: Failed to create bucket: ' . $bucket_name );
						}
					} catch ( Exception $create_error ) {
						error_log( 'GenericS3: Failed to create bucket: ' . $create_error->getMessage() );
					}
				}

				if ( $bucket_exists ) {
					// Upload the backup file
					if ( file_exists( $args[1] ) ) {
						if ( $s3->putObjectFile( $args[1], $bucket_name, basename( $args[1] ), 'private' ) ) {
							$args[2] = $args[2] . '<br> '.esc_html__('Upload Database Backup on Generic S3 bucket','wpdbbkp') . ' ' . $bucket_name;
						} else {
							$args[2] = $args[2] . '<br>'.esc_html__('Failed to upload Database Backup on Generic S3 bucket','wpdbbkp') . ' ' . $bucket_name . ' - Check file permissions and S3 credentials';
							$args[4] = $args[4] .= 'Generic S3, ';
						}
					} else {
						$args[2] = $args[2] . '<br>'.esc_html__('Backup file not found for Generic S3 upload','wpdbbkp') . ': ' . $args[1];
						$args[4] = $args[4] .= 'Generic S3, ';
					}
				} else {
					$args[2] = $args[2] . '<br>'.esc_html__('Invalid bucket name or Generic S3 details','wpdbbkp') . ' - Bucket: ' . $bucket_name;
					$args[4] = $args[4] .= 'Generic S3, ';
				}

			} catch ( Exception $e ) {
				$args[2] = $args[2] . '<br>'.esc_html__('Failed to upload Database Backup on Generic S3 - Error: ','wpdbbkp') . $e->getMessage();
				$args[4] = $args[4] .= 'Generic S3, ';
			}
		}
	}
}

/**
 * Generic S3 Client Class for S3-compatible storage.
 *
 * @class WPDatabaseBackupGenericS3Client
 */
class WPDatabaseBackupGenericS3Client {

	/**
	 * AWS Access key
	 *
	 * @var string
	 */
	private $accessKey = null;

	/**
	 * AWS Secret Key
	 *
	 * @var string
	 */
	private $secretKey = null;

	/**
	 * Custom endpoint URL
	 *
	 * @var string
	 */
	private $endpoint = null;

	/**
	 * Constructor
	 *
	 * @param string $accessKey AWS Access key.
	 * @param string $secretKey AWS Secret Key.
	 * @param string $endpoint  Custom endpoint URL.
	 */
	public function __construct( $accessKey, $secretKey, $endpoint ) {
		$this->accessKey = $accessKey;
		$this->secretKey = $secretKey;
		$this->endpoint  = rtrim( $endpoint, '/' );
	}

	/**
	 * List buckets
	 *
	 * @return array|bool
	 */
	public function listBuckets() {
		$request = array(
			'verb'     => 'GET',
			'bucket'   => '',
			'resource' => '',
		);

		$result = $this->sendRequest( $request );

		if ( $result && isset( $result['body'] ) ) {
			return $this->parseBucketList( $result['body'] );
		}

		return false;
	}

	/**
	 * Create bucket
	 *
	 * @param string $bucket Bucket name.
	 * @return bool
	 */
	public function putBucket( $bucket ) {
		$request = array(
			'verb'     => 'PUT',
			'bucket'   => $bucket,
			'resource' => '/',
		);

		$result = $this->sendRequest( $request );

		// For bucket creation, both 200 (created) and 409 (already exists) are success
		if ( $result && $result['status'] === 409 ) {
			error_log( 'GenericS3: Bucket already exists: ' . $bucket );
			return true; // Bucket already exists, which is fine
		}

		return ( $result && $result['status'] >= 200 && $result['status'] < 300 );
	}

	/**
	 * Upload file to bucket
	 *
	 * @param string $filePath Local file path.
	 * @param string $bucket   Bucket name.
	 * @param string $uri      Object URI.
	 * @param string $acl      ACL permission.
	 * @return bool
	 */
	public function putObjectFile( $filePath, $bucket, $uri, $acl = 'private' ) {
		if ( ! file_exists( $filePath ) ) {
			error_log( 'GenericS3: File does not exist: ' . $filePath );
			return false;
		}

		$filedata = file_get_contents( $filePath );
		if ( false === $filedata ) {
			error_log( 'GenericS3: Failed to read file: ' . $filePath );
			return false;
		}

		$file_size = strlen( $filedata );
		error_log( 'GenericS3: Attempting to upload file ' . $filePath . ' (' . $file_size . ' bytes) to bucket ' . $bucket . ' with URI ' . $uri );

		$content_type = $this->getMimeType( $filePath );
		$content_md5 = base64_encode( md5( $filedata, true ) );

		$request = array(
			'verb'     => 'PUT',
			'bucket'   => $bucket,
			'resource' => '/' . $uri,
			'headers'  => array(
				'Content-Type'   => $content_type,
				'Content-MD5'    => $content_md5,
				'Content-Length' => strlen( $filedata ),
				'x-amz-acl'      => $acl,
			),
			'body'     => $filedata,
		);

		$result = $this->sendRequest( $request );

		$success = ( $result && $result['status'] >= 200 && $result['status'] < 300 );
		if ( $success ) {
			error_log( 'GenericS3: Successfully uploaded file to ' . $bucket . '/' . $uri );
		} else {
			error_log( 'GenericS3: Failed to upload file to ' . $bucket . '/' . $uri . ' - Status: ' . ( $result ? $result['status'] : 'No response' ) );
		}

		return $success;
	}

	/**
	 * Send HTTP request to S3
	 *
	 * @param array $request Request parameters.
	 * @return array|bool
	 */
	private function sendRequest( $request ) {
		$verb     = isset( $request['verb'] ) ? $request['verb'] : 'GET';
		$bucket   = isset( $request['bucket'] ) ? $request['bucket'] : '';
		$resource = isset( $request['resource'] ) ? $request['resource'] : '/';
		$headers  = isset( $request['headers'] ) ? $request['headers'] : array();
		$body     = isset( $request['body'] ) ? $request['body'] : '';

		// Build URL - handle different S3-compatible endpoint formats
		$url = rtrim( $this->endpoint, '/' );

		// For AWS S3-like endpoints, the bucket is part of the domain
		if ( strpos( $this->endpoint, 'amazonaws.com' ) !== false && ! empty( $bucket ) ) {
			$url = str_replace( 's3.', 's3-' . $bucket . '.', $url );
			$url .= $resource;
		} elseif ( strpos( $this->endpoint, 'amazonaws.com' ) !== false && empty( $bucket ) ) {
			// For AWS S3 bucket listing, use the base endpoint
			$url .= $resource;
		} else {
			// For other S3-compatible services, bucket is part of the path
			if ( ! empty( $bucket ) ) {
				$url .= '/' . $bucket;
			}
			// For bucket listing, ensure we have a trailing slash
			if ( empty( $bucket ) && empty( $resource ) ) {
				$url .= '/';
			} else {
				$url .= $resource;
			}
		}

		// Build AWS4 headers
		$amz_date = gmdate( 'Ymd\THis\Z' );
		$date_stamp = gmdate( 'Ymd' );
		$region = $this->getRegionFromEndpoint();
		$service = 's3';

		$headers['x-amz-date'] = $amz_date;
		$headers['Host'] = parse_url( $this->endpoint, PHP_URL_HOST );

		// Add content hash for AWS4
		$body = isset( $request['body'] ) ? $request['body'] : '';
		$headers['x-amz-content-sha256'] = hash( 'sha256', $body );

		// Generate AWS4 signature
		$canonical_uri = $this->getCanonicalUri( $bucket, $resource );
		$canonical_querystring = '';
		$canonical_headers = $this->getCanonicalHeaders( $headers );
		$signed_headers = $this->getSignedHeaders( $headers );

		$canonical_request = $this->createCanonicalRequest( $verb, $canonical_uri, $canonical_querystring, $canonical_headers, $signed_headers, $headers['x-amz-content-sha256'] );

		$credential_scope = $date_stamp . '/' . $region . '/' . $service . '/aws4_request';
		$string_to_sign = $this->createStringToSign( $amz_date, $credential_scope, $canonical_request );

		$signature = $this->calculateSignature( $date_stamp, $region, $service, $string_to_sign );

		$authorization_header = 'AWS4-HMAC-SHA256 Credential=' . $this->accessKey . '/' . $credential_scope . ', SignedHeaders=' . $signed_headers . ', Signature=' . $signature;
		$headers['Authorization'] = $authorization_header;

		// Debug logging for AWS4 signature (only on errors)
		if ( isset( $_GET['debug_generics3'] ) ) {
			error_log( 'GenericS3: AWS4 Signature Details - URL: ' . $url . ', Region: ' . $region . ', Credential Scope: ' . $credential_scope );
			error_log( 'GenericS3: Canonical URI: ' . $canonical_uri );
			error_log( 'GenericS3: Authorization: ' . substr( $authorization_header, 0, 100 ) . '...' );
		}

		// Make request
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $verb );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $this->buildHeadersArray( $headers ) );

		if ( ! empty( $body ) ) {
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
		}

		// SSL settings
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );

		$response = curl_exec( $ch );
		$status   = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$error    = curl_error( $ch );

		curl_close( $ch );

		if ( $error ) {
			error_log( 'GenericS3: CURL error - ' . $error . ' for URL: ' . $url );
			return false;
		}

		// Log non-success status codes for debugging
		if ( $status < 200 || $status >= 300 ) {
			error_log( 'GenericS3: HTTP ' . $status . ' for URL: ' . $url . ' Response: ' . substr( $response, 0, 500 ) );
		}

		return array(
			'status' => $status,
			'body'   => $response,
		);
	}

	/**
	 * Get region from endpoint URL
	 *
	 * @return string
	 */
	private function getRegionFromEndpoint() {
		$host = parse_url( $this->endpoint, PHP_URL_HOST );

		// For AWS S3 endpoints
		if ( strpos( $host, 's3.' ) === 0 && strpos( $host, '.amazonaws.com' ) !== false ) {
			$parts = explode( '.', $host );
			if ( count( $parts ) >= 3 ) {
				return $parts[1];
			}
		}

		// For other S3-compatible services, try to extract region from URL
		if ( preg_match( '/([a-z0-9-]+)\.digitaloceanspaces\.com/', $host, $matches ) ) {
			return $matches[1];
		}

		if ( preg_match( '/([a-z0-9-]+)\.linodeobjects\.com/', $host, $matches ) ) {
			return $matches[1];
		}

		// For Backblaze B2
		if ( strpos( $host, 'backblazeb2.com' ) !== false ) {
			if ( preg_match( '/s3\.([a-z0-9-]+)\.backblazeb2\.com/', $host, $matches ) ) {
				return $matches[1];
			}
			// Default Backblaze region
			return 'us-west-002';
		}

		// Default to us-east-1 for compatibility
		return 'us-east-1';
	}

	/**
	 * Get canonical URI
	 *
	 * @param string $bucket Bucket name.
	 * @param string $resource Resource path.
	 * @return string
	 */
	private function getCanonicalUri( $bucket, $resource ) {
		// For bucket listing operations (empty bucket), canonical URI should be '/'
		if ( empty( $bucket ) ) {
			return '/';
		}

		if ( strpos( $this->endpoint, 'amazonaws.com' ) !== false ) {
			// For AWS S3, bucket is in domain, so canonical URI doesn't include bucket
			return $resource;
		} else {
			// For other services, bucket is in path
			return '/' . $bucket . $resource;
		}
	}

	/**
	 * Get canonical headers string
	 *
	 * @param array $headers Headers array.
	 * @return string
	 */
	private function getCanonicalHeaders( $headers ) {
		$canonical_headers = '';
		ksort( $headers );
		foreach ( $headers as $key => $value ) {
			$canonical_headers .= strtolower( $key ) . ':' . trim( $value ) . "\n";
		}
		return $canonical_headers;
	}

	/**
	 * Get signed headers string
	 *
	 * @param array $headers Headers array.
	 * @return string
	 */
	private function getSignedHeaders( $headers ) {
		$signed_headers = array();
		foreach ( $headers as $key => $value ) {
			$signed_headers[] = strtolower( $key );
		}
		sort( $signed_headers );
		return implode( ';', $signed_headers );
	}

	/**
	 * Create canonical request
	 *
	 * @param string $method HTTP method.
	 * @param string $canonical_uri Canonical URI.
	 * @param string $canonical_querystring Canonical query string.
	 * @param string $canonical_headers Canonical headers.
	 * @param string $signed_headers Signed headers.
	 * @param string $payload_hash Payload hash.
	 * @return string
	 */
	private function createCanonicalRequest( $method, $canonical_uri, $canonical_querystring, $canonical_headers, $signed_headers, $payload_hash ) {
		return $method . "\n" .
			   $canonical_uri . "\n" .
			   $canonical_querystring . "\n" .
			   $canonical_headers . "\n" .
			   $signed_headers . "\n" .
			   $payload_hash;
	}

	/**
	 * Create string to sign
	 *
	 * @param string $amz_date AMZ date.
	 * @param string $credential_scope Credential scope.
	 * @param string $canonical_request Canonical request.
	 * @return string
	 */
	private function createStringToSign( $amz_date, $credential_scope, $canonical_request ) {
		$canonical_request_hash = hash( 'sha256', $canonical_request );
		return "AWS4-HMAC-SHA256\n" . $amz_date . "\n" . $credential_scope . "\n" . $canonical_request_hash;
	}

	/**
	 * Calculate signature
	 *
	 * @param string $date_stamp Date stamp.
	 * @param string $region Region.
	 * @param string $service Service.
	 * @param string $string_to_sign String to sign.
	 * @return string
	 */
	private function calculateSignature( $date_stamp, $region, $service, $string_to_sign ) {
		$k_date = hash_hmac( 'sha256', $date_stamp, 'AWS4' . $this->secretKey, true );
		$k_region = hash_hmac( 'sha256', $region, $k_date, true );
		$k_service = hash_hmac( 'sha256', $service, $k_region, true );
		$k_signing = hash_hmac( 'sha256', 'aws4_request', $k_service, true );

		return hash_hmac( 'sha256', $string_to_sign, $k_signing );
	}

	/**
	 * Build headers array for curl
	 *
	 * @param array $headers Headers array.
	 * @return array
	 */
	private function buildHeadersArray( $headers ) {
		$result = array();
		foreach ( $headers as $key => $value ) {
			$result[] = $key . ': ' . $value;
		}
		return $result;
	}

	/**
	 * Get MIME type of file
	 *
	 * @param string $filePath File path.
	 * @return string
	 */
	private function getMimeType( $filePath ) {
		$mime_types = array(
			'txt'  => 'text/plain',
			'htm'  => 'text/html',
			'html' => 'text/html',
			'php'  => 'text/html',
			'css'  => 'text/css',
			'js'   => 'application/javascript',
			'json' => 'application/json',
			'xml'  => 'application/xml',
			'swf'  => 'application/x-shockwave-flash',
			'flv'  => 'video/x-flv',
			'png'  => 'image/png',
			'jpe'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'jpg'  => 'image/jpeg',
			'gif'  => 'image/gif',
			'bmp'  => 'image/bmp',
			'ico'  => 'image/vnd.microsoft.icon',
			'tiff' => 'image/tiff',
			'tif'  => 'image/tiff',
			'svg'  => 'image/svg+xml',
			'svgz' => 'image/svg+xml',
			'zip'  => 'application/zip',
			'rar'  => 'application/x-rar-compressed',
			'exe'  => 'application/x-msdownload',
			'msi'  => 'application/x-msdownload',
			'cab'  => 'application/vnd.ms-cab-compressed',
			'mp3'  => 'audio/mpeg',
			'qt'   => 'video/quicktime',
			'mov'  => 'video/quicktime',
			'pdf'  => 'application/pdf',
			'psd'  => 'image/vnd.adobe.photoshop',
			'ai'   => 'application/postscript',
			'eps'  => 'application/postscript',
			'ps'   => 'application/postscript',
			'doc'  => 'application/msword',
			'rtf'  => 'application/rtf',
			'xls'  => 'application/vnd.ms-excel',
			'ppt'  => 'application/vnd.ms-powerpoint',
			'odt'  => 'application/vnd.oasis.opendocument.text',
			'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
		);

		$ext = strtolower( pathinfo( $filePath, PATHINFO_EXTENSION ) );
		if ( array_key_exists( $ext, $mime_types ) ) {
			return $mime_types[ $ext ];
		} elseif ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME );
			$mimetype = finfo_file( $finfo, $filePath );
			finfo_close( $finfo );
			return $mimetype;
		} else {
			return 'application/octet-stream';
		}
	}

	/**
	 * Parse bucket list XML response
	 *
	 * @param string $xml XML response.
	 * @return array
	 */
	private function parseBucketList( $xml ) {
		$buckets = array();

		if ( empty( $xml ) ) {
			return $buckets;
		}

		// Simple XML parsing for bucket list
		if ( strpos( $xml, '<ListAllMyBucketsResult' ) !== false ) {
			preg_match_all( '/<Bucket><Name>([^<]+)<\/Name><CreationDate>([^<]+)<\/CreationDate><\/Bucket>/', $xml, $matches );
			if ( isset( $matches[1] ) && isset( $matches[2] ) ) {
				foreach ( $matches[1] as $index => $name ) {
					$buckets[] = array(
						'name' => $name,
						'time' => isset( $matches[2][ $index ] ) ? $matches[2][ $index ] : '',
					);
				}
			}
		}

		return $buckets;
	}
}
