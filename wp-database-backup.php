<?php
/**
 * Plugin Name: WP Database Backup - Unlimited Database & Files Backup by Backup for WP
 * Plugin URI: https://wordpress.org/plugins/wp-database-backup
 * Description: This plugin helps you to create/restore Unlimited  WordPress Database & Files backup.
 * Version: 7.5
 * Author: Backup for WP
 * Author URI: https://backupforwp.com/
 * Text Domain: wpdbbkp
 * Domain Path: /lang
 *
 *  This plugin helps you to create Database Backup easily.
 *
 *  License: GPL v2
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WPDatabaseBackup' ) ) :

	/**
	 * Main WPDatabaseBackup Class.
	 *
	 * @class WPDatabaseBackup
	 *
	 * @version 7.3
	 */
	final class WPDatabaseBackup {

		/**
		 * Plugin version
		 *
		 * @var string
		 */
		public $version = '7.5';

		/**
		 * Plugin instance
		 *
		 * @var object
		 */
		protected static $instance = null;

		/**
		 * Create Instance.
		 */
		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Clone.
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '1.0' );
		}

		/**
		 * Construct.
		 */
		public function __construct() {
			// Define constants.
			$this->define_constants();
			register_activation_hook( __FILE__, array( $this, 'installation' ) );
			$this->installation(2);
			// Include required files.
			$this->includes();
		}

		/**
		 * Define Constants.
		 */
		private function define_constants() {
			if ( ! defined( 'WPDB_PLUGIN_URL' ) ) {
				define( 'WPDB_PLUGIN_URL', WP_CONTENT_URL . '/plugins/wp-database-backup' );
			}
			define( 'WPDB_PLUGIN_FILE', __FILE__ );
			define('WP_BACKUP_PLUGIN_FILE',__FILE__ );
			define( 'WPDB_PATH', plugin_dir_path( __FILE__ ) );
			define( 'WPDB_ROOTPATH', str_replace( '\\', '/', ABSPATH ) );
			define( 'WPDB_VERSION', $this->version );
			define( 'WPDBPLUGIN_VERSION', WPDB_VERSION );
			$wp_all_backup_backups_dir=get_option('wp_db_backup_backups_dir');
			if(!empty($wp_all_backup_backups_dir)){
				define( 'WPDB_BACKUPS_DIR',$wp_all_backup_backups_dir);
			}else{
				define( 'WPDB_BACKUPS_DIR','db-backup');
			}
		}

		/**
		 * Include Requred files and lib.
		 */
		private function includes()
		{
			include_once 'includes/admin/mb-helper-functions.php';
			include_once 'includes/admin/class-wpdb-admin.php';
			include_once 'includes/admin/Destination/wp-backup-destination-upload-action.php';
			include_once 'includes/class-wpdbbackuplog.php';
			include_once 'includes/admin/filter.php';
			include_once 'includes/admin/class-wpdbbkp-newsletter.php';
			include_once 'includes/features.php';
			$wp_db_incremental_backup = get_option('wp_db_incremental_backup');
			$wpdb_clouddrive_cd = get_option('wpdb_clouddrive_token', false);
			$wp_db_backup_destination_bb = get_option('wp_db_backup_destination_bb', false);
			if (($wp_db_incremental_backup == 1 && $wp_db_backup_destination_bb ==1 )|| ($wpdb_clouddrive_cd && !empty($wpdb_clouddrive_cd))) {
				include_once 'includes/admin/cron-create-full-backup-incremental.php';
			} else {
				include_once 'includes/admin/cron-create-full-backup.php';
			}
			include_once 'includes/class-wpdbfullbackuplog.php';

		}
		/**
		 * Installation setting at time of activation.
		 */
		public function installation($flag=1) {
			add_option( 'wp_db_backup_destination_SFTP', 0 , '' , false );
			add_option( 'wp_db_backup_destination_FTP', 0 , '' , false );
			add_option( 'wp_db_backup_destination_Email',0 , '' , false );
			add_option( 'wp_db_backup_destination_s3', 0 , '' , false );
			add_option( 'wp_db_remove_local_backup', 0 , '' , false );
			add_option( 'wp_db_remove_on_uninstall', 0 , '' , false );
			add_option('wp_db_backup_backup_type','complete', '' , false );
			add_option('wp_db_backup_exclude_dir',"wp-content/backupwordpress-728d36f682-backups|.git|db-backup", '' , false );
			add_option('wp_db_backup_backups_dir','db-backup', '' , false );
			add_option('bb_last_backup_timestamp',0, '' , false );
			add_option('wp_db_backup_sftp_details',null, '' , false );
			
			if($flag!=2){
				add_option( 'wpdbbkp_activation_redirect', true, '' , false );
			}

			$this->create_processed_files_table();
		}

		/**
		 * Logger.
		 */
		public function logger() {
			_deprecated_function( 'Wpekaplugin->logger', '1.0', 'new WPDB_Logger()' );
			return new WPDB_Logger();
		}

		public function create_processed_files_table() {
			global $wpdb;
			$table_name = $wpdb->prefix . 'wpdbbkp_processed_files';
			$charset_collate = $wpdb->get_charset_collate();
		
			// Check if the table already exists
			//phpcs:ignore  -- Reason: Direct SQL execution is required here.
			if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
				//phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
				$sql = "CREATE TABLE $table_name (
					id mediumint(9) NOT NULL AUTO_INCREMENT,
					file_path text NOT NULL,
					processed_at TIMESTAMP on update CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
					status ENUM('added', 'updated', 'deleted') DEFAULT 'added' NOT NULL,
					PRIMARY KEY  (id),
					UNIQUE (file_path(250))
				) $charset_collate;";
		
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				dbDelta($sql);
			}
		}
	}

endif;

/**
 * Returns the main instance of WP to prevent the need to use globals.
 */
function wpdb() {
	return WPDatabaseBackup::instance();
}

// Global for backwards compatibility.
$GLOBALS['wpdbplugin'] = wpdb();