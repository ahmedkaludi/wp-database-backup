<?php
ob_start();
if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

class WPALLMenu {

    private $path = '';
    private $type = '';
    private $archive_filename = '';
    private $database_dump_filename = '';
    private $mysqldump_command_path;
    private $excludes = array();
    private $root = '';
    private $db;
    private $files = array();
    private $excluded_files = array();
    private $unreadable_files = array();
    private $errors = array();
    private $warnings = array();
    private $archive_method = '';
    private $mysqldump_method = '';
    private $zip_command_path;

    public function __construct() {
        add_action('init', array('WPALLMenu', 'init'));
        add_action('admin_init', array($this, 'wp_all_backup_admin_init'));
        add_filter('cron_schedules', array($this, 'wp_all_backup_cron_schedules'));
        add_action('wp_all_backup_event', array($this, 'wp_all_backup_event_process'));
        add_action('wp', array($this, 'wp_all_backup_scheduler_activation'));
        add_action('wp_logout', array($this, 'wp_all_cookie_expiration'));////Fixed Vulnerability 22-06-2016 for prevent direct download      
    }

    static function version() {
        return VERSION;
    }

    static function init() {
        // add_action('admin_menu', array('WPALLMenu', 'adminPage'));
    }

    static function adminPage() {
        add_menu_page('WP ALL Backup', 'WP ALL Backup', 'update_plugins', 'wpallbackup-listing', array('WPALLMenu', 'renderAdminPage'), WPALLBK_PLUGIN_URL . '/assets/images/wpallbk.png');
        add_submenu_page('wpallbackup-listing', 'Setting', 'Setting', 'manage_options', 'wpallbackup-settings', array('WPALLMenu', 'wpallbackupSettings'));
        add_submenu_page('wpallbackup-listing', 'Destination', 'Destination', 'manage_options', 'wpallbackup-destination', array('WPALLMenu', 'wpallbackupDestination'));
        add_submenu_page('wpallbackup-listing', 'Help', 'Help', 'manage_options', 'wpallbackup-help', array('WPALLMenu', 'wpallbackupHelp'));
    }

    static function renderAdminPage() {
        include('create-backup.php');
    }

    static function wpallbackupSettings() {
        include('wpallbackup-settings.php');
    }

    static function wpallbackupDestination() {
        include('wpallbackup-destination.php');
    }

    static function wpallbackupHelp() {
        include('wpallbackup-help.php');
    }

    //Start Fixed Vulnerability 22-06-2016 for prevent direct download
    function wp_all_cookie_expiration() {
        setcookie('can_download', 0, time() - 300, COOKIEPATH, COOKIE_DOMAIN);
        if (SITECOOKIEPATH != COOKIEPATH) {
            setcookie('can_download', 0, time() - 300, SITECOOKIEPATH, COOKIE_DOMAIN);
        }
    }
    //End
    function wp_all_backup_admin_init() {
        //Start Fixed Vulnerability
           if (isset($_GET['page']) && $_GET['page'] == 'wpallbackup-listing' && current_user_can('manage_options')) {
            setcookie('can_download', 1, 0, COOKIEPATH, COOKIE_DOMAIN);
            if (SITECOOKIEPATH != COOKIEPATH) {
                setcookie('can_download', 1, 0, SITECOOKIEPATH, COOKIE_DOMAIN);
            }
        } else {
            setcookie('can_download', 0, time() - 300, COOKIEPATH, COOKIE_DOMAIN);
            if (SITECOOKIEPATH != COOKIEPATH) {
                setcookie('can_download', 0, time() - 300, SITECOOKIEPATH, COOKIE_DOMAIN);
            }
        }
// End Fixed Vulnerability 22-06-2016 for prevent direct download
        if (is_admin()) {
            if (isset($_GET['action']) && current_user_can('manage_options')) {
                switch ((string) $_GET['action']) {
                    case 'create':
                        $this->wp_all_backup_event_process();
                        wp_redirect(site_url() . '/wp-admin/admin.php?page=wpallbackup-listing&notification=create');
                        break;
                    case 'removebackuppro':
                        $index = (int) $_GET['index'];
                        $options = (array)get_option('wp_db_backup_backups');
                        $newoptions = array();
                        $count = 0;
                        foreach ($options as $option) {
                            if ($count != $index) {
                                $newoptions[] = $option;
                            }
                            $count++;
                        }
                        if(isset($options[$index]['dir'])){
                            @unlink($options[$index]['dir']);
                        }
                        
                        update_option('wp_db_backup_backups', $newoptions);
                        $nonce = wp_create_nonce( 'wp-database-backup' );
                        wp_safe_redirect( site_url() . '/wp-admin/admin.php?page=wp-database-backup&notification=delete&_wpnonce=' . $nonce );
                        break;
                    case 'restorebackuppro':
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
                        break;
                }
            }
        }
        register_setting('wp_all_backup_options', 'wp_all_backup_options', array($this, 'wp_all_backup_validate'));
    }

