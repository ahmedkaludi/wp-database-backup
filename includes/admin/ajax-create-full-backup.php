<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

add_action('wp_ajax_wpdbbkp_ajax_wp_config_path', 'wpdbbkp_ajax_wp_config_path');
add_action('wp_ajax_nopriv_wpdbbkp_ajax_wp_config_path', 'wpdbbkp_ajax_wp_config_path');
if(!function_exists('wpdbbkp_ajax_wp_config_path')){
	function wpdbbkp_ajax_wp_config_path()
	{
		if(isset($_POST['wpdbbkp_admin_security_nonce']) && wp_verify_nonce($_POST['wpdbbkp_admin_security_nonce'], 'wpdbbkp_ajax_check_nonce')){
			$wpdbbkp_admin_class_obj = new Wpdb_Admin();
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
	        $siteName = preg_replace('/[^A-Za-z0-9\_]/', '_', get_bloginfo('name')); //added in v2.1 for Backup zip labeled with the site name(Help when backing up multiple sites).
	        $FileName = $siteName . '_' . Date("Y_m_d") . '_' . Time() .'_'. substr(md5(AUTH_KEY), 0, 7).'_wpall';
	        $WPDBFileName = $FileName . '.zip';
	        $wp_all_backup_type = get_option('wp_db_backup_backup_type');
	        $logFile = $path_info['basedir'] . '/' . WPDB_BACKUPS_DIR . '/log/' . $FileName . '.txt';

	        $logMessage = "\n#--------------------------------------------------------\n";
	        $logMessage = "\n NOTICE: Do NOT post to public sites or forums ";
	        $logMessage .= "\n#--------------------------------------------------------\n";
	        $logMessage .= "\n Backup File Name : " . $WPDBFileName;
	        $logMessage .= "\n Backup File Path : " . $path_info['baseurl'] . '/' . WPDB_BACKUPS_DIR . '/' . $WPDBFileName;
	        $logMessage .= "\n Backup Type : " . $wp_all_backup_type;
	        $logMessage .= "\n #--------------------------------------------------------\n";

	        //Start Number of backups to store on this server 
	        $options = (array)get_option('wp_db_backup_backups');
	        $newoptions = array();
	        $number_of_existing_backups = count($options);
	       // error_log("number_of_existing_backups");
	      //  error_log($number_of_existing_backups);
	        $number_of_backups_from_user = get_option('wp_all_backup_max_backups');
	      //  error_log("number_of_backups_from_user");
	       // error_log($number_of_backups_from_user);
	        error_log("Delete old Backup:");

	        if (!empty($number_of_backups_from_user)) {
	            if (!($number_of_existing_backups < $number_of_backups_from_user)) {
	                $diff = $number_of_existing_backups - $number_of_backups_from_user;
	                for ($i = 0; $i <= $diff; $i++) {
	                    $index = $i;
	                    error_log($options[$index]['dir']);
	                    @unlink($options[$index]['dir']);
	                }
	                for ($i = ($diff + 1); $i < $number_of_existing_backups; $i++) {
	                  //  error_log($i);
	                    $index = $i;

	                    $newoptions[] = $options[$index];
	                }

	                update_option('wp_db_backup_backups', $newoptions);
	            }
	        }
	        $return_data['files_added'] = $files_added;
	        $return_data['siteName'] = $siteName;
	        $return_data['FileName'] = $FileName;
	        $return_data['logFile'] = $logFile;
	        $return_data['logMessage'] = $logMessage;
	        echo json_encode($return_data);
		}
		wp_die();
	}
}


add_action('wp_ajax_wpdbbkp_ajax_mysqldump', 'wpdbbkp_ajax_mysqldump');
add_action('wp_ajax_nopriv_wpdbbkp_ajax_mysqldump', 'wpdbbkp_ajax_mysqldump');

