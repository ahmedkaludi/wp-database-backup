<?php
/**
 * Backup admin.
 *
 * @package wpdbbkp
 */

ob_start();
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main class wpdb_admin.
 *
 * @class Wpdb_Admin
 */
class Wpdb_Admin {

	/**
	 * Construct.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'wp_db_backup_admin_init' ) );
		add_action( 'admin_init', array( $this, 'admin_scripts_style' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 9 );
		add_filter( 'cron_schedules', array( $this, 'wp_db_backup_cron_schedules' ) );
		add_action( 'wp_db_backup_event', array( $this, 'wp_db_backup_event_process' ) );
		add_action( 'wp', array( $this, 'wp_db_backup_scheduler_activation' ) );
		add_action( 'wp_logout', array( $this, 'wp_db_cookie_expiration' ) ); // Fixed Vulnerability 22-06-2016 for prevent direct download.
		add_action( 'wp_db_backup_completed', array( $this, 'wp_db_backup_completed_local' ), 12 );
	}

	/**
	 * Backup Menu.
	 */
	public function admin_menu() {
		$page = add_management_page( 'WP-DB Backup', 'WP-DB Backup ', 'manage_options', 'wp-database-backup', array( $this, 'wp_db_backup_settings_page' ) );
	}

	/**
	 * Start Fixed Vulnerability 22-06-2016 for prevent direct download.
	 */
	public function wp_db_cookie_expiration() {
		setcookie( 'can_download', 0, time() - 300, COOKIEPATH, COOKIE_DOMAIN );
		if ( SITECOOKIEPATH !== COOKIEPATH ) {
			setcookie( 'can_download', 0, time() - 300, SITECOOKIEPATH, COOKIE_DOMAIN );
		}
	}

	/**
	 * If Checked then it will remove local backup after uploading to destination.
	 *
	 * @param array $args - backup details.
	 */
	public function wp_db_backup_completed_local( &$args ) {
		$wp_db_remove_local_backup = get_option( 'wp_db_remove_local_backup' );
		if ( 1 === $wp_db_remove_local_backup ) {
			if ( file_exists( $args[1] ) ) {
				unlink( $args[1] );// File path.
			}
		}
	}