    function wp_all_backup_validate($input) {
        return $input;
    }

    function wp_all_backup_event_process() {
        //added in v.2.4 for time out issue
        ini_set("max_execution_time", "5000");
        ini_set("max_input_time", "5000");
        ini_set('memory_limit', '1000M');
        set_time_limit(0);

        $details = $this->wp_all_backup_create_archive();
        $options = get_option('wp_db_backup_backups');
        $Destination = "";
        $logMessageAttachment = "";
        $logMessage = "";
        if (!$options) {
            $options = array();
        }

        //Email
      
        if (get_option('wp_db_log') == 1) {
            $this->write_log($details['logfileDir'], $logMessage);
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

        update_option('wp_db_backup_backups', $options);
        $destination="Local, ";
         $args = array($details['filename'], $details['dir'], $logMessage, $details['size'],$details['logfileDir'],$details['type'],$destination);     
         do_action_ref_array('wp_all_backup_completed', array(&$args));
    }

    function wp_all_backup_format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    function wp_all_backup_create_archive() {
        $source_directory = WPAllBackup::wp_all_backup_wp_config_path();
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
        $this->wpallbk_mysqldump($FileName, $logFile);
        if (get_option('wp_db_backup_backup_type') == 'complete') {
            $handle = fopen(WPAllBackup::wp_all_backup_wp_config_path() . '/wp_installer.php', 'w+');
            fwrite($handle, $this->wp_all_backup_create_installer($FileName));
            fclose($handle);
        }
        //End Number of backups to store on this server 
        $methodZip = 0;
        if ($this->get_zip_command_path() && (get_option('wp_db_backup_backup_type') == 'File' || get_option('wp_db_backup_backup_type') == 'complete')) {
            if (!$this->zip($path_info['basedir'] . '/' . WPDB_BACKUPS_DIR . '/' . $WPDBFileName)) {
                $methodZip = 1;
             //   error_log('Error : zip');
            }
        } else {
            $methodZip = 1;
           // error_log('Error : get_zip_command_path');
        }
        if ($methodZip == 0) {
          //  error_log("Zip method: Zip cmd");
            $logMessage.="\n Exclude Folders and Files : " . get_option('wp_db_backup_exclude_dir');
            return $this->wpallbk_update_backup_info($FileName, $logFile, $logMessage);
        } else if (class_exists('ZipArchive')) {
            $logMessage .= "\n Zip method: ZipArchive \n";
            $zip = new ZipArchive;
            $zip->open($path_info['basedir'] . '/' . WPDB_BACKUPS_DIR . '/' . $WPDBFileName, ZipArchive::CREATE);

            if (get_option('wp_db_backup_backup_type') == 'Database' || get_option('wp_db_backup_backup_type') == 'complete') {

                $filename = $FileName . '.sql';

                $zip->addFile(WPAllBackup::wp_all_backup_wp_config_path() . '/' . $filename, $filename);
            }
            if (get_option('wp_db_backup_backup_type') == 'File' || get_option('wp_db_backup_backup_type') == 'complete') {

                $wp_all_backup_exclude_dir = get_option('wp_db_backup_exclude_dir');
                if (empty($wp_all_backup_exclude_dir)) {
                    $excludes = WPDB_BACKUPS_DIR;
                } else {
                    $excludes = WPDB_BACKUPS_DIR . '|' . $wp_all_backup_exclude_dir;
                }
                $logMessage.="\n Exclude Folders and Files :  $excludes";
                foreach ($this->get_files() as $file) {
                    // Skip dot files,
                    if (method_exists($file, 'isDot') && $file->isDot())
                        continue;

                    // Skip unreadable files
                    if (!@realpath($file->getPathname()) || !$file->isReadable())
                        continue;

                    // Excludes
                    if ($excludes && preg_match('(' . $excludes . ')', str_ireplace(trailingslashit($this->get_root()), '', self::conform_dir($file->getPathname()))))
                        continue;

                    if ($file->isDir())
                        $zip->addEmptyDir(trailingslashit(str_ireplace(trailingslashit($this->get_root()), '', self::conform_dir($file->getPathname()))));

                    elseif ($file->isFile()) {
                        $zip->addFile($file->getPathname(), str_ireplace(trailingslashit($this->get_root()), '', self::conform_dir($file->getPathname())));
                        $logMessage .= "\n Added File: " . $file->getPathname();
                    }

                    if (++$files_added % 500 === 0)
                        if (!$zip->close() || !$zip->open($path_info['basedir'] . '/' . WPDB_BACKUPS_DIR . '/' . $WPDBFileName, ZIPARCHIVE::CREATE))
                            return;
                }
            }
            $zip->close();

            return $this->wpallbk_update_backup_info($FileName, $logFile, $logMessage);
        } else {


          //  error_log("Class ZipArchive Not Present");
            $logMessage .= "\n Zip method: pclzip \n";
            // set maximum execution time go non stop                        
            // Include the PclZip library
            require_once( 'lib/class-pclzip.php' );

            // Set the arhive filename
            $arcname = $path_info['basedir'] . '/' . WPDB_BACKUPS_DIR . '/' . $WPDBFileName;
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
                $v_dir = WPAllBackup::wp_all_backup_wp_config_path() . '/' . $filename;

                $v_remove = WPAllBackup::wp_all_backup_wp_config_path();

                // Create the archive
                $v_list = $archive->create($v_dir, PCLZIP_OPT_REMOVE_PATH, $v_remove);
                if ($v_list == 0) {
                    error_log("ERROR : '" . $archive->errorInfo(true) . "'");
                }
            } else {
                $v_dir = WPAllBackup::wp_all_backup_wp_config_path();

                $v_remove = $v_dir;
                // Create the archive
                $v_list = $archive->create($v_dir, PCLZIP_OPT_REMOVE_PATH, $v_remove);
                if ($v_list == 0) {
                    error_log("Error : " . $archive->errorInfo(true));
                }
            }
            return $this->wpallbk_update_backup_info($FileName, $logFile, $logMessage);
        }
    }

