<?php

// Anonimization code
add_filter('wpdbbkp_process_db_fields', 'bkpforwp_anonimize_database', 10, 3);
add_action('wp_ajax_wpdbbkp_check_extract_status', 'wpdbbkp_check_extract_status');
function wpdbbkp_check_extract_status(){
  if ( !isset( $_POST['wpdbbkp_admin_security_nonce'] ) || !wp_verify_nonce( 	wp_unslash($_POST['wpdbbkp_admin_security_nonce']), 'wpdbbkp_ajax_check_nonce' ) ){ 
    wp_send_json_success(esc_html__('Invalid nonce check.', 'wpdbbkp'));
    return;  
 }
  $get_progress = get_transient('wpdbbkp_track_progress');
  if($get_progress==false){
    wp_send_json_success(['success'=>1,'message'=>esc_html__('Starring Extraction Process', 'wpdbbkp')]);
  }else{
    if($get_progress=='Process Completed'){
      delete_transient('wpdbbkp_track_progress');
    }
    wp_send_json_success(['success'=>1,'message'=>$get_progress]);
  }
}

add_action('wp_ajax_wpdbbkp_upload_site_chunk', 'wpdbbkp_upload_site_chunk');
function wpdbbkp_upload_site_chunk() {
    if ( !isset( $_POST['wpdbbkp_admin_security_nonce'] ) || !wp_verify_nonce( 	wp_unslash($_POST['wpdbbkp_admin_security_nonce']), 'wpdbbkp_ajax_check_nonce' ) ){ 
      wp_send_json_success(esc_html__('Invalid nonce check.', 'wpdbbkp'));
      return;  
  }
   if (!isset($_FILES['file']) || !isset($_POST['fileName']) || !isset($_POST['offset'])) {
      wp_send_json_error("Invalid request.");
  }

  $upload_dir = WP_CONTENT_DIR . '/uploads/wpdbbkp/temp';
  if (!file_exists($upload_dir)) {
      mkdir($upload_dir, 0755, true);
  }

  $file_name = sanitize_file_name($_POST['fileName']);
  $file_path = $upload_dir . '/' . $file_name;

  // Append chunk to the file
  file_put_contents($file_path, file_get_contents($_FILES['file']['tmp_name']), FILE_APPEND);

  wp_send_json_success(esc_html__('Chunk uploaded successfully.', 'wpdbbkp'));
}
add_action('wp_ajax_wpdbbkp_extract_uploaded_site', 'wpdbbkp_extract_uploaded_site');
function wpdbbkp_extract_uploaded_site() {

  try {

      if ( !isset( $_POST['wpdbbkp_admin_security_nonce'] ) || !wp_verify_nonce( 	wp_unslash($_POST['wpdbbkp_admin_security_nonce']), 'wpdbbkp_ajax_check_nonce' ) ){ 

        wp_send_json_success(esc_html__('Invalid nonce check.', 'wpdbbkp'));

        return;  

    }

      ini_set('max_execution_time', 0); // No time limit

      set_time_limit(0);

      error_log("AJAX extraction started");



      if (!isset($_POST['fileName'])) {

          throw new Exception("No file provided.");

      }



      $upload_dir = WP_CONTENT_DIR . '/uploads/wpdbbkp/temp';

      $backup_file = $upload_dir . '/' . sanitize_file_name($_POST['fileName']);



      if (!file_exists($backup_file)) {

          throw new Exception("Backup file not found.");

      }



      $extract_path = $upload_dir . '/extracted/';

       $zip = new ZipArchive();

      if ($zip->open($backup_file) !== TRUE) {

          throw new Exception("Failed to open the ZIP archive.");

      }



      if (!file_exists($extract_path)) {

          mkdir($extract_path, 0755, true);

      }



      error_log("ZIP contains " . $zip->numFiles . " files");



      

      $root_folder = '';

      for ($i = 0; $i < $zip->numFiles; $i++) {

          $entry = $zip->getNameIndex($i);

          if (preg_match('#^(.*?)/wp-content/#', $entry, $matches)) {

              $root_folder = $matches[1] . '/';

              break;

          }

      }

      error_log("Detected root folder: $root_folder");



      

      for ($i = 0; $i < $zip->numFiles; $i++) {

          $entry = $zip->getNameIndex($i);

          $normalized_entry = str_replace($root_folder, '', $entry);



          if (preg_match('#^wp-content/plugins/#', $normalized_entry) ||

              preg_match('#^wp-content/themes/#', $normalized_entry) ||

              (pathinfo($normalized_entry, PATHINFO_EXTENSION) === 'sql' && substr_count($normalized_entry, '/') === 0)

          ) {

            if ($zip->extractTo($extract_path, $entry)) {

                  set_transient('wpdbbkp_track_progress', $entry, 3600);

                  error_log("Successfully extracted: $entry");



              } else {

                  error_log("Extraction failed: $entry");

              }

          }

      }



      $zip->close();

      set_transient('wpdbbkp_track_progress', 'Extraction of files completed. Now taking live plugins & themes', 3600);

      $plugins_path = $extract_path . '/wp-content/plugins/';

      $themes_path = $extract_path . '/wp-content/themes/';

      

      // Move extracted files to wp-content

      $ignore_plugins = ['wp-database-backup'];



      if (file_exists($plugins_path)) {

          wpdbbkp_move_extracted_files($plugins_path, WP_CONTENT_DIR . '/plugins/', $ignore_plugins);

      } else {

          error_log("Plugins directory not found in extracted folder.");

      }



      if (file_exists($themes_path)) {

          wpdbbkp_move_extracted_files($themes_path, WP_CONTENT_DIR . '/themes/');

      } else {

          error_log("Themes directory not found in extracted folder OR extraction failed.");

      }



      // Process SQL file if found

      $sql_file = wpdbbkp_find_sql_file($extract_path);

      if ($sql_file) {

          set_transient('wpdbbkp_track_progress', 'Migrating Database...', 3600);

          $wp_config_prefix = wpdbbkp_get_wp_config_table_prefix();
          error_log("Sql File .".$sql_file);
          wpdbbkp_update_sql_table_prefix($sql_file);

          wpdbbkp_restore_database($sql_file);

      } else {

          error_log("No SQL file found in extracted folder.");

      }

     

      error_log("Extraction completed successfully");

      $wpdbbkp_folder = WP_CONTENT_DIR . '/uploads/wpdbbkp';
      if (file_exists($wpdbbkp_folder)) {
          wpdbbkp_delete_folder($wpdbbkp_folder);
          error_log("Deleted wpdbbkp folder after successful migration.");
      }
      set_transient('wpdbbkp_track_progress', 'Process Completed', 3600);
      wp_send_json_success(esc_html__('Plugins, Themes, Database & Table Prefix Updated!', 'wpdbbkp'));



  } catch (Exception $e) {

      error_log("Error: " . $e->getMessage());

      wp_send_json_error($e->getMessage());

  }

}