if(!function_exists('wpdbbkp_ajax_mysqldump')){
	function wpdbbkp_ajax_mysqldump()
	{
		$all_db_tables = array();
		$all_db_tables['status'] = 'failure';
		$wpdbbkp_admin_class_obj = new Wpdb_Admin();
		if(isset($_POST['wpdbbkp_admin_security_nonce']) && wp_verify_nonce($_POST['wpdbbkp_admin_security_nonce'], 'wpdbbkp_ajax_check_nonce')){
			if((isset($_POST['FileName']) && !empty($_POST['FileName'])) && (isset($_POST['logFile']) && !empty($_POST['logFile']))){
				$FileName = sanitize_text_field($_POST['FileName']);
				$logFile = sanitize_text_field($_POST['logFile']);
				$path_info = wp_upload_dir();
				if (get_option('wp_db_backup_backup_type') == 'Database' || get_option('wp_db_backup_backup_type') == 'complete') {

		            $filename = $FileName . '.sql';
		            /* Begin : Generate SQL DUMP using cmd 06-03-2016 */
		            $mySqlDump = 0;

		            if ($wpdbbkp_admin_class_obj->get_mysqldump_command_path()) {
		                if (!$wpdbbkp_admin_class_obj->mysqldump($path_info['basedir'] . '/db-backup/' . $filename)) {
		                    $mySqlDump = 1;
		                } else {
		                    $logMessage = "\n# Database dump method: mysqldump";
		                }
		            } else {
		                $mySqlDump = 1;
		            }
		            $mySqlDump = 1;
		            if ($mySqlDump == 1) {
		            	 global $wpdb;
		                $tables = $wpdb->get_col('SHOW TABLES');
		                $all_db_tables['status'] = 'success';
		                $all_db_tables['tables'] = $tables;
		            }
		        }
			}
		}
		echo json_encode($all_db_tables);
		wp_die();
	}
}

