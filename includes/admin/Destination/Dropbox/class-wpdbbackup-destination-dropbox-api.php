<?php
//phpcs:ignoreFile -- Thirdparty code.
/**
 * Class for communicating with Dropbox API V2.
 *
 * @package wpdbbkp
 */

if ( ! class_exists( 'WPDBBackup_Destination_Dropbox_API' ) ) {
	/**
	 * Destination backup.
	 *
	 * @class WPDBBackup_Destination_Dropbox_API
	 */
	final class WPDBBackup_Destination_Dropbox_API {


		/**
		 * URL to Dropbox API endpoint.
		 */
		const API_URL = 'https://api.dropboxapi.com/';

		/**
		 * URL to Dropbox content endpoint.
		 */
		const API_CONTENT_URL = 'https://content.dropboxapi.com/';

		/**
		 * URL to Dropbox for authentication.
		 */
		const API_WWW_URL = 'https://www.dropbox.com/';

		/**
		 * API version.
		 */
		const API_VERSION_URL = '2/';

		/**
		 * oAuth vars
		 *
		 * @var string
		 */
		private $oauth_app_key = '';

		/**
		 * @var string
		 */
		private $oauth_app_secret = '';

		/**
		 * @var string
		 */
		private $oauth_token = '';

		/**
		 * Job object for logging.
		 *
		 * @var WPDBBackup_Job
		 */
		private $job_object;

		/**
		 * Constructor function.
		 *
		 * @param string         $boxtype - destination type.
		 * @param WPDBBackup_Job $job_object - Job details.
		 *
		 * @throws WPDBBackup_Destination_Dropbox_API_Exception - Exception handling.
		 */
		public function __construct( $boxtype = 'dropbox', WPDBBackup_Job $job_object = null ) {
			if ( 'dropbox' === $boxtype ) {
				$this->oauth_app_key    = 'cv3o964lig1qrga';
				$this->oauth_app_secret = '7g05tjesk5fgqjk';
			} else {
				$this->oauth_app_key    = 'cv3o964lig1qrga';
				$this->oauth_app_secret = '7g05tjesk5fgqjk';
			}

			if ( empty( $this->oauth_app_key ) || empty( $this->oauth_app_secret ) ) {
				throw new WPDBBackup_Destination_Dropbox_API_Exception( 'No App key or App Secret specified.' );
			}
			$default =[
				'step_working' => '',
				'steps_data'=> array(),
				'job'=>array(),
			 ];
			
			$this->job_object = $job_object?$job_object:json_decode(wp_json_encode($default));
		}

		// Helper methods.

		/**
		 * List a folder
		 *
		 * This is a helper method to use filesListFolder and
		 * filesListFolderContinue to construct an array of files within a given
		 * folder path.
		 *
		 * @param string $path - Path.
		 *
		 * @return array
		 */
		public function list_Folder( $path ) {
			$files  = array();
			$result = $this->filesListFolder( array( 'path' => $path ) );
			if ( ! $result ) {
				return array();
			}

			$files = array_merge( $files, $result['entries'] );

			$args = array( 'cursor' => $result['cursor'] );

			while ( $result['has_more'] == true ) {
				$result = $this->filesListFolderContinue( $args );
				$files  = array_merge( $files, $result['entries'] );
			}

			return $files;
		}

		/**
		 * Uploads a file to Dropbox.
		 *
		 * @param        $file
		 * @param string $path
		 * @param bool   $overwrite
		 *
		 * @return array
		 * @throws WPDBBackup_Destination_Dropbox_API_Exception
		 */
		public function upload( $file, $path = '', $overwrite = true ) {
			$file = str_replace( '\\', '/', $file );
			$output ='';
			if ( ! is_readable( $file ) ) {
				throw new WPDBBackup_Destination_Dropbox_API_Exception( "Error: File ".esc_url($file)." is not readable or doesn't exist." );
			}

			if ( filesize( $file ) < 5242880 ) { // chunk transfer on bigger uploads
				global $wp_filesystem;

				// Initialize the WordPress filesystem if it hasn't been initialized yet.
				if ( ! function_exists( 'WP_Filesystem' ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
				}

				WP_Filesystem();

				$file_content = '';
				if ( $wp_filesystem->exists( $file ) ) {
					$file_content = $wp_filesystem->get_contents( $file );
				}

				if($file_content){
					$output = $this->filesUpload(
						array(
							'contents' => $file_content,
							'path'     => $path,
							'mode'     => ( $overwrite ) ? 'overwrite' : 'add',
						)
					);
				}
				
			} else {
				$output = $this->multipartUpload( $file, $path, $overwrite );
			}

			return $output;
		}

		/**
		 * @param        $file
		 * @param string $path
		 * @param bool   $overwrite
		 *
		 * @return array|mixed|string
		 * @throws WPDBBackup_Destination_Dropbox_API_Exception
		 */
		public function multipartUpload( $file, $path = '', $overwrite = true ) {
			global $wp_filesystem;
		
			// Initialize the WordPress filesystem
			if (empty($wp_filesystem)) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}
		
			$file = str_replace( '\\', '/', $file );
		
			if ( ! $wp_filesystem->exists( $file ) || ! $wp_filesystem->is_readable( $file ) ) {
				throw new WPDBBackup_Destination_Dropbox_API_Exception( "Error: File ".esc_url($file)." is not readable or doesn't exist." );
			}
		
			$chunk_size = 4194304; // 4194304 = 4MB
		
			$file_handle = $wp_filesystem->get_contents( $file );
			if ( ! $file_handle ) {
				throw new WPDBBackup_Destination_Dropbox_API_Exception( 'Can not open source file for transfer.' );
			}
		
			if ( isset($this->job_object->step_working) && ! isset( $this->job_object->steps_data[ $this->job_object->step_working ]['uploadid'] ) ) {
				$session = $this->filesUploadSessionStart();
				$this->job_object->steps_data[ $this->job_object->step_working ]['uploadid'] = $session['session_id'];
			}
			if ( isset($this->job_object->step_working) && ! isset( $this->job_object->steps_data[ $this->job_object->step_working ]['offset'] ) ) {
				$this->job_object->steps_data[ $this->job_object->step_working ]['offset'] = 0;
			}
			if ( isset($this->job_object->step_working) && ! isset( $this->job_object->steps_data[ $this->job_object->step_working ]['totalread'] ) ) {
				$this->job_object->steps_data[ $this->job_object->step_working ]['totalread'] = 0;
			}
		
			$offset = $this->job_object->steps_data[ $this->job_object->step_working ]['offset'];
		
			// Seek to current position
			$file_handle = substr($file_handle, $offset);
		
			while ( $offset < strlen($file_handle) ) {
				$chunk = substr($file_handle, $offset, $chunk_size);
				$chunk_upload_start = microtime( true );
		
				if ( $this->job_object && method_exists($this->job_object,'is_debug') && method_exists($this->job_object,'log') && $this->job_object->is_debug() ) {
					/* translators: %s: chunk of data being uploaded */
					$this->job_object->log( sprintf( __( 'Uploading %s of data', 'backwpup' ), size_format( strlen( $chunk ) ) ) );
				}
		
				$this->filesUploadSessionAppendV2(
					array(
						'contents' => $chunk,
						'cursor'   => array(
							'session_id' => $this->job_object->steps_data[ $this->job_object->step_working ]['uploadid'],
							'offset'     => $offset,
						),
					)
				);
		
				$chunk_upload_time = microtime( true ) - $chunk_upload_start;
				$this->job_object->steps_data[ $this->job_object->step_working ]['totalread'] += strlen( $chunk );
		
				$offset += strlen($chunk);
				$this->job_object->steps_data[ $this->job_object->step_working ]['offset'] = $offset;
		
				if ( $this->job_object->job['backuptype'] === 'archive' ) {
					$this->job_object->substeps_done = $this->job_object->steps_data[ $this->job_object->step_working ]['offset'];
					if ( strlen( $chunk ) == $chunk_size ) {
						$time_remaining = $this->job_object->do_restart_time();
						if ( $time_remaining < $chunk_upload_time ) {
							$chunk_size = floor( $chunk_size / $chunk_upload_time * ( $time_remaining - 3 ) );
							if ( $chunk_size < 0 ) {
								$chunk_size = 1024;
							}
							if ( $chunk_size > 4194304 ) {
								$chunk_size = 4194304;
							}
						}
					}
				}
				if(property_exists($this->job_object,'update_working_data')){
					$this->job_object->update_working_data();
				}
			}
		
			if(method_exists($this->job_object,'log')){
				/* translators: %s: total size of data uploaded  */
				$this->job_object->log( sprintf( __( 'Finishing upload session with a total of %s uploaded', 'backwpup' ), size_format( $this->job_object->steps_data[ $this->job_object->step_working ]['totalread'] ) ) );
			}
			$response = $this->filesUploadSessionFinish(
				array(
					'cursor' => array(
						'session_id' => $this->job_object->steps_data[ $this->job_object->step_working ]['uploadid'],
						'offset'     => $this->job_object->steps_data[ $this->job_object->step_working ]['totalread'],
					),
					'commit' => array(
						'path' => $path,
						'mode' => ( $overwrite ) ? 'overwrite' : 'add',
					),
				)
			);
		
			unset( $this->job_object->steps_data[ $this->job_object->step_working ]['uploadid'] );
			unset( $this->job_object->steps_data[ $this->job_object->step_working ]['offset'] );
		
			return $response;
		}		

		// Authentication

		/**
		 * Set the oauth tokens for this request.
		 *
		 * @param $token
		 *
		 * @throws WPDBBackup_Destination_Dropbox_API_Exception
		 */
		public function setOAuthTokens( $token ) {
			if ( empty( $token['access_token'] ) ) {
				throw new WPDBBackup_Destination_Dropbox_API_Exception( 'No oAuth token specified.' );
			}

			$this->oauth_token = $token;
		}

		/**
		 * Returns the URL to authorize the user.
		 *
		 * @return string The authorization URL
		 */
		public function oAuthAuthorize() {
			return self::API_WWW_URL . 'oauth2/authorize?response_type=code&client_id=' . $this->oauth_app_key;
		}

		/**
		 * Tkes the oauth code and returns the access token.
		 *
		 * @param string $code The oauth code
		 *
		 * @return array An array including the access token, account ID, and
		 * other information.
		 */
		public function oAuthToken( $code ) {
			return $this->request(
				'oauth2/token',
				array(
					'code'          => trim( $code ),
					'grant_type'    => 'authorization_code',
					'client_id'     => $this->oauth_app_key,
					'client_secret' => $this->oauth_app_secret,
				),
				'oauth'
			);
		}

		// Auth Endpoints

		/**
		 * Revokes the auth token.
		 *
		 * @return array
		 */
		public function authTokenRevoke() {
			 return $this->request( 'auth/token/revoke' );
		}

		// Files Endpoints

		/**
		 * Deletes a file.
		 *
		 * @param array $args An array of arguments
		 *
		 * @return array Information on the deleted file
		 */
		public function filesDelete( $args ) {
			$args['path'] = $this->formatPath( $args['path'] );

			try {
				return $this->request( 'files/delete', $args );
			} catch ( WPDBBackup_Destination_Dropbox_API_Request_Exception $e ) {
				$this->handleFilesDeleteError( $e->getError() );
			}
		}

		/**
		 * Gets the metadata of a file.
		 *
		 * @param array $args An array of arguments
		 *
		 * @return array The file's metadata
		 */
		public function filesGetMetadata( $args ) {
			 $args['path'] = $this->formatPath( $args['path'] );
			try {
				return $this->request( 'files/get_metadata', $args );
			} catch ( WPDBBackup_Destination_Dropbox_API_Request_Exception $e ) {
				$this->handleFilesGetMetadataError( $e->getError() );
			}
		}

		/**
		 * Gets a temporary link from Dropbox to access the file.
		 *
		 * @param array $args An array of arguments
		 *
		 * @return array Information on the file and link
		 */
		public function filesGetTemporaryLink( $args ) {
			$args['path'] = $this->formatPath( $args['path'] );
			try {
				return $this->request( 'files/get_temporary_link', $args );
			} catch ( WPDBBackup_Destination_Dropbox_API_Request_Exception $e ) {
				$this->handleFilesGetTemporaryLinkError( $e->getError() );
			}
		}

		/**
		 * Lists all the files within a folder.
		 *
		 * @param array $args An array of arguments
		 *
		 * @return array A list of files
		 */
		public function filesListFolder( $args ) {
			$args['path'] = $this->formatPath( $args['path'] );
			try {
				return $this->request( 'files/list_folder', $args );
			} catch ( WPDBBackup_Destination_Dropbox_API_Request_Exception $e ) {
				$this->handleFilesListFolderError( $e->getError() );
			}
		}

		/**
		 * Continue to list more files.
		 *
		 * When a folder has a lot of files, the API won't return all at once.
		 * So this method is to fetch more of them.
		 *
		 * @param array $args An array of arguments
		 *
		 * @return array An array of files
		 */
		public function filesListFolderContinue( $args ) {
			try {
				return $this->request( 'files/list_folder/continue', $args );
			} catch ( WPDBBackup_Destination_Dropbox_API_Request_Exception $e ) {
				$this->handleFilesListFolderContinueError( $e->getError() );
			}
		}

		/**
		 * Uploads a file to Dropbox.
		 *
		 * The file must be no greater than 150 MB.
		 *
		 * @param array $args An array of arguments
		 *
		 * @return array    The uploaded file's information.
		 */
		public function filesUpload( $args ) {
			$args['path'] = $this->formatPath( $args['path'] );

			if ( isset( $args['client_modified'] )
				&& $args['client_modified'] instanceof DateTime
			) {
				$args['client_modified'] = $args['client_modified']->format( 'Y-m-d\TH:m:s\Z' );
			}

			try {
				return $this->request( 'files/upload', $args, 'upload' );
			} catch ( WPDBBackup_Destination_Dropbox_API_Request_Exception $e ) {
				$this->handleFilesUploadError( $e->getError() );
			}
		}

		/**
		 * Append more data to an uploading file
		 *
		 * @param array $args An array of arguments
		 */
		public function filesUploadSessionAppendV2( $args ) {
			try {
				return $this->request(
					'files/upload_session/append_v2',
					$args,
					'upload'
				);
			} catch ( WPDBBackup_Destination_Dropbox_API_Request_Exception $e ) {
				$error = $e->getError();

				// See if we can fix the error first
				if ( $error['.tag'] == 'incorrect_offset' ) {
					$args['cursor']['offset'] = $error['correct_offset'];
					return $this->request(
						'files/upload_session/append_v2',
						$args,
						'upload'
					);
				}

				// Otherwise, can't fix
				$this->handleFilesUploadSessionLookupError( $error );
			}
		}

		/**
		 * Finish an upload session.
		 *
		 * @param array $args
		 *
		 * @return array Information on the uploaded file
		 */
		public function filesUploadSessionFinish( $args ) {
			 $args['commit']['path'] = $this->formatPath( $args['commit']['path'] );

			try {
				return $this->request( 'files/upload_session/finish', $args, 'upload' );
			} catch ( WPDBBackup_Destination_Dropbox_API_Request_Exception $e ) {
				$error = $e->getError();
				if ( $error['.tag'] == 'lookup_failed' ) {
					if ( $error['lookup_failed']['.tag'] == 'incorrect_offset' ) {
						$args['cursor']['offset'] = $error['lookup_failed']['correct_offset'];
						return $this->request( 'files/upload_session/finish', $args, 'upload' );
					}
				}
				$this->handleFilesUploadSessionFinishError( $e->getError() );
			}
		}

		/**
		 * Starts an upload session.
		 *
		 * When a file larger than 150 MB needs to be uploaded, then this API
		 * endpoint is used to start a session to allow the file to be uploaded in
		 * chunks.
		 *
		 * @param array $args
		 *
		 * @return array    An array containing the session's ID.
		 */
		public function filesUploadSessionStart( $args = array() ) {
			return $this->request( 'files/upload_session/start', $args, 'upload' );
		}

		// Users endpoints

		/**
		 * Get user's current account info.
		 *
		 * @return array
		 */
		public function usersGetCurrentAccount() {
			return $this->request( 'users/get_current_account' );
		}

		/**
		 * Get quota info for this user.
		 *
		 * @return array
		 */
		public function usersGetSpaceUsage() {
			return $this->request( 'users/get_space_usage' );
		}

		// Private functions

		/**
		 * @param        $url
		 * @param array  $args
		 * @param string $endpointFormat
		 * @param string $data
		 * @param bool   $echo
		 *
		 * @throws WPDBBackup_Destination_Dropbox_API_Exception
		 * @return array|mixed|string
		 */
		private function request( $endpoint, $args = array(), $endpointFormat = 'rpc', $echo = false ) {
			// Get complete URL
			switch ( $endpointFormat ) {
				case 'oauth':
					$url = self::API_URL . $endpoint;
					break;

				case 'rpc':
					$url = self::API_URL . self::API_VERSION_URL . $endpoint;
					break;

				case 'upload':
				case 'download':
					$url = self::API_CONTENT_URL . self::API_VERSION_URL . $endpoint;
					break;
			}

			if ( $this->job_object && method_exists($this->job_object,'is_debug')&& method_exists($this->job_object,'log') && $this->job_object->is_debug() && $endpointFormat != 'oauth' ) {
				$message    = 'Call to ' . $endpoint;
				$parameters = $args;
				if ( isset( $parameters['contents'] ) ) {
					$message .= ', with ' . size_format( strlen( $parameters['contents'] ) ) . ' of data';
					unset( $parameters['contents'] );
				}
				if ( ! empty( $parameters ) ) {
					$message .= ', with parameters: ' . wp_json_encode( $parameters );
				}
				$this->job_object->log( $message );
			}

			$headers['Expect'] = '';

			if ( $endpointFormat != 'oauth' ) {
				$headers['Authorization'] = 'Bearer ' . $this->oauth_token['access_token'];
			}

			if ( $endpointFormat == 'oauth' ) {
				$POSTFIELDS = http_build_query( $args, null, '&' );
				$headers['Content-Type'] = 'application/x-www-form-urlencoded';
			} elseif ( $endpointFormat == 'rpc' ) {
				if ( ! empty( $args ) ) {
					$POSTFIELDS = $args;
				} else {
					$POSTFIELDS = array();
				}
				$headers['Content-Type'] = 'application/json';
			} elseif ( $endpointFormat == 'upload' ) {
				if ( isset( $args['contents'] ) ) {
					$POSTFIELDS = $args['contents'];
					unset( $args['contents'] );
				} else {
					$POSTFIELDS = array();
				}
				$headers['Content-Type'] = 'application/octet-stream';
				if ( ! empty( $args ) ) {
					$headers['Dropbox-API-Arg'] = wp_json_encode( $args );
				} else {
					$headers['Dropbox-API-Arg'] = '{}';
				}
			} else {
				
				$headers['Dropbox-API-Arg'] = wp_json_encode( $args );
			}
			$Agent = 'WP-Database-Backup/V.4.5.1; WordPress/4.8.2; ' . home_url();
			$output = '';

			$request  = new WP_Http();
			$result   = $request->request(
				$url,
				array(
					'method'     => 'POST',
					'body'       => $POSTFIELDS,
					'user-agent' => $Agent,
					'sslverify'  => false,
					'headers'    => $headers,
				)
			);
			$responce = wp_remote_retrieve_body( $result );
			$output   = json_decode( $responce, true );

			// Handle error codes
			// If 409 (endpoint-specific error), let the calling method handle it

			// Code 429 = rate limited
			if ( wp_remote_retrieve_response_code( $result ) == 429 ) {
				$wait = 0;
				if ( preg_match( "/retry-after:\s*(.*?)\r/i", $responce[0], $matches ) ) {
					$wait = trim( $matches[1] );
				}
				// only wait if we get a retry-after header.
				if ( ! empty( $wait ) ) {
  					trigger_error( sprintf( '(429) Your app is making too many requests and is being rate limited. Error 429 can be triggered on a per-app or per-user basis. Wait for %d seconds.', esc_html($wait) ), E_USER_WARNING );
					sleep( $wait );
				} else {
					throw new WPDBBackup_Destination_Dropbox_API_Exception( '(429) This indicates a transient server error.' );
				}

				// redo request
				return $this->request( $url, $args, $endpointFormat, $echo );
			} // We can't really handle anything else, so throw it back to the caller
			elseif ( isset( $output['error'] ) || wp_remote_retrieve_response_code( $result ) >= 400 ) {
				$code = wp_remote_retrieve_response_code( $result );
				if ( wp_remote_retrieve_response_code( $result ) == 400 ) {
					$message = '(400) Bad input parameter: ' . wp_strip_all_tags( $responce[1] );
				} elseif ( wp_remote_retrieve_response_code( $result ) == 401 ) {
					$message = '(401) Bad or expired token. This can happen if the user or Dropbox revoked or expired an access token. To fix, you should re-authenticate the user.';
				} elseif ( wp_remote_retrieve_response_code( $result ) == 409 ) {
					$message = $output['error_summary'];
				} elseif ( wp_remote_retrieve_response_code( $result ) >= 500 ) {
					$message = '(' . wp_remote_retrieve_response_code( $result ) . ') There is an error on the Dropbox server.';
				} else {
					$message = '(' . wp_remote_retrieve_response_code( $result ) . ') Invalid response.';
				}
				if ( $this->job_object && method_exists($this->job_object,'log') && method_exists($this->job_object,'is_debug') && $this->job_object->is_debug() ) {
					$this->job_object->log( 'Response with header: ' . $responce[0] );
				}
			} else {
				if ( ! is_array( $output ) ) {
					return $responce[1];
				} else {
					return $output;
				}
			}
		}

		/**
		 * Formats a path to be valid for Dropbox.
		 *
		 * @param string $path
		 *
		 * @return string The formatted path
		 */
		private function formatPath( $path ) {
			if ( ! empty( $path ) && substr( $path, 0, 1 ) != '/' ) {
				$path = "/$path";
			} elseif ( $path == '/' ) {
				$path = '';
			}

			return $path;
		}

		// Error Handlers

		private function handleFilesDeleteError( $error ) {
			switch ( $error['.tag'] ) {
				case 'path_lookup':
					$this->handleFilesLookupError( $error['path_lookup'] );
					break;

				case 'path_write':
					$this->handleFilesWriteError( $error['path_write'] );
					break;

				case 'other':
					trigger_error( 'Could not delete file.', E_USER_WARNING );
					break;
			}
		}

		private function handleFilesGetMetadataError( $error ) {
			switch ( $error['.tag'] ) {
				case 'path':
					$this->handleFilesLookupError( $error['path'] );
					break;

				case 'other':
					trigger_error( 'Cannot look up file metadata.', E_USER_WARNING );
					break;
			}
		}

		private function handleFilesGetTemporaryLinkError( $error ) {
			switch ( $error['.tag'] ) {
				case 'path':
					$this->handleFilesLookupError( $error['path'] );
					break;

				case 'other':
					trigger_error( 'Cannot get temporary link.', E_USER_WARNING );
					break;
			}
		}

		private function handleFilesListFolderError( $error ) {
			switch ( $error['.tag'] ) {
				case 'path':
					$this->handleFilesLookupError( $error['path'] );
					break;

				case 'other':
					trigger_error( 'Cannot list files in folder.', E_USER_WARNING );
					break;
			}
		}

		private function handleFilesListFolderContinueError( $error ) {
			switch ( $error['.tag'] ) {
				case 'path':
					$this->handleFilesLookupError( $error['path'] );
					break;

				case 'reset':
					trigger_error( 'This cursor has been invalidated.', E_USER_WARNING );
					break;

				case 'other':
					trigger_error( 'Cannot list files in folder.', E_USER_WARNING );
					break;
			}
		}

		private function handleFilesLookupError( $error ) {
			switch ( $error['.tag'] ) {
				case 'malformed_path':
					trigger_error( 'The path was malformed.', E_USER_WARNING );
					break;

				case 'not_found':
					trigger_error( 'File could not be found.', E_USER_WARNING );
					break;

				case 'not_file':
					trigger_error( 'That is not a file.', E_USER_WARNING );
					break;

				case 'not_folder':
					trigger_error( 'That is not a folder.', E_USER_WARNING );
					break;

				case 'restricted_content':
					trigger_error( 'This content is restricted.', E_USER_WARNING );
					break;

				case 'invalid_path_root':
					trigger_error( 'Path root is invalid.', E_USER_WARNING );
					break;

				case 'other':
					trigger_error( 'File could not be found.', E_USER_WARNING );
					break;
			}
		}

		private function handleFilesUploadSessionFinishError( $error ) {
			switch ( $error['.tag'] ) {
				case 'lookup_failed':
					$this->handleFilesUploadSessionLookupError(
						$error['lookup_failed']
					);
					break;

				case 'path':
					$this->handleFilesWriteError( $error['path'] );
					break;

				case 'too_many_shared_folder_targets':
					trigger_error( 'Too many shared folder targets.', E_USER_WARNING );
					break;

				case 'other':
					trigger_error( 'The file could not be uploaded.', E_USER_WARNING );
					break;
			}
		}

		private function handleFilesUploadSessionLookupError( $error ) {
			switch ( $error['.tag'] ) {
				case 'not_found':
					trigger_error( 'Session not found.', E_USER_WARNING );
					break;

				case 'incorrect_offset':
					trigger_error(
						'Incorrect offset given. Correct offset is ' .
						esc_html($error['correct_offset']) . '.',
						E_USER_WARNING
					);
					break;

				case 'closed':
					trigger_error(
						'This session has been closed already.',
						E_USER_WARNING
					);
					break;

				case 'not_closed':
					trigger_error( 'This session is not closed.', E_USER_WARNING );
					break;

				case 'other':
					trigger_error(
						'Could not look up the file session.',
						E_USER_WARNING
					);
					break;
			}
		}

		private function handleFilesUploadError( $error ) {
			switch ( $error['.tag'] ) {
				case 'path':
					$this->handleFilesUploadWriteFailed( $error['path'] );
					break;

				case 'other':
					trigger_error( 'There was an unknown error when uploading the file.', E_USER_WARNING );
					break;
			}
		}

		private function handleFilesUploadWriteFailed( $error ) {
			$this->handleFilesWriteError( $error['reason'] );
		}

		private function handleFilesWriteError( $error ) {
			$message = '';

			// Type of error
			switch ( $error['.tag'] ) {
				case 'malformed_path':
					$message = 'The path was malformed.';
					break;

				case 'conflict':
					$message = 'Cannot write to the target path due to conflict.';
					break;

				case 'no_write_permission':
					$message = 'You do not have permission to save to this location.';
					break;

				case 'insufficient_space':
					$message = 'You do not have enough space in your Dropbox.';
					break;

				case 'disallowed_name':
					$message = 'The given name is disallowed by Dropbox.';
					break;

				case 'team_folder':
					$message = 'Unable to modify team folders.';
					break;

				case 'other':
					$message = 'There was an unknown error when uploading the file.';
					break;
			}

			trigger_error( esc_html($message), E_USER_WARNING );
		}

	}
}
/**
 *
 */
if ( ! class_exists( 'WPDBBackup_Destination_Dropbox_API_Exception' ) ) {
	class WPDBBackup_Destination_Dropbox_API_Exception extends Exception {


	}
}
/**
 * Exception thrown when there is an error in the Dropbox request.
 */
if ( ! class_exists( 'WPDBBackup_Destination_Dropbox_API_Request_Exception' ) ) {
	class WPDBBackup_Destination_Dropbox_API_Request_Exception extends WPDBBackup_Destination_Dropbox_API_Exception {


		/**
		 * The request error array.
		 */
		protected $error;

		public function __construct( $message, $code = 0, $previous = null, $error = null ) {
			$this->error = $error;
			parent::__construct( $message, $code, $previous );
		}

		public function getError() {
			return $this->error;
		}

	}
}