function wpdbbkp_delete_folder($folder_path) {
  if (!is_dir($folder_path)) {
      return;
  }

  $files = array_diff(scandir($folder_path), array('.', '..'));

  foreach ($files as $file) {
      $file_path = $folder_path . DIRECTORY_SEPARATOR . $file;
      if (is_dir($file_path)) {
          wpdbbkp_delete_folder($file_path);
      } else {
          set_transient('wpdbbkp_track_progress', 'Cleaning : '.$file_path, 3600);
          unlink($file_path);
      }
  }

  rmdir($folder_path);
}




function wpdbbkp_find_sql_file($directory) {

    $files = scandir($directory);

    foreach ($files as $file) {

        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {

            return $directory . '/' . $file;

        }

    }

    return false;

}



function wpdbbkp_get_wp_config_table_prefix() {

    $config_file = ABSPATH . 'wp-config.php';
    error_log("[ERROR] Config Path: $config_file");
    if (!file_exists($config_file)) {

        return 'wp_';

    }

    $config_contents = file_get_contents($config_file);

    if (preg_match("/\$table_prefix\s*=\s*'([^']+)'/", $config_contents, $matches)) {

        return $matches[1];

    }

    return 'wp_';

}
function wpdbbkp_update_sql_table_prefix($sql_file) {
  if (!file_exists($sql_file)) {
      die("❌ ERROR: SQL file not found: $sql_file\n");
  }

  $sql_content = file_get_contents($sql_file);

  // Find wp-config.php
  $wp_config_path = find_wp_config();
  if (!$wp_config_path) {
      die("❌ ERROR: wp-config.php not found!\n");
  }

  // Extract table prefix from wp-config.php
  $config_content = file_get_contents($wp_config_path);
  if (preg_match("/\\\$table_prefix\s*=\s*['\"]([^'\"]+)['\"]\s*;/", $config_content, $matches)) {
      $new_prefix = $matches[1];
      echo "✅ SUCCESS: Extracted new prefix from wp-config.php: '$new_prefix'\n";
  } else {
      die("❌ ERROR: Could not extract table prefix from wp-config.php!\n");
  }

  // Detect old prefix from the SQL file
  if (preg_match("/(CREATE TABLE|INSERT INTO|ALTER TABLE|UPDATE|DELETE FROM)\s+[`']?([a-zA-Z0-9]+?_)/i", $sql_content, $matches)) {
      $old_prefix = $matches[2];
      echo "✅ SUCCESS: Found old prefix in SQL file: '$old_prefix'\n";
  } else {
      die("❌ ERROR: No table prefix found in the SQL file!\n");
  }

  // Prevent replacing if old prefix is same as new prefix
  if ($old_prefix === $new_prefix) {
      die("ℹ️ INFO: Old and new prefixes are the same ('$old_prefix'), no changes needed.\n");
  }

  // Replace all occurrences of the old prefix with the new one
  $sql_content = preg_replace("/\b" . preg_quote($old_prefix, '/') . "/i", $new_prefix, $sql_content);

  // Write the updated content back to the SQL file
  file_put_contents($sql_file, $sql_content);

  echo "✅ SUCCESS: Table prefix updated from '$old_prefix' to '$new_prefix' in $sql_file!\n";
}