add_action('wp_ajax_wpdbbkp_ajax_create_mysql_backup', 'wpdbbkp_ajax_create_mysql_backup');
add_action('wp_ajax_nopriv_wpdbbkp_ajax_create_mysql_backup', 'wpdbbkp_ajax_create_mysql_backup');
if(!function_exists('wpdbbkp_ajax_create_mysql_backup')){
	function wpdbbkp_ajax_create_mysql_backup()
	{
		if(isset($_POST['wpdbbkp_admin_security_nonce']) && wp_verify_nonce($_POST['wpdbbkp_admin_security_nonce'], 'wpdbbkp_ajax_check_nonce')){
			if((isset($_POST['logFile']) && !empty($_POST['logFile'])) && (isset($_POST['tableName']) && !empty($_POST['tableName'])) && (isset($_POST['FileName']) && !empty($_POST['FileName']))){
				$logFile = sanitize_text_field($_POST['logFile']);
				$table = sanitize_text_field($_POST['tableName']);
				$FileName = sanitize_text_field($_POST['FileName']);
				$filename = $FileName . '.sql';
				$path_info = wp_upload_dir();

				$wpdbbkp_admin_class_obj = new Wpdb_Admin();
				$handle = fopen($path_info['basedir'] . '/db-backup/' . $filename, 'a+');

				global $wpdb;
		        /* BEGIN : Prevent saving backup plugin settings in the database dump */
		        $options_backup = get_option('wp_db_backup_backups');
		        $settings_backup = get_option('wp_all_backup_options');
		        delete_option('wp_all_backup_options');
		        delete_option('wp_db_backup_backups');
		        $wp_db_exclude_table = array();
		        $wp_db_exclude_table = get_option('wp_db_exclude_table');
		        $logMessage = "\n#--------------------------------------------------------\n";
		        $logMessage .= "\n Database Table Backup";
		        $logMessage .= "\n#--------------------------------------------------------\n";
		        if (!empty($wp_db_exclude_table)) {
		            $logMessage.= 'Exclude Table : ' . implode(', ', $wp_db_exclude_table);
		            $logMessage .= "\n#--------------------------------------------------------\n";
		        }
		        /* END : Prevent saving backup plugin settings in the database dump */
		        $tables = $wpdb->get_col('SHOW TABLES');
		        $output = '';
		        if (empty($wp_db_exclude_table) || (!(in_array($table, $wp_db_exclude_table)))) {
		            $logMessage .= "\n $table";
		            $result = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_N);
		            $row2 = $wpdb->get_row('SHOW CREATE TABLE ' . $table, ARRAY_N);
		            $output .= "\n\n" . $row2[1] . ";\n\n";
		            $logMessage .= "(" . count($result) . ")";
		            for ($i = 0; $i < count($result); $i++) {
		                $row = $result[$i];
		                $output .= 'INSERT INTO ' . $table . ' VALUES(';
		                for ($j = 0; $j < count($result[0]); $j++) {
		                    $row[$j] = $wpdb->_real_escape($row[$j]);
		                    $output .= (isset($row[$j])) ? '"' . $row[$j] . '"' : '""';
		                    if ($j < (count($result[0]) - 1)) {
		                        $output .= ',';
		                    }
		                }
		                $output .= ");\n";
		            }
		            $output .= "\n";
		        }
		        $wpdb->flush();
		        $logMessage .= "\n#--------------------------------------------------------\n";
		        /* BEGIN : Prevent saving backup plugin settings in the database dump */
		        add_option('wp_db_backup_backups', $options_backup);
		        add_option('wp_all_backup_options', $settings_backup);
		        /* END : Prevent saving backup plugin settings in the database dump */
		        if (get_option('wp_db_log') == 1) {
		            wpdbbkp_write_log($logFile, $logMessage);
		            $upload_path['logfile'] = $logFile;
		        } else {
		            $upload_path['logfile'] = "";
		        }
		        fwrite($handle, $output);
				fclose($handle);

				$logMessage = "\n# Database dump method: PHP";
				if (get_option('wp_db_log') == 1) {
	                //$logMessage.="\n# Exclude Table : " . @implode(', ', get_option('wp_db_exclude_table'));
	                wpdbbkp_write_log($logFile, $logMessage);
	            }
			}
		}
		echo 'success';
		wp_die();
	}
}

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

add_action('wp_ajax_wpdbbkp_ajax_after_mysql_backup', 'wpdbbkp_ajax_after_mysql_backup');
add_action('wp_ajax_nopriv_wpdbbkp_ajax_after_mysql_backup', 'wpdbbkp_ajax_after_mysql_backup');
if(!function_exists('wpdbbkp_ajax_after_mysql_backup')){
	function wpdbbkp_ajax_after_mysql_backup() {
		$method_zip_array = array();
		$method_zip_array['status'] = 'failure';
		if(isset($_POST['wpdbbkp_admin_security_nonce']) && wp_verify_nonce($_POST['wpdbbkp_admin_security_nonce'], 'wpdbbkp_ajax_check_nonce')){
			if((isset($_POST['FileName']) && !empty($_POST['FileName'])) && (isset($_POST['logFile']) && !empty($_POST['logFile']))){
				$FileName = sanitize_text_field($_POST['FileName']);
				$logFile = sanitize_text_field($_POST['logFile']);
				$path_info = wp_upload_dir();
				$WPDBFileName = $FileName . '.zip';

				$wpdbbkp_admin_class_obj = new Wpdb_Admin();
				if (get_option('wp_db_backup_backup_type') == 'complete') {
		            // $handle = fopen($wpdbbkp_admin_class_obj->wp_db_backup_wp_config_path() . '/wp_installer.php', 'w+');
		            // fwrite($handle, $wp_all_obj->wp_all_backup_create_installer($FileName));
		            // fclose($handle);
		        }
		        //End Number of backups to store on this server 
		        // $methodZip = 0;
		        // if ($wpdbbkp_admin_class_obj->get_zip_command_path() && (get_option('wp_db_backup_backup_type') == 'File' || get_option('wp_db_backup_backup_type') == 'complete')) {
		        //     if (!$wpdbbkp_admin_class_obj->zip($path_info['basedir'] . '/' . WPDB_BACKUPS_DIR . '/' . $WPDBFileName)) {
		        //         $methodZip = 1;
		        //     }
		        // } else {
		        //     $methodZip = 1;
		        // }
		        $methodZip = 1;
		        $method_zip_array['status'] = 'success';
		        $method_zip_array['methodZip'] = $methodZip;
		        $method_zip_array['ZipArchive'] = class_exists('ZipArchive');
			}
		}
		echo json_encode($method_zip_array);
		wp_die();
	}
}