    function wp_all_backup_create_installer($filename) {
        $content = file_get_contents(WPDB_PATH . 'includes/admin/template/template_install.txt');
        $content = str_replace('WPALLBK_SITEURL', site_url(), $content);
        $content = str_replace('WPALLBK_DATABASE_FILE', $filename . ".sql", $content);
        $content = str_replace('WPALLBK_ZIP_FILE', $filename . ".zip", $content);
        return $content;
    }

    private function wp_all_backup_create_mysql_backup($logFile) {
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
        foreach ($tables as $table) {
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
        }
        $wpdb->flush();
        $logMessage .= "\n#--------------------------------------------------------\n";
        /* BEGIN : Prevent saving backup plugin settings in the database dump */
        add_option('wp_db_backup_backups', $options_backup);
        add_option('wp_all_backup_options', $settings_backup);
        /* END : Prevent saving backup plugin settings in the database dump */
        if (get_option('wp_db_log') == 1) {
            $this->write_log($logFile, $logMessage);
            $upload_path['logfile'] = $logFile;
        } else {
            $upload_path['logfile'] = "";
        }
        return $output;
    }

    public function wpallbk_mysqldump($FileName, $logFile) {
        if (get_option('wp_db_backup_backup_type') == 'Database' || get_option('wp_db_backup_backup_type') == 'complete') {

            $filename = $FileName . '.sql';
            /* Begin : Generate SQL DUMP using cmd 06-03-2016 */
            $mySqlDump = 0;
            if ($this->get_mysqldump_command_path()) {
                if (!$this->mysqldump(WPAllBackup::wp_all_backup_wp_config_path() . '/' . $filename)) {
                    $mySqlDump = 1;
                } else {
                    $logMessage = "\n# Database dump method: mysqldump";
                }
            } else {
                $mySqlDump = 1;
            }
            if ($mySqlDump == 1) {
                $handle = fopen(WPAllBackup::wp_all_backup_wp_config_path() . '/' . $filename, 'w+');
                fwrite($handle, $this->wp_all_backup_create_mysql_backup($logFile));
                fclose($handle);
                $logMessage = "\n# Database dump method: PHP";
            }
            if (get_option('wp_db_log') == 1) {
                //$logMessage.="\n# Exclude Table : " . @implode(', ', get_option('wp_db_exclude_table'));
                $this->write_log($logFile, $logMessage);
            }
        }
    }