/**
* Finds the root wp-config.php file by checking parent directories.
*/
function find_wp_config() {
  $dir = __DIR__;

  while ($dir !== dirname($dir)) { // Keep going up until the root
      $config_path = $dir . '/wp-config.php';
      if (file_exists($config_path)) {
          return $config_path;
      }
      $dir = dirname($dir);
  }

  return false; // wp-config.php not found
}








function wpdbbkp_move_extracted_files($source, $destination, $ignore = []) {
  if (!file_exists($source)) return;

  if (!file_exists($destination)) {
      mkdir($destination, 0755, true);
  }

  $files = array_diff(scandir($source), array('.', '..'));
  foreach ($files as $file) {
      $srcFile = rtrim($source, '/') . '/' . $file;
      $destFile = rtrim($destination, '/') . '/' . $file;

      if (in_array($file, $ignore)) {
          continue;
      }

      set_transient('wpdbbkp_track_progress', 'Copying: ' . $destFile, 3600);

      if (is_dir($srcFile)) {
          if (file_exists($destFile)) {
              wpdbbkp_delete_directory($destFile);
          }
          rename($srcFile, $destFile);
      } else {
          if (file_exists($destFile)) {
              unlink($destFile);
          }
          rename($srcFile, $destFile);
      }
  }
}

function wpdbbkp_delete_directory($dir) {
  if (!is_dir($dir)) {
      return unlink($dir);
  }

  $files = array_diff(scandir($dir), array('.', '..'));
  foreach ($files as $file) {
      $filePath = $dir . DIRECTORY_SEPARATOR . $file;
      if (is_dir($filePath)) {
          wpdbbkp_delete_directory($filePath);
      } else {
          unlink($filePath);
      }
  }

  return rmdir($dir);
}


