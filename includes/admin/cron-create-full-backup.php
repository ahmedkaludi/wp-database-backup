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
	if ( ! wp_next_scheduled( 'wpdbkup_event_fullbackup' )) {
		if(  true === isset( $options['enable_autobackups'] ) ){
			if(isset($options['autobackup_frequency']) && $options['autobackup_frequency'] != 'disabled' && isset($options['autobackup_type']) && ($options['autobackup_type'] == 'full' || $options['autobackup_type'] == 'files')){
				$timestamp = strtotime('today 22:59'); 
				if(isset($options['autobackup_full_time']) && !empty($options['autobackup_full_time'])){
					
					wp_schedule_event( $timestamp, 'thirty_minutes', 'wpdbkup_event_fullbackup' );
				}
				else{
					if($options['autobackup_frequency'] == 'weekly'){
						$timestamp = strtotime('next sunday 22:59');
					}elseif($options['autobackup_frequency'] == 'monthly'){
						$timestamp = strtotime('first day of next month 22:59');
					}
					wp_schedule_event( $timestamp, $options['autobackup_frequency'], 'wpdbkup_event_fullbackup' );
				}
			
			}
		}
	}else{
		if((true === isset( $options['enable_autobackups'] )) && isset($options['autobackup_type']) && (!in_array($options['autobackup_type'], array('full','files'))))
		{
			wp_clear_scheduled_hook('wpdbkup_event_fullbackup');
		}

	}


}
add_action( 'wpdbkup_event_fullbackup', 'wpdbbkp_cron_backup' );

add_action( 'wpdbbkp_backup_files_cron', 'wpdbbkp_backup_files_cron_with_resume' );

function wp_db_fullbackup_add_cron_schedules($schedules){
    if(!isset($schedules["ten_minutes"])){
        $schedules["ten_minutes"] = array(
            'interval' => 10*60,
            'display' => __('Once every 10 minutes', 'wpdbkup'));
    }
	if(!isset($schedules["thirty_minutes"])){
        $schedules["thirty_minutes"] = array(
            'interval' => 30*60,
            'display' => __('Once every 30 minutes', 'wpdbkup'));
    }
    return $schedules;
}

add_filter('cron_schedules','wp_db_fullbackup_add_cron_schedules');