add_action('wp_ajax_wpdbbkp_ajax_method_zip', 'wpdbbkp_ajax_method_zip');
add_action('wp_ajax_nopriv_wpdbbkp_ajax_method_zip', 'wpdbbkp_ajax_method_zip');
if(!function_exists('wpdbbkp_ajax_method_zip')){
	function wpdbbkp_ajax_method_zip() {
		$method_zip_array = array();
		$method_zip_array['status'] = 'failure';
		if(isset($_POST['wpdbbkp_admin_security_nonce']) && wp_verify_nonce($_POST['wpdbbkp_admin_security_nonce'], 'wpdbbkp_ajax_check_nonce')){
			if((isset($_POST['FileName']) && !empty($_POST['FileName'])) && (isset($_POST['logFile']) && !empty($_POST['logFile'])) && (isset($_POST['logMessage']) && !empty($_POST['logMessage']))){
				$FileName = sanitize_text_field($_POST['FileName']);
				$logFile = sanitize_text_field($_POST['logFile']);
				$log_msg = sanitize_text_field($_POST['logMessage']);
				//  error_log("Zip method: Zip cmd");
            	$log_msg.="\n Exclude Folders and Files : " . get_option('wp_db_backup_exclude_dir');
				$method_zip_array['logMessage'] = $log_msg;
				$wpdbbkp_admin_class_obj = new Wpdb_Admin();
				$update_backup_info = $wpdbbkp_admin_class_obj->wpdbbkp_update_backup_info($FileName, $logFile, $log_msg);
				$method_zip_array['update_backup_info'] = $update_backup_info;
				$method_zip_array['status'] = 'success';
				$method_zip_array['logMessage'] = $log_msg;
			}
		}
		echo json_encode($method_zip_array);
		wp_die();
	}
}

add_action('wp_ajax_wpdbbkp_ajax_get_backup_files', 'wpdbbkp_ajax_get_backup_files');
add_action('wp_ajax_nopriv_wpdbbkp_ajax_get_backup_files', 'wpdbbkp_ajax_get_backup_files');
if(!function_exists('wpdbbkp_ajax_get_backup_files')){
	function wpdbbkp_ajax_get_backup_files() {
		$backup_files_array = array(); $file_iterator_count = 0;
		$backup_files_array['status'] = 'failure';
		$path_info = wp_upload_dir();
		$wpdbbkp_admin_class_obj = new Wpdb_Admin();
		if(isset($_POST['wpdbbkp_admin_security_nonce']) && wp_verify_nonce($_POST['wpdbbkp_admin_security_nonce'], 'wpdbbkp_ajax_check_nonce')){
			if((isset($_POST['FileName']) && !empty($_POST['FileName'])) && (isset($_POST['logMessage']) && !empty($_POST['logMessage'])) && (isset($_POST['logFile']) && !empty($_POST['logFile']))){
				$FileName = sanitize_text_field($_POST['FileName']);
				$WPDBFileName = $FileName . '.zip';
				$logMessage = sanitize_text_field($_POST['logMessage']);
				$logFile = sanitize_text_field($_POST['logFile']);
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

	            // update_option('wp_db_backup_log_message', sanitize_text_field($logMessage));

	            $backup_files_array['status'] = 'success';
	            $backup_files_array['chunk_count'] = $file_iterator_count;

			    if (get_option('wp_db_backup_backup_type') == 'Database'){
				    $update_backup_info = $wpdbbkp_admin_class_obj->wpdbbkp_update_backup_info($FileName, $logFile, $logMessage);
					$backup_files_array['update_backup_info'] = $update_backup_info;
				}
			}
		}
		echo json_encode($backup_files_array);
		wp_die();
	}
}