function wpdbbkp_restore_database($sql_file) {

  global $wpdb;

  $new_site_url = get_site_url();



  if (!file_exists($sql_file)) {

      error_log("[ERROR] SQL file not found: $sql_file");

      return false;

  }



  $sql_content = file_get_contents($sql_file);

  if (!$sql_content) {

      error_log("[ERROR] Failed to read SQL file: $sql_file");

      return false;

  }



  // Fetch old site URL from wp_options before updating

  $old_site_url = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'siteurl'");



  if (!$old_site_url) {

      error_log("[ERROR] Failed to fetch old site URL.");

      return false;

  }



  error_log("[INFO] Old Site URL detected: $old_site_url");



  // Split SQL statements properly

  $queries = preg_split('/;\s*\n/', $sql_content, -1, PREG_SPLIT_NO_EMPTY);



  // Tables to exclude (e.g., Users and Usermeta for security reasons)

  $excluded_tables = ['users','usermeta','options'];



  $wpdb->query('SET foreign_key_checks = 0'); // Disable FK checks

  $wpdb->query('START TRANSACTION'); // Start transaction



  try {

      foreach ($queries as $query) {

          $query = trim($query);

          if (empty($query)) continue;

          $query = str_replace('options','option_tmp',$query);

          // Check for CREATE TABLE and extract table name

          if (preg_match('/CREATE TABLE `([^`]*)`/', $query, $matches)) {

              $table_name = $matches[1];



              // Skip excluded tables

              foreach ($excluded_tables as $excluded) {

                  if (strpos($table_name, $excluded) !== false) {

                      error_log("[SKIPPED] Table excluded: $table_name");

                      continue 2;

                  }

              }



              // Drop existing table before restoring

              $wpdb->query("DROP TABLE IF EXISTS `$table_name`");

              error_log("[DROPPED] Table: $table_name");

          }



          // Skip execution of queries for excluded tables

          foreach ($excluded_tables as $excluded) {

              if (strpos($query, $excluded) !== false) {

                  error_log("[SKIPPED] Query contains excluded table reference.");

                  continue 2;

              }

          }



          // Execute the query

          $result = $wpdb->query($query);

          if ($result === false) {

              throw new Exception("[ERROR] Failed to execute query: " . $wpdb->last_error);

          }

      }







      // **Find and replace old URLs in all tables**

      $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);

      foreach ($tables as $table) {

          $table_name = $table[0];



          // Skip excluded tables

          foreach ($excluded_tables as $excluded) {

              if (strpos($table_name, $excluded) !== false) {

                  error_log("[SKIPPED] URL Replacement in: $table_name");

                  continue 2;

              }

          }



          // Get all columns for the table

          $columns = $wpdb->get_results("SHOW COLUMNS FROM `$table_name`", ARRAY_A);

          foreach ($columns as $column) {

              $column_name = $column['Field'];



              // Update all occurrences of old URL in text-based columns

              $wpdb->query(

                  $wpdb->prepare(

                      "UPDATE `$table_name` SET `$column_name` = REPLACE(`$column_name`, %s, %s) WHERE `$column_name` LIKE %s",

                      $old_site_url,

                      $new_site_url,

                      '%' . $wpdb->esc_like($old_site_url) . '%'

                  )

              );

          }

      }



      $blogname = $wpdb->get_var("SELECT option_value FROM {$wpdb->prefix}option_tmp WHERE option_name = 'blogname'");

        if ($blogname) {

            $wpdb->query(

                $wpdb->prepare(

                    "UPDATE {$wpdb->prefix}options SET option_value = %s WHERE option_name = 'blogname'",

                    $blogname

                )

            );

            error_log("[SUCCESS] Updated wp_options with blogname from option_tmp");

        }
      $active_plugins = $wpdb->get_var("SELECT option_value FROM {$wpdb->prefix}option_tmp WHERE option_name = 'active_plugins'");

        if ($active_plugins) {

            $wpdb->query(

                $wpdb->prepare(

                    "UPDATE {$wpdb->prefix}options SET option_value = %s WHERE option_name = 'active_plugins'",

                    $active_plugins

                )

            );

            error_log("[SUCCESS] Updated wp_options with active_plugins from option_tmp");

        }

      $wpdb->query('COMMIT'); // Commit transaction

      error_log("[SUCCESS] Database restoration and URL replacement completed from: $sql_file");

      return true;



  } catch (Exception $e) {

      $wpdb->query('ROLLBACK'); // Rollback in case of an error

      error_log($e->getMessage());

      return false;

  } finally {

      $wpdb->query('SET foreign_key_checks = 1'); // Re-enable FK checks

  }

}
function bkpforwp_anonimize_database($value, $table, $column)
{
  $enable_anonymization = get_option('bkpforwp_enable_anonymization', false);
  $anonymization_type = get_option('bkpforwp_anonymization_type', false);
  $enable_backup_encryption = get_option('bkpforwp_enable_backup_encryption', false);
  $anonymization_pass = get_option('bkpforwp_anonymization_pass', '');


  if (isset($enable_anonymization) && $enable_anonymization == 1) {
    global $wpdb;
    $bkpforwp_process_table = array($wpdb->prefix . 'options', $wpdb->prefix . 'users', $wpdb->prefix . 'usermeta', $wpdb->prefix . 'wc_customer_lookup', $wpdb->prefix . 'edd_customers', $wpdb->prefix . 'edd_customermeta');
    $bkpforwp_process_cols = array('mailserver_pass', 'mailserver_login', 'user_email', 'email', 'user_url', 'nickname', 'name', 'twitter', 'facebook', 'instagram', 'phone', 'mobile', 'address', 'city', 'zip', 'pincode', 'user_login', 'postcode', 'state', 'user_ip', 'ip_address');

    //Masking Logic
    if (isset($anonymization_type) && $anonymization_type == 'masked_data') {
      if (in_array($table, $bkpforwp_process_table)) {
        $check_str = implode(',', $bkpforwp_process_cols);
        if (stripos($check_str, $column) !== false) {
          return str_replace($value, str_repeat('*', strlen($value)), $value);
        }
      }
    }
    //FakeData Logic

    if (isset($anonymization_type) && $anonymization_type == 'fake_data') {
      if (function_exists('wp_privacy_anonymize_data')) {
        $bkpforwp_process_email = implode(',', array('email', 'user_email'));
        $bkpforwp_process_url = implode(',', array('url', 'user_url', 'twitter', 'facebook', 'instagram'));
        $bkpforwp_process_ip = implode(',', array('user_ip', 'ip_address'));
        $bkpforwp_process_text = implode(',', array('nickname', 'name', 'address', 'phone', 'mobile', 'city', 'zip', 'pincode', 'user_login', 'postcode', 'state'));

        if (in_array($table, $bkpforwp_process_table)) {

          //For email
          if (stripos($bkpforwp_process_email, $column) !== false) {
            return str_replace($value, wp_privacy_anonymize_data('email', $value), $value);
          }

          if (stripos($bkpforwp_process_url, $column) !== false) {
            return str_replace($value, wp_privacy_anonymize_data('url', $value), $value);
          }

          if (stripos($bkpforwp_process_ip, $column) !== false) {
            return str_replace($value, wp_privacy_anonymize_data('ip', $value), $value);
          }

          if (stripos($bkpforwp_process_text, $column) !== false) {
            return str_replace($value, wp_privacy_anonymize_data('text', $value), $value);
          }

        }

        return $value;

      } else {
        if (in_array($table, $bkpforwp_process_table)) {
          $check_str = implode(',', $bkpforwp_process_cols);
          if (stripos($check_str, $column) !== false) {
            return str_replace($value, str_repeat('*', strlen($value)), $value);
          }
        }
      }

    }

    if (isset($anonymization_type) && $anonymization_type == 'encrypted_data' && !empty($anonymization_pass)) {
      require_once 'class-symmetric-encryption.php';

      if (in_array($table, $bkpforwp_process_table)) {
        $check_str = implode(',', $bkpforwp_process_cols);
        if (stripos($check_str, $column) !== false) {
          $enc_pass = $anonymization_pass;
          $encryption = new SymmetricEncryption();
          return str_replace($value, '<==>' . $encryption->encrypt($value, $enc_pass, $enc_pass) . '<==>', $value);
        }

      }

    }

  }
  return $value;
}

