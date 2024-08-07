<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**********************************
 * Cron schedule for full backup
 **********************************/
add_action( 'init','wp_db_fullbackup_scheduler_activation');

 function wp_db_fullbackup_scheduler_activation() {
	$options = get_option( 'wp_db_backup_options' );
	if ( ( ! wp_next_scheduled( 'wpdbkup_event_fullbackup' ) ) && ( true === isset( $options['enable_autobackups'] ) ) ) {
		if(isset($options['autobackup_frequency']) && $options['autobackup_frequency'] != 'disabled' && isset($options['autobackup_type']) && ($options['autobackup_type'] == 'full' || $options['autobackup_type'] == 'files')){
			if(isset($options['autobackup_full_time']) && !empty($options['autobackup_full_time'])){
				wp_schedule_event( time(), 'thirty_minutes', 'wpdbkup_event_fullbackup' );
			}
			else{
				wp_schedule_event( time(), $options['autobackup_frequency'], 'wpdbkup_event_fullbackup' );
			}
		  
		}
	}

}
add_action( 'wpdbkup_event_fullbackup', 'wpdbbkp_cron_backup' );


if ( ! function_exists( 'wpdbbkp_add_custom_cron_schedule_db' ) ) {
	function wpdbbkp_add_custom_cron_schedule_db( $schedules ) {
		$schedules['every_tweleve_minutes'] = array(
			'interval' => 720, 
			'display'  => __( 'Every 12 Minutes', 'wpdbbkp' )
		);
		return $schedules;
	}
	add_filter( 'cron_schedules', 'wpdbbkp_add_custom_cron_schedule_db' );
}

// Schedule the custom cron event
if ( ! function_exists( 'wpdbbkp_schedule_backup_db' ) ) {
	function wpdbbkp_schedule_backup_db() {
		$db_process_check = get_transient( 'wpdbbkp_db_cron_event_check' );
		if ( ! wp_next_scheduled( 'wpdbbkp_cron_backup_hook_db' ) && ! $db_process_check) {
			$path_info = wp_upload_dir();
			$progressFile = $path_info['basedir'] . '/db-backup/db_progress.json';
			$progress_json  = file_exists( $progressFile ) ? true : false ;
			if($progress_json){
				wp_schedule_event( time(), 'every_tweleve_minutes', 'wpdbbkp_cron_backup_hook_db' );
			}
		}
	}
	add_action( 'wp', 'wpdbbkp_schedule_backup_db' );
}

if ( ! function_exists( 'wpdbbkp_cron_backup_hook_db' ) ) {
	function wpdbbkp_cron_backup_hook_db_cb() {
		$path_info = wp_upload_dir();
		$progressFile = $path_info['basedir'] . '/db-backup/db_progress.json';
		$progress_json  = file_exists( $progressFile ) ? json_decode( file_get_contents( $progressFile ), true ) : null ;
		if($progress_json){
			$args = array(
				'logFile'   => $progress_json['logFile'], 
				'tableName' => $progress_json['tableName'], 
				'FileName'  => $progress_json['FileName'],
				'offset'   => $progress_json['offset'],
				'tables'   => $progress_json['tables'],
				'progress' => $progress_json['progress'],
				'from_cron' => true

			);
			wpdbbkp_cron_create_mysql_backup( $args );
		}
	}
	add_action( 'wpdbbkp_cron_backup_hook_db', 'wpdbbkp_cron_backup_hook_db_cb' );
}
add_action( 'backup_files_cron_new', 'backup_files_cron_with_resume' );

function wp_db_fullbackup_add_cron_schedules($schedules){
    if(!isset($schedules["ten_minutes"])){
        $schedules["ten_minutes"] = array(
            'interval' => 10*60,
            'display' => __('Once every 10 minutes'));
    }
	if(!isset($schedules["thirty_minutes"])){
        $schedules["thirty_minutes"] = array(
            'interval' => 30*60,
            'display' => __('Once every 30 minutes'));
    }
    return $schedules;
}

if ( ! wp_next_scheduled( 'backup_files_cron_new' ) ) {

	$trasient_lock 	= get_transient( 'wpdbbkp_backup_status' );
	$status_lock 	= get_option( 'wpdbbkp_backupcron_status','inactive');
	$total_chunks 	= get_option( 'wpdbbkp_total_chunk_cnt',false );
	$current_chunk_args 	= get_option( 'wpdbbkp_current_chunk_args',false );
	$last_update 	= get_option('wpdbbkp_last_update',false);


    $should_run_backup = ($status_lock == 'active');

    if ( !$should_run_backup && $trasient_lock ) {
        $time_diff = time() - intval( $last_update );
        if ( $time_diff < 600 ) { // 10 minutes * 60 seconds
            $should_run_backup = false;
        }
    }

   
    if ( !$total_chunks || !$current_chunk_args ) {
        $should_run_backup = false;
    }

    
    if ( $should_run_backup ) {
        wp_schedule_event( time(), 'ten_minutes', 'backup_files_cron_new' );
    }

}

/*************************************************
 * Create custom enpoint for running cron backup
 *************************************************/

add_action( 'rest_api_init', 'wpdbbkp_cron_backup_api');

function wpdbbkp_cron_backup_api(){
    register_rest_route( 'wpdbbkp/v1', '/cron_backup/(?P<token>[a-zA-Z0-9]+)', array(
        'methods' => 'GET',
        'callback' => 'wpdbbkp_cron_backup',
		'permission_callback' =>'__return_true',
		'args' => array(
		'token' => array(
			'validate_callback' => function($param, $request, $key) {
			 $saved_token=get_option('wpdbbkp_api_token',false);
			 delete_option('wpdbbkp_api_token');
			 if($saved_token && $saved_token==$param){return true;}else{return false;}
		}),
		
    )
	));
}