add_action('wp_ajax_wpdbbkp_ajax_files_backup', 'wpdbbkp_ajax_files_backup');
add_action('wp_ajax_nopriv_wpdbbkp_ajax_files_backup', 'wpdbbkp_ajax_files_backup');
if(!function_exists('wpdbbkp_ajax_files_backup')){
	function wpdbbkp_ajax_files_backup() {
		$file_backup_array = array();
		$file_backup_array['status'] = 'failure';
		$wpdbbkp_admin_class_obj = new Wpdb_Admin();
		if(isset($_POST['wpdbbkp_admin_security_nonce']) && wp_verify_nonce($_POST['wpdbbkp_admin_security_nonce'], 'wpdbbkp_ajax_check_nonce')){
			if((isset($_POST['FileName']) && !empty($_POST['FileName'])) && (isset($_POST['logFile']) && !empty($_POST['logFile'])) && (isset($_POST['chunk_count'])) && (isset($_POST['files_added']))){
				$FileName = sanitize_text_field($_POST['FileName']);
				$logFile = sanitize_text_field($_POST['logFile']);
				// $logMessage = sanitize_text_field(get_option('wp_db_backup_log_message'));
				$logMessage = '';
				$files_added = intval($_POST['files_added']);
				$bkp_chunk_cnt = intval($_POST['chunk_count']);
				$WPDBFileName = $FileName . '.zip';
				$path_info = wp_upload_dir();
				$total_chunk_cnt = intval($_POST['total_chunk_cnt']);

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
	                // $wbf_array = array();
	                // $wbf_array_chunk = array();
	                // $wbf_array = iterator_to_array($wp_backup_files, true);
	                // $wbf_array_chunk = array_chunk($wbf_array, 2000);
	                // if(isset($wbf_array_chunk[$bkp_chunk_cnt - 1]) && !empty($wbf_array_chunk[$bkp_chunk_cnt - 1])){
	                // 	$file_object = $wbf_array_chunk[$bkp_chunk_cnt - 1];
	                // }

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

				                    // if (++$files_added % 500 === 0){
				                    //     if (!$zip->close() || !$zip->open($path_info['basedir'] . '/' . WPDB_BACKUPS_DIR . '/' . $WPDBFileName, ZIPARCHIVE::CREATE)){
				                    //         // return;
				                    //     }
				                    // }
				                }
			                }
			            }
		            }
	            }
	            $zip->close();
				// update_option('wp_db_backup_log_message', $logMessage);
	            if($total_chunk_cnt == $bkp_chunk_cnt){
					$update_backup_info = $wpdbbkp_admin_class_obj->wpdbbkp_update_backup_info($FileName, $logFile, $logMessage);
					$file_backup_array['update_backup_info'] = $update_backup_info;
				}
				$file_backup_array['status'] = 'success';
				$file_backup_array['files_added'] = $files_added;
			}
		}
		echo json_encode($file_backup_array);
		wp_die();	
	}
}