add_filter('wpdbbkp_sql_query_restore', 'bkpforwp_sql_query_restore', 1);
function bkpforwp_sql_query_restore($sql_query)
{
  $anonymization_type = get_option('bkpforwp_anonymization_type', false);
  $anonymization_pass = get_option('bkpforwp_anonymization_pass', '');
  if (isset($anonymization_type) && $anonymization_type == 'encrypted_data' && !empty($anonymization_pass)) {

    $pattern = '/<==>(.*?)<==>/i';
    return preg_replace_callback($pattern, 'bkpforwp_sql_restore_replace', $sql_query);
  }
  return $sql_query;
}

function bkpforwp_sql_restore_replace($matches)
{
  $anonymization_pass = get_option('bkpforwp_anonymization_pass', '');
  $enc_pass = isset($anonymization_pass) ? $anonymization_pass : false;
  if ($enc_pass) {
    require_once 'class-symmetric-encryption.php';
    $encryption = new SymmetricEncryption();
    return $encryption->decrypt($matches[0], $enc_pass, $enc_pass);
  }
  return $matches[0];
}

add_action('wpdbbkp_database_backup_options', 'bkpforwp_database_backup_options');
function bkpforwp_database_backup_options()
{
  $settings = get_option('wp_db_backup_options');
  $autobackup_days = isset($settings['autobackup_days']) ? implode(',', $settings['autobackup_days']) : ',';
  $autobackup_time = isset($settings['autobackup_time']) ? $settings['autobackup_time'] : '';
  $autobackup_date = isset($settings['autobackup_date']) ? $settings['autobackup_date'] : '';
  ?>


  <div class="row form-group autobackup_frequency_pro" style="display:none"><label
      class="col-sm-12 autobackup_daily_pro">We will automatically backup at 00:00 AM daily. <b><a
          href="javascript:modify_backup_frequency();">Change Back Frequency Timings</a></b></label></div>
  <div class="row form-group autobackup_frequency_pro" style="display:none"><label
      class="col-sm-12 autobackup_weekly_pro">We will automatically backup every Sunday on weekly basis. <b><a
          href="javascript:modify_backup_frequency();">Change Back Frequency Timings</a></b></label></div>
  <div class="row form-group autobackup_frequency_pro" style="display:none"><label
      class="col-sm-12 autobackup_monthly_pro">We will automatically backup on 1st on Monday on monthly basis. <b><a
          href="javascript:modify_backup_frequency();">Change Back Frequency Timings</a></b></label></div>


  <div class="row form-group autobackup_days database_autobackup" style="display:none">
    <label class="col-sm-3" for="autobackup_days"><?php esc_html_e('Database Backup Days', 'wpdbbkp'); ?></label>
    <div class="col-sm-9">
      <select id="autobackup_days" class="form-control bkpforwp_multiselect"
        name="wp_db_backup_options[autobackup_days][]" multiple>
        <option value="Mon" <?php if (strpos($autobackup_days, 'Mon') !== false) {
          echo 'selected';
        } ?>>
          <?php esc_html_e('Monday', 'wpdbbkp'); ?></option>
        <option value="Tue" <?php if (strpos($autobackup_days, 'Tue') !== false) {
          echo 'selected';
        } ?>>
          <?php esc_html_e('Tuesday', 'wpdbbkp'); ?></option>
        <option value="Wed" <?php if (strpos($autobackup_days, 'Wed') !== false) {
          echo 'selected';
        } ?>>
          <?php esc_html_e('Wednesday', 'wpdbbkp'); ?></option>
        <option value="Thu" <?php if (strpos($autobackup_days, 'Thu') !== false) {
          echo 'selected';
        } ?>>
          <?php esc_html_e('Thursday', 'wpdbbkp'); ?></option>
        <option value="Fri" <?php if (strpos($autobackup_days, 'Fri') !== false) {
          echo 'selected';
        } ?>>
          <?php esc_html_e('Friday', 'wpdbbkp'); ?></option>
        <option value="Sat" <?php if (strpos($autobackup_days, 'Sat') !== false) {
          echo 'selected';
        } ?>>
          <?php esc_html_e('Saturday', 'wpdbbkp'); ?></option>
        <option value="Sun" <?php if (strpos($autobackup_days, 'Sun') !== false) {
          echo 'selected';
        } ?>>
          <?php esc_html_e('Sunday', 'wpdbbkp'); ?></option>
      </select>
    </div>
  </div>
  <div class="row form-group autobackup_date database_autobackup" style="display:none">
    <label class="col-sm-3" for="autobackup_date"><?php esc_html_e('Database Backup Date', 'wpdbbkp'); ?></label>
    <div class="col-sm-9">
      <input type="date" id="autobackup_date" value="<?php echo esc_attr($autobackup_date); ?>"
        class="form-control bkpforwp_multiselect" name="wp_db_backup_options[autobackup_date]">
    </div>
  </div>
  <div class="row form-group autobackup_time database_autobackup" style="display:none">
    <label class="col-sm-3" for="autobackup_time"><?php esc_html_e('Database Backup Time', 'wpdbbkp'); ?></label>
    <div class="col-sm-9">
      <input type="time" id="autobackup_time" value="<?php echo esc_attr($autobackup_time); ?>"
        class="form-control bkpforwp_multiselect" name="wp_db_backup_options[autobackup_time]">
    </div>
  </div>

  <?php
}

