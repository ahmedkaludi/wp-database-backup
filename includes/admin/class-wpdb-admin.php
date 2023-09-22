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
		add_action( 'init', array( $this, 'wp_db_backup_scheduler_activation' ) );
		add_action( 'wp_logout', array( $this, 'wp_db_cookie_expiration' ) ); // Fixed Vulnerability 22-06-2016 for prevent direct download.
		add_action( 'wp_db_backup_completed', array( $this, 'wp_db_backup_completed_local' ), 12 );
		add_action('admin_enqueue_scripts', array( $this, 'wpdbbkp_admin_style'));
		add_action('admin_enqueue_scripts', array( $this, 'wpdbbkp_admin_newsletter_script'));
		add_action('wp_ajax_wpdbbkp_send_query_message', array( $this, 'wpdbbkp_send_query_message'));
		add_filter( 'plugin_action_links_' . plugin_basename( WP_BACKUP_PLUGIN_FILE ), array( $this, 'add_settings_plugin_action_wp' ), 10, 4 );
		add_action( 'admin_notices', array($this, 'check_ziparchive_avalable_admin_notice' ));
		
	}

	/**
	 * admin Notice.
	 */
	public function check_ziparchive_avalable_admin_notice() {
		if (!class_exists( 'ZipArchive' ) ) { ?>
			<div class="notice notice-info is-dismissible"><p><strong><?php echo esc_html__('Info!', 'wpdbbkp') ?></strong> <?php echo esc_html__(' Enable Zip Extension in php.ini to work BackupForWP all functionality smoothly.', 'wpdbbkp') ?> </p></div>
		<?php } 
	}
	
	/**
	 * Backup Menu.
	 */
	public function admin_menu() {
		//$page = add_management_page( 'WP-DB Backup', 'WP-DB Backup ', 'manage_options', 'wp-database-backup', array( $this, 'wp_db_backup_settings_page' ));
		add_menu_page(
			'Backups',
			'Backups', 
			'manage_options', 
			'wp-database-backup', 
			array( $this, 'wp_db_backup_settings_page' ), 
			'dashicons-database-view', 
			99 
		);

		add_submenu_page(
			'wp-database-backup',
			'Auto Scheduler',
			'Auto Scheduler',
			'manage_options',
			'wp-database-backup#tab_db_schedul',
			array($this, 'wp_db_backup_settings_page' ));

		add_submenu_page(
			'wp-database-backup',
			'Save Backups to',
			'Save Backups to',
			'manage_options',
			'wp-database-backup#tab_db_destination',
			array($this, 'wp_db_backup_settings_page' ));

		add_submenu_page(
			'wp-database-backup',
			'Settings',
			'Settings',
			'manage_options',
			'wp-database-backup#tab_db_setting',
			array($this, 'wp_db_backup_settings_page' ));



				add_submenu_page(
					'wp-database-backup',
					'Help & Support',
					'Help & Support',
					'manage_options',
					'wp-database-backup#tab_db_help',
					array($this, 'wp_db_backup_settings_page' ));

		if(!defined('BKPFORWP_VERSION')){
			add_submenu_page(
				'wp-database-backup',
				'Upgrade to Premium',
				'Upgrade to Premium',
				'manage_options',
				'wp-database-backup#tab_db_upgrade',
				array($this, 'wp_db_backup_settings_page' ));
		}
		else{
			add_submenu_page(
				'wp-database-backup',
				'Modules',
				'Modules',
				'manage_options',
				'wp-database-backup#tab_db_features',
				array($this, 'wp_db_backup_settings_page' ));
				add_submenu_page(
					'wp-database-backup',
					'Licence',
					'Licence',
					'manage_options',
					'wp-database-backup#tab_db_licence',
					array($this, 'wp_db_backup_settings_page' ));
		}



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
		//redirect to plugin page on activation
		if (get_option('wpdbbkp_activation_redirect', false)) {
			delete_option('wpdbbkp_activation_redirect');
			if(!isset($_GET['activate-multi']))
			{
				wp_redirect("admin.php?page=wp-database-backup");
			}
		}

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

						$nonce = wp_create_nonce( 'wp-database-backup' );
						wp_safe_redirect( esc_url( site_url() . '/wp-admin/admin.php?page=wp-database-backup&notification=save&tab=searchreplace&_wpnonce=' . $nonce ) );
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
						wp_safe_redirect( site_url() . '/wp-admin/admin.php?page=wp-database-backup&notification=save&_wpnonce=' . $nonce );
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
					if ( isset( $_POST['local_backup_submit'] ) && 'Save Settings' === $_POST['local_backup_submit'] ) {
						if ( true === isset( $_POST['wp_db_local_backup'] ) ) {
							update_option( 'wp_db_local_backup', 1 );
						} else {
							update_option( 'wp_db_local_backup', 0 );
						}
					}

					if ( isset( $_POST['wp_db_backup_options'] ) ) {
						update_option( 'wp_db_backup_options', $_POST['wp_db_backup_options']);
					}
					
					do_action('wpdbbkp_save_pro_options');
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
					wp_safe_redirect( site_url() . '/wp-admin/admin.php?page=wp-database-backup' );
				}
				$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
				if ( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $nonce, 'wp-database-backup' ) ) {
					if ( isset( $_GET['action'] ) && current_user_can( 'manage_options' ) ) {
						switch ( (string) $_GET['action'] ) {
							case 'createdbbackup':
								$this->wp_db_backup_event_process();
								wp_safe_redirect( site_url() . '/wp-admin/admin.php?page=wp-database-backup&notification=create&_wpnonce=' . $nonce );
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
									wp_safe_redirect( site_url() . '/wp-admin/admin.php?page=wp-database-backup&notification=delete&_wpnonce=' . $nonce );
									exit;

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
										while ( false !== ($file = readdir( $dh )) ) {
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
								wp_safe_redirect( site_url() . '/wp-admin/admin.php?page=wp-database-backup&notification=clear_temp_db_backup_file&_wpnonce=' . $nonce );
								exit;
								break;
							case 'restorebackup':
								$index      = (int) $_GET['index'];
								$options    = get_option( 'wp_db_backup_backups' );
								$restore_limit = get_option( 'wp_db_restore_limit');
								$newoptions = array();
								$count      = 0;
								foreach ( $options as $option ) {
									if ( $count !== $index ) {
										$newoptions[] = $option;
									}
									$count++;
								}
								if ( isset( $options[ $index ]['restore_limit'] ) && $options[ $index ]['restore_limit']==1) {
									include_once ABSPATH . 'wp-admin/includes/plugin.php';
									if ( !is_plugin_active( 'wp-database-backup-pro/wp-database-backup-pro.php' ) ) {
										wp_safe_redirect( site_url() . '/wp-admin/admin.php?page=wp-database-backup&notification=restore_limit&_wpnonce=' . $nonce );
									} 
								}
								if(!empty($options[ $index ]['restore_limit'])){
									$options[ $index ]['restore_limit']=1;
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
								ignore_user_abort(true);
								if ( '' !== ( trim( (string) $database_name ) ) && '' !== ( trim( (string) $database_user ) ) && '' !== ( trim( (string) $database_host ) ) ) {
									$conn = mysqli_connect( (string) $database_host, (string) $database_user, (string) $datadase_password ); // phpcs:ignore
									if ( $conn ) {
										// Start Select the database.
										if ( ! mysqli_select_db( $conn, (string) $database_name) ) { // phpcs:ignore
											$sql = 'CREATE DATABASE IF NOT EXISTS `' . (string) $database_name . '`';
											mysqli_query($conn, $sql); // phpcs:ignore
											mysqli_select_db($conn, (string) $database_name ); // phpcs:ignore
										}
										/* END: Select the Database */
	
										/* BEGIN: Remove All Tables from the Database */
										$found_tables = null;
										$result       = mysqli_query( $conn, 'SHOW TABLES FROM `' . (string) $database_name . '`' ); // phpcs:ignore

										if ( $result ) {
											// $row = mysqli_fetch_row( $result ); // phpcs:ignore
											while ($row = mysqli_fetch_row($result)) {
												$found_tables[] = $row[0];
											}

											if ( count( $found_tables ) > 0 ) {
												foreach ( $found_tables as $table_name ) {
													mysqli_query( $conn, "DROP TABLE " . (string) $database_name . ".".$table_name ); // phpcs:ignore
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

												mysqli_query($conn, "SET sql_mode = ''");

												for ( $i = 0; $i < $sql_queries_count; $i++ ) {
													$sql_query_=apply_filters( 'wpdbbkp_sql_query_restore', $sql_queries[ $i ] );
													mysqli_query($conn, $sql_query_ ); // phpcs:ignore
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
								wp_safe_redirect( site_url() . '/wp-admin/admin.php?page=wp-database-backup&notification=restore&_wpnonce=' . $nonce );
								exit;
								break;

							case 'wpdbbkrestorefullbackup':
		                        $index = (int) $_GET['index'];
		                        require_once( 'class-restore.php' );
		                        $restore = new Wpbp_Restore();
		                        $restore->start($index);
		                        if (get_option('wp_db_log') == 1) {
		                            $options = get_option('wp_db_backup_backups');
		                            $path_info = wp_upload_dir();
		                            $logFileName = explode(".", $options[$index]['filename']);
		                            $logfile = $path_info['basedir'] . '/' . WPDB_BACKUPS_DIR . '/log/' . $logFileName[0] . '.txt';
		                            $message = "\n\n Restore Backup at " . date("Y-m-d h:i:sa");
		                            $this->write_log($logfile, $message);
		                        }
		                        $nonce = wp_create_nonce( 'wp-database-backup' );
		                        wp_safe_redirect( site_url() . '/wp-admin/admin.php?page=wp-database-backup&notification=restore&_wpnonce=' . $nonce );
								exit;
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
		$settings = get_option( 'wp_db_backup_options' ); 
		$wp_db_log = get_option( 'wp_db_log' ); ?>
		<div class="bootstrap-wrapper">
		<?php
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
			
		<div id="wpdbbkpModal" class="modal">
			<div class="wpdbbkpmodal-content">
				<div class="wpdbbkp-modal-header">
					<span class="wpdbbkp-close">&times;</span>
					<h2><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>&nbsp;<span id="wpdbbkp-modal-header-title"></span></h2>
				</div>
				<div class="wpdbbkp-modal-body">
					<p  id="wpdbbkp-modal-body-text"></p>
				</div>
				<div class="wpdbbkp-modal-footer">
					<a class="btn btn-primary" onclick="return confirm('Are you sure you want to restore backup?')" id="wpdbbkp-proceed-btn">Continue Anyway</a>&nbsp;<a class="btn btn-default wpdbbkp-close">Close</a>
				</div>
			</div>
		</div>

			<div class="panel panel-default">
				<div class="panel-heading head-logo">
					<a href="https://backupforwp.com/" target="blank"><img
								src="<?php echo esc_attr( WPDB_PLUGIN_URL ); ?>/assets/images/wp-database-backup.png" width="230px"></a>
				</div>
				<div class="panel-body">
					<ul class="nav nav-tabs wbdbbkp_has_nav">
						<li class="active"><a href="#db_home" data-toggle="tab"><?php echo esc_html__('Backups', 'wpdbbkp') ?></a></li>
						<li><a href="#db_schedul" data-toggle="tab"><?php echo esc_html__('Auto Scheduler', 'wpdbbkp') ?></a></li>
						<li><a href="#db_destination" data-toggle="tab"><?php echo esc_html__('Save Backups to', 'wpdbbkp') ?></a></li>
						<li><a href="#db_setting" data-toggle="tab"><?php echo esc_html__('Settings', 'wpdbbkp') ?></a></li>
						<li><a href="#searchreplace" data-toggle="tab"><?php echo esc_html__('Search and Replace', 'wpdbbkp') ?></a></li>
						<li style="display:none"><a href="#db_upgrade" data-toggle="tab"><?php echo esc_html__('Upgrade', 'wpdbbkp') ?></a></li>
						<?php do_action( 'wpdbbkp_pro_tab_links' ); ?>
						<li><a href="#db_help" data-toggle="tab"><?php echo esc_html__('Help &amp; Support', 'wpdbbkp') ?></a></li>
						<li id="db_info_link" title="System Info"><a href="#db_info" data-toggle="tab"><span class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a></li>
						
					</ul>

					<?php
					echo '<div class="tab-content">';
					echo '<div class="tab-pane active"  id="db_home">';

					$nonce                     = wp_create_nonce( 'wp-database-backup' );
					$wp_db_backup_search_text  = get_option( 'wp_db_backup_search_text' );
					$wp_db_backup_replace_text = get_option( 'wp_db_backup_replace_text' );
					if ( ( false === empty( $wp_db_backup_search_text ) ) && ( false === empty( $wp_db_backup_replace_text ) ) ) {
						echo '<a href="' . esc_url( site_url() ) . '/wp-admin/admin.php?page=wp-database-backup&action=createdbbackup&_wpnonce=' . esc_attr( $nonce ) . '" id="create_backup" class="btn btn-primary"> <span class="glyphicon glyphicon-plus-sign"></span> '.esc_html__('Create Database Backup with Search/Replace', 'wpdbbkp').'</a>';
						echo '<p>Backup file will replace <b>' . esc_attr( $wp_db_backup_search_text ) . '</b> text with <b>' . esc_attr( $wp_db_backup_replace_text ) . '</b>. For Regular Database Backup without replace then Go to Dashboard=>Tool=>WP-DB Backup > Settings > '.esc_html__('Search and Replace - Set Blank Fields', 'wpdbbkp').' </p>';
					} else {
						echo '<a href="' . esc_url( site_url() ) . '/wp-admin/admin.php?page=wp-database-backup&action=createdbbackup&_wpnonce=' . esc_attr( $nonce ) . '" id="create_backup" class="btn btn-primary"> <span class="glyphicon glyphicon-plus-sign"></span> '.esc_html__('Create Database Backup', 'wpdbbkp').'</a>';
						echo '<a href="#" id="wpdbbkp-create-full-backup" class="btn btn-primary"> <span class="glyphicon glyphicon-plus-sign"></span> '.esc_html__('Create Full Backup', 'wpdbbkp').'</a>';
						echo '<a href="#" id="wpdbbkp-stop-full-backup" class="btn btn-danger wpdbbkp-cancel-btn" style="display:none;margin-bottom: 20px;margin-left: 10px;" > <span class="glyphicon glyphicon-ban"></span> '.esc_html__('Stop Backup Process', 'wpdbbkp').'</a>';
					}
					include_once 'admin-header-notification.php'; ?>

					<?php
					if ( $options ) {

						echo ' <script>
						var $j = jQuery.noConflict();
						$j(document).ready(function () {
							
							var table = $j("#wpdbbkp_table").DataTable({
								order: [[0, "desc"]],
							});
							$j(".popoverid").popover();
							$j("#create_backup").click(function() {
								$j(".wpdbbkp_notification").hide();
								$j("#backup_process").show();
								$j("#create_backup").attr("disabled", true);
								
							});
						});

						function excludetableall(){
							var checkboxes = document.getElementsByClassName("wp_db_exclude_table");
							var checked = "";
							if($j("#wp_db_exclude_table_all").prop("checked") == true){
								checked = "checked";
							}
							$j(".wp_db_exclude_table").each(function() {
								this.checked = checked;
							});
						}

					</script>';
						echo ' <div class="table-responsive">
                                <div id="wpdbbkp_dataTables" class="dataTables_wrapper form-inline" role="grid">

                                <table class="table table-striped table-bordered table-hover display" id="wpdbbkp_table">
                                    <thead>';
						echo '<tr class="wpdb-header">';
						echo '<th class="manage-column" scope="col" width="5%" style="text-align: center;">#</th>';
						echo '<th class="manage-column" scope="col" width="25%">Date</th>';
						if($wp_db_log==1){
							echo '<th class="manage-column" scope="col" width="5%">Log</th>';
						}
						echo '<th class="manage-column" scope="col" width="10%">Destination</th>';
						echo '<th class="manage-column" scope="col" width="15%">Type</th>';
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
							'FTP'        => 'glyphicon glyphicon-tasks',
							'S3'         => 'glyphicon glyphicon-cloud-upload',
							'Drive'      => 'glyphicon glyphicon-hdd',
							'DropBox'    => 'glyphicon glyphicon-inbox',
						);
						foreach ( $options as $option ) {
							$str_class = ( 0 === (int) $option['size'] ) ? 'text-danger' : 'wpdb_download';
							echo '<tr class="' . ( ( 0 === ( $count % 2 ) ) ? esc_attr( $str_class ) . ' alternate' : esc_attr( $str_class ) ) . '">';
							echo '<td style="text-align: center;">' . esc_attr( $count ) . '</td>';
							$curr_date = new DateTime(date( 'Y-m-d H:i:s', $option['date'] ));
							$curr_date->setTimezone(new DateTimeZone(wp_timezone_string()));
							echo '<td><span style="display:none">' . esc_attr( $curr_date->format('Y-m-d H:i:s') ) . '</span><span title="'.esc_attr( $curr_date->format('jS, F Y h:i:s A') ) .'">' .$this->wpdbbkp_get_timeago($option['date']).'</span>';
							echo '</td>';
							if($wp_db_log==1){
								echo '<td class="wpdb_log" align="center">';
							if (!empty($option['log'])) {
								if(isset($option['type']) && ($option['type'] == 'complete' || $option['type'] == 'database')){
							echo '<a href="' . $option['log'] . '" target="_blank" class="label label-warning" title="There might be partial backup. Please check Log File for verify backup.">';
                            echo  '<span class="glyphicon glyphicon-list-alt"></span>';
                            echo '</a>';
								}else{
									echo '<a class="popoverid btn" role="button" data-toggle="popover" data-html="true" title="There might be partial backup. Please check Log file to verify backup." data-content="' . wp_kses_post( $option['log'] ) . '"><span class="glyphicon glyphicon-list-alt" aria-hidden="true"></span></a>';
								}
							}
							echo '</td>';
							}
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
							if(isset($option['type']) && !empty($option['type'])){
								if($option['type'] == 'complete'){
									echo '<td>'.esc_html__('Full Backup', 'wpdbbkp').'</td>';	
								}
								if($option['type'] == 'database'){
									echo '<td>'.esc_html__('Database', 'wpdbbkp').'</td>';	
								}
							}else{
								echo '<td>Database</td>';
							}
							echo '<td>';
							echo '<a class="btn btn-default" href="' . esc_url( $option['url'] ) . '" style="color: #21759B;border-color:#337ab7;">';
							echo '<span class="glyphicon glyphicon-download-alt"></span> Download</a></td>';
							echo '<td>' . esc_attr( $this->wp_db_backup_format_bytes( $option['size'] ) ) . '</td>';
							$remove_backup_href = esc_url( site_url() ) . '/wp-admin/admin.php?page=wp-database-backup&action=removebackup&_wpnonce=' . esc_attr( $nonce ) . '&index=' . esc_attr( ( $count - 1 ) );
							echo '<td><a title="Remove Database Backup" onclick="return confirm(\'Are you sure you want to delete database backup?\')" href="' . $remove_backup_href . '" class="btn btn-default"><span style="color:red" class="glyphicon glyphicon-trash"></span> Remove <a/> ';
							if ( isset( $option['search_replace'] ) && 1 === (int) $option['search_replace'] ) {
								echo '<span style="margin-left:15px" title="' . esc_html( $option['log'] ) . '" class="glyphicon glyphicon-search"></span>';
							} else {
								$restore_url_href = esc_url( site_url() ) . '/wp-admin/admin.php?page=wp-database-backup&action=restorebackup&_wpnonce=' . esc_attr( $nonce ) . '&index=' . esc_attr( ( $count - 1 ) );
								if(isset($option['type']) && !empty($option['type'])){
									if($option['type'] == 'complete'){
										$restore_url_href = esc_url( site_url() ) . '/wp-admin/admin.php?page=wp-database-backup&action=wpdbbkrestorefullbackup&_wpnonce=' . esc_attr( $nonce ) . '&index=' . esc_attr( ( $count - 1 ) );
									}
									
								}
								echo '<a title="Restore Database Backup" onclick="wpdbbkp_restore_backup(this);" href="javascript:void(0);"  data-msg="Are you sure you want to restore database backup? It will overwrite all data /files with the respective backup and all recent changes would be lost. Are you sure you want to continue?"  data-title="Restore Backup" data-href="'.$restore_url_href.'" class="btn btn-default"><span class="glyphicon glyphicon-refresh" style="color:blue"></span> Restore <a/>';
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
		
						echo '<p>If you like <b>WP Database Backup</b> please leave us a <a target="_blank" href="http://wordpress.org/support/view/plugin-reviews/wp-database-backup" title="Rating" sl-processed="1"> <span class="glyphicon glyphicon-star" aria-hidden="true"></span> <span class="glyphicon glyphicon-star" aria-hidden="true"></span> <span class="glyphicon glyphicon-star" aria-hidden="true"></span> <span class="glyphicon glyphicon-star" aria-hidden="true"></span> <span class="glyphicon glyphicon-star" aria-hidden="true"></span> rating </a>. Many thanks in advance!</p>';
					echo '</div>';

					echo '<div class="tab-pane" id="db_schedul">';
					echo '<div class="panel-group">';
					echo '<form method="post" action="" name="wp_auto_commenter_form">';
					wp_nonce_field( 'wp-database-backup' );
					$enable_autobackups = '0';
					if ( isset( $settings['enable_autobackups'] ) ) {
						$enable_autobackups = $settings['enable_autobackups'];
					}

					$autobackup_frequency = '0';
					if ( isset( $settings['autobackup_frequency'] ) ) {
						$autobackup_frequency = $settings['autobackup_frequency'];
					}
					$full_autobackup_frequency = 'disabled'; 
					if ( isset( $settings['full_autobackup_frequency'] ) ) {
						$full_autobackup_frequency = $settings['full_autobackup_frequency'];
					}
					$autobackup_type = ''; 
					if ( isset( $settings['autobackup_type'] ) ) {
						$autobackup_type = $settings['autobackup_type'];
					}

					echo '<div class="row form-group"><label class="col-sm-3" for="enable_autobackups">Enable Auto Backups</label>';
					echo '<div class="col-sm-9"><input type="checkbox" id="enable_autobackups" name="wp_db_backup_options[enable_autobackups]" value="1" ' . checked( 1, $enable_autobackups, false ) . '/>';
					echo '<div class="alert alert-default" role="alert"><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span> AutoBackups will be based on Wordpress Cron so it can have execution delay of +/- 30 mins . If you have disabled Wordpress Cron then autobackup will not work until you have set Server Cron for wordpress.</div>';
					echo '</div>';
					echo '</div>';

					echo '<div class="row form-group autobackup_type" style="display:none"><label class="col-sm-3" for="autobackup_frequency">Which part should we backup for you ?</label>';
					echo '<div class="col-sm-9"><select id="autobackup_type" class="form-control" name="wp_db_backup_options[autobackup_type]">';
					echo '<option value="">Select Backup Type</option>';
					echo '<option value="full" ' . selected( 'full', $autobackup_type, false ) . '>Full(Files + DB)</option>';
					echo '<option value="files" ' . selected( 'files', $autobackup_type, false ) . '>Files Only</option>';
					echo '<option value="db" ' . selected( 'db', $autobackup_type, false ) . '>Database Only</option>';
					echo '</select>';
					echo '</div></div>';

					echo '<div class="row form-group autobackup_frequency" style="display:none"><label class="col-sm-3" for="autobackup_frequency">How  often should we run Automatically?</label>';
					echo '<div class="col-sm-9"><select id="autobackup_frequency" class="form-control" name="wp_db_backup_options[autobackup_frequency]">';
					echo '<option value="daily" ' . selected( 'daily', $autobackup_frequency, false ) . '>Daily</option>';
					echo '<option value="weekly" ' . selected( 'weekly', $autobackup_frequency, false ) . '>Weekly</option>';
					echo '<option value="monthly" ' . selected( 'monthly', $autobackup_frequency, false ) . '>Monthly</option>';
					echo '</select>';
					echo '</div></div>';

					echo '<div class="row form-group autobackup_frequency_lite" style="display:none"><label class="col-sm-12 autobackup_daily_lite" >We will automatically backup at 00:00 AM daily.  <b><a href="javascript:modify_backup_frequency();">Change Back Frequency Timings</a></b></label></div>';
					echo '<div class="row form-group autobackup_frequency_lite" style="display:none"><label class="col-sm-12 autobackup_weekly_lite" >We will automatically backup every Sunday on weekly basis. <b><a href="javascript:modify_backup_frequency();">Change Back Frequency Timings</a></b></label></div>';
					echo '<div class="row form-group autobackup_frequency_lite" style="display:none"><label class="col-sm-12 autobackup_monthly_lite" >We will automatically backup on 1st on Monday on monthly basis. <b><a href="javascript:modify_backup_frequency();">Change Back Frequency Timings</a></b></label></div>';

					do_action('wpdbbkp_database_backup_options');
					echo '<p class="submit">';
					echo '<input type="submit" name="Submit" class="btn btn-primary" value="Save Settings" />';
					echo '</p>';
					echo '</form>';
					echo '</div>';
					echo '</div>';

					do_action('wpdbbkp_pro_tab_content');
				
					?>

			<div class="tab-pane" id="db_upgrade">
						<div class="panel-group ">
						        <div class="gn-flex-container row">
						          <div class=" col-md-12">
									<div class="wpdbbkp-wrapper">
            <div class="wpdbbkp-wr">
                <div class="bkp-wpdbbkp-img">
                    <span class="sp_ov"></span>
                </div>
                <div class="bkp-wpdbbkp-cnt">
                    <h1><?php esc_html_e('UPGRADE to PRO Version','wpdbbkp');?></h1>
                    <a class="buy" href="https://backupforwp.com/pricing/#pricings" target="_blank">Purchase Now</a>
                </div>
                <div class="pvf">
                    <div class="pvf-cnt">
                        <div class="pvf-tlt">
                            <h2><?php esc_html_e('Compare Pro vs. Free Version','wpdbbkp');?></h2>
                            <span><?php esc_html_e('See what you\'ll get with the professional version','wpdbbkp');?></span>
                        </div>
                        <div class="pvf-cmp">
                            <div class="fr">
                                <h1><?php esc_html_e('FREE','wpdbbkp');?></h1>
                                <div class="fr-fe">
                                    <div class="fe-1">
                                        <h4><?php esc_html_e('Continious Development','wpdbbkp');?></h4>
                                        <p><?php esc_html_e('We take bug reports and feature requests seriously. We\'re continiously developing &amp; improve this product for last 2 years with passion and love.','wpdbbkp');?></p>
                                    </div>
                                    <div class="fe-1">
                                        <h4><?php esc_html_e('10+ Features','wpdbbkp');?></h4>
                                        <p><?php esc_html_e('We\'re constantly expanding the plugin and make it more useful. We have wide variety of features which will fit any use-case.','wpdbbkp');?></p>
                                    </div>
                                </div><!-- /. fr-fe -->
                            </div><!-- /. fr -->
                            <div class="pr">
                                <h1>PRO</h1>
                                <div class="pr-fe">
                                    <span><?php esc_html_e('Everything in Free, and:','wpdbbkp');?></span>
                                    <div class="fet">
                                        <div class="fe-2">
                                            <div class="fe-t">
                                                <img src="<?php echo WPDB_PLUGIN_URL;?>/assets/images/right-tick.png" alt="right-tick">
                                                <h4><?php esc_html_e('Anonymization Function','wpdbbkp');?></h4>
                                            </div>
                                            <p><?php esc_html_e('Anonymization you critical data during backup with three methods','wpdbbkp');?> </p>
                                        </div>
                                        <div class="fe-2">
                                            <div class="fe-t">
                                                <img src="<?php echo WPDB_PLUGIN_URL;?>/assets/images/right-tick.png" alt="right-tick">
                                                <h4><?php esc_html_e('Pre-update backups','wpdbbkp');?></h4>
                                            </div>
                                            <p> <?php esc_html_e('Automatically backs up your website before any updates to plugins, themes and WordPress core.','wpdbbkp');?></p>
                                        </div>

                                        <div class="fe-2">
                                            <div class="fe-t">
                                                <img src="<?php echo WPDB_PLUGIN_URL;?>/assets/images/right-tick.png" alt="right-tick">
                                                <h4><?php esc_html_e('Backup time and scheduling','wpdbbkp'); ?></h4>
                                            </div>
                                            <p><?php esc_html_e('Set exact times to create or delete backups.','wpdbbkp');?></p>
                                        </div>


                                        <div class="fe-2">
                                            <div class="fe-t">
                                                <img src="<?php echo WPDB_PLUGIN_URL;?>/assets/images/right-tick.png" alt="right-tick">
                                                <h4><?php esc_html_e('Fast, personal support','wpdbbkp'); ?></h4>
                                            </div>
                                            <p><?php esc_html_e('Provides expert help and support from the developers whenever you need it.','wpdbbkp'); ?></p>
                                        </div>
                                        <div class="fe-2">
                                            <div class="fe-t">
                                                <img src="<?php echo WPDB_PLUGIN_URL;?>/assets/images/right-tick.png" alt="right-tick">
                                                <h4><?php esc_html_e('Continuous Updates','wpdbbkp'); ?></h4>
                                            </div>
                                            <p><?php esc_html_e('We\'re continuously updating our premium features and releasing them.','wpdbbkp'); ?></p>
                                        </div>
                                        <div class="fe-2">
                                            <div class="fe-t">
                                                <img src="<?php echo WPDB_PLUGIN_URL;?>/assets/images/right-tick.png" alt="right-tick">
                                                <h4><?php esc_html_e('Documentation','wpdbbkp'); ?></h4>
                                            </div>
                                            <p><?php esc_html_e('We create tutorials for every possible feature and keep it updated for you.','wpdbbkp'); ?></p>
                                        </div>
                                    </div><!-- /. fet -->
                                    <div class="pr-btn">
                                        <a href="https://backupforwp.com/pricing/#pricings" target="_blank"><?php esc_html_e('Upgrade to Pro','wpdbbkp'); ?></a>
                                    </div><!-- /. pr-btn -->
                                </div><!-- /. pr-fe -->
                            </div><!-- /.pr -->
                        </div><!-- /. pvf-cmp -->
                    </div><!-- /. pvf-cnt -->
            
                    <div class="wpdbbkpfaq">
                        <h2><?php esc_html_e('Frequently Asked Questions','wpdbbkp'); ?></h2>
                        <div class="faq-lst">
                            <div class="lt">
                                <ul>
                                    <li>
                                        <span><?php esc_html_e('Is there a setup fee?','wpdbbkp'); ?></span>
                                        <p><?php esc_html_e('No. There are no setup fees on any of our plans','wpdbbkp'); ?></p>
                                    </li>
                                    <li>
                                        <span><?php esc_html_e('What\'s the time span for your contracts?','wpdbbkp'); ?></span>
                                        <p><?php esc_html_e('All the plans are year-to-year which are subscribed annually except for lifetime plan.','wpdbbkp'); ?></p>
                                    </li>
                                    <li>
                                        <span><?php esc_html_e('What payment methods are accepted?','wpdbbkp'); ?></span>
                                        <p><?php esc_html_e('We accepts PayPal and Credit Card payments.','wpdbbkp'); ?></p>
                                    </li>
                                    <li>
                                        <span><?php esc_html_e('Do you offer support if I need help?','wpdbbkp'); ?></span>
                                        <p><?php esc_html_e('Yes! Top-notch customer support for our paid customers is key for a quality product, so we\'ll do our very best to resolve any issues you encounter via our support page.','wpdbbkp'); ?></p>
                                    </li>
                                    <li>
                                        <span><?php esc_html_e('Can I use the plugins after my subscription is expired?','wpdbbkp'); ?></span>
                                        <p><?php esc_html_e('Yes, you can use the plugins, but you will not get future updates for those plugins.','wpdbbkp'); ?></p>
                                    </li>
                                </ul>
                            </div>
                            <div class="rt">
                                <ul>
                                    <li>
                                        <span><?php esc_html_e('Can I cancel my membership at any time?','wpdbbkp'); ?></span>
                                        <p><?php esc_html_e('Yes. You can cancel your membership by contacting us.','wpdbbkp'); ?></p>
                                    </li>
                                    <li>
                                        <span><?php esc_html_e('Can I change my plan later on?','wpdbbkp'); ?></span>
                                        <p><?php esc_html_e('Yes. You can upgrade your plan by contacting us.','wpdbbkp'); ?></p>
                                    </li>
                                    <li>
                                        <span><?php esc_html_e('Do you offer refunds?','wpdbbkp'); ?></span>
                                        <p><?php esc_html_e('You are fully protected by our 100% Money-Back Guarantee Unconditional. If during the next 14 days you experience an issue that makes the plugin unusable, and we are unable to resolve it, we\'ll happily offer a full refund.','wpdbbkp'); ?></p>
                                    </li>
                                    <li>
                                        <span><?php esc_html_e('Do I get updates for the premium plugin?','wpdbbkp'); ?></span>
                                        <p><?php esc_html_e('Yes, you will get updates for all the premium plugins until your subscription is active.','wpdbbkp'); ?></p>
                                    </li>
                                </ul>
                            </div>
                        </div><!-- /.faq-lst -->
                    </div><!-- /.faq -->
                </div><!-- /. pvf -->
            </div>
        </div>
					
						            </div>
						          </div>
						        </div>
						</div>
			

				<div class="tab-pane" id="db_help">
						<div class="panel-group ">
						        <div class="gn-flex-container row">
						          <div class="wpdbbkp-left-side col-md-8">
						            <p> <?php echo esc_html__('We are dedicated to provide Technical support &amp; Help to our users. Use the below form for sending your questions. ', 'wpdbbkp') ?> </p>
						            <div class="wpdbbkp_support_div_form" id="technical-form">
						              <ul>
						                <li>
						                  <label class="wpdbbkp-support-label"> <?php echo esc_html__('Email', 'wpdbbkp') ?> <span class="wpdbbkp-star-mark">*</span>
						                  </label>
						                  <div class="support-input">
						                    <input type="text" id="wpdbbkp_query_email" name="wpdbbkp_query_email" size="47" placeholder="Enter your Email" required="">
						                  </div>
						                </li>
						                <li>
						                  <label class="wpdbbkp-support-label"> <?php echo esc_html__('Query', 'wpdbbkp') ?> <span class="wpdbbkp-star-mark">*</span>
						                  </label>
						                  <div class="support-input">
						                    <textarea rows="5" cols="50" id="wpdbbkp_query_message" name="wpdbbkp_query_message" placeholder="Write your query"></textarea>
						                  </div>
						                </li>
						                <li>
						                  <button class="button button-primary wpdbbkp-send-query"> <?php echo esc_html__('Send Support Request', 'wpdbbkp') ?> </button>
						                </li>
						              </ul>
						              <div class="clear"></div>
						              <span class="wpdbbkp-query-success wpdbbkp-result wpdbbkp-hide"> <?php echo esc_html__('Message sent successfully, Please wait we will get back to you shortly', 'wpdbbkp') ?> </span>
						              <span class="wpdbbkp-query-error wpdbbkp-result wpdbbkp-hide"> <?php echo esc_html__('Message not sent. please check your network connection', 'wpdbbkp') ?> </span>
						            </div>
						          </div>
								  <div class="wpdbbkp-right-side col-md-4">
								  <div class="wpdbbkp-bio-box" id="wpdbbkp_Bio">
                <h3><?php echo esc_html__('Vision &amp; Mission', 'wpdbbkp') ?></h3>
                <p class="wpdbbkp-p"><?php echo esc_html__('We strive to provide the best Backup Plugin in the world.', 'wpdbbkp') ?></p>
              <p class="wpdbbkp_boxdesk"> <?php echo esc_html__(' Delivering a good user experience means a lot to us, so we try our best to reply each and every question.', 'wpdbbkp') ?></p>
           </div>
				</div>
						        </div>
						</div>
					</div>

				<div class="tab-pane" id="db_info">

					<div class="panel panel-group panel-default">
						<div class="panel-heading">
								<a class="toggle_anchor" data-toggle="collapse" data-parent="#accordion" href="#collapsedb">
								<h4 class="panel-title">
									<?php esc_attr_e( 'System Check', 'wpdbbk' ); ?>
									</h4>
								</a>
						</div>
						<div id="collapsedb" class="panel-collapse collapse in">
							<div class="panel-body list-group">

								<div class="row list-group-item">
									<?php
									if(function_exists('disk_free_space')){
									/* get disk space free (in bytes) */
									$df = disk_free_space( WPDB_ROOTPATH );
									/* and get disk space total (in bytes)  */
									$dt = disk_total_space( WPDB_ROOTPATH );
									/* now we calculate the disk space used (in bytes) */
									$du = $dt - $df;
									/* percentage of disk used - this will be used to also set the width % of the progress bar */
									$dp = sprintf( '%.2f', ( $du / $dt ) * 100 );
									$dp = isset( $dp )? $dp : 'NA';
									/* and we formate the size from bytes to MB, GB, etc. */
									$df = $this->wp_db_backup_format_bytes( $df );
									$du = $this->wp_db_backup_format_bytes( $du );
									$dt = $this->wp_db_backup_format_bytes( $dt );
									}
									$du=$dp=$df=$dt='NA';
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
									<a type="button" href="<?php echo esc_url( site_url() ); ?>/wp-admin/admin.php?page=wp-database-backup&action=clear_temp_db_backup_file&_wpnonce=<?php echo esc_attr( $nonce ); ?>" class="btn btn-warning"><span class="glyphicon glyphicon-trash" aria-hidden="true"></span> Clear all old/temp database backup files</a>
									<p><?php echo esc_html__("Click above button to clear all your old or temporary created database backup
										files.
										It only delete file from backup directory which is not in 'Database Backups'
										listing(all other file excluding backup files listed in 'Database Backups' ).
										Before
										using this option make sure that you have save your database backup on safe
										place.", 'wpdbbkp') ?></p>
									<p><?php echo esc_html__("The disk that your backup is saved on doesn't have enough free space? Backup disk
										is
										almost full? Low disk space for backup? Backup failed due to lack of space? As
										you
										may set up a schedule to automatically do backup daily or weekly, and the size
										of
										disk space is limited, so your backup disk will run out of space quickly or
										someday.
										It is a real pain to manually delete old backups. Don't worry about it. WP
										Database
										Backup makes it easy to delete old/temparary backup files using this option.", 'wpdbbkp') ?></p>

								</div>

								<div class="row list-group-item">
									<div class="col-md-1"><a href="" target="_blank" title="Help"><span
												class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
									</div>
									<?php if ( true === isset( $_SERVER['DOCUMENT_ROOT'] ) ) { ?>
									<div class="col-md-3"><?php echo esc_html__('Root Path', 'wpdbbkp') ?></div>
									<div class="col-md-5"><?php echo esc_attr( sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) ); ?></div>
									<?php } ?>
								</div>


								<div class="row list-group-item">
									<div class="col-md-1"><a href="" target="_blank" title="Help"><span
												class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
									</div>
									<div class="col-md-3"><?php echo esc_html__('ABSPATH', 'wpdbbkp') ?></div>
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
									<div class="col-md-3"><?php echo esc_html__('Loaded PHP INI', 'wpdbbkp') ?></div>
									<div class="col-md-5"><?php echo esc_attr( php_ini_loaded_file() ); ?></div>
								</div>
								<div class="row list-group-item">
									<div class="col-md-1"><a href="" target="_blank" title="Help"><span
												class="glyphicon glyphicon-question-sign" aria-hidden="true"></span></a>
									</div>
									<div class="col-md-3"><?php echo esc_html__('Memory Limit', 'wpdbbkp') ?></div>
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
										class="col-md-1"><?php echo esc_attr( substr( sprintf( '%o', @fileperms( esc_attr( $upload_dir['basedir'] ) . '/db-backup' ) ), -4 ) ); ?></div>
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
						<a class="toggle_anchor" data-toggle="collapse" data-parent="#accordion" href="#collapsedbinfo">
							<h4 class="panel-title"><?php echo esc_html__('Database Information', 'wpdbbkp') ?></h4>
							</a>
						</div>

						<div id="collapsedbinfo" class="panel-collapse collapse in">
							<div class="panel-body">
								<table class="table table-condensed">
									<tr class="success">
										<th><?php echo esc_html__('Setting', 'wpdbbkp') ?></th>
										<th><?php echo esc_html__('Value', 'wpdbbkp') ?></th>
									</tr>
									<tr>
										<td><?php echo esc_html__('Database Host', 'wpdbbkp') ?></td>
										<td><?php echo esc_attr( DB_HOST ); ?></td>
									</tr>
									<tr class="default">
										<td><?php echo esc_html__('Database Name', 'wpdbbkp') ?></td>
										<td> <?php echo esc_attr( DB_NAME ); ?></td>
									</tr>
									<tr>
										<td><?php echo esc_html__('Database User', 'wpdbbkp') ?></td>
										<td><?php echo esc_attr( DB_USER ); ?></td>
										</td>
									</tr>
									<tr>
										<td><?php echo esc_html__('Database Type', 'wpdbbkp') ?></td>
										<td><?php echo esc_html__('MYSQL', 'wpdbbkp') ?></td>
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
										<td><?php echo esc_html__('Database Version', 'wpdbbkp') ?></td>
										<td>v<?php echo esc_attr( $mysqlversion ); ?></td>
									</tr>
								</table>

							</div>
						</div>
					</div>

					<div class="panel panel-default">
						<div class="panel-heading">
							
								<a class="toggle_anchor"  data-toggle="collapse" data-parent="#accordion" href="#collapsedbtable">
								<h4 class="panel-title">
								<?php echo esc_html__('Tables Information', 'wpdbbkp') ?>
									</h4>
								</a>
							
						</div>
						<div id="collapsedbtable" class="panel-collapse collapse in">
							<div class="panel-body">
								<table class="table table-condensed">
									<tr class="success">
										<th><?php echo esc_html__('No.', 'wpdbbkp') ?></th>
										<th><?php echo esc_html__('Tables', 'wpdbbkp') ?></th>
										<th><?php echo esc_html__('Records', 'wpdbbkp') ?></th>

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
							
								<a class="toggle_anchor"  data-toggle="collapse" data-parent="#accordion" href="#collapsewp">
								<h4 class="panel-title">
								<?php echo esc_html__('WordPress Information', 'wpdbbkp') ?>
									</h4>
								</a>
							
						</div>
						<div id="collapsewp" class="panel-collapse collapse in">
							<div class="panel-body">
								<table class="table table-condensed">
									<tr class="success">
										<th><?php echo esc_html__('Setting', 'wpdbbkp') ?></th>
										<th><?php echo esc_html__('Value', 'wpdbbkp') ?></th>

									</tr>
									<tr>
										<td><?php echo esc_html__('WordPress Version', 'wpdbbkp') ?></td>
										<td><?php bloginfo( 'version' ); ?></td>
									</tr>
									<tr>
										<td><?php echo esc_html__('Home URL', 'wpdbbkp') ?></td>
										<td> <?php echo esc_url( home_url() ); ?></td>
									</tr>
									<tr>
										<td><?php echo esc_html__('Site URL', 'wpdbbkp') ?></td>
										<td><?php echo esc_url( site_url() ); ?></td>
									</tr>
									<tr>
										<td><?php echo esc_html__('Upload directory URL', 'wpdbbkp') ?></td>
										<td><?php $upload_dir = wp_upload_dir(); ?>
											<?php echo esc_url( $upload_dir['baseurl'] ); ?></td>
									</tr>
								</table>

							</div>
						</div>
					</div>

					<div class="panel panel-default">
						<div class="panel-heading">
							
								<a class="toggle_anchor"  data-toggle="collapse" data-parent="#accordion" href="#collapsewpsetting">
								<h4 class="panel-title">
								<?php echo esc_html__('WordPress Settings', 'wpdbbkp') ?>
									</h4>
								</a>
							
						</div>
						<div id="collapsewpsetting" class="panel-collapse collapse in">
							<div class="panel-body">
								<table class="table table-condensed">
									<tr class="success">
										<th><?php echo esc_html__('Plugin Name', 'wpdbbkp') ?></th>
										<th><?php echo esc_html__('Version', 'wpdbbkp') ?></th>
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
										<th><?php echo esc_html__('Active Theme Name', 'wpdbbkp') ?></th>
										<th><?php echo esc_html__('Version', 'wpdbbkp') ?></th>
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
									<?php echo esc_html__('Drafts Post Count', 'wpdbbkp') ?> <span class="badge">
										<?php
											$count_posts = wp_count_posts();
										echo esc_attr( $count_posts->draft );
										?>
		</span>
									</button>
									<button class="btn btn-primary" type="button">
									<?php echo esc_html__('Publish Post Count', 'wpdbbkp') ?> <span class="badge">
										<?php

										echo esc_attr( $count_posts->publish );
										?>
		</span>
									</button>
									<button class="btn btn-primary" type="button">
									<?php echo esc_html__('Drafts Pages Count', 'wpdbbkp') ?> <span class="badge">
										<?php
											$count_pages = wp_count_posts( 'page' );
										echo esc_attr( $count_pages->draft );
										?>
		</span>
									</button>
									<button class="btn btn-primary" type="button">
									<?php echo esc_html__('Publish Pages Count', 'wpdbbkp') ?> <span class="badge">
										<?php

										echo esc_attr( $count_pages->publish );
										?>
		</span>
									</button>
									<button class="btn btn-primary" type="button">
									<?php echo esc_html__('Approved Comments Count', 'wpdbbkp') ?> <span class="badge">
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
				
				<div class="tab-pane" id="db_setting">

				<div class="panel-group">
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
							<span class="input-group-addon" id="sizing-addon2"><?php echo esc_html__('Maximum Local Backups', 'wpdbbkp') ?></span>
							<input type="number" name="wp_local_db_backup_count" value="<?php echo esc_html( $wp_local_db_backup_count ); ?>" class="form-control" placeholder="Maximum Local Backups" aria-describedby="sizing-addon2">

						</div>
						<div class="alert alert-default" role="alert">
							<span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span><?php echo esc_html__(' The maximum
							number of Local Database Backups that should be kept, regardless of their size.', 'wpdbbkp') ?></br>
							<?php echo esc_html__('Leave blank for keep unlimited database backups.', 'wpdbbkp') ?>
						</div>
						<hr>
						<div class="input-group">
							<label><input type="checkbox" <?php echo esc_attr( $checked ); ?> name="wp_db_log"> <?php echo esc_html__('Enable Log', 'wpdbbkp') ?></label>
						</div>
						<hr>
						<div class="input-group">
						<label><input type="checkbox" <?php echo esc_attr( $wp_db_backup_enable_auto_upgrade_checked ); ?> name="wp_db_backup_enable_auto_upgrade"> <?php echo esc_html__('Enable Auto Backups Before Upgrade', 'wpdbbkp') ?></label>
							<p><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>
							<?php echo esc_html__('If checked then it will create database backup on(before) upgrade/update plugin, theme, WordPress.', 'wpdbbkp') ?>
								<br><?php echo esc_html__('Leave blank/un-checked for disable this feature.', 'wpdbbkp') ?>
							</p>
						</div>
						<hr>
						<div class="input-group">
						<label><input type="checkbox" <?php echo esc_attr( $remove_local_backup ); ?> name="wp_db_remove_local_backup"> <?php echo esc_html__('Remove local backup', 'wpdbbkp') ?></label>
							<p><span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>
							<?php echo esc_html__('If Checked then it will remove local backup.', 'wpdbbkp') ?>
								<br><?php echo esc_html__('Use this option only when you have set any destination.', 'wpdbbkp') ?>
								<br><?php echo esc_html__('If somesites you need only external backup.', 'wpdbbkp') ?>
							</p>
						</div>
						<hr>
						<div class="panel panel-default">
							<div class="panel-heading">
									<a data-toggle="collapse" data-parent="#accordion" href="#collapseExclude">
									<h4 class="panel-title">
									<?php echo esc_html__('Exclude Table From Database Backup', 'wpdbbkp') ?>
									</h4>
									</a>
							</div>
							<div id="collapseExclude" class="panel-collapse collapse in">
								<div class="panel-body">
									<table class="table table-condensed">
										<tr class="success">
											<th><?php echo esc_html__('No.', 'wpdbbkp') ?></th>
											<th><?php echo esc_html__('Tables', 'wpdbbkp') ?></th>
											<th><?php echo esc_html__('Records', 'wpdbbkp') ?></th>
											<th><?php echo esc_html__('Exclude Table', 'wpdbbkp') ?></th>
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

				<div class="tab-pane" id="searchreplace">
					<div class="panel-group">
							<?php
							$wp_db_backup_search_text  = get_option( 'wp_db_backup_search_text' );
							$wp_db_backup_replace_text = get_option( 'wp_db_backup_replace_text' );
							?>
							<form action="" method="post">
								<?php wp_nonce_field( 'wp-database-backup' ); ?>
								
								<p><?php echo esc_html__('If you even need to migrate your WordPress site to a different domain name, 
								or add an SSL certificate to it, you must update the URLs in your database backup file
								 then you can use this feature.','wpdbbkp'); ?> <br>
								 <?php echo esc_html__(' This feature allow you to Search and Replace text in your database backup file. ','wpdbbkp'); ?>
								 <br> <?php echo esc_html__('if you want only exclude tables from search and replace text then Go to Dashboard=>Tool=>WP-DB Backup > Setting > Exclude Table From Database Backup setting. The tables you selected will be skipped over for each backup you make.', 'wpdbbkp') ?> 
								</p>
								<br>
								<div class="input-group">
									<span class="input-group-addon" id="wp_db_backup_search_text"><?php echo esc_html__('Search For', 'wpdbbkp') ?></span>
									<input type="text" name="wp_db_backup_search_text" value="<?php echo esc_html( $wp_db_backup_search_text ); ?>" class="form-control" placeholder="http://localhost/wordpress" aria-describedby="wp_db_backup_search_text">

								</div>
								<br>
								<div class="input-group">
									<span class="input-group-addon" id="wp_db_backup_replace_text"><?php echo esc_html__('Replace With', 'wpdbbkp') ?></span>
									<input type="text" name="wp_db_backup_replace_text" value="<?php echo esc_html( $wp_db_backup_replace_text ); ?>" class="form-control" placeholder="http://site.com" aria-describedby="wp_db_backup_replace_text">

								</div>

								<div class="alert alert-default" role="alert">
									<span class="glyphicon glyphicon-info-sign" aria-hidden="true"></span>
									<?php echo esc_html__("Leave blank those fields if you don't want use this feature and want only regular Database backup.", 'wpdbbkp') ?>
									<br>
									<?php echo esc_html__('Ex:', 'wpdbbkp') ?>
									<br><?php echo esc_html__('Search For:', 'wpdbbkp') ?> http://localhost/wordpress/
									<br><?php echo esc_html__('Replace With:', 'wpdbbkp') ?> http://domain.com/

									<br><br>
									<?php echo esc_html__('Note - This is Search & Replace data in your WordPress Database Backup File not in current Database installation.', 'wpdbbkp') ?>
									
								</div>

								<input class="btn btn-primary" type="submit" name="wpsetting_search" value="Save">
							</form>
						</div>
				


				</div>
				<div class="tab-pane panel-group" id="db_destination">
					<?php
					include plugin_dir_path( __FILE__ ) . 'Destination/wp-backup-destination.php';
					?>
				</div>              


			</div>
			</div>
			
	
		</div>
		<a aria-label="Open Support" aria-expanded="false" class="wpdbbkp-support-button" href="https://backupforwp.com/support/" target="_blank">
				<span class="wpdbbkp-support-icon">
					<svg width="24" height="22" xmlns="http://www.w3.org/2000/svg"><path d="M20.347 20.871l-.003-.05c0 .017.001.034.003.05zm-.243-4.278a2 2 0 0 1 .513-1.455c1.11-1.226 1.383-2.212 1.383-4.74C22 5.782 18.046 2 13.125 2h-2.25C5.954 2 2 5.78 2 10.399c0 4.675 4.01 8.626 8.875 8.626h2.25c.834 0 1.606-.207 3.212-.798a2 2 0 0 1 1.575.083l2.355 1.161-.163-2.878zM10.875 0h2.25C19.13 0 24 4.656 24 10.399c0 2.6-.25 4.257-1.9 6.08l.243 4.279c.072.845-.807 1.471-1.633 1.162l-3.682-1.816c-1.212.446-2.527.921-3.903.921h-2.25C4.869 21.025 0 16.142 0 10.4 0 4.656 4.869 0 10.875 0z" fill="#FFF"></path></svg>
				</span>
			</a>
		<?php
	}

	/**
	 * Redirect to premium website
	*/
	public function wp_db_backup_premium_interface_render()
	{
		wp_redirect("https://backupforwp.com/pricing/");
		exit;
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

				$check_count      = $wpdb->get_var( "SELECT count(*) FROM {$table}"); // phpcs:ignore
				$check_count = intval($check_count);
				$sub_limit =500;
				if(isset($check_count) && $check_count>$sub_limit){
					$result =array();
					$t_sub_queries= ceil($check_count/$sub_limit);
					for($sub_i=0;$sub_i<$t_sub_queries;$sub_i++)
					{
						$sub_offset = $sub_i*$sub_limit;
						$sub_result = $wpdb->get_results( "SELECT * FROM {$table} LIMIT {$sub_limit} OFFSET {$sub_offset}", ARRAY_A  ); 
						if($sub_result){
							$result = array_merge($result,$sub_result);
						}
						sleep(1);
					}
				}
				else{
					$result       = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A  ); // phpcs:ignore
				}
				

				$row2         = $wpdb->get_row( 'SHOW CREATE TABLE ' . $table, ARRAY_N ); // phpcs:ignore
				$output      .= "\n\n" . $row2[1] . ";\n\n";
				$result_count = count( $result );
				for ( $i = 0; $i < $result_count; $i++ ) {
					$row            = $result[ $i ];
					$output        .= 'INSERT INTO ' . $table . ' VALUES(';
					$result_o_index = count( $result[0] );
					$j=0;
					foreach ($row as $key => $value) {
						$row[ $key] = $wpdb->_real_escape( apply_filters( 'wpdbbkp_process_db_fields', $row[$key],$table,$key) );
						$output   .= ( isset( $row[ $key ] ) ) ? '"' . $row[ $key ] . '"' : '""';
						if ( $j < ( $result_o_index - 1 ) ) {
							$output .= ',';
						}
						$j++;
					}
					$output .= ");\n";
				}
				$output .= "\n";
			}
			sleep(1);
		}
		$wpdb->flush();
		/* BEGIN : Prevent saving backup plugin settings in the database dump */
		add_option( 'wp_db_backup_backups', $options_backup );
		add_option( 'wp_db_backup_options', $settings_backup );
		/* END : Prevent saving backup plugin settings in the database dump */
		return $output;
	}

		/**
	 * Create database backup new function.
	 */
	public function wp_db_backup_create_mysql_backup_new($table) {
		global $wpdb;
		$output              = '';

		$check_count      = $wpdb->get_var( "SELECT count(*) FROM {$table}"); // phpcs:ignore
		$check_count = intval($check_count);
		$sub_limit =500;
		if(isset($check_count) && $check_count>$sub_limit){
			$result =array();
			$t_sub_queries= ceil($check_count/$sub_limit);
			for($sub_i=0;$sub_i<$t_sub_queries;$sub_i++)
			{
				$sub_offset = $sub_i*$sub_limit;
				$sub_result = $wpdb->get_results( "SELECT * FROM {$table} LIMIT {$sub_limit} OFFSET {$sub_offset}", ARRAY_A  ); 
				if($sub_result){
					$result = array_merge($result,$sub_result);
				}
				sleep(1);
			}
		}
		else{
			$result       = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A  ); // phpcs:ignore
		}
			

			$row2         = $wpdb->get_row( 'SHOW CREATE TABLE ' . $table, ARRAY_N ); // phpcs:ignore
			$output      .= "\n\n" . $row2[1] . ";\n\n";
			$result_count = count( $result );
			for ( $i = 0; $i < $result_count; $i++ ) {
				$row            = $result[ $i ];
				$output        .= 'INSERT INTO ' . $table . ' VALUES(';
				$result_o_index = count( $result[0] );
				$j=0;
				if(!empty($row)){
				foreach ($row as $key => $value) {
					$row[ $key] = $wpdb->_real_escape( apply_filters( 'wpdbbkp_process_db_fields', $row[$key],$table,$key) );
					$output   .= ( isset( $row[ $key ] ) ) ? '"' . $row[ $key ] . '"' : '""';
					if ( $j < ( $result_o_index - 1 ) ) {
						$output .= ',';
					}
					$j++;
				}
			}
				$output .= ");\n";
			}
			$output .= "\n";

		sleep(1);
			
		$wpdb->flush();
		
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
			'/xwpdbbkpp/mysql/bin/mysqldump',
			'/Program Files/xwpdbbkpp/mysql/bin/mysqldump',
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
			if ( file_exists( $sql_filename ) ) {
				unlink( $sql_filename );
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
		wp_mkdir_p($path_info['basedir'] . '/' . WPDB_BACKUPS_DIR);
		wp_mkdir_p($path_info['basedir'] . '/' . WPDB_BACKUPS_DIR . '/log');
		fclose(fopen($path_info['basedir'] . '/' . WPDB_BACKUPS_DIR . '/index.php', 'w'));
		fclose(fopen($path_info['basedir'] . '/' . WPDB_BACKUPS_DIR . '/log/index.php', 'w'));
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
		$wp_db_file_name = $wp_site_name . '_' . date( 'Y_m_d' ) . '_' . time() . '_' . substr( md5( AUTH_KEY ), 0, 7 ) . '_wpdb';
		$sql_filename    = $wp_db_file_name . '.sql';
		$filename        = $wp_db_file_name . '.zip';
		$logname        = $wp_db_file_name . '.txt';

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
			/* BEGIN : Prevent saving backup plugin settings in the database dump */
			$options_backup  = get_option( 'wp_db_backup_backups' );
			$settings_backup = get_option( 'wp_db_backup_options' );
			delete_option( 'wp_db_backup_backups' );
			delete_option( 'wp_db_backup_options' );
			/* END : Prevent saving backup plugin settings in the database dump */
			global $wpdb;
			$tables              = $wpdb->get_col( 'SHOW TABLES' );
			$wp_db_exclude_table = get_option( 'wp_db_exclude_table',array());
			if(!empty($tables)){
			foreach($tables as $table){
				if ( empty( $wp_db_exclude_table ) || ( ! ( in_array( $table, $wp_db_exclude_table, true ) ) ) ) {
					$handle = fopen( $path_info['basedir'] . '/db-backup/' . $sql_filename, 'a' ); // phpcs:ignore
					fwrite( $handle, $this->wp_db_backup_create_mysql_backup_new($table) ); // phpcs:ignore
					fclose( $handle ); // phpcs:ignore
					
				}
			}
		 }
			/* BEGIN : Prevent saving backup plugin settings in the database dump */
			add_option( 'wp_db_backup_backups', $options_backup );
			add_option( 'wp_db_backup_options', $settings_backup );
			/* END : Prevent saving backup plugin settings in the database dump */
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
			'log_dir'      => ( $path_info['basedir'] . '/db-backup/log/' . $logname ),
			'log_url'      => ( $path_info['baseurl'] . '/db-backup/log/' . $logname ),
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
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$my_theme     = wp_get_theme();
		$active_plugin = count(get_option('active_plugins'));
		$total_plugin  = count(get_plugins());
		$log_message  = '<b>WordPress Version</b> : ' . get_bloginfo( 'version' );
		$log_message .= '<br> <b>Database Version</b> : ' . $mysqlversion;
		$log_message .= '<br> <b>Active Theme Name</b> : ' . $my_theme->get( 'Name' );
		$log_message .= '<br> <b>Theme Version</b> : ' . $my_theme->get( 'Version' );
		$log_message .= '<br> <b>Plugin Count</b> : ' . $total_plugin;
		$log_message .= '<br> <b>Active Plugins</b> : ' . $active_plugin;

		$upload_path['size']    = filesize( $upload_path['dir'] );
		$upload_path['sqlfile'] = $path_info['basedir'] . '/db-backup/' . $sql_filename;
		$wp_db_log              = get_option( 'wp_db_log' );
		if ( 1 === (int) $wp_db_log ) {
			$wp_db_exclude_table = get_option( 'wp_db_exclude_table' );
			if ( ! empty( $wp_db_exclude_table ) ) {
				$log_message .= '<br> <b>Exclude Table</b> : ' . implode( ', ', $wp_db_exclude_table );
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

		$cron_condition = apply_filters('wpdbbkp_dbback_cron_condition',true );
		if(wp_doing_cron() && !$cron_condition){
			wp_die();
		}

		set_time_limit( 0 );
		ignore_user_abort(true);

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
		$wp_db_remove_local_backup = get_option( 'wp_db_remove_local_backup' );
		$destination               = ( 1 === (int) $wp_db_remove_local_backup ) ? '' : 'Local, ';

		$args = array( $details['filename'], $details['dir'], $log_message, $details['size'], $destination );
		do_action_ref_array( 'wp_db_backup_completed', array( &$args ) );

		$options[]                 = array(
			'date'           => time(),
			'filename'       => $details['filename'],
			'url'            => $details['url'],
			'dir'            => $details['dir'],
			'log'            => $details['log_url'],
			'search_replace' => $is_search_replace_flag,
			'sqlfile'        => $details['sqlfile'],
			'size'           => $details['size'],
			'type'			 =>'database',
			'destination'    => $args[4]
		);
		if ( 1 !== (int) $wp_db_remove_local_backup ) {
			update_option( 'wp_db_backup_backups', $options );
		}
		if(isset($details['log_dir']) && !empty($details['log_dir']))
		{
			if (is_writable($details['log_dir']) || !file_exists($details['log_dir'])) {

				if ($handle = @fopen($details['log_dir'], 'a'))
				{
					if (fwrite($handle,  str_replace(array("<br>","<b>","</b>"), array("\n","",""), $args[2])))
					{
						fclose($handle);
					}
				}
			
			}
		}
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
			$cron_freq = apply_filters( 'wpdbbkp_dbback_cron_frequency',$options['autobackup_frequency']);
			wp_schedule_event( time(), $cron_freq, 'wp_db_backup_event' );
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

				wp_register_script( 'bootstrapjs', WPDB_PLUGIN_URL . '/assets/js/bootstrap.min.js', array( 'jquery' ), WPDB_VERSION, false );
				wp_enqueue_script( 'bootstrapjs' );

				wp_register_style( 'bootstrapcss', WPDB_PLUGIN_URL . '/assets/css/bootstrap.min.css', array(), WPDB_VERSION );
				wp_enqueue_style( 'bootstrapcss' );

				wp_register_script( 'dataTablesjs', WPDB_PLUGIN_URL . '/assets/js/jquery.dataTables.js', array( 'jquery' ), WPDB_VERSION, false );
				wp_enqueue_script( 'dataTablesjs' );

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

	// loading admin scripts/styles on admin page only

	public function wpdbbkp_admin_style($hook_suffix)
	{
		if($hook_suffix=="tools_page_wp-database-backup" || $hook_suffix=="toplevel_page_wp-database-backup")
		{
			wp_enqueue_script('wpdbbkp-admin-script', WPDB_PLUGIN_URL . '/assets/js/wpdbbkp-admin.js', array('jquery'), WPDB_VERSION, 'true' );
			wp_localize_script('wpdbbkp-admin-script', 'wpdbbkp_script_vars', array(
				'nonce' => wp_create_nonce( 'wpdbbkp-admin-nonce' ),
			)
			);

			// Adding custom js 
	        $local = array(                    
	                'ajax_url'                     => admin_url( 'admin-ajax.php' ),            
	                'wpdbbkp_admin_security_nonce'     => wp_create_nonce('wpdbbkp_ajax_check_nonce'),
	        ); 
	        //wp_register_script('wpdbbkp-admin-fb', WPDB_PLUGIN_URL . '/assets/js/wpdbbkp-admin-full-backup.js', array(), WPDB_VERSION , true );  
			wp_register_script('wpdbbkp-admin-fb', WPDB_PLUGIN_URL . '/assets/js/wpdbbkp-admin-cron-backup.js', array(), WPDB_VERSION , true );  

	        wp_localize_script('wpdbbkp-admin-fb', 'wpdbbkp_localize_admin_data', $local );        
	        wp_enqueue_script('wpdbbkp-admin-fb');
	        // Custom Js ends
		}
	}

	public function wpdbbkp_admin_newsletter_script($hook_suffix ) {
		if($hook_suffix=="tools_page_wp-database-backup" || $hook_suffix=="toplevel_page_wp-database-backup")
		{
			wp_enqueue_script('wpdbbkp-admin-newsletter-script', WPDB_PLUGIN_URL . '/assets/js/wpdbbkp-admin-newsletter.js', array('jquery'), WPDB_VERSION, 'true' );
			
			$current_screen = get_current_screen(); 
		   
			if(isset($current_screen->post_type)){                  
				$post_type = $current_screen->post_type;                
			}
	
			$post_id = get_the_ID();
			if(isset($_GET['tag_ID'])){
					$post_id = intval($_GET['tag_ID']);
			}
	
			
	
			$data = array(     
				'current_url'                  => wpdbbkp_get_current_url(), 
				'post_id'                      => $post_id,
				'ajax_url'                     => admin_url( 'admin-ajax.php' ),            
				'post_type'                    => $post_type,   
				'page_now'                     => $hook_suffix,
				'wpdbbkp_security_nonce'         => wp_create_nonce('wpdbbkp_ajax_check_nonce'),
			);
							
			$data = apply_filters('wpdbbkp_localize_filter',$data,'wpdbbkp_localize_data');		
		
			wp_localize_script( 'wpdbbkp-admin-newsletter-script', 'wpdbbkp_localize_data', $data );
			
		}	
	
	}

	public function wpdbbkp_sanitize_textarea_field( $str ) {

		if ( is_object( $str ) || is_array( $str ) ) {
			return '';
		}
		
		$str = (string) $str;
		
		$filtered = wp_check_invalid_utf8( $str );
		
		if ( strpos( $filtered, '<' ) !== false ) {
			$filtered = wp_pre_kses_less_than( $filtered );
			// This will strip extra whitespace for us.
			$filtered = wp_strip_all_tags( $filtered, false );
		
			// Use HTML entities in a special case to make sure no later
			// newline stripping stage could lead to a functional tag.
			$filtered = str_replace( "<\n", "&lt;\n", $filtered );
		}
		
		$filtered = trim( $filtered );
		
		$found = false;
		while ( preg_match( '/%[a-f0-9]{2}/i', $filtered, $match ) ) {
			$filtered = str_replace( $match[0], '', $filtered );
			$found    = true;
		}
		
		if ( $found ) {
			// Strip out the whitespace that may now exist after removing the octets.
			$filtered = trim( preg_replace( '/ +/', ' ', $filtered ) );
		}
		
		return $filtered;
		}
		
		public function wpdbbkp_send_query_message(){   
					
			if ( ! isset( $_POST['wpdbbkp_security_nonce'] ) ){
			   return; 
			}
			if ( !wp_verify_nonce( $_POST['wpdbbkp_security_nonce'], 'wpdbbkp-admin-nonce' ) ){
			   return;  
			}

			if( ! current_user_can( 'manage_options' ) ) { 
				return;
			 }
			   
			$message        = $this->wpdbbkp_sanitize_textarea_field($_POST['message']); 
			$email          = $this->wpdbbkp_sanitize_textarea_field($_POST['email']);   
									
			if(function_exists('wp_get_current_user')){
		
				$user           = wp_get_current_user();
		
				$message = '<p>'.$message.'</p><br><br>'.'Query from BackupforWP plugin support tab';
				
				$user_data  = $user->data;        
				$user_email = $user_data->user_email;     
				
				if($email){
					$user_email = $email;
				}            
				//php mailer variables        
				$sendto    = 'team@magazine3.in';
				$subject   = "BackupforWP Query";
				
				$headers[] = 'Content-Type: text/html; charset=UTF-8';
				$headers[] = 'From: '. esc_attr($user_email);            
				$headers[] = 'Reply-To: ' . esc_attr($user_email);
				// Load WP components, no themes.   
		
				$sent = wp_mail($sendto, $subject, $message, $headers); 
		
				if($sent){
		
					 echo json_encode(array('status'=>'t'));  
		
				}else{
		
					echo json_encode(array('status'=>'f'));            
		
				}
				
			}
							
			wp_die();           
		}
		public function add_settings_plugin_action_wp( $actions, $plugin_file, $plugin_data, $context ) {
			$plugin_actions['settings'] = sprintf(
			  '<a href="%s">' . _x( 'Settings', 'wpdbbkp' ) . '</a>',
			  admin_url( 'options-general.php?page=wp-database-backup' )
			);
			$actions = array_merge( $actions, $plugin_actions );
			return $actions;
		}	

		public function wpdbbkp_update_backup_info($FileName, $logFile, $logMessage = '') {
	        $path_info = wp_upload_dir();
	        $filename = $FileName . '.sql';
	        $WPDBFileName = $FileName . '.zip';
	        @unlink($this->wp_db_backup_wp_config_path() . '/' . $filename);
	        @unlink($this->wp_db_backup_wp_config_path() . '/wp_installer.php');
	        @$filesize = filesize($path_info['basedir'] . '/' . WPDB_BACKUPS_DIR . '/' . $WPDBFileName);

	        $upload_path = array(
	            'filename' => ($WPDBFileName),
	            'dir' => ($path_info['basedir'] . '/' . WPDB_BACKUPS_DIR . '/' . $WPDBFileName),
	            'url' => ($path_info['baseurl'] . '/' . WPDB_BACKUPS_DIR . '/' . $WPDBFileName),
	            'size' => ($filesize),
	            'type' => get_option('wp_db_backup_backup_type')
	        );


	        if (get_option('wp_db_log') == 1) {
	            $this->write_log($logFile, $logMessage);
	            $upload_path['logfile'] = $path_info['baseurl'] . '/' . WPDB_BACKUPS_DIR . '/log/' . $FileName . '.txt';
	            $upload_path['logfileDir'] = $logFile;
	        } else {
	            $upload_path['logfile'] = "";
	        }
	        $args = array($FileName, $logFile, $logMessage);
	        do_action_ref_array('wpdbbkp_backup_completed', array(&$args));
	        return $upload_path;
	    }

		private function write_log($logFile, $logMessage) {
	        // Actually write the log file
	        if (is_writable($logFile) || !file_exists($logFile)) {

	            if (!$handle = @fopen($logFile, 'a'))
	                return;

	            if (!fwrite($handle, $logMessage))
	                return;

	            fclose($handle);

	            return true;
	        }
	    }

		public function get_zip_command_path() {

	        // Check shell_exec is available
	        if (!self::is_shell_exec_available())
	            return '';

	        // Return now if it's already been set
	        if (isset($this->zip_command_path))
	            return $this->zip_command_path;

	        $this->zip_command_path = '';

	        // Does zip work
	        if (is_null(shell_exec('hash zip 2>&1'))) {

	            // If so store it for later
	            $this->set_zip_command_path('zip');

	            // And return now
	            return $this->zip_command_path;
	        }

	        // List of possible zip locations
	        $zip_locations = array(
	            '/usr/bin/zip'
	        );

	        // Find the one which works
	        foreach ($zip_locations as $location)
	            if (@is_executable(self::conform_dir($location)))
	                $this->set_zip_command_path($location);

	        return $this->zip_command_path;
	    }

		public function set_zip_command_path($path) {

	        $this->zip_command_path = $path;
	    }

	    /* Begin : Generate Zip using cmd 06-03-2016 V.3.9 */

	    public function zip($WPDBFileName) {

	        $this->archive_method = 'zip';
	        //  var_dump( 'cd ' . escapeshellarg( $this->get_root() ) . ' && ' . escapeshellcmd( $this->get_zip_command_path() ) . ' -rq ' . escapeshellarg( $WPDBFileName ) . ' ./' . ' 2>&1');
	        //echo "hi";exit;
	        $wp_all_backup_exclude_dir = get_option('wp_db_backup_exclude_dir');
	        if (empty($wp_all_backup_exclude_dir)) {
	            $excludes = WPDB_BACKUPS_DIR;
	        } else {
	            $excludes = WPDB_BACKUPS_DIR . '|' . $wp_all_backup_exclude_dir;
	        }
	        // Zip up $this->root with excludes
	        if (!empty($excludes)) {
	          //  error_log('in exclude rule' . $excludes);
	            //      var_dump('cd ' . escapeshellarg( $this->get_root() ) . ' && ' . escapeshellcmd( $this->get_zip_command_path() ) . ' -rq ' . escapeshellarg($WPDBFileName) . ' ./' . ' -x ' . $this->exclude_string( 'zip' ) . ' 2>&1' );exit;
	            $stderr = shell_exec('cd ' . escapeshellarg($this->get_root()) . ' && ' . escapeshellcmd($this->get_zip_command_path()) . ' -rq ' . escapeshellarg($WPDBFileName) . ' ./' . ' -x ' . $this->exclude_string('zip') . ' 2>&1');
	        }

	        // Zip up $this->root without excludes
	        else {
	          //  error_log('without exclude rule');
	            $stderr = shell_exec('cd ' . escapeshellarg($this->get_root()) . ' && ' . escapeshellcmd($this->get_zip_command_path()) . ' -rq ' . escapeshellarg($WPDBFileName) . ' ./' . ' 2>&1');
	        }
	        error_log($stderr);
	        // error_log('cd ' . escapeshellarg( $this->get_root() ) . ' && ' . escapeshellcmd( $this->get_zip_command_path() ) . ' -rq ' . escapeshellarg( $WPDBFileName ) . ' ./' . ' 2>&1');
	        if (!empty($stderr))
	            $this->warning($this->get_archive_method(), $stderr);

	        return $this->verify_archive($WPDBFileName);
	    }

		public function get_root() {

	        if (empty($this->root))
	            $this->set_root(self::conform_dir(self::get_home_path()));

	        return $this->root;
	    }

		public function set_root($path) {

	        if (empty($path) || !is_string($path) || !is_dir($path))
	            throw new Exception('Invalid root path <code>' . $path . '</code> must be a valid directory path');

	        $this->root = self::conform_dir($path);
	    }

		public static function get_home_path() {

	        $home_url = home_url();
	        $site_url = site_url();

	        $home_path = ABSPATH;

	        // If site_url contains home_url and they differ then assume WordPress is installed in a sub directory
	        if ($home_url !== $site_url && strpos($site_url, $home_url) === 0)
	            $home_path = trailingslashit(substr(self::conform_dir(ABSPATH), 0, strrpos(self::conform_dir(ABSPATH), str_replace($home_url, '', $site_url))));

	        return self::conform_dir($home_path);
	    }

		private function warning($context, $warning) {

	        if (empty($context) || empty($warning))
	            return;
	        $this->warnings[$context][$_key = md5(implode(':', (array) $warning))] = $warning;
	    }

		public function exclude_string($context = 'zip') {

	        // Return a comma separated list by default
	        $separator = ', ';
	        $wildcard = '';

	        // The zip command
	        if ($context === 'zip') {
	            $wildcard = '*';
	            $separator = ' -x ';

	            // The PclZip fallback library
	        } elseif ($context === 'regex') {
	            $wildcard = '([\s\S]*?)';
	            $separator = '|';
	        }

	        $wp_all_backup_exclude_dir = get_option('wp_db_backup_exclude_dir');
	        if (empty($wp_all_backup_exclude_dir)) {
	            $excludes = WPDB_BACKUPS_DIR;
	        } else {
	            $excludes = WPDB_BACKUPS_DIR . '|' . $wp_all_backup_exclude_dir;
	        }

	        //$excludes = $this->get_excludes();
	        $excludes = explode("|", $excludes);
	        foreach ($excludes as $key => &$rule) {

	            $file = $absolute = $fragment = false;

	            // Files don't end with /
	            if (!in_array(substr($rule, -1), array('\\', '/')))
	                $file = true;

	            // If rule starts with a / then treat as absolute path
	            elseif (in_array(substr($rule, 0, 1), array('\\', '/')))
	                $absolute = true;

	            // Otherwise treat as dir fragment
	            else
	                $fragment = true;

	            // Strip $this->root and conform
	            $rule = str_ireplace($this->get_root(), '', untrailingslashit(self::conform_dir($rule)));

	            // Strip the preceeding slash
	            if (in_array(substr($rule, 0, 1), array('\\', '/')))
	                $rule = substr($rule, 1);

	            // Escape string for regex
	            if ($context === 'regex')
	                $rule = str_replace('.', '\.', $rule);

	            // Convert any existing wildcards
	            if ($wildcard !== '*' && strpos($rule, '*') !== false)
	                $rule = str_replace('*', $wildcard, $rule);

	            // Wrap directory fragments and files in wildcards for zip
	            if ($context === 'zip' && ( $fragment || $file ))
	                $rule = $wildcard . $rule . $wildcard;

	            // Add a wildcard to the end of absolute url for zips
	            if ($context === 'zip' && $absolute)
	                $rule .= $wildcard;

	            // Add and end carrot to files for pclzip but only if it doesn't end in a wildcard
	            if ($file && $context === 'regex')
	                $rule .= '$';

	            // Add a start carrot to absolute urls for pclzip
	            if ($absolute && $context === 'regex')
	                $rule = '^' . $rule;
	        }

	        // Escape shell args for zip command
	        if ($context === 'zip')
	            $excludes = array_map('escapeshellarg', array_unique($excludes));

	        return implode($separator, $excludes);
	    }

		public function get_archive_method() {
	        return $this->archive_method;
	    }

	    public function verify_archive($WPDBFileName) {
	        // If we've already passed then no need to check again
	        if (!empty($this->archive_verified))
	            return true;

	        // If there are errors delete the backup file.
	        if ($this->get_errors($this->get_archive_method()) && file_exists($WPDBFileName))
	            unlink($WPDBFileName);

	        // If the archive file still exists assume it's good
	        if (file_exists($WPDBFileName))
	            return $this->archive_verified = true;

	        return false;
    	}

	    public function get_files() {

	        if (!empty($this->files))
	            return $this->files;

	        $this->files = array();

	        // We only want to use the RecursiveDirectoryIterator if the FOLLOW_SYMLINKS flag is available
	        if (defined('RecursiveDirectoryIterator::FOLLOW_SYMLINKS')) {

	            $this->files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->get_root(), RecursiveDirectoryIterator::FOLLOW_SYMLINKS), RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD);

	            // Skip dot files if the SKIP_Dots flag is available
	            if (defined('RecursiveDirectoryIterator::SKIP_DOTS'))
	                $this->files->setFlags(RecursiveDirectoryIterator::SKIP_DOTS + RecursiveDirectoryIterator::FOLLOW_SYMLINKS);


	            // If RecursiveDirectoryIterator::FOLLOW_SYMLINKS isn't available then fallback to a less memory efficient method
	        } else {

	            $this->files = $this->get_files_fallback($this->get_root());
	        }

	        return $this->files;
	    }

		public function wpdbbkp_get_timeago( $time )
		{
			$time_difference = time() - $time;

			if( $time_difference < 1 ) { return 'less than 1 second ago'; }
			$condition = array( 12 * 30 * 24 * 60 * 60 =>  'year',
						30 * 24 * 60 * 60       =>  'month',
						24 * 60 * 60            =>  'day',
						60 * 60                 =>  'hour',
						60                      =>  'minute',
						1                       =>  'second'
			);

			foreach( $condition as $secs => $str )
			{
				$d = $time_difference / $secs;

				if( $d >= 1 )
				{
					$t = floor( $d );
					return 'About ' . $t . ' ' . $str . ( $t > 1 ? 's' : '' ) . ' ago';
				}
			}
		}
	// function wp_db_process_timezone(){
	// 	$timezone = wp_timezone_string();
	// 	$timezone_fix=array(
	// 		'UTC-12'=>
	// 	)
	// }

	}

new Wpdb_Admin();