    public function wpallbk_update_backup_info($FileName, $logFile, $logMessage = '') {
        $path_info = wp_upload_dir();
        $filename = $FileName . '.sql';
        $WPDBFileName = $FileName . '.zip';
        @unlink(WPAllBackup::wp_all_backup_wp_config_path() . '/' . $filename);
        @unlink(WPAllBackup::wp_all_backup_wp_config_path() . '/wp_installer.php');
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
        do_action_ref_array('wp_all_backup_complete_backup', array(&$args));
        return $upload_path;
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

    public function get_archive_method() {
        return $this->archive_method;
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

    public function get_excludes() {

        $excludes = array();
        $wp_all_backup_exclude_dir = get_option('wp_db_backup_exclude_dir');
        if (empty($wp_all_backup_exclude_dir)) {
            $excludes = WPDB_BACKUPS_DIR;
        } else {
            $excludes = WPDB_BACKUPS_DIR . '|' . $wp_all_backup_exclude_dir;
        }
        if (is_string($excludes))
            $excludes = explode('|', $excludes);

        // If path() is inside root(), exclude it
        if (strpos(WPAllBackup::wp_all_backup_wp_config_path(), $this->get_root()) !== false)
            array_unshift($excludes, trailingslashit(WPAllBackup::wp_all_backup_wp_config_path()));

        return array_unique($excludes);
    }

    public function set_excludes($excludes, $append = false) {
        $wp_all_backup_exclude_dir = get_option('wp_db_backup_exclude_dir');
        if (empty($wp_all_backup_exclude_dir)) {
            $excludes = WPDB_BACKUPS_DIR;
        } else {
            $excludes = WPDB_BACKUPS_DIR . '|' . $wp_all_backup_exclude_dir;
        }
        if (is_string($excludes))
            $excludes = explode('|', $excludes);

        if ($append)
            $excludes = array_merge($this->excludes, $excludes);

        $this->excludes = array_filter(array_unique(array_map('trim', $excludes)));
    }

    private function warning($context, $warning) {

        if (empty($context) || empty($warning))
            return;



        $this->warnings[$context][$_key = md5(implode(':', (array) $warning))] = $warning;
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

    /* End : Generate Zip using cmd 06-03-2016 V.3.9 */
    /* Begin : Generate SQL DUMP using cmd 06-03-2016 V.3.9 */

    public function set_zip_command_path($path) {

        $this->zip_command_path = $path;
    }

    public function set_mysqldump_command_path($path) {

        $this->mysqldump_command_path = $path;
    }

    public function get_mysqldump_command_path() {

        // Check shell_exec is available
        if (!self::is_shell_exec_available())
            return '';

        // Return now if it's already been set
        if (isset($this->mysqldump_command_path))
            return $this->mysqldump_command_path;

        $this->mysqldump_command_path = '';

        // Does mysqldump work
        if (is_null(shell_exec('hash mysqldump 2>&1'))) {

            // If so store it for later
            $this->set_mysqldump_command_path('mysqldump');

            // And return now
            return $this->mysqldump_command_path;
        }

        // List of possible mysqldump locations
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
            '/Program Files/MySQL/MySQL Server 4.1/bin/mysqldump'
        );

        // Find the one which works
        foreach ($mysqldump_locations as $location)
            if (@is_executable(self::conform_dir($location)))
                $this->set_mysqldump_command_path($location);

        return $this->mysqldump_command_path;
    }

    public static function is_shell_exec_available() {

        // Are we in Safe Mode
        if (self::is_safe_mode_active())
            return false;

        // Is shell_exec or escapeshellcmd or escapeshellarg disabled?
        if (array_intersect(array('shell_exec', 'escapeshellarg', 'escapeshellcmd'), array_map('trim', explode(',', @ini_get('disable_functions')))))
            return false;

        // Can we issue a simple echo command?
        if (!@shell_exec('echo WP Backup'))
            return false;

        return true;
    }

    public static function is_safe_mode_active($ini_get_callback = 'ini_get') {

        if (( $safe_mode = @call_user_func($ini_get_callback, 'safe_mode') ) && strtolower($safe_mode) != 'off')
            return true;

        return false;
    }

    public function mysqldump($SQLfilename) {

        $this->mysqldump_method = 'mysqldump';



        $host = explode(':', DB_HOST);

        $host = reset($host);
        $port = strpos(DB_HOST, ':') ? end(explode(':', DB_HOST)) : '';

        // Path to the mysqldump executable
        $cmd = escapeshellarg($this->get_mysqldump_command_path());

        // We don't want to create a new DB
        $cmd .= ' --no-create-db';

        // Allow lock-tables to be overridden
        if (!defined('WPDB_MYSQLDUMP_SINGLE_TRANSACTION') || WPDB_MYSQLDUMP_SINGLE_TRANSACTION !== false)
            $cmd .= ' --single-transaction';

        // Make sure binary data is exported properly
        $cmd .= ' --hex-blob';

        // Username
        $cmd .= ' -u ' . escapeshellarg(DB_USER);

        // Don't pass the password if it's blank
        if (DB_PASSWORD)
            $cmd .= ' -p' . escapeshellarg(DB_PASSWORD);

        // Set the host
        $cmd .= ' -h ' . escapeshellarg($host);

        // Set the port if it was set
        if (!empty($port) && is_numeric($port))
            $cmd .= ' -P ' . $port;

        // The file we're saving too
        $cmd .= ' -r ' . escapeshellarg($SQLfilename);

        $wp_db_exclude_table = array();
        $wp_db_exclude_table = get_option('wp_db_exclude_table');
        if (!empty($wp_db_exclude_table)) {
            foreach ($wp_db_exclude_table as $wp_db_exclude_table) {
                $cmd .= ' --ignore-table=' . DB_NAME . '.' . $wp_db_exclude_table;
                // error_log(DB_NAME.'.'.$wp_db_exclude_table);
            }
        }

        // The database we're dumping
        $cmd .= ' ' . escapeshellarg(DB_NAME);

        // Pipe STDERR to STDOUT
        $cmd .= ' 2>&1';
        // Store any returned data in an error
        $stderr = shell_exec($cmd);

        // Skip the new password warning that is output in mysql > 5.6 
        if (trim($stderr) === 'Warning: Using a password on the command line interface can be insecure.') {
            $stderr = '';
        }

        if ($stderr) {
            $this->error($this->get_mysqldump_method(), $stderr);
            error_log($stderr);
        }

        return $this->verify_mysqldump($SQLfilename);
    }

    public function error($context, $error) {

        if (empty($context) || empty($error))
            return;

        $this->errors[$context][$_key = md5(implode(':', (array) $error))] = $error;
    }

    public function verify_mysqldump($SQLfilename) {

        //$this->do_action( 'wpdb_mysqldump_verify_started' );
        // If we've already passed then no need to check again
        if (!empty($this->mysqldump_verified))
            return true;

        // If there are mysqldump errors delete the database dump file as mysqldump will still have written one
        if ($this->get_errors($this->get_mysqldump_method()) && file_exists($SQLfilename))
            unlink($SQLfilename);

        // If we have an empty file delete it
        if (@filesize($SQLfilename) === 0)
            unlink($SQLfilename);

        // If the file still exists then it must be good
        if (file_exists($SQLfilename))
            return $this->mysqldump_verified = true;

        return false;
    }

    public function get_errors($context = null) {

        if (!empty($context))
            return isset($this->errors[$context]) ? $this->errors[$context] : array();

        return $this->errors;
    }

    public function get_mysqldump_method() {
        return $this->mysqldump_method;
    }

    /* End : Generate SQL DUMP using cmd 06-03-2016 */

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

    public static function conform_dir($dir, $recursive = false) {

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
        if (!$recursive && self::conform_dir($dir, true) != $dir)
            return self::conform_dir($dir);

        return (string) $dir;
    }

    public function wp_all_backup_cron_schedules($schedules) {
        $schedules['hourly'] = array(
            'interval' => 3600,
            'display' => 'hourly'
        );
        $schedules['twicedaily'] = array(
            'interval' => 43200,
            'display' => 'twicedaily'
        );
        $schedules['daily'] = array(
            'interval' => 86400,
            'display' => 'daily'
        );
        $schedules['weekly'] = array(
            'interval' => 604800,
            'display' => 'weekly'
        );
        $schedules['monthly'] = array(
            'interval' => 2635200,
            'display' => 'monthly'
        );
        return $schedules;
    }

    public function wp_all_backup_scheduler_activation() {
        $options = get_option('wp_all_backup_options');
        if ((!wp_next_scheduled('wp_all_backup_event')) && (isset($options['enable_autobackups']))) {
            wp_schedule_event(time(), $options['autobackup_frequency'], 'wp_all_backup_event');
        }
    }    

}

// end WPALLMenu

$WPALLMenu = new WPALLMenu();