add_action('wpdbbkp_full_backup_options', 'bkpforwp_full_backup_options');
function bkpforwp_full_backup_options()
{

  $settings = get_option('wp_db_backup_options');
  $autobackup_days = isset($settings['autobackup_full_days']) ? implode(',', $settings['autobackup_full_days']) : ',';
  $autobackup_time = isset($settings['autobackup_full_time']) ? $settings['autobackup_full_time'] : '';
  $autobackup_date = isset($settings['autobackup_full_date']) ? $settings['autobackup_full_date'] : '';
  $autobackup_date = isset($settings['autobackup_full_date']) ? $settings['autobackup_full_date'] : '';
  $senable_exact_backup_time = get_option('bkpforwp_enable_exact_backup_time', false);
  if ($senable_exact_backup_time) {
    ?>
    <div class="row form-group autobackup_full_days full_autobackup" style="display:none">
      <label class="col-sm-3" for="autobackup_full_days"><?php esc_html_e('Full Backup Days', 'wpdbbkp'); ?></label>
      <div class="col-sm-9">
        <select id="autobackup_full_days" class="form-control bkpforwp_multiselect"
          name="wp_db_backup_options[autobackup_full_days][]" multiple>
          <option value="Mon" <?php if (strpos($autobackup_days, 'Mon') !== false) {
            echo 'selected';
          } ?>>
            <?php esc_html_e('Monday', 'wpdbbkp'); ?></option>
          <option value="Tue" <?php if (strpos($autobackup_days, 'Tue') !== false) {
            echo 'selected';
          } ?>>
            <?php esc_html_e('Tuesday', 'wpdbbkp'); ?></option>
          <option value="Wed" <?php if (strpos($autobackup_days, 'Wed') !== false) {
            echo 'selected';
          } ?>>
            <?php esc_html_e('Wednesday', 'wpdbbkp'); ?></option>
          <option value="Thu" <?php if (strpos($autobackup_days, 'Thu') !== false) {
            echo 'selected';
          } ?>>
            <?php esc_html_e('Thursday', 'wpdbbkp'); ?></option>
          <option value="Fri" <?php if (strpos($autobackup_days, 'Fri') !== false) {
            echo 'selected';
          } ?>>
            <?php esc_html_e('Friday', 'wpdbbkp'); ?></option>
          <option value="Sat" <?php if (strpos($autobackup_days, 'Sat') !== false) {
            echo 'selected';
          } ?>>
            <?php esc_html_e('Saturday', 'wpdbbkp'); ?></option>
          <option value="Sun" <?php if (strpos($autobackup_days, 'Sun') !== false) {
            echo 'selected';
          } ?>>
            <?php esc_html_e('Sunday', 'wpdbbkp'); ?></option>
        </select>
      </div>
    </div>
    <div class="row form-group autobackup_full_date full_autobackup" style="display:none">
      <label class="col-sm-3" for="autobackup_full_date"><?php esc_html_e('Full Backup Date', 'wpdbbkp'); ?></label>
      <div class="col-sm-9">
        <input type="date" id="autobackup_full_date" value="<?php echo esc_attr($autobackup_date); ?>" class="form-control"
          name="wp_db_backup_options[autobackup_full_date]">
      </div>
    </div>
    <div class="row form-group autobackup_full_time full_autobackup" style="display:none">
      <label class="col-sm-3" for="autobackup_full_time"><?php esc_html_e('Full Backup Time', 'wpdbbkp'); ?></label>
      <div class="col-sm-9">
        <input type="time" id="autobackup_full_time" value="<?php echo esc_attr($autobackup_time); ?>" class="form-control"
          name="wp_db_backup_options[autobackup_full_time]">
      </div>
    </div>
    <?php
  }
}