	/**
	 * Admin init.
	 */
	public function wp_db_backup_admin_init() {
		// Start Fixed Vulnerability 04-08-2016 for data save in options.
		if ( isset( $_GET['page'] ) && 'wp-database-backup' === $_GET['page'] ) {
			if ( ! empty( $_POST ) && ! ( isset( $_POST['option_page'] ) && 'wp_db_backup_options' === $_POST['option_page'] ) ) {
				if ( false === isset( $_REQUEST['_wpnonce'] ) || false === wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'wp-database-backup' ) ) {
					die( 'WPDB :: Invalid Access' );
				}
			}

			// End Fixed Vulnerability 04-08-2016 for data save in options.
			if ( isset( $_GET['page'] ) && 'wp-database-backup' === $_GET['page'] && current_user_can( 'manage_options' ) ) {
				setcookie( 'can_download', 1, 0, COOKIEPATH, COOKIE_DOMAIN );
				if ( SITECOOKIEPATH !== COOKIEPATH ) {
					setcookie( 'can_download', 1, 0, SITECOOKIEPATH, COOKIE_DOMAIN );
				}
			} else {
				setcookie( 'can_download', 0, time() - 300, COOKIEPATH, COOKIE_DOMAIN );
				if ( SITECOOKIEPATH !== COOKIEPATH ) {
					setcookie( 'can_download', 0, time() - 300, SITECOOKIEPATH, COOKIE_DOMAIN );
				}
			}
			// End Fixed Vulnerability 22-06-2016 for prevent direct download.
			if ( is_admin() && current_user_can( 'manage_options' ) ) {
				if ( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'wp-database-backup' ) ) {
					if ( isset( $_POST['wpsetting_search'] ) ) {
						if ( isset( $_POST['wp_db_backup_search_text'] ) ) {
							update_option( 'wp_db_backup_search_text', sanitize_text_field( wp_unslash( $_POST['wp_db_backup_search_text'] ) ) );
						}
						if ( isset( $_POST['wp_db_backup_replace_text'] ) ) {
							update_option( 'wp_db_backup_replace_text', sanitize_text_field( wp_unslash( $_POST['wp_db_backup_replace_text'] ) ) );
						}
						$nonce = wp_create_nonce( 'wp-database-backup' );
						wp_safe_redirect( esc_url( site_url() . '/wp-admin/tools.php?page=wp-database-backup&notification=save&tab=searchreplace&_wpnonce=' . $nonce ) );
					}

					if ( isset( $_POST['wpsetting'] ) ) {
						if ( isset( $_POST['wp_local_db_backup_count'] ) ) {
							update_option( 'wp_local_db_backup_count', wp_db_filter_data( sanitize_text_field( wp_unslash( $_POST['wp_local_db_backup_count'] ) ) ) );
						}

						if ( isset( $_POST['wp_db_log'] ) ) {
							update_option( 'wp_db_log', 1 );
						} else {
							update_option( 'wp_db_log', 0 );
						}
						if ( isset( $_POST['wp_db_remove_local_backup'] ) ) {
							update_option( 'wp_db_remove_local_backup', 1 );
						} else {
							update_option( 'wp_db_remove_local_backup', 0 );
						}

						if ( isset( $_POST['wp_db_backup_enable_auto_upgrade'] ) ) {
							update_option( 'wp_db_backup_enable_auto_upgrade', 1 );
						} else {
							update_option( 'wp_db_backup_enable_auto_upgrade', 0 );
						}

						if ( isset( $_POST['wp_db_backup_enable_htaccess'] ) ) {
							update_option( 'wp_db_backup_enable_htaccess', 1 );
						} else {
							update_option( 'wp_db_backup_enable_htaccess', 0 );
							$path_info = wp_upload_dir();
							if ( file_exists( $path_info['basedir'] . '/db-backup/.htaccess' ) ) {
								unlink( $path_info['basedir'] . '/db-backup/.htaccess' );
							}
						}

						if ( isset( $_POST['wp_db_exclude_table'] ) ) {
							update_option( 'wp_db_exclude_table', $this->recursive_sanitize_text_field( wp_unslash( $_POST['wp_db_exclude_table'] ) ) ); // phpcs:ignore
						} else {
							update_option( 'wp_db_exclude_table', '' );
						}
						$nonce = wp_create_nonce( 'wp-database-backup' );
						wp_safe_redirect( site_url() . '/wp-admin/tools.php?page=wp-database-backup&notification=save&_wpnonce=' . $nonce );
					}

					if ( true === isset( $_POST['wp_db_local_backup_path'] ) ) {
						update_option( 'wp_db_local_backup_path', wp_db_filter_data( sanitize_text_field( wp_unslash( $_POST['wp_db_local_backup_path'] ) ) ) );
					}

					if ( isset( $_POST['wp_db_backup_email_id'] ) ) {
						update_option( 'wp_db_backup_email_id', wp_db_filter_data( sanitize_email( wp_unslash( $_POST['wp_db_backup_email_id'] ) ) ) );
					}

					if ( isset( $_POST['wp_db_backup_email_attachment'] ) ) {
						$email_attachment = sanitize_text_field( wp_unslash( $_POST['wp_db_backup_email_attachment'] ) );
						update_option( 'wp_db_backup_email_attachment', $email_attachment );
					}
					if ( isset( $_POST['Submit'] ) && 'Save Settings' === $_POST['Submit'] ) {
						if ( isset( $_POST['wp_db_backup_destination_Email'] ) ) {
							update_option( 'wp_db_backup_destination_Email', 1 );
						} else {
							update_option( 'wp_db_backup_destination_Email', 0 );
						}

						if ( true === isset( $_POST['wp_db_local_backup'] ) ) {
							update_option( 'wp_db_local_backup', 1 );
						} else {
							update_option( 'wp_db_local_backup', 0 );
						}
					}
				}
				$wp_db_backup_destination_email = get_option( 'wp_db_backup_destination_Email' );

				if ( isset( $_GET['page'] ) && 'wp-database-backup' === $_GET['page'] && isset( $_GET['action'] ) && 'unlink' === $_GET['action'] ) {
					// Specify the target directory and add forward slash.
					$dir = plugin_dir_path( __FILE__ ) . 'Destination/Dropbox/tokens/';

					// Open the directory.
					$dir_handle = opendir( $dir );
					// Loop over all of the files in the folder.
					$file = readdir( $dir_handle );
					while ( $file ) {
						// If $file is NOT a directory remove it.
						if ( ! is_dir( $file ) ) {
							unlink( $dir . $file );
						}
					}
					// Close the directory.
					closedir( $dir_handle );
					wp_safe_redirect( site_url() . '/wp-admin/tools.php?page=wp-database-backup' );
				}
				$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
				if ( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $nonce, 'wp-database-backup' ) ) {
					if ( isset( $_GET['action'] ) && current_user_can( 'manage_options' ) ) {
						switch ( (string) $_GET['action'] ) {
							case 'createdbbackup':
								$this->wp_db_backup_event_process();
								wp_safe_redirect( site_url() . '/wp-admin/tools.php?page=wp-database-backup&notification=create&_wpnonce=' . $nonce );
								break;
							case 'removebackup':
								if ( true === isset( $_GET['index'] ) ) {
									$index      = (int) $_GET['index'];
									$options    = get_option( 'wp_db_backup_backups' );
									$newoptions = array();
									$count      = 0;
									foreach ( $options as $option ) {
										if ( $count !== $index ) {
											$newoptions[] = $option;
										}
										$count++;
									}
									if ( file_exists( $options[ $index ]['dir'] ) ) {
										unlink( $options[ $index ]['dir'] );
									}
									$file_sql = explode( '.', $options[ $index ]['dir'] );
									if ( file_exists( $file_sql[0] . '.sql' ) ) {
										unlink( $file_sql[0] . '.sql' );
									}
									update_option( 'wp_db_backup_backups', $newoptions );
									$nonce = wp_create_nonce( 'wp-database-backup' );
									wp_safe_redirect( site_url() . '/wp-admin/tools.php?page=wp-database-backup&notification=delete&_wpnonce=' . $nonce );
								}
								break;
							case 'clear_temp_db_backup_file':
								$options           = get_option( 'wp_db_backup_backups' );
								$newoptions        = array();
								$backup_check_list = array( '.htaccess', 'index.php' );
								$delete_message    = 'WPDB : Deleted Files:';
								foreach ( $options as $option ) {
									$backup_check_list[] = $option['filename'];
								}
								$path_info         = wp_upload_dir();
								$wp_db_backup_path = $path_info['basedir'] . '/db-backup';
								// Open a directory, and read its contents.
								if ( is_dir( $wp_db_backup_path ) ) {
									$dh = opendir( $wp_db_backup_path );
									if ( $dh ) {
										$file = readdir( $dh );
										while ( false !== $file ) {
											if ( ! ( in_array( $file, $backup_check_list, true ) ) ) {
												if ( file_exists( $wp_db_backup_path . '/' . $file ) ) {
													unlink( $wp_db_backup_path . '/' . $file );
												}
												$delete_message .= ' ' . $file;
											}
										}
										closedir( $dh );
									}
								}
								wp_safe_redirect( site_url() . '/wp-admin/tools.php?page=wp-database-backup&notification=clear_temp_db_backup_file&_wpnonce=' . $nonce );
								break;
							case 'restorebackup':
								$index      = (int) $_GET['index'];
								$options    = get_option( 'wp_db_backup_backups' );
								$newoptions = array();
								$count      = 0;
								foreach ( $options as $option ) {
									if ( $count !== $index ) {
										$newoptions[] = $option;
									}
									$count++;
								}
								if ( isset( $options[ $index ]['sqlfile'] ) ) { // Added for extract zip file V.3.3.0.
									$database_file = ( $options[ $index ]['sqlfile'] );
								} else {
									$database_file = ( $options[ $index ]['dir'] );
									$file_sql      = explode( '.', $options[ $index ]['dir'] );
									$database_file = ( $file_sql[0] . '.sql' );
								}
								$database_name     = $this->wp_backup_get_config_db_name();
								$database_user     = $this->wp_backup_get_config_data( 'DB_USER' );
								$datadase_password = $this->wp_backup_get_config_data( 'DB_PASSWORD' );
								$database_host     = $this->wp_backup_get_config_data( 'DB_HOST' );
								$path_info         = wp_upload_dir();
								// Added for extract zip file V.3.3.0.
								$ext_path_info     = $path_info['basedir'] . '/db-backup';
								$database_zip_file = $options[ $index ]['dir'];

								if ( class_exists( 'ZipArchive' ) ) {
									$zip = new ZipArchive();
									if ( $zip->open( $database_zip_file ) === true ) {
										$zip->extractTo( $ext_path_info );
										$zip->close();
									}
								} else {
									require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
									$archive = new PclZip( $database_zip_file );
									$dir     = $path_info['basedir'] . '/db-backup/';

									if ( ! $archive->extract( PCLZIP_OPT_PATH, $dir ) ) {
										wp_die( 'Unable to extract zip file. Please check that zlib php extension is enabled.', 'ZIP Error' );
									}
								}

								// End for extract zip file V.3.3.0.
								set_time_limit( 0 );
								if ( '' !== ( trim( (string) $database_name ) ) && '' !== ( trim( (string) $database_user ) ) && '' !== ( trim( (string) $datadase_password ) ) && '' !== ( trim( (string) $database_host ) ) ) {
									$conn = mysqli_connect( (string) $database_host, (string) $database_user, (string) $datadase_password ); // phpcs:ignore
									if ( $conn ) {
										// Start Select the database.
										if ( ! mysqli_select_db( (string) $database_name, $conn ) ) { // phpcs:ignore
											$sql = 'CREATE DATABASE IF NOT EXISTS `' . (string) $database_name . '`';
											mysqli_query( $sql, $conn ); // phpcs:ignore
											mysqli_select_db( (string) $database_name, $conn ); // phpcs:ignore
										}
										/* END: Select the Database */

										/* BEGIN: Remove All Tables from the Database */
										$found_tables = null;
										$result       = mysqli_query( 'SHOW TABLES FROM `{' . (string) $database_name . '}`', $conn ); // phpcs:ignore
										if ( $result ) {
											$row = mysqli_fetch_row( $result ); // phpcs:ignore
											while ( $row ) {
												$found_tables[] = $row[0];
											}
											if ( count( $found_tables ) > 0 ) {
												foreach ( $found_tables as $table_name ) {
													mysqli_query( 'DROP TABLE `{' . (string) $database_name . "}`.{$table_name}", $conn ); // phpcs:ignore
												}
											}
										}
										/* END: Remove All Tables from the Database */

										/* BEGIN: Restore Database Content */
										if ( isset( $database_file ) ) {
											$database_file = $database_file;
											if ( file_exists( $database_file ) ) {
												$sql_file = file_get_contents( $database_file, true );

												$sql_queries       = explode( ";\n", $sql_file );
												$sql_queries_count = count( $sql_queries );
												for ( $i = 0; $i < $sql_queries_count; $i++ ) {
													mysqli_query( $sql_queries[ $i ], $conn ); // phpcs:ignore
												}
											}
										}
									}
								}
								if ( isset( $options[ $index ]['sqlfile'] ) && file_exists( $options[ $index ]['sqlfile'] ) ) { // Added for extract zip file V.3.3.0.
									if ( file_exists( $options[ $index ]['sqlfile'] ) ) {
										unlink( $options[ $index ]['sqlfile'] );
									}
								} else {
									$database_file = ( $options[ $index ]['dir'] );
									$file_sql      = explode( '.', $options[ $index ]['dir'] );
									$database_file = ( $file_sql[0] . '.sql' );
									if ( file_exists( $database_file ) ) {
										unlink( $database_file );
									}
								}
								wp_safe_redirect( site_url() . '/wp-admin/tools.php?page=wp-database-backup&notification=restore&_wpnonce=' . $nonce );
								break;

							/* END: Restore Database Content */
						}
					}
				}
			}
		}

		if ( is_admin() && current_user_can( 'manage_options' ) ) {
			register_setting( 'wp_db_backup_options', 'wp_db_backup_options', array( $this, 'wp_db_backup_validate' ) );
			add_settings_section( 'wp_db_backup_main', 'WP-Database-Backup', array( $this, 'wp_db_backup_section_text' ), 'manage_options' );
		}
	}

	/**
	 * Validate data.
	 *
	 * @param string $input - Input data.
	 */
	public function wp_db_backup_validate( $input ) {
		return $input;
	}

	/**
	 * Setting page.
	 */
	public function wp_db_backup_settings_page() {
		$options  = get_option( 'wp_db_backup_backups' );
		$settings = get_option( 'wp_db_backup_options' ); ?>
		<div class="bootstrap-wrapper">
		<?php
			include_once 'admin-header-notification.php';
		$wp_db_local_backup_path = get_option( 'wp_db_local_backup_path' );
		if ( false === empty( $wp_db_local_backup_path ) && false === file_exists( $wp_db_local_backup_path ) ) {
			echo '<div class="alert alert-warning alert-dismissible fade in" role="alert">
                      <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>
                      <a href="#db_destination" data-toggle="tab">';
			esc_attr_e( 'Invalid Local Backup Path : ', 'wp-database-backup' );
			echo esc_attr( $wp_db_local_backup_path );
			echo '</a></div>';
		}

		$upload_dir = wp_upload_dir();
		$dir        = $upload_dir['basedir'] . '/db-backup';
		if ( ! is_dir( $dir ) ) {
			$dir = $upload_dir['basedir'];
		}
		if ( is_dir( $dir ) && ! is_writable( $dir ) ) {
			?>
				<div class="row">
				<div class="col-xs-12 col-sm-12 col-md-12">
					<div class="alert alert-danger alert-dismissible fade in" role="alert">
						<button type="button" class="close" data-dismiss="alert" aria-label="Close">
							<span aria-hidden="true">×</span></button>
						<h4>WP Database Backup</h4>
						<p>Error: Permission denied, make sure you have write permission for <?php echo esc_attr( $dir ); ?>
							folder</p>
					</div>
					</button>
				</div>
				</div>
				<?php
		}
		?>
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3><a href="http://www.wpseeds.com/documentation/docs/wp-database-backup/" target="blank"><img
								src="<?php echo esc_attr( WPDB_PLUGIN_URL ); ?>/assets/images/wp-database-backup.png"></a>Database
						Backup Settings <a href="https://www.wpseeds.com/product/wp-all-backup/" target="_blank"><span
								style='float:right'
								class="label label-success">Get Pro 'WP All Backup' Plugin</span></a>
					</h3>
				</div>
				<div class="panel-body">
					<ul class="nav nav-tabs">
						<li class=""><a href="#db_home" data-toggle="tab">Database Backups</a></li>
						<li><a href="#db_schedul" data-toggle="tab">Scheduler</a></li>
						<li><a href="#db_setting" data-toggle="tab">Settings</a></li>
						<li><a href="#searchreplace" data-toggle="tab">Search and Replace</a></li>
						<li><a href="#db_destination" data-toggle="tab">Destination</a></li>
						<li><a href="#db_info" data-toggle="tab">System Information</a></li>
						<li><a href="#db_help" data-toggle="tab">Help</a></li>
						<li><a href="#db_advanced" data-toggle="tab">Pro Feature</a></li>
					</ul>

					<?php
					echo '<div class="tab-content">';
					echo '<div class="tab-pane active"  id="db_home">';
					echo '<p class="submit">';
					$nonce                     = wp_create_nonce( 'wp-database-backup' );
					$wp_db_backup_search_text  = get_option( 'wp_db_backup_search_text' );
					$wp_db_backup_replace_text = get_option( 'wp_db_backup_replace_text' );
					if ( ( false === empty( $wp_db_backup_search_text ) ) && ( false === empty( $wp_db_backup_replace_text ) ) ) {
						echo '<a href="' . esc_url( site_url() ) . '/wp-admin/tools.php?page=wp-database-backup&action=createdbbackup&_wpnonce=' . esc_attr( $nonce ) . '" id="create_backup" class="btn btn-primary"> <span class="glyphicon glyphicon-plus-sign"></span> Create New Database Backup with Search/Replace</a>';
						echo '<p>Backup file will replace <b>' . esc_attr( $wp_db_backup_search_text ) . '</b> text with <b>' . esc_attr( $wp_db_backup_replace_text ) . '</b>. For Regular Database Backup without replace then Go to Dashboard=>Tool=>WP-DB Backup > Settings > Search and Replace - Set Blank Fields </p>';
					} else {
						echo '<a href="' . esc_url( site_url() ) . '/wp-admin/tools.php?page=wp-database-backup&action=createdbbackup&_wpnonce=' . esc_attr( $nonce ) . '" id="create_backup" class="btn btn-primary"> <span class="glyphicon glyphicon-plus-sign"></span> Create New Database Backup</a>';
					}
					echo '</p>';
					?>

					<?php
					if ( $options ) {
						echo ' <div class="table-responsive">
                                <div id="dataTables-example_wrapper" class="dataTables_wrapper form-inline" role="grid">

                                <table class="table table-striped table-bordered table-hover display" id="example">
                                    <thead>';
						echo '<tr class="wpdb-header">';
						echo '<th class="manage-column" scope="col" width="10%" style="text-align: center;">SL No</th>';
						echo '<th class="manage-column" scope="col" width="30%">Date</th>';
						echo '<th class="manage-column" scope="col" width="5%"></th>';
						echo '<th class="manage-column" scope="col" width="15%">Destination</th>';
						echo '<th class="manage-column" scope="col" width="10%">Backup File</th>';
						echo '<th class="manage-column" scope="col" width="10%">Size</th>';
						echo '<th class="manage-column" scope="col" width="20%">Action</th>';
						echo '</tr>';
						echo '</thead>';

						echo '<tbody>';
						$count            = 1;
						$destination_icon = array(
							'Local'      => 'glyphicon glyphicon-home',
							'Local Path' => 'glyphicon glyphicon-folder-open',
							'Email'      => 'glyphicon glyphicon-envelope',
							'FTP'        => 'glyphicon glyphicon-folder-open',
							'S3'         => 'glyphicon glyphicon-cloud-upload',
							'Drive'      => 'glyphicon glyphicon-hdd',
							'DropBox'    => 'glyphicon glyphicon-inbox',
						);
						foreach ( $options as $option ) {
							$str_class = ( 0 === (int) $option['size'] ) ? 'text-danger' : 'wpdb_download';
							echo '<tr class="' . ( ( 0 === ( $count % 2 ) ) ? esc_attr( $str_class ) . ' alternate' : esc_attr( $str_class ) ) . '">';
							echo '<td style="text-align: center;">' . esc_attr( $count ) . '</td>';
							echo '<td><span style="display:none">' . esc_attr( gmdate( 'Y M jS h:i:s A', $option['date'] ) ) . '</span>' . esc_attr( gmdate( 'jS, F Y h:i:s A', $option['date'] ) ) . '</td>';
							echo '<td class="wpdb_log">';
							if ( false === empty( $option['log'] ) ) {
								echo '<button id="popoverid" type="button" class="popoverid btn" data-toggle="popover" title="Log" data-content="' . wp_kses_post( $option['log'] ) . '"><span class="glyphicon glyphicon-list-alt" aria-hidden="true"></span></button>';
							}
							echo '</td>';
							echo '<td>';
							if ( ! empty( $option['destination'] ) ) {
								$destination = ( explode( ',', $option['destination'] ) );
								foreach ( $destination as $dest ) {
									$key = trim( $dest );
									if ( ! empty( $dest ) && array_key_exists( $key, $destination_icon ) ) {
										echo '<span class="' . esc_attr( $destination_icon[ $key ] ) . '" title="' . esc_attr( $dest ) . '"></span> ';
									}
								}
							}
							echo '</td>';
							echo '<td>';
							echo '<a href="' . esc_url( $option['url'] ) . '" style="color: #21759B;">';
							echo '<span class="glyphicon glyphicon-download-alt"></span> Download</a></td>';
							echo '<td>' . esc_attr( $this->wp_db_backup_format_bytes( $option['size'] ) ) . '</td>';
							echo '<td><a title="Remove Database Backup" onclick="return confirm(\'Are you sure you want to delete database backup?\')" href="' . esc_url( site_url() ) . '/wp-admin/tools.php?page=wp-database-backup&action=removebackup&_wpnonce=' . esc_attr( $nonce ) . '&index=' . esc_attr( ( $count - 1 ) ) . '" class="btn btn-default"><span style="color:red" class="glyphicon glyphicon-trash"></span> Remove <a/> ';
							if ( isset( $option['search_replace'] ) && 1 === (int) $option['search_replace'] ) {
								echo '<span style="margin-left:15px" title="' . esc_html( $option['log'] ) . '" class="glyphicon glyphicon-search"></span>';
							} else {
								echo '<a title="Restore Database Backup" onclick="return confirm(\'Are you sure you want to restore database backup?\')" href="' . esc_url( site_url() ) . '/wp-admin/tools.php?page=wp-database-backup&action=restorebackup&_wpnonce=' . esc_attr( $nonce ) . '&index=' . esc_attr( ( $count - 1 ) ) . '" class="btn btn-default"><span class="glyphicon glyphicon-refresh" style="color:blue"></span> Restore <a/>';
							}
							echo '</td></tr>';
							$count++;
						}
						echo '</tbody>';

						echo ' </table>
                                </div>
                                  </div>';
					} else {
						echo '<p>No Database Backups Created!</p>';
					}
					echo "<div class='alert alert-success' role='alert'><h4>" . wp_kses_post( $coupon ) . '</h4></div>';
					echo "<div class=''><p><a target='_blank' href='https://www.wpseeds.com/product/wp-all-backup/'>WP All Backup</a> - Creates a Backup of your entire website: that's your Database, current WP Core, all your Themes, Plugins and Uploads.</p></div>";
					echo "<div class=''><p>Use <b>WPDBSPECIAL40</b> Coupon and get Pro version in just <a target='_blank' href='https://www.wpseeds.com/product/wp-all-backup/'><b>$13.20</b></a> - Lifetime License, 1 Year Support, 1 Year Updates.</p></div>";
					echo '<p>If you like <b>WP Database Backup</b> please leave us a <a target="_blank" href="http://wordpress.org/support/view/plugin-reviews/wp-database-backup" title="Rating" sl-processed="1"> <span class="glyphicon glyphicon-star" aria-hidden="true"></span> <span class="glyphicon glyphicon-star" aria-hidden="true"></span> <span class="glyphicon glyphicon-star" aria-hidden="true"></span> <span class="glyphicon glyphicon-star" aria-hidden="true"></span> <span class="glyphicon glyphicon-star" aria-hidden="true"></span> rating </a>. Many thanks in advance!
                                        <a target="_blank" class="text-right" href="https://www.wpseeds.com/support/"><button style="float:right" type="button" class="btn btn-default">Support</button></a>
                                        <a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/"><button style="float:right" type="button" class="btn btn-default">Documentation</button></a>
                                        <a target="_blank" href="https://www.wpseeds.com/product/wp-all-backup/"><button style="float:right" type="button" class="btn btn-default">Premium</button></a>
                                        <a target="_blank" href="http://www.wpseeds.com"><button style="float:right" type="button" class="btn btn-default">More plugins</button></a></p>
	                                      ';
					echo '</div>';

					echo '<div class="tab-pane" id="db_schedul">';
					echo '<form method="post" action="options.php" name="wp_auto_commenter_form">';
					wp_nonce_field( 'wp-database-backup' );
					settings_fields( 'wp_db_backup_options' );
					do_settings_sections( 'wp-database-backup' );

					$enable_autobackups = '0';
					if ( isset( $settings['enable_autobackups'] ) ) {
						$enable_autobackups = $settings['enable_autobackups'];
					}

					$autobackup_frequency = '0';
					if ( isset( $settings['autobackup_frequency'] ) ) {
						$autobackup_frequency = $settings['autobackup_frequency'];
					}

					echo '<div class="row form-group"><label class="col-sm-2" for="enable_autobackups">Enable Auto Backups</label>';
					echo '<div class="col-sm-2"><input type="checkbox" id="enable_autobackups" name="wp_db_backup_options[enable_autobackups]" value="1" ' . checked( 1, $enable_autobackups, false ) . '/></div>';
					echo '</div>';
					echo '<div class="row form-group"><label class="col-sm-2" for="wp_db_backup_options">Auto Database Backup Frequency</label>';
					echo '<div class="col-sm-2"><select id="wp_db_backup_options" class="form-control" name="wp_db_backup_options[autobackup_frequency]">';
					echo '<option value="hourly" ' . selected( 'hourly', $autobackup_frequency, false ) . '>Hourly</option>';
					echo '<option value="twicedaily" ' . selected( 'twicedaily', $autobackup_frequency, false ) . '>Twice Daily</option>';
					echo '<option value="daily" ' . selected( 'daily', $autobackup_frequency, false ) . '>Daily</option>';
					echo '<option value="weekly" ' . selected( 'weekly', $autobackup_frequency, false ) . '>Weekly</option>';
					echo '<option value="monthly" ' . selected( 'monthly', $autobackup_frequency, false ) . '>Monthly</option>';
					echo '</select>';
					echo '</div></div>';

					echo '<p class="submit">';
					echo '<input type="submit" name="Submit" class="btn btn-primary" value="Save Settings" />';
					echo '</p>';
					echo '</form>';
					echo '</div>';

					echo '<div class="tab-pane" id="db_help">';
					echo '<p>';
					?>

					<script>
						var $j = jQuery.noConflict();
						$j(document).ready(function () {
							$j('.popoverid').popover();
							var table = $j('#example').DataTable();
							$j("#create_backup").click(function() {
								$j("#backup_process").show();
								$j("#create_backup").attr("disabled", true);
							});
						});

						function excludetableall(){
							var checkboxes = document.getElementsByClassName('wp_db_exclude_table');
							var checked = '';
							if($j('#wp_db_exclude_table_all').prop("checked") == true){
								checked = 'checked';
							}
							$j('.wp_db_exclude_table').each(function() {
								this.checked = checked;
							});
						}

					</script>
					<div class="panel-group" id="accordion">

						<div class="panel panel-default">
							<div class="panel-heading">
								<h4 class="panel-title">
									<a data-toggle="collapse" data-parent="#accordion" href="#collapseDocumentation">
										Documentation links
									</a>
								</h4>
							</div>
							<div id="collapseDocumentation" class="panel-collapse collapse in">
								<div class="panel-body">
									<p>
									<ul>
										<li class="page_item page-item-257 page_item_has_children"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/setup/">Setup</a>
											<ul class="children">
												<li class="page_item page-item-258"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/setup/installation/">Installation</a>
												</li>
											</ul>
										</li>
										<li class="page_item page-item-295 page_item_has_children"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/how-to/">How
												To</a>
											<ul class="children">
												<li class="page_item page-item-299"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/how-to/restore-database-backup/">Restore
														Database Backup</a></li>
												<li class="page_item page-item-301"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/how-to/backup-your-wordpress-site-database/">Backup
														Your WordPress Site Database</a></li>
											</ul>
										</li>
										<li class="page_item page-item-340 page_item_has_children"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/setting/">Setting</a>
											<ul class="children">
												<li class="page_item page-item-342"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/setting/number-of-backups/">Number
														of backups</a></li>
												<li class="page_item page-item-349"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/setting/exclude-tables/">Exclude
														Tables</a></li>
												<li class="page_item page-item-358"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/setting/log-setting/">Log
														Setting</a></li>
												<li class="page_item page-item-363"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/setting/schedule-settings/">Schedule
														Settings</a></li>
											</ul>
										</li>
										<li class="page_item page-item-306 page_item_has_children"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/destination/">Destination</a>
											<ul class="children">
												<li class="page_item page-item-310"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/destination/email-notification/">Email Notification</a></li>
												<li class="page_item page-item-319"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/destination/store-database-backup-on-ftp/">Storedatabase backup on FTP</a></li>
												<li class="page_item page-item-326"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/destination/store-database-backup-on-google-drive/">Store database backup on Google drive</a></li>
												<li class="page_item page-item-334"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/destination/store-database-backup-on-dropbox/">Store database backup on Dropbox</a></li>
												<li class="page_item page-item-336"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/destination/store-database-backup-on-amazon-s3/">Store database backup on Amazon S3</a></li>
											</ul>
										</li>
										<li class="page_item page-item-264 page_item_has_children"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/faq/">FAQ</a>
											<ul class="children">
												<li class="page_item page-item-265"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/faq/on-click-create-new-database-backup-it-goes-to-blank-page/">On Click Create New Database Backup it goes to blank page</a></li>
												<li class="page_item page-item-267"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/faq/always-get-an-empty-0-bits-backup-file/">Always get an empty (0 bits) backup file?</a></li>
												<li class="page_item page-item-269"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/faq/how-to-restore-database-backup/">How to restore database backup?</a></li>
												<li class="page_item page-item-271"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/faq/how-to-create-database-backup/">How to create database Backup?</a></li>
											</ul>
										</li>
										<li class="page_item page-item-273"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/features/">Features</a></li>
										<li class="page_item page-item-277"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/changelog/">Changelog</a></li>
										<li class="page_item page-item-279"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/reviews/">Reviews</a></li>
										<li class="page_item page-item-373"><a target="_blank" href="http://www.wpseeds.com/documentation/docs/wp-database-backup/pricing/">Pricing</a></li>
									</ul>

									</p>
								</div>
							</div>
						</div>

						<div class="panel panel-default">
							<div class="panel-heading">
								<h4 class="panel-title">
									<a data-toggle="collapse" data-parent="#accordion" href="#collapseOne">
										Create Backup
									</a>
								</h4>
							</div>
							<div id="collapseOne" class="panel-collapse collapse in">
								<div class="panel-body">
									<p>Step 1) Click on Create New Database Backup</p>
									<p>Step 2) Download Database Backup file.</p>
								</div>
							</div>
						</div>

						<div class="panel-group" id="accordion">
							<div class="panel panel-default">
								<div class="panel-heading">
									<h4 class="panel-title">
										<a data-toggle="collapse" data-parent="#accordion" href="#collapseTwo">
											Restore Backup
										</a>
									</h4>
								</div>
								<div id="collapseTwo" class="panel-collapse collapse in">
									<div class="panel-body">
										<p>Click on Restore Database Backup </p>
										<p>OR</p>

										<p>Step 1) Login to phpMyAdmin.</p>
										<p>Step 2) Click Databases and select the database that you will be importing
											your
											data into.</p>
										<p>Step 3) Across the top of the screen will be a row of tabs. Click the Import
											tab.</p>
										<p>Step 4) On the next screen will be a location of text file box, and next to
											that
											a button named Browse.</p>
										<p>Step 5) Click Browse. Locate the backup file stored on your computer.</p>
										<p>Step 6) Click the Go button.</p>
									</div>
								</div>
							</div>

							<div class="panel-group" id="accordion">
								<div class="panel panel-default">
									<div class="panel-heading">
										<h4 class="panel-title">
											<a data-toggle="collapse" data-parent="#accordion" href="#collapseThree">
												Support
											</a>
										</h4>
									</div>
									<div id="collapseThree" class="panel-collapse collapse in">
										<div class="panel-body">
											<button type="button" class="btn btn-default"><a
													href='https://wpallbackup.com/support/'>Support</a></button>
											<button type="button" class="btn btn-default"><a
													href='http://www.wpseeds.com/documentation/docs/wp-database-backup'>Documentation</a>
											</button>
											<p>If you want more feature or any suggestion then drop me mail we are try
												to
												implement in our wp-database-backup plugin and also try to make it more
												user
												friendly</p>
											<p><span class="glyphicon glyphicon-envelope"></span> Drop Mail
												:walke.prashant28@gmail.com</p>
											If you like this plugin then Give <a target="_blank" href="http://wordpress.org/support/view/plugin-reviews/wp-database-backup" title="Rating" sl-processed="1">rating </a>on
											<a target="_blank" href="http://wordpress.org/support/view/plugin-reviews/wp-database-backup" title="Rating" sl-processed="1">WordPress.org</a></p>
											<p></br><a title="WP-DB-Backup" href="http://www.wpseeds.com/documentation/docs/wp-database-backup" target="_blank">More Information</a></p>
											<p>Support us to improve plugin. your idea and support are always welcome.
											</p>


										</div>
									</div>
								</div>

							</div>
						</div>
					</div>


				</div>

				<div class="tab-pane" id="db_info">

					<div class="panel panel-default">
						<div class="panel-heading">
							<h4 class="panel-title">
								<a data-toggle="collapse" data-parent="#accordion" href="#collapsedb">
									<?php esc_attr_e( 'System Check', 'wpdbbk' ); ?>

								</a>
							</h4>
						</div>
						<div id="collapsedb" class="panel-collapse collapse in">
							<div class="panel-body list-group">

								<div class="row list-group-item">
									<?php
									/* get disk space free (in bytes) */
									$df = disk_free_space( WPDB_ROOTPATH );
									/* and get disk space total (in bytes)  */
									$dt = disk_total_space( WPDB_ROOTPATH );
									/* now we calculate the disk space used (in bytes) */
									$du = $dt - $df;
									/* percentage of disk used - this will be used to also set the width % of the progress bar */
									$dp = sprintf( '%.2f', ( $du / $dt ) * 100 );

									/* and we formate the size from bytes to MB, GB, etc. */
									$df = $this->wp_db_backup_format_bytes( $df );
									$du = $this->wp_db_backup_format_bytes( $du );
									$dt = $this->wp_db_backup_format_bytes( $dt );
									?>
									<div class="col-md-1"><a href="" target="_blank" title="Help"><span
												class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
									</div>
									<div class="col-md-3">Disk Space</div>
									<div class="col-md-5">
										<div class="progress">
											<div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="<?php echo esc_attr( trim( $dp ) ); ?>" aria-valuemin="0" aria-valuemax="100" style="width:<?php echo esc_attr( trim( $dp ) ); ?>%"> <?php echo esc_attr( $dp ); ?>%
											</div>
										</div>
									</div>
									<div class="col-md-1"></div>
									<div class="col-md-4"></div>
									<div class="col-md-5">
										<div class='prginfo'>
											<p><?php echo esc_attr( $du ) . ' of ' . esc_attr( $dt ) . ' used '; ?></p>
											<p><?php echo esc_attr( $df ) . ' of ' . esc_attr( $dt ) . ' free '; ?></p>
											<p>
												<small>
													<?php esc_attr_e( 'Note: This value is the physical servers hard-drive allocation.', 'wpdbbkp' ); ?>
													<br/>
													<?php esc_attr_e( "On shared hosts check your control panel for the 'TRUE' disk space quota value.", 'wpdbbkp' ); ?>
												</small>
											</p>
										</div>

									</div>
								</div>

								<div class=""><br>
									<a type="button" href="<?php echo esc_url( site_url() ); ?>/wp-admin/tools.php?page=wp-database-backup&action=clear_temp_db_backup_file&_wpnonce=<?php echo esc_attr( $nonce ); ?>" class="btn btn-warning"><span class="glyphicon glyphicon-trash" aria-hidden="true"></span> Clear all old/temp database backup files</a>
									<p>Click above button to clear all your old or temporary created database backup
										files.
										It only delete file from backup directory which is not in 'Database Backups'
										listing(all other file excluding backup files listed in 'Database Backups' ).
										Before
										using this option make sure that you have save your database backup on safe
										place.</p>
									<p>The disk that your backup is saved on doesn’t have enough free space? Backup disk
										is
										almost full? Low disk space for backup? Backup failed due to lack of space? As
										you
										may set up a schedule to automatically do backup daily or weekly, and the size
										of
										disk space is limited, so your backup disk will run out of space quickly or
										someday.
										It is a real pain to manually delete old backups. Don’t worry about it. WP
										Database
										Backup makes it easy to delete old/temparary backup files using this option.</p>

								</div>

								<div class="row list-group-item">
									<div class="col-md-1"><a href="" target="_blank" title="Help"><span
												class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
									</div>
									<?php if ( true === isset( $_SERVER['DOCUMENT_ROOT'] ) ) { ?>
									<div class="col-md-3">Root Path</div>
									<div class="col-md-5"><?php echo esc_attr( sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) ); ?></div>
									<?php } ?>
								</div>


								<div class="row list-group-item">
									<div class="col-md-1"><a href="" target="_blank" title="Help"><span
												class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
									</div>
									<div class="col-md-3">ABSPATH</div>
									<div class="col-md-5"><?php echo esc_attr( ABSPATH ); ?></div>
								</div>

								<div class="row list-group-item">
									<div class="col-md-1"><a href="" target="_blank" title="Help"><span
												class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
									</div>
									<div class="col-md-3"><?php esc_attr_e( 'Upload directory URL', 'wpdbbk' ); ?></div>
									<div class="col-md-5">
									<?php
										$upload_dir = wp_upload_dir();
									echo esc_url( $upload_dir['baseurl'] )
									?>
		</div>
									<div class="col-md-3"></div>
								</div>

								<div class="row list-group-item">
									<div class="col-md-1"><a href="" target="_blank" title="Help"><span
												class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
									</div>
									<div class="col-md-3"><?php esc_attr_e( 'Upload directory', 'wpdbbk' ); ?></div>
									<div class="col-md-5"><?php echo esc_attr( $upload_dir['basedir'] ); ?></div>
									<div class="col-md-1">
										<?php echo esc_attr( substr( sprintf( '%o', fileperms( $upload_dir['basedir'] ) ), -4 ) ); ?></div>
									<div
										class="col-md-2"><?php echo ( ! is_writable( $upload_dir['basedir'] ) ) ? '<p class="text-danger"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span> Not writable </p>' : '<p class="text-success"><span class="glyphicon glyphicon-ok" aria-hidden="true"></span> writable</p>'; ?>
									</div>
								</div>

								<div class="row list-group-item">
									<div class="col-md-1"><a href="" target="_blank" title="Help"><span
												class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
									</div>
									<div class="col-md-3">Loaded PHP INI</div>
									<div class="col-md-5"><?php echo esc_attr( php_ini_loaded_file() ); ?></div>
								</div>
								<div class="row list-group-item">
									<div class="col-md-1"><a href="" target="_blank" title="Help"><span
												class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
									</div>
									<div class="col-md-3">Memory Limit</div>
									<div class="col-md-5">
									<?php
									echo esc_attr( WP_MEMORY_LIMIT );
									echo '(Max &nbsp;' . esc_attr( WP_MAX_MEMORY_LIMIT );
									?>
		)
									</div>
								</div>


								<div class="row list-group-item">
									<div class="col-md-1"><a href="" target="_blank" title="Help"><span
												class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
									</div>
									<div class="col-md-3"><?php esc_attr_e( 'Max Execution Time', 'wpdbbk' ); ?></div>
									<div class="col-md-5"> <?php echo esc_attr( ini_get( 'max_execution_time' ) ); ?></div>
									<div class="col-md-1"></div>
									<div
										class="col-md-2"><?php echo esc_attr( ini_get( 'max_execution_time' ) ) < 60 ? '<p class="text-danger"  data-toggle="tooltip" data-placement="left" title="For large site set high"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span> Low </p>' : ''; ?></div>
								</div>
								<div class="row  list-group-item">
									<div class="col-md-1"><a href="" target="_blank" title="Help"><span
												class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
									</div>
									<div class="col-md-3"><?php esc_attr_e( 'Database backup directory', 'wpdbbk' ); ?></div>
									<div
										class="col-md-5"> <?php echo esc_attr( $upload_dir['basedir'] . '/db-backup' ); ?></div>
									<div
										class="col-md-1"><?php echo esc_attr( substr( sprintf( '%o', fileperms( esc_attr( $upload_dir['basedir'] ) . '/db-backup' ) ), -4 ) ); ?></div>
									<div
										class="col-md-2"><?php echo ( ! is_writable( $upload_dir['basedir'] . '/db-backup' ) ) ? '<p class="text-danger"><span class="glyphicon glyphicon-remove" aria-hidden="true"></span> Not writable </p>' : '<p class="text-success"><span class="glyphicon glyphicon-ok" aria-hidden="true"></span> writable</p>'; ?></div>
								</div>

								<div class="row list-group-item">
									<div class="col-md-1"><a href="" target="_blank" title="Help"><span
												class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
									</div>
									<div class="col-md-3"><?php esc_attr_e( 'Class ZipArchive Present : ', 'wpdbbk' ); ?></div>
									<div class="col-md-5"> 
									<?php
										echo ( class_exists( 'ZipArchive' ) ) ? 'Yes </p>' : '<p class="">No</p>';
									?>
										</div>
									<div class="col-md-3"></div>
								</div>

								<div class="row list-group-item">
									<div class="col-md-1"><a href="" target="_blank" title="Help"><span
												class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
									</div>
									<div class="col-md-3"><?php esc_attr_e( 'mysqldump (cmd) Present : ', 'wpdbbk' ); ?></div>
									<div class="col-md-5"> 
									<?php
										$wpdb_admin = new Wpdb_Admin();
									echo ( $wpdb_admin->get_mysqldump_command_path() ) ? 'Yes </p>' : '<p class="">No</p>';
									?>
		</div>
									<div class="col-md-3"></div>
								</div>

							</div>
						</div>
					</div>

					<div class="panel panel-default">
						<div class="panel-heading">
							<h4 class="panel-title">
								<a data-toggle="collapse" data-parent="#accordion" href="#collapsedb">
									Database Information

								</a>
							</h4>
						</div>

						<div id="collapsedb" class="panel-collapse collapse in">
							<div class="panel-body">
								<table class="table table-condensed">
									<tr class="success">
										<th>Setting</th>
										<th>Value</th>
									</tr>
									<tr>
										<td>Database Host</td>
										<td><?php echo esc_attr( DB_HOST ); ?></td>
									</tr>
									<tr class="default">
										<td>Database Name</td>
										<td> <?php echo esc_attr( DB_NAME ); ?></td>
									</tr>
									<tr>
										<td>Database User</td>
										<td><?php echo esc_attr( DB_USER ); ?></td>
										</td>
									</tr>
									<tr>
										<td>Database Type</td>
										<td>MYSQL</td>
									</tr>
									<tr>
										<?php
										// Get MYSQL Version.
										global $wpdb;
										$mysqlversion = wp_cache_get( 'wpdb_mysqlversion' );
										if ( true === empty( $mysqlversion ) ) {
											$mysqlversion = $wpdb->get_var( 'SELECT VERSION() AS version' ); // phpcs:ignore
											wp_cache_set( 'wpdb_mysqlversion', $mysqlversion, '', 18000 );
										}
										?>
										<td>Database Version</td>
										<td>v<?php echo esc_attr( $mysqlversion ); ?></td>
									</tr>
								</table>

							</div>
						</div>
					</div>

					<div class="panel panel-default">
						<div class="panel-heading">
							<h4 class="panel-title">
								<a data-toggle="collapse" data-parent="#accordion" href="#collapsedbtable">
									Tables Information

								</a>
							</h4>
						</div>
						<div id="collapsedbtable" class="panel-collapse collapse">
							<div class="panel-body">
								<table class="table table-condensed">
									<tr class="success">
										<th>No.</th>
										<th>Tables</th>
										<th>Records</th>

									</tr>
									<?php
									$no           = 0;
									$row_usage    = 0;
									$data_usage   = 0;
									$tablesstatus = $wpdb->get_results( 'SHOW TABLE STATUS' ); // phpcs:ignore
									foreach ( $tablesstatus as $tablestatus ) {
										$tablestatus_arr = (array) $tablestatus;
										if ( 0 === ( $no % 2 ) ) {
											$style = '';
										} else {
											$style = ' class="alternate"';
										}
										$no++;
										echo '<tr' . esc_attr( $style ) . '>';
										echo '<td>' . esc_attr( number_format_i18n( $no ) ) . '</td>';
										echo '<td>' . esc_attr( $tablestatus_arr['Name'] ) . '</td>';
										echo '<td>' . esc_attr( number_format_i18n( $tablestatus_arr['Rows'] ) ) . '</td>';

										$row_usage += $tablestatus_arr['Rows'];

										echo '</tr>';
									}
									echo '<tr class="thead">';
									echo '<th> Total:</th>';
									echo '<th>' . esc_attr( number_format_i18n( $no ) ) . ' Table </th>';
									echo '<th>' . esc_attr( number_format_i18n( $row_usage ) ) . ' Records</th>';

									echo '</tr>';
									?>
								</table>

							</div>
						</div>
					</div>
					<div class="panel panel-default">
						<div class="panel-heading">
							<h4 class="panel-title">
								<a data-toggle="collapse" data-parent="#accordion" href="#collapsewp">
									WordPress Information

								</a>
							</h4>
						</div>
						<div id="collapsewp" class="panel-collapse collapse">
							<div class="panel-body">
								<table class="table table-condensed">
									<tr class="success">
										<th>Setting</th>
										<th>Value</th>

									</tr>
									<tr>
										<td>WordPress Version</td>
										<td><?php bloginfo( 'version' ); ?></td>
									</tr>
									<tr>
										<td>Home URL</td>
										<td> <?php echo esc_url( home_url() ); ?></td>
									</tr>
									<tr>
										<td>Site URL</td>
										<td><?php echo esc_url( site_url() ); ?></td>
									</tr>
									<tr>
										<td>Upload directory URL</td>
										<td><?php $upload_dir = wp_upload_dir(); ?>
											<?php echo esc_url( $upload_dir['baseurl'] ); ?></td>
									</tr>
								</table>

							</div>
						</div>
					</div>

					<div class="panel panel-default">
						<div class="panel-heading">
							<h4 class="panel-title">
								<a data-toggle="collapse" data-parent="#accordion" href="#collapsewpsetting">
									WordPress Settings

								</a>
							</h4>
						</div>
						<div id="collapsewpsetting" class="panel-collapse collapse">
							<div class="panel-body">
								<table class="table table-condensed">
									<tr class="success">
										<th>Plugin Name</th>
										<th>Version</th>
									</tr>
									<?php
									$plugins = get_plugins();
									foreach ( $plugins as $plugin ) {
										echo '<tr>
                                           <td>' . esc_attr( $plugin['Name'] ) . '</td>
                                           <td>' . esc_attr( $plugin['Version'] ) . '</td>
                                        </tr>';
									}
									?>
								</table>
								<table class="table table-condensed">
									<tr class="success">
										<th>Active Theme Name</th>
										<th>Version</th>
									</tr>
									<?php
									$my_theme = wp_get_theme();

									echo '<tr>
                                           <td>' . esc_attr( $my_theme->get( 'Name' ) ) . '</td>
                                           <td>' . esc_attr( $my_theme->get( 'Version' ) ) . '</td>
                                        </tr>';
									?>
								</table>
								<div class="row">
									<button class="btn btn-primary" type="button">
										Drafts Post Count <span class="badge">
										<?php
											$count_posts = wp_count_posts();
										echo esc_attr( $count_posts->draft );
										?>
		</span>
									</button>
									<button class="btn btn-primary" type="button">
										Publish Post Count <span class="badge">
										<?php

										echo esc_attr( $count_posts->publish );
										?>
		</span>
									</button>
									<button class="btn btn-primary" type="button">
										Drafts Pages Count <span class="badge">
										<?php
											$count_pages = wp_count_posts( 'page' );
										echo esc_attr( $count_pages->draft );
										?>
		</span>
									</button>
									<button class="btn btn-primary" type="button">
										Publish Pages Count <span class="badge">
										<?php

										echo esc_attr( $count_pages->publish );
										?>
		</span>
									</button>
									<button class="btn btn-primary" type="button">
										Approved Comments Count <span class="badge">
										<?php
											$comments_count = wp_count_comments();
										echo esc_attr( $comments_count->approved );
										?>
		</span>
									</button>
								</div>
							</div>
						</div>
					</div>


				</div>
				<div class="tab-pane" id="db_advanced">
					<h4>A 'WP ALL Backup' Plugin will backup and restore your entire site at will,
						complete with Dropbox,FTP,Email,Google drive, Amazon S3 integration.</h4>
					<h2>Pro Features </h2><h4><?php echo wp_kses_post( $coupon ); ?></h4>
					<div class="row">
						<div class="col-md-3"><span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span>
							Complete
							Backup
						</div>
						<div class="col-md-3"><span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span> Only
							Selected file Backup
						</div>
						<div class="col-md-3"><span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span>
							ZipArchive
						</div>
						<div class="col-md-3"><span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span>
							PclZip
						</div>
						<div class="col-md-3"><span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span>
							Scheduled
							backups
						</div>
						<div class="col-md-3"><span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span> Set
							backup interval
						</div>
						<div class="col-md-3"><span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span>
							Manual
							backup
						</div>
						<div class="col-md-3"><span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span>
							Multisite
							compatible
						</div>
						<div class="col-md-3"><span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span>
							Backup
							entire site
						</div>
						<div class="col-md-3"><span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span>
							Include
							media files
						</div>
						<div class="col-md-3"><span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span>
							Exclude
							specific files
						</div>
						<div class="col-md-3"><span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span>
							Downloadable log files
						</div>
						<div class="col-md-3"><span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span>
							Simple
							one-click restore
						</div>
						<div class="col-md-3"><span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span> Set
							number of backups to store
						</div>
						<div class="col-md-3"><span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span>
							Automatically remove oldest backup
						</div>
						<div class="col-md-3"><span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span>
							Dropbox
							integration
						</div>
						<div class="col-md-3"><span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span> FTP
							and
							SFTP integration
						</div>
						<div class="col-md-3"><span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span>
							Server
							info quick view
						</div>
						<div class="col-md-3"><span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span>
							Support
						</div>
					</div>
					<h3>Key Features</h3>
					<div class="row">

						<div class="col-md-3">
							<h4>Fast</h4>
							<p class="bg-success">
								This plugin can help you to rapidly create site backup.
								Capture your entire site, including media files, or pick and choose specific files and
								tables.
							</p>
						</div>
						<div class="col-md-3">
							<h4>Scheduled Backups</h4>
							<p class="bg-info">
								Create manual backups, as needed, or schedule automated backups.
								Trigger monthly, daily or hourly backups that are there when you need them most.
							</p>
						</div>
						<div class="col-md-3">
							<h4>Essay to use</h4>
							<p class="bg-info">
								Create and store as many backups of your site as you want.
								Get added protection and convenience with one-click restoration.
								Delete old backups options.
							</p>
						</div>
						<div class="col-md-3">
							<h4>Integration</h4>
							<p class="bg-success">
								Tie directly into other destination.
								Save directly to your favorite cloud services including Dropbox,
								by FTP/SFTP for added security.
							</p>
						</div>
					</div>


					<a href="https://www.wpseeds.com/product/wp-all-backup/" target="_blank"><h4><span
								class="label label-success">Get Pro 'WP All Backup' Plugin</span></h4></a>
				</div>
				<div class="tab-pane" id="db_setting">
					<div class="panel panel-default">
						<div class="panel-body">
							<?php
							$wp_local_db_backup_count         = get_option( 'wp_local_db_backup_count' );
							$wp_db_log                        = get_option( 'wp_db_log' );
							$wp_db_exclude_table              = array();
							$wp_db_exclude_table              = get_option( 'wp_db_exclude_table' );
							$wp_db_backup_enable_auto_upgrade = get_option( 'wp_db_backup_enable_auto_upgrade' );
							if ( 1 === (int) $wp_db_backup_enable_auto_upgrade ) {
								$wp_db_backup_enable_auto_upgrade_checked = 'checked';
							} else {
								$wp_db_backup_enable_auto_upgrade_checked = '';
							}
							if ( 1 === (int) $wp_db_log ) {
								$checked = 'checked';
							} else {
								$checked = '';
							}
							$wp_db_remove_local_backup = get_option( 'wp_db_remove_local_backup' );
							if ( 1 === (int) $wp_db_remove_local_backup ) {
								$remove_local_backup = 'checked';
							} else {
								$remove_local_backup = '';
							}
							?>
							<form action="" method="post">
								<?php wp_nonce_field( 'wp-database-backup' ); ?>
								<div class="input-group">
									<span class="input-group-addon" id="sizing-addon2">Maximum Local Backups</span>
									<input type="number" name="wp_local_db_backup_count" value="<?php echo esc_html( $wp_local_db_backup_count ); ?>" class="form-control" placeholder="Maximum Local Backups" aria-describedby="sizing-addon2">

								</div>
								<div class="alert alert-default" role="alert">
									<span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> The maximum
									number of Local Database Backups that should be kept, regardless of their size.</br>
									Leave blank for keep unlimited database backups.
								</div>
								<hr>
								<div class="input-group">
									<input type="checkbox" <?php echo esc_attr( $checked ); ?> name="wp_db_log"> Enable Log.
								</div>
								<hr>
								<div class="input-group">
									<input type="checkbox" <?php echo esc_attr( $wp_db_backup_enable_auto_upgrade_checked ); ?> name="wp_db_backup_enable_auto_upgrade"> Enable Auto Backups Before Upgrade.
									<p><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>
										If checked then it will create database backup on(before) upgrade/update plugin, theme, WordPress.
										<br>Leave blank/un-checked for disable this feature.
									</p>
								</div>
								<hr>
								<div class="input-group">
									<input type="checkbox" <?php echo esc_attr( $remove_local_backup ); ?> name="wp_db_remove_local_backup"> Remove local backup.
									<p><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>
										If Checked then it will remove local backup.
										<br>Use this option only when you have set any destination.
										<br>If somesites you need only external backup.
									</p>
								</div>
								<hr>
								<div class="input-group">
									<input type="checkbox" <?php checked( get_option( 'wp_db_backup_enable_htaccess' ), '1' ); ?>  name="wp_db_backup_enable_htaccess"> Enable .htaccess File In Storage Directory
									<p>Disable if issues occur when downloading backup/archive files.</p>
								</div>
								<hr>

								<div class="panel panel-default">
									<div class="panel-heading">
										<h4 class="panel-title">
											<a data-toggle="collapse" data-parent="#accordion" href="#collapseExclude">
												Exclude Table From Database Backup.
											</a>
										</h4>
									</div>
									<div id="collapseExclude" class="panel-collapse collapse in">
										<div class="panel-body">
											<table class="table table-condensed">
												<tr class="success">
													<th>No.</th>
													<th>Tables</th>
													<th>Records</th>
													<th><input type="checkbox" value="" onclick="excludetableall()" name="wp_db_exclude_table_all" id="wp_db_exclude_table_all">Exclude Table</th>
												</tr>
												<?php
												$no           = 0;
												$row_usage    = 0;
												$data_usage   = 0;
												$tablesstatus = $wpdb->get_results( 'SHOW TABLE STATUS' ); // phpcs:ignore
												foreach ( $tablesstatus as $tablestatus ) {
													$tablestatus_arr = (array) $tablestatus;
													if ( 0 === ( $no % 2 ) ) {
														$style = '';
													} else {
														$style = ' class="alternate"';
													}
													$no++;
													echo '<tr' . esc_attr( $style ) . '>';
													echo '<td>' . esc_attr( number_format_i18n( $no ) ) . '</td>';
													echo '<td>' . esc_attr( $tablestatus_arr['Name'] ) . '</td>';
													echo '<td>' . esc_attr( number_format_i18n( $tablestatus_arr['Rows'] ) ) . '</td>';
													if ( false === empty( $wp_db_exclude_table ) && in_array( $tablestatus_arr['Name'], $wp_db_exclude_table, true ) ) {
														$checked = 'checked';
													} else {
														$checked = '';
													}
													echo '<td> <input class="wp_db_exclude_table" type="checkbox" ' . esc_attr( $checked ) . ' value="' . esc_attr( $tablestatus_arr['Name'] ) . '" name="wp_db_exclude_table[' . esc_attr( $tablestatus_arr['Name'] ) . ']"></td>';

													$row_usage += $tablestatus_arr['Rows'];

													echo '</tr>';
												}
												echo '<tr class="thead">';
												echo '<th>Total:</th>';
												echo '<th>' . esc_attr( number_format_i18n( $no ) ) . ' Table</th>';
												echo '<th>' . esc_attr( number_format_i18n( $row_usage ) ) . ' Records</th>';
												echo '<th></th>';
												echo '</tr>';
												?>
											</table>
										</div>
									</div>
								</div>
								<hr>
								<input class="btn btn-primary" type="submit" name="wpsetting" value="Save">
							</form>
						</div>
					</div>

				</div>
				<div class="tab-pane" id="searchreplace">
					<div class="panel panel-default">
						<div class="panel-body">
							<?php
							$wp_db_backup_search_text  = get_option( 'wp_db_backup_search_text' );
							$wp_db_backup_replace_text = get_option( 'wp_db_backup_replace_text' );
							?>
							<form action="" method="post">
								<?php wp_nonce_field( 'wp-database-backup' ); ?>
								<br>
								<p>If you even need to migrate your WordPress site to a different domain name, or add an SSL certificate to it, you must update the URLs in your database backup file then you can use this feature. <br> This feature allow you to Search and Replace text in your database backup file. <br> if you want only exclude tables from search and replace text then Go to Dashboard=>Tool=>WP-DB Backup > Setting > Exclude Table From Database Backup setting. The tables you selected will be skipped over for each backup you make.
								</p>
								<br>
								<div class="input-group">
									<span class="input-group-addon" id="wp_db_backup_search_text">Search For</span>
									<input type="text" name="wp_db_backup_search_text" value="<?php echo esc_html( $wp_db_backup_search_text ); ?>" class="form-control" placeholder="http://localhost/wordpress" aria-describedby="wp_db_backup_search_text">

								</div>
								<br>
								<div class="input-group">
									<span class="input-group-addon" id="wp_db_backup_replace_text">Replace With</span>
									<input type="text" name="wp_db_backup_replace_text" value="<?php echo esc_html( $wp_db_backup_replace_text ); ?>" class="form-control" placeholder="http://site.com" aria-describedby="wp_db_backup_replace_text">

								</div>

								<div class="alert alert-default" role="alert">
									<span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>
									Leave blank those fields if you don't want use this feature and want only regular Database backup.
									<br>
									Ex:
									<br>Search For: http://localhost/wordpress/
									<br>Replace With: http://domain.com/

									<br><br>
									Note - This is Search & Replace data in your WordPress Database Backup File not in current Database installation.
									<p> <a href="https://www.wpseeds.com/documentation/docs/wp-database-backup/search-and-replace/" target="_blank">Documentation</a></p>
								</div>

								<input class="btn btn-primary" type="submit" name="wpsetting_search" value="Save">
							</form>
						</div>
					</div>


				</div>
				<div class="tab-pane" id="db_destination">
					<?php
					include plugin_dir_path( __FILE__ ) . 'Destination/wp-backup-destination.php';
					?>
				</div>              


			</div>

		</div>
		</div>

		<?php
	}

	/**
	 * Run after complete backup.
	 *
	 * @param bool $bytes - bytes details.
	 * @param int  $precision - precision details.
	 */
	public function wp_db_backup_format_bytes( $bytes, $precision = 2 ) {
		$units  = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$bytes  = max( $bytes, 0 );
		$pow    = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow    = min( $pow, count( $units ) - 1 );
		$bytes /= pow( 1024, $pow );
		return round( $bytes, $precision ) . ' ' . $units[ $pow ];
	}

	/**
	 * Create database bakup function.
	 */
	public function wp_db_backup_create_mysql_backup() {
		global $wpdb;
		/* BEGIN : Prevent saving backup plugin settings in the database dump */
		$options_backup  = get_option( 'wp_db_backup_backups' );
		$settings_backup = get_option( 'wp_db_backup_options' );
		delete_option( 'wp_db_backup_backups' );
		delete_option( 'wp_db_backup_options' );
		/* END : Prevent saving backup plugin settings in the database dump */
		$wp_db_exclude_table = array();
		$wp_db_exclude_table = get_option( 'wp_db_exclude_table' );
		$tables              = $wpdb->get_col( 'SHOW TABLES' ); // phpcs:ignore
		$output              = '';
		foreach ( $tables as $table ) {
			if ( empty( $wp_db_exclude_table ) || ( ! ( in_array( $table, $wp_db_exclude_table, true ) ) ) ) {
				$result       = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_N ); // phpcs:ignore
				$row2         = $wpdb->get_row( 'SHOW CREATE TABLE ' . $table, ARRAY_N ); // phpcs:ignore
				$output      .= "\n\n" . $row2[1] . ";\n\n";
				$result_count = count( $result );
				for ( $i = 0; $i < $result_count; $i++ ) {
					$row            = $result[ $i ];
					$output        .= 'INSERT INTO ' . $table . ' VALUES(';
					$result_o_index = count( $result[0] );
					for ( $j = 0; $j < $result_o_index; $j++ ) {
						$row[ $j ] = $wpdb->_real_escape( $row[ $j ] );
						$output   .= ( isset( $row[ $j ] ) ) ? '"' . $row[ $j ] . '"' : '""';
						if ( $j < ( $result_o_index - 1 ) ) {
							$output .= ',';
						}
					}
					$output .= ");\n";
				}
				$output .= "\n";
			}
		}
		$wpdb->flush();
		/* BEGIN : Prevent saving backup plugin settings in the database dump */
		add_option( 'wp_db_backup_backups', $options_backup );
		add_option( 'wp_db_backup_options', $settings_backup );
		/* END : Prevent saving backup plugin settings in the database dump */
		return $output;
	}

	/**
	 * Mysql Dump set path.
	 *
	 * @param string $path - Path.
	 */
	public function set_mysqldump_command_path( $path ) {
		$this->mysqldump_command_path = $path;
	}

			/**
			 * Mysql Dump get path.
			 */
	public function get_mysqldump_command_path() {

		// Check shell_exec is available.
		if ( ! self::is_shell_exec_available() ) {
			return '';
		}

		// Return now if it's already been set.
		if ( isset( $this->mysqldump_command_path ) ) {
			return $this->mysqldump_command_path;
		}

		$this->mysqldump_command_path = '';

		// Does mysqldump work.
		if ( is_null( shell_exec( 'hash mysqldump 2>&1' ) ) ) { // phpcs:ignore

			// If so store it for later.
			$this->set_mysqldump_command_path( 'mysqldump' );

			// And return now.
			return $this->mysqldump_command_path;
		}

		// List of possible mysqldump locations.
		$mysqldump_locations = array(
			'/usr/local/bin/mysqldump',
			'/usr/local/mysql/bin/mysqldump',
			'/usr/mysql/bin/mysqldump',
			'/usr/bin/mysqldump',
			'/opt/local/lib/mysql6/bin/mysqldump',
			'/opt/local/lib/mysql5/bin/mysqldump',
			'/opt/local/lib/mysql4/bin/mysqldump',
			'/xampp/mysql/bin/mysqldump',
			'/Program Files/xampp/mysql/bin/mysqldump',
			'/Program Files/MySQL/MySQL Server 6.0/bin/mysqldump',
			'/Program Files/MySQL/MySQL Server 5.5/bin/mysqldump',
			'/Program Files/MySQL/MySQL Server 5.4/bin/mysqldump',
			'/Program Files/MySQL/MySQL Server 5.1/bin/mysqldump',
			'/Program Files/MySQL/MySQL Server 5.0/bin/mysqldump',
			'/Program Files/MySQL/MySQL Server 4.1/bin/mysqldump',
		);

		// Find the one which works.
		foreach ( $mysqldump_locations as $location ) {
			if ( is_executable( self::conform_dir( $location ) ) ) {
				$this->set_mysqldump_command_path( $location );
			}
		}

		return $this->mysqldump_command_path;
	}

	/**
	 * Check dir.
	 *
	 * @param string $dir - Dir Details.
	 * @param bool   $recursive - Recursive.
	 */
	public static function conform_dir( $dir, $recursive = false ) {

		// Assume empty dir is root.
		if ( ! $dir ) {
			$dir = '/';
		}

		// Replace single forward slash (looks like double slash because we have to escape it).
		$dir = str_replace( '\\', '/', $dir );
		$dir = str_replace( '//', '/', $dir );

		// Remove the trailing slash.
		if ( '/' !== $dir ) {
			$dir = untrailingslashit( $dir );
		}

		// Carry on until completely normalized.
		if ( ! $recursive && self::conform_dir( $dir, true ) !== $dir ) {
			return self::conform_dir( $dir );
		}

		return (string) $dir;
	}

		/**
		 * Check Shell.
		 */
	public static function is_shell_exec_available() {

		// Are we in Safe Mode.
		if ( self::is_safe_mode_active() ) {
			return false;
		}

		// Is shell_exec or escapeshellcmd or escapeshellarg disabled?
		if ( array_intersect( array( 'shell_exec', 'escapeshellarg', 'escapeshellcmd' ), array_map( 'trim', explode( ',', ini_get( 'disable_functions' ) ) ) ) ) {
			return false;
		}

		// Can we issue a simple echo command?
		if ( ! shell_exec( 'echo WP Backup' ) ) { // phpcs:ignore
			return false;
		}

		return true;
	}

	/**
	 * Check Safe mode active.
	 *
	 * @param string $ini_get_callback - String cmd.
	 * @return bool
	 */
	public static function is_safe_mode_active( $ini_get_callback = 'ini_get' ) {
		$safe_mode = call_user_func( $ini_get_callback, 'safe_mode' );
		if ( ( $safe_mode ) && 'off' !== strtolower( $safe_mode ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Database dump.
	 *
	 * @param string $sql_filename - File name.
	 */
	public function mysqldump( $sql_filename ) {
		$this->mysqldump_method = 'mysqldump';

		$host = explode( ':', DB_HOST );

		$host = reset( $host );
		$port = strpos( DB_HOST, ':' ) ? end( explode( ':', DB_HOST ) ) : '';

		// Path to the mysqldump executable.
		$cmd = escapeshellarg( $this->get_mysqldump_command_path() );

		// We don't want to create a new DB.
		$cmd .= ' --no-create-db';

		// Allow lock-tables to be overridden.
		if ( ! defined( 'WPDB_MYSQLDUMP_SINGLE_TRANSACTION' ) || WPDB_MYSQLDUMP_SINGLE_TRANSACTION !== false ) {
			$cmd .= ' --single-transaction';
		}

		// Make sure binary data is exported properly.
		$cmd .= ' --hex-blob';

		// Username.
		$cmd .= ' -u ' . escapeshellarg( DB_USER );

		// Don't pass the password if it's blank.
		if ( DB_PASSWORD ) {
			$cmd .= ' -p' . escapeshellarg( DB_PASSWORD );
		}

		// Set the host.
		$cmd .= ' -h ' . escapeshellarg( $host );

		// Set the port if it was set.
		if ( ! empty( $port ) && is_numeric( $port ) ) {
			$cmd .= ' -P ' . $port;
		}

		// The file we're saving too.
		$cmd .= ' -r ' . escapeshellarg( $sql_filename );

		$wp_db_exclude_table = array();
		$wp_db_exclude_table = get_option( 'wp_db_exclude_table' );
		if ( ! empty( $wp_db_exclude_table ) ) {
			foreach ( $wp_db_exclude_table as $wp_db_exclude_table ) {
				$cmd .= ' --ignore-table=' . DB_NAME . '.' . $wp_db_exclude_table;
			}
		}

		// The database we're dumping.
		$cmd .= ' ' . escapeshellarg( DB_NAME );

		// Pipe STDERR to STDOUT.
		$cmd .= ' 2>&1';
		// Store any returned data in an error.
		$stderr = shell_exec( $cmd ); // phpcs:ignore

		// Skip the new password warning that is output in mysql > 5.6.
		if ( trim( $stderr ) === 'Warning: Using a password on the command line interface can be insecure.' ) {
			$stderr = '';
		}

		if ( $stderr ) {
			$this->error( $this->get_mysqldump_method(), $stderr );
		}

		return $this->verify_mysqldump( $sql_filename );
	}

	/**
	 * Error.
	 *
	 * @param string $context -  Data.
	 * @param object $error - Error data.
	 */
	public function error( $context, $error ) {
		if ( empty( $context ) || empty( $error ) ) {
			return;
		}
		$error_str                         = implode( ':', (array) $error );
		$_key                              = md5( $error_str );
		$this->errors[ $context ][ $_key ] = $error;
	}

	/**
	 * Verify Dump.
	 *
	 * @param string $sql_filename - Sql file.
	 * @return bool
	 */
	public function verify_mysqldump( $sql_filename ) {

		// If we've already passed then no need to check again.
		if ( ! empty( $this->mysqldump_verified ) ) {
			return true;
		}

		// If there are mysqldump errors delete the database dump file as mysqldump will still have written one.
		if ( $this->get_errors( $this->get_mysqldump_method() ) && file_exists( $sql_filename ) ) {
			if ( file_exists( $database_file ) ) {
				unlink( $database_file );
			}
		}

		// If we have an empty file delete it.
		if ( 0 === filesize( $sql_filename ) ) {
			if ( file_exists( $sql_filename ) ) {
				unlink( $sql_filename );
			}
		}

		// If the file still exists then it must be good.
		if ( file_exists( $sql_filename ) ) {
			$this->mysqldump_verified = true;
			return $this->mysqldump_verified;
		}

		return false;
	}

	/**
	 * Get error.
	 *
	 * @param string $context -  Data.
	 * @return string
	 */
	public function get_errors( $context = null ) {
		if ( ! empty( $context ) ) {
			return isset( $this->errors[ $context ] ) ? $this->errors[ $context ] : array();
		}

		return $this->errors;
	}

	/**
	 * Get mysql dump method.
	 *
	 * @return string
	 */
	public function get_mysqldump_method() {
		return $this->mysqldump_method;
	}

	// End : Generate SQL DUMP using cmd 06-03-2016.

	/**
	 * Create zip.
	 */
	public function wp_db_backup_create_archive() {
		// Begin : Setup Upload Directory, Secure it and generate a random file name.

		$source_directory = $this->wp_db_backup_wp_config_path();

		$path_info    = wp_upload_dir();
		$htasses_text = '';
		wp_mkdir_p( $path_info['basedir'] . '/db-backup' );
		fclose( fopen( $path_info['basedir'] . '/db-backup/index.php', 'w' ) ); // phpcs:ignore
		// Added htaccess file 08-05-2015 for prevent directory listing.
		// Fixed Vulnerability 22-06-2016 for prevent direct download.
		if ( 1 === (int) get_option( 'wp_db_backup_enable_htaccess' ) ) {
			$f = fopen( $path_info['basedir'] . '/db-backup/.htaccess', 'w' ); // phpcs:ignore
			fwrite( // phpcs:ignore
				$f,
				'#These next two lines will already exist in your .htaccess file
				RewriteEngine On
				RewriteBase /
				# Add these lines right after the preceding two
				RewriteCond %{REQUEST_FILENAME} ^.*(.zip)$
				RewriteCond %{HTTP_COOKIE} !^.*can_download.*$ [NC]
				RewriteRule . - [R=403,L]'
			); // phpcs:ignore
			fclose( $f ); // phpcs:ignore
		}
		// Begin : Generate SQL DUMP and save to file database.sql.
		$wp_site_name    = preg_replace( '/[^A-Za-z0-9\_]/', '_', get_bloginfo( 'name' ) );
		$wp_db_file_name = $wp_site_name . '_' . gmdate( 'Y_m_d' ) . '_' . time() . '_' . substr( md5( AUTH_KEY ), 0, 7 ) . '_wpdb';
		$sql_filename    = $wp_db_file_name . '.sql';
		$filename        = $wp_db_file_name . '.zip';

		// Begin : Generate SQL DUMP using cmd 06-03-2016.
		$my_sql_dump = 0;
		if ( $this->get_mysqldump_command_path() ) {
			if ( ! $this->mysqldump( $path_info['basedir'] . '/db-backup/' . $sql_filename ) ) {
				$my_sql_dump = 1;
			}
		} else {
			$my_sql_dump = 1;
		}
		if ( 1 === (int) $my_sql_dump ) {
			$handle = fopen( $path_info['basedir'] . '/db-backup/' . $sql_filename, 'w+' ); // phpcs:ignore
			fwrite( $handle, $this->wp_db_backup_create_mysql_backup() ); // phpcs:ignore
			fclose( $handle ); // phpcs:ignore
		}
		/* End : Generate SQL DUMP using cmd 06-03-2016 */

		$wp_db_backup_search_text  = get_option( 'wp_db_backup_search_text' );
		$wp_db_backup_replace_text = get_option( 'wp_db_backup_replace_text' );
		if ( ( false === empty( $wp_db_backup_search_text ) ) && ( false === empty( $wp_db_backup_replace_text ) ) ) {
			$backup_str = file_get_contents( $path_info['basedir'] . '/db-backup/' . $sql_filename ); // phpcs:ignore
			$filecontent = wp_remote_get( $path_info['basedir'] . '/db-backup/' . $sql_filename );
			$backup_str = str_replace( $wp_db_backup_search_text, $wp_db_backup_replace_text, $backup_str ); // phpcs:ignore
			file_put_contents( $path_info['basedir'] . '/db-backup/' . $sql_filename, $backup_str ); // phpcs:ignore
		}

		/* End : Generate SQL DUMP and save to file database.sql */
		$upload_path = array(
			'filename' => ( $filename ),
			'dir'      => ( $path_info['basedir'] . '/db-backup/' . $filename ),
			'url'      => ( $path_info['baseurl'] . '/db-backup/' . $filename ),
			'size'     => 0,
		);
		$arcname     = $path_info['basedir'] . '/db-backup/' . $wp_db_file_name . '.zip';
		if ( class_exists( 'ZipArchive' ) ) {
			$zip = new ZipArchive();
			$zip->open( $arcname, ZipArchive::CREATE );
			$zip->addFile( $path_info['basedir'] . '/db-backup/' . $sql_filename, $sql_filename );
			$zip->close();
		} else {
			require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
			$archive  = new PclZip( $arcname );
			$v_dir    = $path_info['basedir'] . '/db-backup/' . $sql_filename;
			$v_remove = $path_info['basedir'] . '/db-backup';
			// Create the archive.
			$v_list = $archive->create( $v_dir, PCLZIP_OPT_REMOVE_PATH, $v_remove );
		}

		global $wpdb;
		$mysqlversion = wp_cache_get( 'wpdb_mysqlversion' );
		if ( true === empty( $mysqlversion ) ) {
			$mysqlversion = $wpdb->get_var( 'SELECT VERSION() AS version' ); // phpcs:ignore
			wp_cache_set( 'wpdb_mysqlversion', $mysqlversion, '', 18000 );
		}
		$my_theme     = wp_get_theme();
		$log_message  = 'WordPress Version :' . get_bloginfo( 'version' );
		$log_message .= ', Database Version :' . $mysqlversion;
		$log_message .= ', Active Theme Name :' . $my_theme->get( 'Name' );
		$log_message .= ', Theme Version :' . $my_theme->get( 'Version' );

		$upload_path['size']    = filesize( $upload_path['dir'] );
		$upload_path['sqlfile'] = $path_info['basedir'] . '/db-backup/' . $sql_filename;
		$wp_db_log              = get_option( 'wp_db_log' );
		if ( 1 === (int) $wp_db_log ) {
			$wp_db_exclude_table = get_option( 'wp_db_exclude_table' );
			if ( ! empty( $wp_db_exclude_table ) ) {
				$log_message .= '<br> Exclude Table : ' . implode( ', ', $wp_db_exclude_table );
			}
			$upload_path['log'] = $log_message;
		}
		$options                     = get_option( 'wp_db_backup_backups' );
		$newoptions                  = array();
		$number_of_existing_backups  = count( (array) $options );
		$number_of_backups_from_user = get_option( 'wp_local_db_backup_count' );
		if ( ! empty( $number_of_backups_from_user ) ) {
			if ( ! ( $number_of_existing_backups < $number_of_backups_from_user ) ) {
				$diff = $number_of_existing_backups - $number_of_backups_from_user;
				for ( $i = 0; $i <= $diff; $i++ ) {
					$index = $i;
					if ( file_exists( $options[ $index ]['dir'] ) ) {
						unlink( $options[ $index ]['dir'] );
					}
					$file_sql = explode( '.', $options[ $index ]['dir'] );
					if ( file_exists( $file_sql[0] . '.sql' ) ) {
						unlink( $file_sql[0] . '.sql' );
					}
				}
				for ( $i = ( $diff + 1 ); $i < $number_of_existing_backups; $i++ ) {
					$index = $i;

					$newoptions[] = $options[ $index ];
				}

				update_option( 'wp_db_backup_backups', $newoptions );
			}
		}
		if ( file_exists( $path_info['basedir'] . '/db-backup/' . $sql_filename ) ) {
			unlink( $path_info['basedir'] . '/db-backup/' . $sql_filename );
		}
		return $upload_path;
	}

	/**
	 * Config Path.
	 */
	public function wp_db_backup_wp_config_path() {
		$base = dirname( __FILE__ );
		$path = false;
		if ( file_exists( dirname( dirname( $base ) ) . '/wp-config.php' ) ) {
			$path = dirname( dirname( $base ) );
		} else {
			if ( file_exists( dirname( dirname( dirname( $base ) ) ) . '/wp-config.php' ) ) {
				$path = dirname( dirname( dirname( $base ) ) );
			} else {
				$path = false;
			}
		}
		if ( false !== $path ) {
			$path = str_replace( '\\', '/', $path );
		}
		return $path;
	}

	/**
	 * Backup Process.
	 */
	public function wp_db_backup_event_process() {
		// Added in v.3.9.5!
		set_time_limit( 0 );

		$details = $this->wp_db_backup_create_archive();
		$options = get_option( 'wp_db_backup_backups' );

		if ( ! $options ) {
			$options = array();
		}
		$is_search_replace_flag = 0;
		$wp_db_log              = get_option( 'wp_db_log' );
		if ( 1 === (int) $wp_db_log ) {
			$log_message               = $details['log'];
			$wp_db_backup_search_text  = get_option( 'wp_db_backup_search_text' );
			$wp_db_backup_replace_text = get_option( 'wp_db_backup_replace_text' );
			if ( ( false === empty( $wp_db_backup_search_text ) ) && ( false === empty( $wp_db_backup_replace_text ) ) ) {
				$log_message           .= ' Replaced/Search text  - ' . $wp_db_backup_search_text . ' With -' . $wp_db_backup_replace_text;
				$is_search_replace_flag = 1;
			}
		} else {
			$log_message = '';
		}

		$options[]                 = array(
			'date'           => time(),
			'filename'       => $details['filename'],
			'url'            => $details['url'],
			'dir'            => $details['dir'],
			'log'            => $log_message,
			'search_replace' => $is_search_replace_flag,
			'sqlfile'        => $details['sqlfile'],
			'size'           => $details['size'],
		);
		$wp_db_remove_local_backup = get_option( 'wp_db_remove_local_backup' );
		if ( 1 !== (int) $wp_db_remove_local_backup ) {
			update_option( 'wp_db_backup_backups', $options );
		}
		$wp_db_remove_local_backup = get_option( 'wp_db_remove_local_backup' );
		$destination               = ( 1 === (int) $wp_db_remove_local_backup ) ? '' : 'Local, ';

		$args = array( $details['filename'], $details['dir'], $log_message, $details['size'], $destination );
		do_action_ref_array( 'wp_db_backup_completed', array( &$args ) );
	}

	/**
	 * Cron schedule.
	 *
	 * @param array $schedules - Schedules details.
	 */
	public function wp_db_backup_cron_schedules( $schedules ) {
		$schedules['hourly']     = array(
			'interval' => 3600,
			'display'  => 'hourly',
		);
		$schedules['twicedaily'] = array(
			'interval' => 43200,
			'display'  => 'twicedaily',
		);
		$schedules['daily']      = array(
			'interval' => 86400,
			'display'  => 'daily',
		);
		$schedules['weekly']     = array(
			'interval' => 604800,
			'display'  => 'Once Weekly',
		);
		$schedules['monthly']    = array(
			'interval' => 2635200,
			'display'  => 'Once a month',
		);
		return $schedules;
	}

	/**
	 * Schedular activation.
	 */
	public function wp_db_backup_scheduler_activation() {
		$options = get_option( 'wp_db_backup_options' );
		if ( ( ! wp_next_scheduled( 'wp_db_backup_event' ) ) && ( true === isset( $options['enable_autobackups'] ) ) ) {
			wp_schedule_event( time(), $options['autobackup_frequency'], 'wp_db_backup_event' );
		}
	}

	/**
	 * Config data.
	 *
	 * @param string $key - key name.
	 */
	public function wp_backup_get_config_data( $key ) {
		$filepath    = get_home_path() . '/wp-config.php';
		$config_file = file_get_contents( "$filepath", true );
		switch ( $key ) {
			case 'DB_NAME':
				preg_match( "/'DB_NAME',\s*'(.*)?'/", $config_file, $matches );
				break;
			case 'DB_USER':
				preg_match( "/'DB_USER',\s*'(.*)?'/", $config_file, $matches );
				break;
			case 'DB_PASSWORD':
				preg_match( "/'DB_PASSWORD',\s*'(.*)?'/", $config_file, $matches );
				break;
			case 'DB_HOST':
				preg_match( "/'DB_HOST',\s*'(.*)?'/", $config_file, $matches );
				break;
		}
		return $matches[1];
	}

	/**
	 * Get db name from config.
	 */
	public function wp_backup_get_config_db_name() {
		$filepath    = get_home_path() . '/wp-config.php';
		$config_file = file_get_contents( "$filepath", true );
		preg_match( "/'DB_NAME',\s*'(.*)?'/", $config_file, $matches );
		return $matches[1];
	}

	/**
	 * Recursive sanitation for an array
	 *
	 * @param array $array - Array data to sanitize.
	 *
	 * @return mixed
	 */
	public function recursive_sanitize_text_field( $array ) {
		foreach ( $array as $key => &$value ) {
			if ( is_array( $value ) ) {
				$value = $this->recursive_sanitize_text_field( $value );
			} else {
				$value = sanitize_text_field( $value );
			}
		}

		return $array;
	}

	/**
	 * Enqueue scripts and style
	 */
	public function admin_scripts_style() {
		if ( true === $this->is_wpdb_page() ) {
				wp_enqueue_script( 'jquery' );

				wp_register_script( 'bootstrapjs', WPDB_PLUGIN_URL . '/assets/js/bootstrap.min.js', array( 'jquery' ), WPDB_VERSION, true );
				wp_enqueue_script( 'bootstrapjs' );

				wp_register_style( 'bootstrapcss', WPDB_PLUGIN_URL . '/assets/css/bootstrap.min.css', array(), WPDB_VERSION );
				wp_enqueue_style( 'bootstrapcss' );

				wp_register_script( 'dataTables', WPDB_PLUGIN_URL . '/assets/js/jquery.dataTables.js', array( 'jquery' ), WPDB_VERSION, true );
				wp_enqueue_script( 'dataTables' );

				wp_register_style( 'dataTablescss', WPDB_PLUGIN_URL . '/assets/css/jquery.dataTables.css', array(), WPDB_VERSION );
				wp_enqueue_style( 'dataTablescss' );

				wp_register_style( 'wpdbcss', WPDB_PLUGIN_URL . '/assets/css/wpdb_admin.css', array(), WPDB_VERSION );
				wp_enqueue_style( 'wpdbcss' );
		}
	}

	/**
	 * Check is plugin page.
	 */
	public function is_wpdb_page() {

		if ( is_admin() ) {

			return isset( $_REQUEST['page'] ) && preg_match( '/wp-database-backup/', $_REQUEST['page'] ) ? true : false; // phpcs:ignore

		} else {

			return true;
		}
	}
}

new Wpdb_Admin();