/************************************************
 * Adding ajax call to check if any cron is working
 ************************************************/

add_action('wp_ajax_wpdbbkp_check_fullbackup_stat', 'wpdbbkp_check_fullbackup_stat');

function wpdbbkp_check_fullbackup_stat(){
	$wpdbbkp_fullbackup_stat=['status'=>esc_html__('inactive','wpdbbkp')];
	if(current_user_can('manage_options') && isset($_POST['wpdbbkp_admin_security_nonce']) && wp_verify_nonce($_POST['wpdbbkp_admin_security_nonce'], 'wpdbbkp_ajax_check_nonce')){
	 $stat=get_option('wpdbbkp_backupcron_status',false);
	 if($stat=='active'){
		$wpdbbkp_fullbackup_stat['status']=esc_html__('active','wpdbbkp'); 
	 }
	}
	echo wp_json_encode($wpdbbkp_fullbackup_stat);
	wp_die();

}


/************************************************
 * Adding ajax call to start manual cron backup
 ************************************************/

add_action('wp_ajax_wpdbbkp_start_cron_manual', 'wpdbbkp_start_cron_manual');

function wpdbbkp_start_cron_manual(){
	$wpdbbkp_cron_manual=['status'=>esc_html('fail'),'msg'=>esc_html__('Invalid Action','wpdbbkp')];
	if(current_user_can('manage_options') && isset($_POST['wpdbbkp_admin_security_nonce']) && wp_verify_nonce($_POST['wpdbbkp_admin_security_nonce'], 'wpdbbkp_ajax_check_nonce')){
	$wpdbbkp_cron_manual=['status'=>esc_html('success'),'msg'=>esc_html__('Cron Started','wpdbbkp')];
	$token=wpdbbkp_token_gen();
	update_option('wpdbbkp_api_token',$token, false);
	$rest_route = get_rest_url(null,'wpdbbkp/v1/cron_backup/'.$token);
	
	  $response = wp_remote_get(esc_url($rest_route),
			array(
				'timeout'     => 9,
				'httpversion' => '1.1',
			)
		);

		if ( is_array( $response ) && ! is_wp_error( $response ) ) {
			$wpdbbkp_cron_manual['response']=$response;
			$wpdbbkp_cron_manual['url']=$rest_route;
		}else{
			$wpdbbkp_cron_manual['response']=false;
			$wpdbbkp_cron_manual['url']='';
		}
	}

	echo wp_json_encode($wpdbbkp_cron_manual);
	wp_die();

}

/************************************************
 * Adding ajax endoint to track backup progress
 ************************************************/

add_action('wp_ajax_wpdbbkp_get_progress', 'wpdbbkp_get_progress');
function wpdbbkp_get_progress(){
	$wpdbbkp_progress=['status'=>esc_html('fail'),'msg'=>esc_html__('Unable to track progress, try reloading the page','wpdbbkp')];
	if(isset($_POST['wpdbbkp_admin_security_nonce']) && wp_verify_nonce($_POST['wpdbbkp_admin_security_nonce'], 'wpdbbkp_ajax_check_nonce') && current_user_can( 'manage_options' )){
		$wpdbbkp_progress['backupcron_status']=esc_html(get_option('wpdbbkp_backupcron_status',false));
		$wpdbbkp_progress['backupcron_step']=esc_html(get_option('wpdbbkp_backupcron_step',false));
		$wpdbbkp_progress['backupcron_current']=esc_html(get_option('wpdbbkp_backupcron_current',false));
		$wpdbbkp_progress['backupcron_progress']=esc_html(get_option('wpdbbkp_backupcron_progress',false));
		$wpdbbkp_progress['status']=esc_html('success');
		$wpdbbkp_progress['redirect_url'] = site_url() . '/wp-admin/admin.php?page=wp-database-backup&notification=create&_wpnonce='.esc_attr(wp_create_nonce( 'wp-database-backup' ));
	}
	echo wp_json_encode($wpdbbkp_progress);
	wp_die();

}

/*****************************************
 * Main function to backup DB and Files
 *****************************************/

 function wpdbbkp_cron_backup(){
		// make sure only one backup process is started
		$cron_condition = apply_filters('wpdbbkp_fullback_cron_condition',true);

		if(!$cron_condition){
			wp_die();
		}

		if(get_transient( 'wpdbbkp_backup_status' )=='active'){
			wp_die();
		}
	    ignore_user_abort(true);
		set_time_limit(0);
		$progress = 0.00;
	    set_transient('wpdbbkp_backup_status','active',600);
		update_option('wpdbbkp_backupcron_status','active', false);
		update_option('wpdbbkp_backupcron_step','Initialization', false);
		update_option('wpdbbkp_backupcron_current','Fetching Config', false);
		$progress = $progress+1;
		update_option('wpdbbkp_backupcron_progress',intval($progress), false);

		$config= wpdbbkp_wp_cron_config_path();
		$common_args=$config;
		$options = get_option( 'wp_db_backup_options' );
		if(isset($options['autobackup_type']) && $options['autobackup_type']=="files")
		{
			$progress= $progress+29;
			update_option('wpdbbkp_backupcron_progress',intval($progress), false);

		}
		else{
		update_option('wpdbbkp_backupcron_step','Fetching Tables', false);
		$common_args['progress'] = $progress+4;
		update_option('wpdbbkp_backupcron_progress',intval($progress), false);
		$tables= wpdbbkp_cron_mysqldump($config);
		$common_args['tables'] = $tables['tables'];
		wpdbbkp_cron_create_mysql_backup($common_args);
		}
		update_option('wpdbbkp_current_chunk_args',$common_args, false);
		backup_files_cron_with_resume();
 }