add_action('wp_ajax_wpdbbkp_ajax_execute_file_backup_else', 'wpdbbkp_ajax_execute_file_backup_else');
add_action('wp_ajax_nopriv_wpdbbkp_ajax_execute_file_backup_else', 'wpdbbkp_ajax_execute_file_backup_else');
if(!function_exists('wpdbbkp_ajax_execute_file_backup_else')){
	function wpdbbkp_ajax_execute_file_backup_else() {
		$return_data_array = array();
		$return_data_array['status'] = 'failure';
		$wpdbbkp_admin_class_obj = new Wpdb_Admin();
		if(isset($_POST['wpdbbkp_admin_security_nonce']) && wp_verify_nonce($_POST['wpdbbkp_admin_security_nonce'], 'wpdbbkp_ajax_check_nonce')){
			if((isset($_POST['FileName']) && !empty($_POST['FileName'])) && (isset($_POST['logFile']) && !empty($_POST['logFile']))){
				$FileName = sanitize_text_field($_POST['FileName']);
				$logFile = sanitize_text_field($_POST['logFile']);
		      //  error_log("Class ZipArchive Not Present");
				$WPDBFileName = $FileName . '.zip';
				$path_info = wp_upload_dir();
				// $logMessage = sanitize_text_field(get_option('wp_db_backup_log_message'));
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
		                error_log("ERROR : '" . $archive->errorInfo(true) . "'");
		            }
		        } else {
		            $v_dir = $wpdbbkp_admin_class_obj->wp_db_backup_wp_config_path();
		            $v_remove = $v_dir;
		            // Create the archive
		            $v_list = $archive->create($v_dir, PCLZIP_OPT_REMOVE_PATH, $v_remove);
		            if ($v_list == 0) {
		                error_log("Error : " . $archive->errorInfo(true));
		            }
		        }
		        // update_option('wp_db_backup_log_message', $logMessage);
		        $update_backup_info = $wpdbbkp_admin_class_obj->wpdbbkp_update_backup_info($FileName, $logFile, $logMessage);
				$return_data_array['status'] = 'success';
				$return_data_array['update_backup_info'] = $update_backup_info;
		    }
	    }
	    echo json_encode($return_data_array);
	    wp_die();
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

add_action('wp_ajax_wpdbbkp_ajax_backup_event_process', 'wpdbbkp_ajax_backup_event_process');
add_action('wp_ajax_nopriv_wpdbbkp_ajax_backup_event_process', 'wpdbbkp_ajax_backup_event_process');
if(!function_exists('wpdbbkp_ajax_backup_event_process')){
	function wpdbbkp_ajax_backup_event_process() {
		if(isset($_POST['wpdbbkp_admin_security_nonce']) && wp_verify_nonce($_POST['wpdbbkp_admin_security_nonce'], 'wpdbbkp_ajax_check_nonce')){
			$details = array();
			$details['filename'] = isset($_POST['filename'])?sanitize_text_field($_POST['filename']):'';
			$details['dir'] = isset($_POST['dir'])?sanitize_text_field($_POST['dir']):'';
			$details['url'] = isset($_POST['url'])?sanitize_url($_POST['url']):'';
			$details['size'] = isset($_POST['size'])?intval($_POST['size']):'';
			$details['type'] = isset($_POST['type'])?sanitize_text_field($_POST['type']):'';
			$details['logfile'] = isset($_POST['logfile'])?$_POST['logfile']:'';
			$details['logfileDir'] = isset($_POST['logfileDir'])?sanitize_text_field($_POST['logfileDir']):'';

			$options = get_option('wp_db_backup_backups');
	        $Destination = "";
	        $logMessageAttachment = "";
	        $logMessage = "";
	        if (!$options) {
	            $options = array();
	        }

	        //Email
	      
	        if (get_option('wp_db_log') == 1) {
	            wpdbbkp_write_log($details['logfileDir'], $logMessage);
	        }        

	        $Destination.=" Local";

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

	        // delete_option('wp_db_backup_log_message');

	        update_option('wp_db_backup_backups', $options);
	        $destination="Local, ";
	        $args = array($details['filename'], $details['dir'], $logMessage, $details['size'],$details['logfileDir'],$details['type'],$destination);     
	        do_action_ref_array('wpdbbkp_backup_completed', array(&$args));
		}
		$response['status'] = 'success';
		$response['redirect_url'] = site_url() . '/wp-admin/admin.php?page=wp-database-backup';
		echo json_encode($response);
		wp_die();
	}
}