add_filter('wpdbbkp_fullback_cron_condition', 'bkpforwp_fullback_cron_condition');
function bkpforwp_fullback_cron_condition($value)
{
  $options_settings = get_option('wp_db_backup_options', false);

  $senable_exact_backup_time = get_option('bkpforwp_enable_exact_backup_time', false);
  if (!$senable_exact_backup_time) {
    return $value;
  }
  if (wp_doing_cron() && $options_settings && isset($options_settings['enable_autobackups']) && $options_settings['enable_autobackups'] == 1 && isset($options_settings['full_autobackup_frequency'])) {
    if ($options_settings['full_autobackup_frequency'] == 'daily' && isset($options_settings['autobackup_full_time']) && $options_settings['autobackup_full_time']) {
      if ($options_settings['autobackup_full_time'] < gmdate("H:i") || $options_settings['autobackup_full_time'] > gmdate("H:i", strtotime('+30 minutes', gmdate("H:i")))) {
        $value = false;
      }
    }
    if ($options_settings['full_autobackup_frequency'] == 'weekly' && isset($options_settings['autobackup_full_time']) && $options_settings['autobackup_full_time'] && isset($options_settings['autobackup_full_days'])) {
      $current_day = gmdate('M');
      $current_time = gmdate('H:i');
      $allowed_days = $options_settings['autobackup_full_days'];
      if (!in_array($current_day, $allowed_days) || ($options_settings['autobackup_full_time'] < $current_time) || $options_settings['autobackup_full_time'] > gmdate("H:i", strtotime('+30 minutes', $current_time))) {
        $value = false;
      }
    }
    if ($options_settings['full_autobackup_frequency'] == 'monthly' && isset($options_settings['autobackup_full_time']) && $options_settings['autobackup_full_time'] && isset($options_settings['autobackup_full_date'])) {
      $current_date = gmdate('d');
      $current_time = gmdate('H:i');
      $allowed_date = gmdate('d', strtotime($options_settings['autobackup_full_date']));
      if (($allowed_date != $current_date) || ($options_settings['autobackup_full_time'] < $current_time || $options_settings['autobackup_full_time'] > gmdate("H:i", strtotime('+30 minutes', $current_time)))) {
        $value = false;
      }
    }
  }
  return $value;
}

