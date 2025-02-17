<?php

// Anonimization code
add_filter('wpdbbkp_process_db_fields', 'bkpforwp_anonimize_database', 10, 3);
add_action( 'rest_api_init', 'wpdbbkp_register_api');

function wpdbbkp_register_api(){
	register_rest_route('wpdbbkp/v1', '/upload-chunk', array(
        'methods' => 'POST',
        'callback' => 'wpdbbkp_handle_chunk_upload',
        'permission_callback' => '__return_true'
    ));

    register_rest_route('wpdbbkp/v1', '/finalize-upload', array(
        'methods' => 'POST',
        'callback' => 'wpdbbkp_finalize_upload',
        'permission_callback' => '__return_true'
    ));
}
function wpdbbkp_handle_chunk_upload(WP_REST_Request $request) {
  $file = $request->get_file_params()['file'];
  $chunkIndex = $request->get_param('chunkIndex');
  $fileName = sanitize_file_name($request->get_param('fileName'));
  
  $upload_dir = wp_upload_dir();
  $temp_dir = $upload_dir['basedir'] . "/wpdbbkp/temp/";

  if (!file_exists($temp_dir)) {
      mkdir($temp_dir, 0755, true);
  }

  $chunk_path = $temp_dir . $fileName . ".part" . $chunkIndex;
  move_uploaded_file($file['tmp_name'], $chunk_path);

  return rest_ensure_response(['success' => true, 'chunk' => $chunkIndex]);
}
function wpdbbkp_finalize_upload(WP_REST_Request $request) {
  global $wpdb;
  $fileName = sanitize_file_name($request->get_param('fileName'));
  $upload_dir = wp_upload_dir();
  $temp_dir = $upload_dir['basedir'] . "/wpdbbkp/temp/";
  $final_path = $upload_dir['basedir'] . "/wpdbbkp/" . $fileName;

  $chunks = glob($temp_dir . $fileName . ".part*");
  if (!$chunks) {
      return rest_ensure_response(['success' => false, 'message' => 'No chunks found']);
  }

  natsort($chunks); // Ensure correct order

  $output = fopen($final_path, "wb");
  foreach ($chunks as $chunk) {
      $input = fopen($chunk, "rb");
      while ($buffer = fread($input, 4096)) {
          fwrite($output, $buffer);
      }
      fclose($input);
      unlink($chunk); // Remove chunk after merging
  }
  fclose($output);
  rmdir($temp_dir); // Cleanup
  

  // Source ZIP and target WordPress path
  $sourceZip = $final_path; // Path to the WordPress ZIP file
  
  
  $zip = new ZipArchive;
  
  if ($zip->open($sourceZip) === TRUE) {
      // Directories for plugins and themes
      $pluginFolder = 'wp-content/plugins/';
      $themeFolder = 'wp-content/themes/';
      $targetPlugins = WP_PLUGIN_DIR  . '/';
      $targetThemes = get_theme_root().'/';
  
      // Plugins and themes to ignore
      $ignorePlugins = ['wp-datbase-backup'];
      $ignoreThemes = [];
      $ignoreTables = ['wp_users', 'wp_usermeta'];
      // Track plugins and themes found in the source
      $sourcePlugins = [];
      $sourceThemes = [];
  
      // Iterate through files in the ZIP
      $sqlFileContent = '';
      for ($i = 0; $i < $zip->numFiles; $i++) {
          $fileName = $zip->getNameIndex($i);
          $is_sql = false;
          if (pathinfo($fileName, PATHINFO_EXTENSION) === 'sql') {
            $is_sql = true;
              // Step 3: Read the .sql file directly
              $sqlFileContent = $zip->getFromIndex($i);
              if(!empty($sqlFileContent)){
                $sqlStatements = explode(';', $sqlFileContent);
                foreach ($sqlStatements as &$statement) {
                    $trimmedStatement = trim($statement);
                   // Skip if the table is one of the ignored tables (wp_users, wp_usermeta)
                    foreach ($ignoreTables as $ignoredTable) {
                        if (stripos($trimmedStatement, 'CREATE TABLE `' . $ignoredTable . '`') !== false) {
                            // Skip the table creation
                            continue 2;  // Skip this iteration of the outer loop
                        }
                        if (stripos($trimmedStatement, 'TRUNCATE TABLE `' . $ignoredTable . '`') !== false) {
                            // Skip truncating the table
                            continue 2;  // Skip this iteration of the outer loop
                        }
                        if (stripos($trimmedStatement, 'INSERT INTO `' . $ignoredTable . '`') !== false) {
                          // Skip INSERT INTO statements for these tables
                          continue 2;  // Skip this iteration of the outer loop
                        }
                    }
        
                    // Check if the statement contains CREATE TABLE and modify it to IF NOT EXISTS
                    if (stripos($statement, 'CREATE TABLE') !== false) {
                        $tableName = '';
                        if (preg_match('/CREATE TABLE `?(.*?)`?/i', $trimmedStatement, $matches)) {
                            $tableName = $matches[1];
                        }
                        // Add the TRUNCATE TABLE statement
                        $truncateStatement = "TRUNCATE TABLE `$tableName`;";
                        // Adding "IF NOT EXISTS" to the CREATE TABLE statement
                        $statement = preg_replace('/CREATE TABLE (.+?)(\()/i', 'CREATE TABLE IF NOT EXISTS $1$2', $statement). "\n" .$truncateStatement;
                    }
                }
                foreach ($sqlStatements as $statement) {
                    $trimmedStatement = trim($statement);
        
                    if (!empty($trimmedStatement)) {
                        $result = $wpdb->query($trimmedStatement);
                        if ($result === false) {
                           // error_log("Failed to execute SQL: " . $wpdb->last_error);
                        }
                    }
                }
              }
              
          }
          if($is_sql==true){
            continue;
          }
          // Handle Plugins
          if (strpos($fileName, $pluginFolder) === 0 && substr($fileName, -1) !== '/') {
              $relativePath = substr($fileName, strlen($pluginFolder));
              $pluginName = explode('/', $relativePath)[0];
              $destination = $targetPlugins . $relativePath;
  
              // Add plugin to source list
              $sourcePlugins[$pluginName] = true;
  
              // Check if plugin should be copied
              if (!in_array($pluginName, $ignorePlugins) && !is_dir($targetPlugins . $pluginName)) {
                  $fileContent = $zip->getFromIndex($i);
                  wpdbbkp_ensure_dir(dirname($destination));
                  file_put_contents($destination, $fileContent);
              }
          }
  
          // Handle Themes
          if (strpos($fileName, $themeFolder) === 0 && substr($fileName, -1) !== '/') {
              $relativePath = substr($fileName, strlen($themeFolder));
              $themeName = explode('/', $relativePath)[0];
              $destination = $targetThemes . $relativePath;
  
              // Add theme to source list
              $sourceThemes[$themeName] = true;
  
              // Check if theme should be copied
              if (!in_array($themeName, $ignoreThemes) && !is_dir($targetThemes . $themeName)) {
                  $fileContent = $zip->getFromIndex($i);
                  wpdbbkp_ensure_dir(dirname($destination));
                  file_put_contents($destination, $fileContent);
              }
          }
      }
      
      
      $zip->close();
  
      // Remove plugins not in source
      $existingPlugins = scandir($targetPlugins);
      foreach ($existingPlugins as $plugin) {
          if ($plugin !== '.' && $plugin !== '..' && is_dir($targetPlugins . $plugin)) {
              if (!isset($sourcePlugins[$plugin]) && !in_array($plugin, $ignorePlugins)) {
                  wpdbbkp_delete_directory($targetPlugins . $plugin);
                 // echo "Removed plugin: $plugin\n";
              }
          }
      }
  
      // Remove themes not in source
      $existingThemes = scandir($targetThemes);
      foreach ($existingThemes as $theme) {
          if ($theme !== '.' && $theme !== '..' && is_dir($targetThemes . $theme)) {
              if (!isset($sourceThemes[$theme]) && !in_array($theme, $ignoreThemes)) {
                  wpdbbkp_delete_directory($targetThemes . $theme);
                 // echo "Removed theme: $theme\n";
              }
          }
      }
  
  }
  return rest_ensure_response(['success' => true, 'file' => $final_path]);
}
// Helper function to create directories recursively
function wpdbbkp_ensure_dir($dir)
{
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Helper function to delete directories recursively
function wpdbbkp_delete_directory($dir)
{
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = "$dir/$file";
        is_dir($path) ? wpdbbkp_delete_directory($path) : unlink($path);
    }
    rmdir($dir);
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