<?php

/**
 * Restore Database and file class
 *
 * @version 1.0
 */
class Wpdbbkp_Restore {
        /**
         * The path where the backup file is stored
         *
         * @string
         * @access private
         */
        private $path = '';

        /**
         * The backup type, must be either complete, file or database
         *
         * @string
         * @access private
         */
        private $type = '';

        function __construct() {

        }


        public function start( $id ) {      
                $options = get_option('wp_db_backup_backups');
                if($id && isset($options[$id]['type']) && isset($options[$id]['dir'])){
                        $this->type = $options[$id]['type'];
                        $this->path = $options[$id]['dir'];
                        $this->restore();
                } 
               
        }

        public function restore() {
                if ( ! class_exists( 'PclZip' ) ){
                        require ABSPATH . 'wp-admin/includes/class-pclzip.php';
                }

                if ( $this->type == 'Complete' || $this->type == 'complete' ){
                        $this->restore_complete();
                }

                if ( $this->type == 'Database' || $this->type == 'database' ){
                        $this->restore_database();
                }

                if ( $this->type == 'File' || $this->type == 'file' ){
                        $this->restore_files();
                }
        }

        public function restore_complete() {
                $filename = basename( $this->path, '.zip' ) . '.sql';
                $file_path = ABSPATH . $filename;

                $this->restore_files();

                $this->restore_database( $file_path );
        }

        public function restore_database($file = null) {
                global $wpdb, $wp_filesystem;
            
                if (!$file) {
                    $archive = new PclZip($this->path);
                    $filename = basename($this->path, '.zip') . '.sql';
                    $path_info = wp_upload_dir();
                    $dir = $path_info['basedir'] . '/' . WPDB_BACKUPS_DIR . '/';
                    $file_path = $dir . $filename;
            
                    if (!$archive->extract(PCLZIP_OPT_PATH, $dir)) {
                        wp_die(
                            esc_html__('Unable to extract zip file. Please check that zlib php extension is enabled.', 'wpdbbkp') . 
                            '<button onclick="history.go(-1);">' . esc_html__('Go Back', 'wpdbbkp') . '</button>', 
                            esc_html__('ZIP Error', 'wpdbbkp')
                        );
                    }
                } else {
                    $file_path = $file;
                }
            
                $database_file = $file_path;
                $database_name = $this->wp_backup_get_config_db_name();
                $database_user = $this->wp_backup_get_config_data('DB_USER');
                $database_password = $this->wp_backup_get_config_data('DB_PASSWORD');
                $database_host = $this->wp_backup_get_config_data('DB_HOST');
            
                ini_set("max_execution_time", "5000"); //phpcs:ignore --Make sure the restore script doesn't timeout
                ini_set("max_input_time", "5000"); //phpcs:ignore --Make sure the restore script doesn't timeout
                ini_set('memory_limit', '1000M'); //phpcs:ignore --Make sure the restore script doesn't timeout
                set_time_limit(0); //phpcs:ignore  --Make sure the restore script doesn't timeout
                ignore_user_abort(true);
            
                if ('' !== trim($database_name) && '' !== trim($database_user) && '' !== trim($database_host)) {
                    $wpdb->db_connect();
                    $wpdb->select($database_name);
            
                    // Check if database exists
                    //phpcs:ignore -- check if database exists
                    $db_exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = %s",
                        $database_name
                    ));
            
                    if (!$db_exists) {
                        //phpcs:ignore -- create database if it doesn't exist
                        $wpdb->query($wpdb->prepare("CREATE DATABASE IF NOT EXISTS `%s`", $database_name));
                        $wpdb->select($database_name);
                    }
                    //phpcs:ignore -- get all tables in the database
                    $tables = $wpdb->get_col($wpdb->prepare("SHOW TABLES FROM `%s`", $database_name));

              
                if (!empty($tables)) {
                        foreach ($tables as $table_name) {
                                //phpcs:ignore -- drop all tables in the database before restore
                                $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS `%s`", $table_name));
                        }
                }
            
                    // Restore database content
                    if (file_exists($database_file)) {
                        if ( ! function_exists( 'WP_Filesystem' ) ) {
                                require_once ABSPATH . '/wp-admin/includes/file.php';
                            }

                            WP_Filesystem();
                            if ( $wp_filesystem ) {
                                $sql_file = $wp_filesystem->get_contents( $database_file );
                                if ( $sql_file !== false ) {
                                        $sql_queries = explode(";\n", $sql_file);
                                        //phpcs:ignore -- set sql_mode to empty to avoid sql_mode errors
                                        $wpdb->query("SET sql_mode = ''");
                        
                                        foreach ($sql_queries as $query) {
                                            $query = apply_filters('wpdbbkp_sql_query_restore', $query);
                                            if (!empty(trim($query))) {

                                                /* Since $query is a dynqmic sql query from the backup file, we can't use $wpdb->prepare
                                                * as we don't know the number / types of arguments in the query. So, we are using $wpdb->query
                                                * directly to execute the query.*/

                                                //phpcs:ignore -- execute the query
                                                $wpdb->query($query);
                                            }
                                        }

                                } 
                            } 
                    }
                }
            
                wp_delete_file($file_path);
            }
            

        public function restore_files( $file = null ) {
                if ( ! $file){
                        $archive = new PclZip( $this->path );
                }
                else{
                        $archive = new PclZip( $file );
                }

                if ( ! $archive->extract( PCLZIP_OPT_PATH, ABSPATH ) ){
                        wp_die( esc_html__('Unable to extract zip file. Please check that zlib php extension is enabled.','wpdbbkp').'<button onclick="history.go(-1);">'.esc_html__('Go Back','wpdbbkp').'</button>', esc_html__('ZIP Error','wpdbbkp') );
                }
        }

        public function wp_backup_get_config_db_name() {
                $filepath=get_home_path().'/wp-config.php';
                $config_file = @file_get_contents("$filepath", true);
                if($config_file){
                        preg_match("/'DB_NAME',\s*'(.*)?'/", $config_file, $matches);
                        return $matches[1];
                }
                
                return '';
        }

        public function wp_backup_get_config_data($key) {
                $filepath=get_home_path().'/wp-config.php';
                $config_file = @file_get_contents("$filepath", true);
                if($config_file){
                        switch($key) {
                                case 'DB_NAME':
                                        preg_match("/'DB_NAME',\s*'(.*)?'/", $config_file, $matches);
                                        break;
                                case 'DB_USER':
                                        preg_match("/'DB_USER',\s*'(.*)?'/", $config_file, $matches);
                                        break;
                                case 'DB_PASSWORD':
                                        preg_match("/'DB_PASSWORD',\s*'(.*)?'/", $config_file, $matches);
                                        break;
                                case 'DB_HOST':
                                        preg_match("/'DB_HOST',\s*'(.*)?'/", $config_file, $matches);
                                        break;
                        }
                        return $matches[1];
                }
                
                return '';
                
        }
}