add_filter('wpdbbkp_dbback_cron_condition', 'bkpforwp_dbback_cron_condition');
function bkpforwp_dbback_cron_condition($value)
{
  $options_settings = get_option('wp_db_backup_options', false);
  if (wp_doing_cron() && $options_settings && isset($options_settings['enable_autobackups']) && $options_settings['enable_autobackups'] == 1 && isset($options_settings['autobackup_frequency'])) {
    if ($options_settings['autobackup_frequency'] == 'daily' && isset($options_settings['autobackup_time'])) {
      if ($options_settings['autobackup_time'] < gmdate("H:i") || $options_settings['autobackup_time'] > gmdate("H:i", strtotime('+30 minutes', gmdate("H:i")))) {
        $value = false;
      }
    }
    if ($options_settings['autobackup_frequency'] == 'weekly' && isset($options_settings['autobackup_time']) && isset($options_settings['autobackup_days'])) {
      $current_day = gmdate('M');
      $current_time = gmdate('H:i');
      $allowed_days = $options_settings['autobackup_days'];
      if (!in_array($current_day, $allowed_days) || ($options_settings['autobackup_time'] < $current_time || $options_settings['autobackup_time'] > gmdate("H:i", strtotime('+30 minutes', $current_time)))) {
        $value = false;
      }
    }
    if ($options_settings['autobackup_frequency'] == 'monthly' && isset($options_settings['autobackup_time']) && isset($options_settings['autobackup_date'])) {
      $current_date = gmdate('d');
      $current_time = gmdate('H:i');
      $allowed_date = gmdate('d', strtotime($options_settings['autobackup_date']));
      if (($allowed_date != $current_date) || ($options_settings['autobackup_time'] < $current_time || $options_settings['autobackup_time'] > gmdate("H:i", strtotime('+30 minutes', $current_time)))) {
        $value = false;
      }
    }
  }
  return $value;
}

add_filter('wpdbbkp_dbback_cron_frequency', 'bkpforwp_dbback_cron_frequency');

function bkpforwp_dbback_cron_frequency($value)
{
  if (wp_doing_cron()) {
    $options = get_option('wp_db_backup_options');
    if (isset($options['autobackup_full_time']) && !empty($options['autobackup_full_time'])) {
      $value = 'thirty_minutes';
    }
  }
  return $value;
}

/**
 * Function to force the new .htaccess file to fix the backup folder protection
 */
function wpdbbkp_fix_htaccess_on_update()
{
  static $wpdbbkp_htaccess_fix = false;

  if (!$wpdbbkp_htaccess_fix && version_compare(WPDB_VERSION, '7.4', '>=')) {
    $wpdbbkp_htaccess_fix = true;
    $option_name = 'wpdbbkp_htaccess_fix';
    if (get_option($option_name, false)) {
      return; // Exit if already fixed
    }

    // Initialize WP Filesystem
    global $wp_filesystem;

    if (!function_exists('WP_Filesystem')) {
      require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    if (!WP_Filesystem()) {
      return;
    }
    // Define the .htaccess content
    $htaccess_content = "
# Disable public access to this folder
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>

<IfModule !mod_authz_core.c>
    Deny from all
</IfModule>
";

    $path_info = wp_upload_dir();
    $backup_folder = $path_info['basedir'] . '/' . WPDB_BACKUPS_DIR . '/';
    $htaccess_file = trailingslashit($backup_folder) . '.htaccess';

    if ($wp_filesystem->exists($htaccess_file)) {
      $wp_filesystem->delete($htaccess_file);
    }

    if (!$wp_filesystem->put_contents($htaccess_file, $htaccess_content, FS_CHMOD_FILE)) {
      return;
    }
    update_option($option_name, time(), false);
  }
}

add_action('admin_init', 'wpdbbkp_fix_htaccess_on_update');