if ( ! wp_next_scheduled( 'wpdbbkp_backup_files_cron' ) ) {
    if ( wpdbbkp_should_bg_cron_run() ) {
        wp_schedule_event( time(), 'ten_minutes', 'wpdbbkp_backup_files_cron' );
    }

}else{
	if(!wpdbbkp_should_bg_cron_run()){
		wp_clear_scheduled_hook('wpdbbkp_backup_files_cron');
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
	$wpdbbkp_fullbackup_stat=['status'=>'inactive'];
	if(current_user_can('manage_options') && isset($_POST['wpdbbkp_admin_security_nonce']) && wp_verify_nonce(wp_unslash($_POST['wpdbbkp_admin_security_nonce']), 'wpdbbkp_ajax_check_nonce')){ //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- using as nonce
	 $stat=get_option('wpdbbkp_backupcron_status',false);
	 if($stat=='active'){
		$wpdbbkp_fullbackup_stat['status']='active'; 
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
	$wpdbbkp_cron_manual=['status'=>'fail','msg'=>esc_html__('Invalid Action','wpdbbkp')];
	if(current_user_can('manage_options') && isset($_POST['wpdbbkp_admin_security_nonce']) && wp_verify_nonce(wp_unslash( $_POST['wpdbbkp_admin_security_nonce']), 'wpdbbkp_ajax_check_nonce')){ //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- using as nonce
	$wpdbbkp_cron_manual=['status'=>'success','msg'=>esc_html__('Cron Started','wpdbbkp')];
	$token=wpdbbkp_token_gen();
	update_option('wpdbbkp_api_token',$token, false);
	$rest_route = get_rest_url(null,'wpdbbkp/v1/cron_backup/'.$token);
	
	  $response = wp_remote_get(esc_url($rest_route),
			array(
				'timeout'     => 3,
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
	$wpdbbkp_progress=['status'=>'fail','msg'=>esc_html__('Unable to track progress, try reloading the page','wpdbbkp')];
	if(isset($_POST['wpdbbkp_admin_security_nonce']) && wp_verify_nonce(wp_unslash( $_POST['wpdbbkp_admin_security_nonce'] ), 'wpdbbkp_ajax_check_nonce') && current_user_can( 'manage_options' )){ //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- using as nonce
		$wpdbbkp_progress['backupcron_status']=esc_html(get_option('wpdbbkp_backupcron_status',false));
		$wpdbbkp_progress['backupcron_step']=esc_html(get_option('wpdbbkp_backupcron_step',false));
		$wpdbbkp_progress['backupcron_current']=esc_html(get_option('wpdbbkp_backupcron_current',false));
		$wpdbbkp_progress['backupcron_progress']=esc_html(get_option('wpdbbkp_backupcron_progress',false));
		$wpdbbkp_progress['status']='success';
		$wpdbbkp_progress['redirect_url'] = esc_url(site_url() . '/wp-admin/admin.php?page=wp-database-backup&notification=create&_wpnonce='.wp_create_nonce( 'wp-database-backup' ));
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
		set_time_limit(0); //phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- need to set time limit for cron
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
		$progress = $progress+4;
		update_option('wpdbbkp_backupcron_progress',intval($progress), false);
		$tables= wpdbbkp_cron_mysqldump($config);
		$count_tables = 1;
		if(isset($tables['tables'])){
			$count_tables = count($tables['tables']);
			$count_tables =  intval($count_tables);
		}
		$count_tables = ($count_tables==0)?1:$count_tables;
		$single_item_percent = number_format(((1/$count_tables)*30),2,".","");
		$options_backup  = get_option( 'wp_db_backup_backups' );
		$settings_backup = get_option( 'wp_db_backup_options' );
		$wp_db_save_settings_in_backup = get_option( 'wp_db_save_settings_in_backup',1);
		if($wp_db_save_settings_in_backup){
			delete_option( 'wp_db_backup_backups' );
			delete_option( 'wp_db_backup_options' );
		}
		if(!empty($tables['tables']) && is_array($tables['tables'])){
			foreach($tables['tables'] as $table){
				$wpdbbkp_backupcron_step = get_option( 'wpdbbkp_backupcron_step', false );
				if('Fetching Tables' != $wpdbbkp_backupcron_step){
					break;
				}
				$common_args['tableName']= $table;
				update_option('wpdbbkp_backupcron_current',$table, false);
				$progress = $progress+$single_item_percent;
				update_option('wpdbbkp_backupcron_progress',intval($progress), false);
				set_transient('wpdbbkp_backup_status','active',600);
				wpdbbkp_cron_create_mysql_backup($common_args);
				sleep(1);
			}
		}
		
	
		$options_backup = wpdbbkp_filter_unique_filenames($options_backup);
		update_option('wp_db_backup_backups',$options_backup, false);
		update_option('wp_db_backup_options',$settings_backup, false);
		update_option('wpdbbkp_backupcron_current','DB Backed Up', false);
	}

		$method_zip = wpdbbkp_cron_method_zip($common_args);
		if(isset($method_zip['status']) && $method_zip['status']=='success'){

			if($method_zip['ZipArchive']){

			update_option('wpdbbkp_backupcron_step','Creating Backup', false);
			update_option('wpdbbkp_backupcron_current','Starting File Backup', false);
			$backup_info=wpdbbkp_cron_get_backup_files($common_args);
			if(isset($backup_info['status']) && $backup_info['status']=='success' && isset($backup_info['chunk_count']) && $backup_info['chunk_count'] > 0){
				
				$total_chunk=$backup_info['chunk_count']+1;
				update_option('wpdbbkp_backupcron_current','0 of '.$total_chunk.' parts done', false );
				update_option('wpdbbkp_total_chunk_cnt',$total_chunk, false);
				update_option('wpdbbkp_current_chunk_cnt',0, false);
				update_option('wpdbbkp_current_chunk_args',$common_args, false);
				wpdbbkp_backup_files_cron_with_resume();
			}
		}
		else{
			update_option('wpdbbkp_backupcron_step','Creating Backup', false);
			update_option('wpdbbkp_backupcron_current','File Backup Started', false);
			wpdbbkp_cron_execute_file_backup_else($common_args);
			update_option('wpdbbkp_backupcron_current','File Backup Complete', false);
			update_option('wpdbbkp_backupcron_progress',100, false);
			wpdbbkp_cron_backup_event_process($method_zip['update_backup_info']);
		}
		
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
			wpdbbkp_write_file_contents($path_info['basedir'] . '/' . WPDB_BACKUPS_DIR . '/index.php','');
			wpdbbkp_write_file_contents($path_info['basedir'] . '/' . WPDB_BACKUPS_DIR . '/log/index.php','');
	        //added htaccess file 08-05-2015 for prevent directory listing
	        //Fixed Vulnerability 22-06-2016 for prevent direct download
	        $htaccess_content = "# Disable public access to this folder
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>

<IfModule !mod_authz_core.c>
    Deny from all
</IfModule>";
			wpdbbkp_write_file_contents($path_info['basedir']  . '/' . WPDB_BACKUPS_DIR . '/.htaccess',$htaccess_content);

	        $siteName = preg_replace('/[^\p{L}\p{M}]+/u', '_', get_bloginfo('name')); //added in v2.1 for Backup zip labeled with the site name(Help when backing up multiple sites).
	        $FileName = $siteName . '_' . gmdate("Y_m_d") . '_' . Time() .'_'. substr(md5(wp_rand(100,9999999)), 0, 9).'_wpall';
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
						//phpcs:ignore  -- get all tables name
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
if(!function_exists('wpdbbkp_cron_create_mysql_backup')){
	function wpdbbkp_cron_create_mysql_backup($args) {
		if (isset($args['logFile']) && !empty($args['logFile']) && 
			isset($args['tableName']) && !empty($args['tableName']) && 
			isset($args['FileName']) && !empty($args['FileName'])) {
	
			$logFile = sanitize_text_field($args['logFile']);
			$table = sanitize_text_field($args['tableName']);
			$FileName = sanitize_text_field($args['FileName']);
			$filename = $FileName . '.sql';
			$path_info = wp_upload_dir();
			$filepath  = $path_info['basedir'] . '/db-backup/' . $filename;
			global $wpdb;
	
			// Load WP Filesystem
			if (!function_exists('WP_Filesystem')) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}
	
			global $wp_filesystem;
			$wp_db_exclude_table = get_option('wp_db_exclude_table', array());
			$logMessage = "\n#--------------------------------------------------------\n";
			$logMessage .= "\n Database Table Backup";
			$logMessage .= "\n#--------------------------------------------------------\n";
			
			if (!empty($wp_db_exclude_table)) {
				$logMessage .= 'Exclude Table: ' . implode(', ', $wp_db_exclude_table) . "\n#--------------------------------------------------------\n";
			}
	
			if (empty($wp_db_exclude_table) || !in_array($table, $wp_db_exclude_table)) {
				$logMessage .= "\nBacking up table: $table";
	
				$output = '';
				$sub_limit = 500;
				$table = esc_sql($table);
				//phpcs:ignore  -- need to get all tables
				$check_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
				$check_count = intval($check_count);
	
				if ($check_count > $sub_limit) {
					$t_sub_queries = ceil($check_count / $sub_limit);
					for ($sub_i = 0; $sub_i < $t_sub_queries; $sub_i++) {
						$sub_offset = $sub_i * $sub_limit;
						// phpcs:ignore  -- need to get chunk of data for selected table
						$sub_result = $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$table}` LIMIT %d OFFSET %d", $sub_limit, $sub_offset), ARRAY_A);
						if ($sub_result) {
							$output .= wpdbbkp_create_sql_insert_statements($table, $sub_result);
						}
						sleep(1);
					}
				} else {
					// phpcs:ignore  -- need to get all data for selected table
					$result = $wpdb->get_results("SELECT * FROM `{$table}`", ARRAY_A);
					$output .= wpdbbkp_create_sql_insert_statements($table, $result);
				}
	

				// phpcs:ignore  -- Get table structure for backup
				$row2 = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
				$output = "\n\n" . $row2[1] . ";\n\n" . $output;
	
				// Write to file in chunks
				$chunk_size = 1024 * 1024; // 1MB chunks
				$total_size = strlen($output);
				$offset = 0;
				$use_php_methods = $total_size > 10 * $chunk_size; // Use PHP methods for large files
	
				$append_content = function($new_content) use ($filepath, $wp_filesystem, $use_php_methods) {
					if ($use_php_methods) {
						//phpcs:ignore  -- Use PHP methods for large files
						file_put_contents($filepath, $new_content, FILE_APPEND);
					} else {
						if (!$wp_filesystem->exists($filepath)) {
							$wp_filesystem->put_contents($filepath, $new_content, FS_CHMOD_FILE);
						} else {
							$current_contents = $wp_filesystem->get_contents($filepath);
							if ($current_contents === false) {
								return false;
							}
							$updated_contents = $current_contents . $new_content;
							$wp_filesystem->put_contents($filepath, $updated_contents, FS_CHMOD_FILE);
						}
					}
				};
	
				while ($offset < $total_size) {
					$chunk = substr($output, $offset, $chunk_size);
					$append_content($chunk);
					$offset += $chunk_size;
					sleep(1);
				}
	
				$logMessage .= "\nBackup completed for table: {$table}";
			}
	
			$wpdb->flush();
			$logMessage .= "\n#--------------------------------------------------------\n";
	
			if (get_option('wp_db_log') == 1) {
				wpdbbkp_write_log($logFile, $logMessage);
				$upload_path['logfile'] = $logFile;
			} else {
				$upload_path['logfile'] = "";
			}
	
			$logMessage = "\n# Database dump method: PHP\n";
			if (get_option('wp_db_log') == 1) {
				wpdbbkp_write_log($logFile, $logMessage);
			}
		}
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
	
	
}

 /***********************
 * Funtion to write log
 ************************/
if(!function_exists('wpdbbkp_write_log')){
	function wpdbbkp_write_log($logFile, $logMessage) {
	    // Actually write the log file
	    if (wpdbbkp_is_writable($logFile) || !wpdbbkp_file_exists($logFile)) {
			wpdbbkp_write_file_contents( $logFile, $logMessage, true );
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
					//phpcs:ignore  -- check if file exists
					if(file_exists( $path_info['basedir'] . '/db-backup/' . $filename )){
						$zip->addFile($path_info['basedir'] . '/db-backup/' . $filename, $filename);
					}
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
					if(!empty($wp_backup_files) && is_array($wp_backup_files) || is_object($wp_backup_files)){
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

 /**********************************************
 * Alternative method for adding  files to ZIP
 ***********************************************/
if(!function_exists('wpdbbkp_cron_execute_file_backup_else')){
	function wpdbbkp_cron_execute_file_backup_else($args) {
		$return_data_array = array();
		$return_data_array['status'] = 'failure';
		require_once WPDB_PATH.'includes/admin/class-wpdb-admin.php';
		$wpdbbkp_admin_class_obj = new Wpdb_Admin();
			if((isset($args['FileName']) && !empty($args['FileName'])) && (isset($args['logFile']) && !empty($args['logFile']))){
				$FileName = sanitize_text_field($args['FileName']);
				$logFile = sanitize_text_field($args['logFile']);
				$WPDBFileName = $FileName . '.zip';
				$path_info = wp_upload_dir();
				$logMessage = '';

		        $logMessage .= "\n Zip method: pclzip \n";
		        // set maximum execution time go non stop                        
		        // Include the PclZip library
		        require_once( WPDB_PATH.'includes/admin/lib/class-pclzip.php' );

		        // Set the arhive filename
		        $arcname = $path_info['basedir'] . '/db-backup/' . $WPDBFileName;
		        $archive = new PclZip($arcname);

		        $wp_all_backup_exclude_dir = get_option('wp_db_backup_exclude_dir');
		        if (empty($wp_all_backup_exclude_dir)) {
		            $excludes = WPDB_BACKUPS_DIR;
		        } else {
		            $excludes = WPDB_BACKUPS_DIR . '|' . $wp_all_backup_exclude_dir;
		        }
		        $logMessage.="\n Exclude Folders and Files : $excludes";

		        // Set the dir to archive
		        if (get_option('wp_db_backup_backup_type') == 'Database') {
		            $filename = $FileName . '.sql';
		            $v_dir = $path_info['basedir'] . '/db-backup/' . $filename;

		            $v_remove = $wpdbbkp_admin_class_obj->wp_db_backup_wp_config_path();

		            // Create the archive
		            $v_list = $archive->create($v_dir, PCLZIP_OPT_REMOVE_PATH, $v_remove);
		            if ($v_list == 0) {
						// if debug is enabled in WordPress
						if (defined('WP_DEBUG') && WP_DEBUG) {
		                	error_log("ERROR : '" . $archive->errorInfo(true) . "'"); //phpcs:ignore -- error will be logged only in debug mode
						}
		            }
		        } else {
		            $v_dir = $wpdbbkp_admin_class_obj->wp_db_backup_wp_config_path();
		            $v_remove = $v_dir;
		            // Create the archive
					update_option('wpdbbkp_backupcron_current','Backing up files', false);
		            $v_list = $archive->create($v_dir, PCLZIP_OPT_REMOVE_PATH, $v_remove);
		            if ($v_list == 0) {
						if (defined('WP_DEBUG') && WP_DEBUG) {
		               	 error_log("Error : " . $archive->errorInfo(true)); //phpcs:ignore -- error will be logged only in debug mode
						}
		            }
		        }
		        $update_backup_info = $wpdbbkp_admin_class_obj->wpdbbkp_update_backup_info($FileName, $logFile, $logMessage);
				$return_data_array['status'] = 'success';
				$return_data_array['update_backup_info'] = $update_backup_info;
		
	    }
	    return $return_data_array;
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
			$details['size'] = isset($args['size'])?intval($args['size']):'';
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
						if ( isset($options[ $index ]['dir']) && file_exists( $options[ $index ]['dir'] ) ) {
							wp_delete_file( $options[ $index ]['dir'] );
						}
						$file_sql = explode( '.', $options[ $index ]['dir'] );
						if ( isset($file_sql[0]) && file_exists( $file_sql[0] . '.sql' ) ) {
							wp_delete_file( $file_sql[0] . '.sql' );
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
	      
	        if (get_option('wp_db_log') == 1 && !empty($details['logfileDir'])) {
	            wpdbbkp_write_log($details['logfileDir'], $logMessage);
	        }   

			$options = get_option('wp_db_backup_backups');

	        $Destination.="Local, ";
			$path_info = wp_upload_dir();
			$filesize = @filesize($path_info['basedir'] . '/' . WPDB_BACKUPS_DIR . '/' . $details['filename']);
	        $options[] = array(
	            'date' => time(),
	            'filename' => $details['filename'],
	            'url' => $details['url'],
	            'dir' => $details['dir'],
	            'log' => $details['logfile'],
	            'destination' => $Destination,
	            'type' => $details['type'],
	            'size' => $filesize
	        );
	        update_option('wp_db_backup_backups', $options, false);
						
			$args2 = array($details['filename'], $details['dir'], $logMessage, $filesize,$Destination,$details['logfile']);
			
			WPDBBackupLocal::wp_db_backup_completed($args2);
			WPDBBackupFTP::wp_db_backup_completed($args2);
			WPDBBackupEmail::wp_db_backup_completed($args2);
			WPDBBackupGoogle::wp_db_backup_completed($args2);
			WPDBBackupDropbox::wp_db_backup_completed($args2);
			WPDatabaseBackupS3::wp_db_backup_completed($args2);
			WPDBBackupSFTP::wp_db_backup_completed($args2);
			WPDatabaseBackupBB::wp_db_backup_completed($args2);
			WPDatabaseBackupCD::wp_db_backup_completed($args2);
			wpdbbkp_fullbackup_log($args2);
			wpdbbkp_backup_completed_notification($args2);
			update_option('wpdbbkp_dashboard_notify','create', false);
			update_option('wpdbbkp_export_notify','create', false);
			update_option('wpdbbkp_backupcron_status','inactive', false);
			update_option('wpdbbkp_backupcron_progress',100, false);
			update_option('wpdbbkp_backupcron_current','Backup Completed', false);
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

/**
 * Log full backup details.
 *
 * @param array $args Arguments for logging the backup.
 *
 * @return bool True if log is written successfully, false otherwise.
 */
function wpdbbkp_fullbackup_log( &$args ) {
    global $wp_filesystem;

    // Initialize the WordPress filesystem if it hasn't been initialized yet.
    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    WP_Filesystem();

    $options     = get_option( 'wp_db_backup_backups' );
    $new_options = array();

    if ( ! empty( $options ) && is_array( $options ) ) {
        foreach ( $options as $option ) {
            if ( ! is_array( $option ) ) {
                continue;
            }
            if ( $option['filename'] === $args[0] ) {
                $option['destination'] = wp_kses_post( $args[4] );
            }
            $new_options[] = $option;
        }
    }

	$new_options = wpdbbkp_filter_unique_filenames( $new_options );


    update_option( 'wp_db_backup_backups', $new_options, false );

    if ( get_option( 'wp_db_log' ) === '1' ) {
        if ( ! empty( $args[5] ) && ! empty( $args[2] ) ) {
            if ( $wp_filesystem->is_writable( $args[5] ) || ! $wp_filesystem->exists( $args[5] ) ) {
                if ( ! $wp_filesystem->put_contents( $args[5], str_replace( '<br>', "\n", $args[2] ), FS_CHMOD_FILE ) ) {
                    return false;
                }
                return true;
            }
        }
    }

    return false;
}

function wpdbbkp_token_gen($length_of_string = 16)
{
    $str_result = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    return substr(str_shuffle($str_result),0, $length_of_string);
}

function wpdbbkp_backup_files_cron_with_resume(){

	$trasient_lock = get_transient( 'wpdbbkp_backup_status','inactive');
	$status_lock = get_option( 'wpdbbkp_backupcron_status','inactive');
	if(!wpdbbkp_should_bg_cron_run() && ($status_lock == 'inactive' || $trasient_lock )){
		return false;
	}
	ignore_user_abort(true);
	set_time_limit(0); //phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- set_time_limit is required here to process the backup
	
	$total_chunk 	= get_option( 'wpdbbkp_total_chunk_cnt',false );
	$current_chunk  = get_option( 'wpdbbkp_current_chunk_cnt',0 );
	$current_args 	= get_option( 'wpdbbkp_current_chunk_args',false );
	$progress 		= get_option('wpdbbkp_backupcron_progress',30);
	
	if($total_chunk){
		$total_chunk =  intval($total_chunk);
	}else{
		$total_chunk = 1;
	}
	$total_chunk = ($total_chunk==0)?1:$total_chunk;
	$single_chunk_percent = number_format(((1/$total_chunk)*64),2,".","");
	$current_args['total_chunk_cnt'] = $total_chunk;
	$chunk_count=$current_chunk+1;
	for($i=$current_chunk;$i<$total_chunk;$i++){
		$status_lock = get_option( 'wpdbbkp_backupcron_status','inactive');
		if($status_lock == 'inactive'){
			break;
		}
		$current_args['chunk_count']=$chunk_count;
		wpdbbkp_cron_files_backup($current_args);
		update_option('wpdbbkp_backupcron_current',$chunk_count.' of '.$total_chunk.' parts done' , false);
		$progress = $progress+$single_chunk_percent;
		update_option('wpdbbkp_backupcron_progress',intval($progress), false);
		update_option('wpdbbkp_last_update',time(), false);
		update_option('wpdbbkp_current_chunk_cnt',$chunk_count, false);
		update_option('wpdbbkp_current_chunk_args',$current_args, false);
		$chunk_count++;
		sleep(1);
	}
	if($chunk_count==($total_chunk+1)){
		$wpdbbkp_admin_class_obj = new Wpdb_Admin();
		$wpdbbkp_update_backup_info =$wpdbbkp_admin_class_obj->wpdbbkp_update_backup_info($current_args['FileName'],$current_args['logFile'],'');
		wpdbbkp_cron_backup_event_process($wpdbbkp_update_backup_info);
	}
}

/************************************************
 * Adding ajax call to stop manual cron backup
 ************************************************/

 add_action('wp_ajax_wpdbbkp_stop_cron_manual', 'wpdbbkp_stop_cron_manual');

 function wpdbbkp_stop_cron_manual(){
	 $wpdbbkp_cron_manual=['status'=>'fail','msg'=>esc_html__('Invalid Action','wpdbbkp')];
	 if(current_user_can('manage_options') && isset($_POST['wpdbbkp_admin_security_nonce']) && wp_verify_nonce(wp_unslash( $_POST['wpdbbkp_admin_security_nonce'] ) , 'wpdbbkp_ajax_check_nonce')){ //phpcs:ignore -- nonce verification
		update_option('wpdbbkp_backupcron_status','inactive',false);
		update_option('wpdbbkp_backupcron_step','Initialization',false);
		update_option('wpdbbkp_backupcron_current','Fetching Config',false);
		update_option('wpdbbkp_backupcron_progress',0, false);
		update_option('wpdbbkp_total_chunk_cnt',0, false);
		update_option('wpdbbkp_current_chunk_cnt',0, false);
		update_option('wpdbbkp_current_chunk_args',[], false);
		delete_transient('wpdbbkp_backup_status');
		$wpdbbkp_cron_manual=['status'=>'success','msg'=>esc_html__('Cron Stopped','wpdbbkp')];
	 }
	
	 echo wp_json_encode($wpdbbkp_cron_manual);
	 wp_die();
	
 }

 function wpdbbkp_should_bg_cron_run(){
	$trasient_lock 	= get_transient( 'wpdbbkp_backup_status' );
	$status_lock 	= get_option( 'wpdbbkp_backupcron_status','inactive');
	$total_chunks 	= get_option( 'wpdbbkp_total_chunk_cnt',0 );
	$current_chunk_args 	= get_option( 'wpdbbkp_current_chunk_args',false );
	$last_update 	= get_option('wpdbbkp_last_update',false);

	// Check if the backup is already running
    $should_run_backup = $status_lock == 'active';
	
   // Dont run cron if total chunks , current chunk args are not set
    if ( !($total_chunks > 0) || !$current_chunk_args ) {
        $should_run_backup = false;
    }

	return $should_run_backup;
 }