// Function to backup files


// Function to upload a batch of files to Backblaze B2
function wpdbbkp_upload_batch_to_server($batch) {
    foreach ($batch as $file_info) {
		return WPDatabaseBackupBB::upload_backup_to_backblaze($file_info['file_path'], $file_info['file_path']);
    }
}


 /*********************************************
 * Fetch config and initialize backup process
 **********************************************/

if(!function_exists('wpdbbkp_wp_cron_config_path')){
	function wpdbbkp_wp_cron_config_path()
	{
	        $path_info = wp_upload_dir();
	        $files_added = 0;

	        wp_mkdir_p($path_info['basedir'] . '/' . WPDB_BACKUPS_DIR);
	        wp_mkdir_p($path_info['basedir'] . '/' . WPDB_BACKUPS_DIR . '/log');
	        fclose(fopen($path_info['basedir'] . '/' . WPDB_BACKUPS_DIR . '/index.php', 'w'));
	        fclose(fopen($path_info['basedir'] . '/' . WPDB_BACKUPS_DIR . '/log/index.php', 'w'));
	        //added htaccess file 08-05-2015 for prevent directory listing
	        //Fixed Vulnerability 22-06-2016 for prevent direct download
	        //fclose(fopen($path_info['basedir'] . '/' . WPDB_BACKUPS_DIR .'/.htaccess', $htassesText));
	        $f = fopen($path_info['basedir']  . '/' . WPDB_BACKUPS_DIR . '/.htaccess', "w");
	        fwrite($f, "#These next two lines will already exist in your .htaccess file
	 RewriteEngine On
	 RewriteBase /
	 # Add these lines right after the preceding two
	 RewriteCond %{REQUEST_FILENAME} ^.*(.zip)$
	 RewriteCond %{HTTP_COOKIE} !^.*can_download.*$ [NC]
	 RewriteRule . - [R=403,L]");
	        fclose($f);
	        $siteName = preg_replace('/[^\p{L}\p{M}]+/u', '_', get_bloginfo('name')); //added in v2.1 for Backup zip labeled with the site name(Help when backing up multiple sites).
	        $FileName = $siteName . '_' . Date("Y_m_d") . '_' . Time() .'_'. substr(md5(AUTH_KEY), 0, 7).'_wpall';
	        $WPDBFileName = $FileName . '.zip';
	        $wp_all_backup_type = get_option('wp_db_backup_backup_type');
	        $logFile = $path_info['basedir'] . '/' . WPDB_BACKUPS_DIR . '/log/' . $FileName . '.txt';
			$upload_folder = str_replace(site_url(),'',$path_info['basedir']);
			$logFileUrl = $path_info['baseurl'].'/'.WPDB_BACKUPS_DIR . '/log/' . $FileName . '.txt';

	        $logMessage = "\n#--------------------------------------------------------\n";
	        $logMessage .= "NOTICE: Do NOT post to public sites or forums\n";
	        $logMessage .= "#--------------------------------------------------------\n";
	        $logMessage .= " Backup File Name : " . $WPDBFileName."\n";
	        $logMessage .= " Backup File Path : " . $path_info['baseurl'] . '/' . WPDB_BACKUPS_DIR . '/' . $WPDBFileName."\n";
	        $logMessage .= " Backup Type : " . $wp_all_backup_type."\n";
	        $logMessage .= "#--------------------------------------------------------\n";
   
	        $return_data['files_added'] = $files_added;
	        $return_data['siteName'] = $siteName;
	        $return_data['FileName'] = $FileName;
	        $return_data['logFile'] = $logFile;
			$return_data['logFileUrl'] = $logFileUrl;
	        $return_data['logMessage'] = $logMessage;
	        return $return_data;
		}
}

 /*************************
 * Get dump of DB tables
 **************************/

if(!function_exists('wpdbbkp_cron_mysqldump')){
	function wpdbbkp_cron_mysqldump($args)
	{
		require_once WPDB_PATH.'includes/admin/class-wpdb-admin.php';
		$all_db_tables = array();
		$all_db_tables['status'] = 'failure';
		$wpdbbkp_admin_class_obj = new Wpdb_Admin();
			if((isset($args['FileName']) && !empty($args['FileName'])) && (isset($args['logFile']) && !empty($args['logFile']))){
				$FileName = sanitize_text_field($args['FileName']);
				$logFile = sanitize_text_field($args['logFile']);
				$path_info = wp_upload_dir();
				if (get_option('wp_db_backup_backup_type') == 'Database' || get_option('wp_db_backup_backup_type') == 'complete') {

		            $filename = $FileName . '.sql';
		            /* Begin : Generate SQL DUMP using cmd 06-03-2016 */
		            $mySqlDump = 1;
		            if ($mySqlDump == 1) {
		            	 global $wpdb;
		                $tables = $wpdb->get_col('SHOW TABLES');
		                $all_db_tables['status'] = 'success';
		                $all_db_tables['tables'] = $tables;
		            }
		        }
			}
		
			return $all_db_tables;
	}
	
}


 /*******************
 * Create DB Backup
 ********************/
if ( ! function_exists( 'wpdbbkp_cron_create_mysql_backup' ) ) {
	function wpdbbkp_cron_create_mysql_backup( $args ) {

		if ( isset( $args['logFile'], $args['FileName'] ) && 
			! empty( $args['logFile'] ) && ! empty( $args['FileName'] ) && ! empty( $args['tables'] ) ) {
					$tables = $args['tables'];
					$count_tables = count($tables);
					$single_item_percent = number_format(((1/$count_tables)*30),2,".","");

					$table_check = isset($args['tableName'])? $args['tableName']:null;
					$start_processing = false;
					$progress = isset($args['progress'])? $args['progress']:4;

					$options_backup  = get_option( 'wp_db_backup_backups' );
					$settings_backup = get_option( 'wp_db_backup_options' );
					delete_option( 'wp_db_backup_backups' );
					delete_option( 'wp_db_backup_options' );

					foreach($tables as $table){
						if(!$table_check || ( $table == $table_check)){
							$start_processing = true;
						}

						if(!$start_processing){
							continue;
						}

						$args['tableName']= $table;
						update_option('wpdbbkp_backupcron_current',$table, false);
						$progress = $progress+$single_item_percent;
						update_option('wpdbbkp_backupcron_progress',intval($progress), false);
						set_transient('wpdbbkp_backup_status','active',600);

						######################################################################


						$logFile  = sanitize_text_field( $args['logFile'] );
						$table    = sanitize_text_field( $args['tableName'] );
						$FileName = sanitize_text_field( $args['FileName'] );
						$filename = $FileName . '.sql';
						$path_info = wp_upload_dir();
						$filepath  = $path_info['basedir'] . '/db-backup/' . $filename;
						$progressFile = $path_info['basedir'] . '/db-backup/db_progress.json';
			
						global $wpdb;
			
						$wp_db_exclude_table = get_option( 'wp_db_exclude_table', array() );
						if(is_array($wp_db_exclude_table)){
						 $wp_db_exclude_table[] = $wpdb->prefix . 'wpdbbkp_processed_files';   
						}else{
						  $wp_db_exclude_table = array($wpdb->prefix . 'wpdbbkp_processed_files');   
						}
						
						$logMessage = "\n#--------------------------------------------------------\n";
						$logMessage .= "\n Database Table Backup";
						$logMessage .= "\n#--------------------------------------------------------\n";
			
						if ( ! empty( $wp_db_exclude_table ) ) {
							$logMessage .= 'Exclude Table: ' . implode( ', ', $wp_db_exclude_table ) . "\n#--------------------------------------------------------\n";
						}
			
						if ( empty( $wp_db_exclude_table ) || ! in_array( $table, $wp_db_exclude_table ) ) {
							$logMessage .= "\nBacking up table: $table";
			
							$sub_limit  = 500;
							$table      = esc_sql( $table );
							$check_count = intval( $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ) );
			
							// Get table structure for backup
							$row2    = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
							if( ! $row2 ) {
								$logMessage .= "\nFailed to get table structure for table: {$table}";
								wpdbbkp_write_log( $logFile, $logMessage );
								continue;
							}
							$output  = "\n\n" . $row2[1] . ";\n\n";
							wpdbbkp_append_to_file( $filepath, $output );
							$output = '';
							$offset = isset( $args['offset'] ) ? intval( $args['offset'] ) : 0;
			
							while ( $offset < $check_count ) {
								$sub_result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` LIMIT %d OFFSET %d", $sub_limit, $offset ), ARRAY_A );
								if ( $sub_result ) {
									$output = wpdbbkp_create_sql_insert_statements( $table, $sub_result );
									// Write chunk to file
									$write_result = wpdbbkp_append_to_file( $filepath, $output );
									if ( false === $write_result ) {
										$logMessage .= "\nFailed to write to file: $filepath";
										break;
									}
									// Clear output to free memory
									$output = '';
			
									// Update progress
									$offset += $sub_limit;
									file_put_contents( $progressFile, json_encode( array( 'FileName'=>$FileName,'logFile'=>$logFile,'tableName'=>$table,'offset' => $offset ,'tables'=>$tables, 'progress'=>$progress) ) );
									set_transient( 'wpdbbkp_db_cron_event_check', true, 600 );
								}
								sleep( 1 ); // Optional sleep to reduce server load
							}
			
							$logMessage .= "\nBackup completed for table: {$table}";
							// Remove progress file upon completion
						}
			
						$wpdb->flush();
						$logMessage .= "\n#--------------------------------------------------------\n";
			
						if ( get_option( 'wp_db_log' ) == 1 ) {
							wpdbbkp_write_log( $logFile, $logMessage );
							$upload_path['logfile'] = $logFile;
						} else {
							$upload_path['logfile'] = '';
						}
			
						$logMessage = "\n# Database dump method: PHP\n";
						if ( get_option( 'wp_db_log' ) == 1 ) {
							wpdbbkp_write_log( $logFile, $logMessage );
						}


						###########################################################################################
						
						sleep(1);
					}
		

			$path_info = wp_upload_dir();
		
			$sql_filename = isset($args['FileName'])? $path_info['basedir'] . '/db-backup/' . $args['FileName'].'.sql':null;
			
			if($sql_filename){
					$return_status = WPDatabaseBackupBB::upload_backup_to_backblaze($sql_filename, $args['FileName'].'.sql');
				if( $return_status['success'] ){
					wp_delete_file($sql_filename);
					wp_delete_file($path_info['basedir'] . '/db-backup/db_progress.json');
					update_option('wpdbbkp_backupcron_current','DB Backed Up', false);
				}
				if($args['from_cron']){
					backup_files_cron_with_resume();
				}
			}
			
			update_option('wp_db_backup_backups',$options_backup, false);
			update_option('wp_db_backup_options',$settings_backup, false);

		}
	}
}
	
function wpdbbkp_append_to_file( $file, $data ) {
	$fp = fopen( $file, 'a' );
	if ( ! $fp ) {
		return false;
	}
	fwrite( $fp, $data );
	fclose( $fp );
}
	/**
	 * Creates SQL INSERT statements for the given data.
	 *
	 * @param string $table Table name.
	 * @param array $rows Data rows.
	 * @return string SQL INSERT statements.
	 */
	function wpdbbkp_create_sql_insert_statements($table, $rows) {
		global $wpdb;
		$output = '';
		foreach ($rows as $row) {
			$output .= 'INSERT INTO `' . $table . '` VALUES(';
			$values = array();
			foreach ($row as $value) {
				$values[] = isset($value) ? '"' . $wpdb->_real_escape($value) . '"' : 'NULL';
			}
			$output .= implode(',', $values) . ");\n";
		}
		return $output;
	}
	
	


 /***********************
 * Funtion to write log
 ************************/
if(!function_exists('wpdbbkp_write_log')){
	function wpdbbkp_write_log($logFile, $logMessage) {
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
}


 /************************************
 * Fetch method for Zip compression
 ************************************/

if(!function_exists('wpdbbkp_cron_method_zip')){
	function wpdbbkp_cron_method_zip($args) {
		$method_zip_array = array();
		$method_zip_array['status'] = 'failure';
		require_once WPDB_PATH.'includes/admin/class-wpdb-admin.php';
			if((isset($args['FileName']) && !empty($args['FileName'])) && (isset($args['logFile']) && !empty($args['logFile'])) && (isset($args['logMessage']) && !empty($args['logMessage']))){
				$FileName = sanitize_text_field($args['FileName']);
				$logFile = sanitize_text_field($args['logFile']);
				$log_msg = sanitize_text_field($args['logMessage']);
            	$log_msg.="\n Exclude Folders and Files : " . get_option('wp_db_backup_exclude_dir')."\n";
				$method_zip_array['logMessage'] = $log_msg;
				$wpdbbkp_admin_class_obj = new Wpdb_Admin();
				$update_backup_info = $wpdbbkp_admin_class_obj->wpdbbkp_update_backup_info($FileName, $logFile, $log_msg);
				$method_zip_array['update_backup_info'] = $update_backup_info;
				$method_zip_array['status'] = 'success';
				$method_zip_array['logMessage'] = $log_msg;
				$method_zip_array['ZipArchive'] = class_exists('ZipArchive');;
			}
		return $method_zip_array;
	}
}

 /***************************************
 * Create Zip file and check totalfiles
 ****************************************/

if(!function_exists('wpdbbkp_cron_get_backup_files')){
	function wpdbbkp_cron_get_backup_files($args) {
		$backup_files_array = array(); $file_iterator_count = 0;
		$backup_files_array['status'] = 'failure';
		$path_info = wp_upload_dir();
		require_once WPDB_PATH.'includes/admin/class-wpdb-admin.php';
		$wpdbbkp_admin_class_obj = new Wpdb_Admin();
		
			if((isset($args['FileName']) && !empty($args['FileName'])) && (isset($args['logMessage']) && !empty($args['logMessage'])) && (isset($args['logFile']) && !empty($args['logFile']))){
				$FileName = sanitize_text_field($args['FileName']);
				$WPDBFileName = $FileName . '.zip';
				$logMessage = sanitize_text_field($args['logMessage']);
				$logFile = sanitize_text_field($args['logFile']);
				if (get_option('wp_db_backup_backup_type') == 'File' || get_option('wp_db_backup_backup_type') == 'complete') {
					$files_object = array();
					$wp_all_backup_exclude_dir = get_option('wp_db_backup_exclude_dir');
	                if (empty($wp_all_backup_exclude_dir)) {
	                    $excludes = WPDB_BACKUPS_DIR;
	                } else {
	                    $excludes = WPDB_BACKUPS_DIR . '|' . $wp_all_backup_exclude_dir;
	                }
	                $logMessage.="\n Exclude Folders and Files :  $excludes";
	                $wp_backup_files = '';
	                $wp_backup_files = $wpdbbkp_admin_class_obj->get_files();
	                $file_iterator_count = iterator_count($wp_backup_files);
	                $file_iterator_count = ceil($file_iterator_count / 2000);
				}

				$logMessage .= "\n Zip method: ZipArchive \n";
	            $zip = new ZipArchive;
	            $zip->open($path_info['basedir'] . '/db-backup/' . $WPDBFileName, ZipArchive::CREATE);

	            if (get_option('wp_db_backup_backup_type') == 'Database' || get_option('wp_db_backup_backup_type') == 'complete') {
	                $filename = $FileName . '.sql';
	                $zip->addFile($path_info['basedir'] . '/db-backup/' . $filename, $filename);
	            }
	            $zip->close();

	            $backup_files_array['status'] = 'success';
	            $backup_files_array['chunk_count'] = $file_iterator_count;
				if (get_option('wp_db_log') == 1) {
				   wpdbbkp_write_log($logFile, $logMessage);
				} 
			}
		
		return $backup_files_array;
	}
}

 /***************************************
 * Adding files to ZIP
 ****************************************/
if(!function_exists('wpdbbkp_cron_files_backup')){
	function wpdbbkp_cron_files_backup($args) {
		$file_backup_array = array();
		$file_backup_array['status'] = 'failure';
		require_once WPDB_PATH.'includes/admin/class-wpdb-admin.php';
		$wpdbbkp_admin_class_obj = new Wpdb_Admin();
		
			if((isset($args['FileName']) && !empty($args['FileName'])) && (isset($args['logFile']) && !empty($args['logFile'])) && (isset($args['chunk_count'])) && (isset($args['files_added']))){
				$FileName = sanitize_text_field($args['FileName']);
				$logFile = sanitize_text_field($args['logFile']);
				$logMessage = '';
				$files_added = intval($args['files_added']);
				$bkp_chunk_cnt = intval($args['chunk_count']);
				$WPDBFileName = $FileName . '.zip';
				$path_info = wp_upload_dir();
				$total_chunk_cnt = intval($args['total_chunk_cnt']);

	            $zip = new ZipArchive;
	            $zip->open($path_info['basedir'] . '/db-backup/' . $WPDBFileName, ZipArchive::CREATE);
	            if (get_option('wp_db_backup_backup_type') == 'File' || get_option('wp_db_backup_backup_type') == 'complete') {
	                $wp_all_backup_exclude_dir = get_option('wp_db_backup_exclude_dir');
	                if (empty($wp_all_backup_exclude_dir)) {
	                    $excludes = WPDB_BACKUPS_DIR;
	                } else {
	                    $excludes = WPDB_BACKUPS_DIR . '|' . $wp_all_backup_exclude_dir;
	                }
	            	
	                $file_object = array();
	                $wp_backup_files = '';
	                $wp_backup_files = $wpdbbkp_admin_class_obj->get_files();
	                $file_start_offset = 1;
	                if($bkp_chunk_cnt > 1){
	                	$file_start_offset = ($bkp_chunk_cnt - 1) * 2000;
	                }
	                $file_end_offset = $file_start_offset + 2000;
	                $file_loop_cnt = 1;
	                foreach ($wp_backup_files as $file) { 
	                	if($file_loop_cnt < $file_start_offset){
	                		$file_loop_cnt++;
	                		continue;
	                	}
	                	if($file_start_offset >=  $file_loop_cnt && $file_loop_cnt < $file_end_offset){
	                		if(!empty($file->getPathname())){
	                			$file_object[] = $file;
	                		}
	                		$file_start_offset++;
	                	}else{
	                		if($file_loop_cnt > $file_end_offset){
	                			break;
	                		}
	                	}
	                	$file_loop_cnt++;
	                }


		            if(!empty($file_object)){
			            if(is_array($file_object)){
				            foreach ($file_object as $file) {
					            if(!empty($file->getPathname())){
					                    // Skip dot files,
				                    if (method_exists($file, 'isDot') && $file->isDot()){
				                        continue;
				                    }

				                    // Skip unreadable files
				                    if (!@realpath($file->getPathname()) || !$file->isReadable()){
				                        continue;
				                    }

				                    // Excludes
				                    if ($excludes && preg_match('(' . $excludes . ')', str_ireplace(trailingslashit($wpdbbkp_admin_class_obj->get_root()), '', conform_dir($file->getPathname())))){
				                        continue;
				                    }

				                    if ($file->isDir()){
				                        $zip->addEmptyDir(trailingslashit(str_ireplace(trailingslashit($wpdbbkp_admin_class_obj->get_root()), '', conform_dir($file->getPathname()))));
				                    }
				                    elseif ($file->isFile()) {
				                        $zip->addFile($file->getPathname(), str_ireplace(trailingslashit($wpdbbkp_admin_class_obj->get_root()), '', conform_dir($file->getPathname())));
				                        $logMessage .= "\n Added File: " . $file->getPathname();
				                    }

				                }
			                }
			            }
		            }
	            }
	            $zip->close();
	            if($total_chunk_cnt == $bkp_chunk_cnt){
					$update_backup_info = $wpdbbkp_admin_class_obj->wpdbbkp_update_backup_info($FileName, $logFile, $logMessage);
					$file_backup_array['update_backup_info'] = $update_backup_info;
				}
				$file_backup_array['status'] = 'success';
				$file_backup_array['files_added'] = $files_added;
			
		}
		return $file_backup_array;
	}
}


if(!function_exists('conform_dir')){
	function conform_dir($dir, $recursive = false) {
	    // Assume empty dir is root
	    if (!$dir)
	        $dir = '/';

	    // Replace single forward slash (looks like double slash because we have to escape it)
	    $dir = str_replace('\\', '/', $dir);
	    $dir = str_replace('//', '/', $dir);

	    // Remove the trailing slash
	    if ($dir !== '/')
	        $dir = untrailingslashit($dir);

	    // Carry on until completely normalized
	    if (!$recursive && conform_dir($dir, true) != $dir)
	        return conform_dir($dir);

	    return (string) $dir;
	}
}

 /**********************************************
 * TO complete the backup process
 ***********************************************/

if(!function_exists('wpdbbkp_cron_backup_event_process')){
	function wpdbbkp_cron_backup_event_process($args) {
	
			$details = array();
			$details['filename'] = isset($args['filename'])?sanitize_text_field($args['filename']):'';
			$details['dir'] = isset($args['dir'])?sanitize_text_field($args['dir']):'';
			$details['url'] = isset($args['url'])?sanitize_url($args['url']):'';
			$details['size'] = isset($args['size'])?intval($args['size']):wpdbbkp_get_foldersize(ABSPATH);
			$details['type'] = isset($args['type'])?sanitize_text_field($args['type']):'';
			$details['logfile'] = isset($args['logfile'])?$args['logfile']:'';
			$details['logfileDir'] = isset($args['logfileDir'])?sanitize_text_field($args['logfileDir']):'';
			$details['logMessage'] = isset($args['logMessage'])?$args['logMessage']:'';

			$options = get_option('wp_db_backup_backups');
	        $Destination = "";
	        $logMessageAttachment = "";
	        $logMessage = $details['logMessage'];
	        if (!$options) {
	            $options = array();
	        }
			
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

					update_option( 'wp_db_backup_backups', $newoptions , false);
				}
			}

	        //Email
	      
	        if (get_option('wp_db_log') == 1) {
	            wpdbbkp_write_log($details['logfileDir'], $logMessage);
	        }   

			$options = get_option('wp_db_backup_backups');

	        $Destination.="Backblaze,";
	        $options[] = array(
	            'date' => time(),
	            'filename' => $details['filename'],
	            'url' => $details['url'],
	            'dir' => $details['dir'],
	            'log' => $details['logfile'],
	            'destination' => $Destination,
	            'type' => $details['type'],
	            'size' => $details['size']
	        );
	        update_option('wp_db_backup_backups', $options, false);
						
			$args2 = array($details['filename'], $details['dir'], $logMessage, $details['size'],$Destination,$details['logfile']);
			wpdbbkp_fullbackup_log($args2);
			wpdbbkp_backup_completed_notification($args2);
			update_option('wpdbbkp_dashboard_notify','create', false);
			update_option('wpdbbkp_backupcron_status','inactive', false);
			update_option('wpdbbkp_backupcron_progress',100, false);
			update_option('wpdbbkp_backupcron_current','Backup Completed', false);
			update_option('wpdbbkp_current_chunk_cnt',0, false);
			delete_transient('wpdbbkp_backup_status');
		}
	
}

function wpdbbkp_backup_completed_notification($args){
			$to = get_option( 'admin_email' ,'');
			if(!empty($to)){
				$to                     = sanitize_email( $to );
				$subject                = 'Full Website Backup (' . get_bloginfo( 'name' ) . ')';
				$filename               = esc_html($args[0]);
				$filesize                =  esc_html($args[3]);
				$site_url               = site_url();
				$log_message_attachment = '';
				$message                = '';

				require_once( WPDB_PATH.'includes/admin/Destination/Email/template-email-notification-bg.php' );
				$headers                = array( 'Content-Type: text/html; charset=UTF-8' );
				wp_mail( $to, $subject, $message, $headers );
			}
}

 function wpdbbkp_fullbackup_log(&$args) {
        
	$options = get_option('wp_db_backup_backups');
	$newoptions = array();

	if(!empty($options) && is_array($options)){

		foreach ($options as $option) {
			if ($option['filename'] == $args[0]) {
				$option['destination'] = wp_kses( $args[4] , wp_kses_allowed_html('post'));  
				$newoptions[] = $option;
			}else{
					$newoptions[] = $option;
			}
		} 

	}
                                
        update_option('wp_db_backup_backups', $newoptions, false);

        if (get_option('wp_db_log') == 1) {
            if(isset($args[4]) && !empty($args[4]))
            {
                if (is_writable($args[5]) || !file_exists($args[5])) {

                    if (!$handle = @fopen($args[5], 'a'))
                        return;

                    if (!fwrite($handle,  str_replace("<br>", "\n", $args[2])))
                        return;

                    fclose($handle);

                    return true;
                }
            }
        }
    }
function wpdbbkp_token_gen($length_of_string = 16)
{
    $str_result = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    return substr(str_shuffle($str_result),0, $length_of_string);
}

function backup_files_cron_with_resume(){
	
	$trasient_lock = get_transient( 'wpdbbkp_backup_status' );
	$status_lock = get_option( 'wpdbbkp_backupcron_status','inactive');
	$last_update 	= get_option('wpdbbkp_last_update',false);
	$last_backup_timestamp = get_option('wp_db_last_backup_timestamp' , 0);
	$start_time = time();
	if($status_lock!='active' || $trasient_lock=='active' || ((!$trasient_lock && $status_lock!='active')|| ($trasient_lock!='active' && $status_lock!='active'))){
		wp_die();
	}
	ignore_user_abort(true);
	set_time_limit(0);

	$root_path = ABSPATH;
	// exclude directories
	$exclude_dir = get_option('wp_db_backup_exclude_dir');
	$exclude_dir = explode('|', $exclude_dir);
    $iterator  = new RecursiveDirectoryIterator($root_path, RecursiveDirectoryIterator::SKIP_DOTS);
    $filter = new wpdbbkpExcludeFilter($iterator , $exclude_dir);
	$files = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);
	$total_files = 0;

	foreach($files as $key=>$file){
		$file_path = $file->getPathname();
		if ($file->isFile() && !wpdbbkp_is_file_processed($file_path,$file->getMTime())) {
			$total_files++;
		}
  	}

	
    $batch = [];
    $batch_limit = 10; // no file to process at one time

	
	$total_chunk 	= $total_files;
	$current_chunk  = get_option( 'wpdbbkp_current_chunk_cnt', 0);
	$current_args 	= get_option( 'wpdbbkp_current_chunk_args', false);
	$progress 		= 30;
	$single_chunk_percent = number_format(((1/$total_files)*64),2,".","");

	$current_args['total_chunk_cnt'] = $total_chunk;
	$chunk_count=$current_chunk+1;

	if($last_update)
	{
		if($trasient_lock=='active'){
			$diff = time()-intval($last_update);
			if($diff<600){
				wp_die();
			}
		}
	}
	
	if(!$total_chunk || !$current_args){
		wp_die();
	}

	$total_size = 0;
	update_option('wpdbbkp_backupcron_step','Backing up Files',false);
	update_option('wpdbbkp_backupcron_current','Scanning Directories' , false);
	foreach($files as $key=>$file){
			$file_path = $file->getPathname();
			$trasient_lock = get_transient( 'wpdbbkp_backup_status' );
			$status_lock = get_option( 'wpdbbkp_backupcron_status','inactive');
		if (($trasient_lock =='active' || $status_lock =='active' ) && $file->isFile() && !wpdbbkp_is_file_processed($file_path,$file->getMTime())) {
			$batch[] = ['file_path' => $file->getPathname(), 'file_name' => $file->getFilename()];
			$total_size += $file->getSize();
			$current_chunk++;
			$progress = $progress+$single_chunk_percent;
			update_option('wpdbbkp_backupcron_progress',intval($progress), false);
			update_option('wpdbbkp_current_chunk_cnt',$current_chunk, false);
			update_option('wpdbbkp_backupcron_current',$chunk_count.' of '.$total_chunk.' files done' , false);
			$return_params  = wpdbbkp_upload_batch_to_server($batch);
			$batch = [];
			if($chunk_count%10==0){
				sleep(1);
				set_transient('wpdbbkp_backup_status','active',600);
			}
			
			if(isset($return_params['success']) && $return_params['success']){
				wpdbbkp_add_processed_file($file_path);
				$chunk_count++;
			}
	  }
	  update_option('wpdbbkp_current_chunk_args',$current_args, false);
	 
	}

	if($current_chunk>=$total_chunk){

		$wpdbbkp_update_backup_info = ['filename' =>$current_args['fileName'],'dir' => '','url' => '','size' => wpdbbkp_get_foldersize(ABSPATH),'type' => get_option('wp_db_backup_backup_type')];
		$wpdbbkp_update_backup_info['logfile'] = $current_args['logFile'];
		$wpdbbkp_update_backup_info['logfileDir'] = $current_args['logFile'];
		$wpdbbkp_update_backup_info['logMessage'] = $current_args['logMessage'];
		wpdbbkp_cron_backup_event_process($wpdbbkp_update_backup_info);
		update_option('wp_db_last_backup_timestamp' , $start_time);
	}

	
}

/************************************************
 * Adding ajax call to stop manual cron backup
 ************************************************/

 add_action('wp_ajax_wpdbbkp_stop_cron_manual', 'wpdbbkp_stop_cron_manual');

 function wpdbbkp_stop_cron_manual(){
	 $wpdbbkp_cron_manual=['status'=>esc_html('fail'),'msg'=>esc_html__('Invalid Action','wpdbbkp')];
	 if(current_user_can('manage_options') && isset($_POST['wpdbbkp_admin_security_nonce']) && wp_verify_nonce($_POST['wpdbbkp_admin_security_nonce'], 'wpdbbkp_ajax_check_nonce')){
		update_option('wpdbbkp_backupcron_status','inactive',false);
		update_option('wpdbbkp_backup_status','inactive',false);
		update_option('wpdbbkp_backupcron_step','Initialization',false);
		update_option('wpdbbkp_backupcron_current','Fetching Config',false);
		update_option('wpdbbkp_current_chunk_cnt','0',false);
		update_option('wpdbbkp_backupcron_progress','0',false);
		set_transient('wpdbbkp_backup_status','inactive',600);

		$path_info = wp_upload_dir();
		$progressFile = $path_info['basedir'] . '/db-backup/db_progress.json';
		if(file_exists($progressFile)){
			wp_delete_file($progressFile);
		}
	 }
	 $wpdbbkp_cron_manual=['status'=>esc_html('success'),'msg'=>esc_html__('Cron Stopped','wpdbbkp')];
	 echo wp_json_encode($wpdbbkp_cron_manual);
	 wp_die();
	
 }

/*
 * Function to add backup files to db
*/
function wpdbbkp_add_processed_file($file_path) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpdbbkp_processed_files';
	$prep_query = $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE file_path = %s", $file_path);
    // Check if the file path already exists
    $exists = $wpdb->get_var($prep_query);

    if ($exists == 0) {
        // Insert the file path if it doesn't exist
        $wpdb->insert(
            $table_name,
            [
                'file_path' => $file_path,
				'status' => 'added'
            ],
            [
                '%s',

				'%s'
            ]
        );
    }else{
		// Update the processed_at timestamp if it exists
		

	 $op = $wpdb->update(
			$table_name,
			[
				'status' => 'updated'
			],
			[
				'file_path' => $file_path
			],
			[
				'%s'
			],
			[
				'%s'
			]
		);
	
	}
}

/*
 * Function to to check whether file is processed or not
*/
function wpdbbkp_is_file_processed($file_path = null, $timestamp = 0) {
    if (!$file_path) {
        return false;
    }
    if (!$timestamp) {
        $timestamp = current_time('timestamp');
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpdbbkp_processed_files';
    $query = $wpdb->prepare(
		"SELECT processed_at FROM $table_name WHERE file_path = %s",
        $file_path
    );

    $result = $wpdb->get_row($query);
	if($result && isset($result->processed_at)){
		$processed_at = strtotime($result->processed_at);
		if($timestamp >= $processed_at){
			return false;
		}else{
		  return true;
		}
	}
	
	return false;
}



class wpdbbkpExcludeFilter extends RecursiveFilterIterator {
    private $excluded;

    public function __construct(RecursiveIterator $iterator, array $excluded) {
        parent::__construct($iterator);
        $this->excluded = $excluded;
    }

	#[\ReturnTypeWillChange]
    public function accept() {
        $current = $this->current();
        $pathname = $current->getPathname();

        foreach ($this->excluded as $exclude) {
            if (fnmatch($exclude, $pathname)) {
                return false;
            }
        }

        return true;
    }

	#[\ReturnTypeWillChange]
    public function getChildren() {
        return new self($this->getInnerIterator()->getChildren(), $this->excluded);
    }
}

 function wpdbbkp_get_foldersize ($dir)
{
	$size = 0;

	foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $each) {
		$size += is_file($each) ? filesize($each) : wpdbbkp_get_foldersize($each);
	}

	return $size;
}

add_action( 'wp_ajax_backup_files_cron_with_resume', 'wpdbbkp_backup_files_cron_with_resume' );

function wpdbbkp_backup_files_cron_with_resume(){
	 // Ensure the function is defined
	 if ( function_exists( 'backup_files_cron_with_resume' ) ) {
        backup_files_cron_with_resume();
        wp_send_json_success( 'Backup process initiated.' );
    } else {
        wp_send_json_error( 'Function not found.' );
    }

    wp_die();
}