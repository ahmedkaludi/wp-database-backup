<?php


// Exit if accessed directly
if( !defined( 'ABSPATH' ) )
    exit;

/**
 * Helper method to check if user is in the plugins page.
 *
 * @author 
 * @since  1.4.0
 *
 * @return bool
 */
function wpdbbkp_is_plugins_page() {
    if(function_exists('get_current_screen')){
        $screen = get_current_screen();
            if(is_object($screen)){
                if($screen->id == 'plugins' || $screen->id == 'plugins-network'){
                    return true;
                }
            }
    }
    return false;
}

function wpdbbkp_get_current_url(){
 
    $link = "http"; 
      
    if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'){
        $link = "https"; 
    } 
  
    $link .= "://"; 
    $link .= $_SERVER['HTTP_HOST']; 
    $link .= $_SERVER['REQUEST_URI']; 
      
    return $link;
}

/**
 * display deactivation logic on plugins page
 * 
 * @since 1.4.0
 */


function wpdbbkp_add_deactivation_feedback_modal() {
    
  
    if( !is_admin() && !wpdbbkp_is_plugins_page()) {
        return;
    }

    $current_user = wp_get_current_user();
    if( !($current_user instanceof WP_User) ) {
        $email = '';
    } else {
        $email = trim( $current_user->user_email );
    }

    require_once WPDB_PATH."includes/admin/deactivate-feedback.php";
    
}

/**
 * send feedback via email
 * 
 * @since 1.4.0
 */
function wpdbbkp_send_feedback() {

    if ( ! isset( $_POST['wpdbbkp_security_nonce'] ) ){
        return; 
    }
    if ( !wp_verify_nonce( $_POST['wpdbbkp_security_nonce'], 'wpdbbkp-pub-nonce' ) ){
    return;  
    } 

    if( ! current_user_can( 'manage_options' ) ) { 
        return;
     }

    if( isset( $_POST['data'] ) ) {
        parse_str( $_POST['data'], $form );
    }

    $text = '';
    if( isset( $form['wpdbbkp_disable_text'] ) ) {
        $text = implode( "\n\r", $form['wpdbbkp_disable_text'] );
    }

    $headers = array();

    $from = isset( $form['wpdbbkp_disable_from'] ) ? $form['wpdbbkp_disable_from'] : '';
    if( $from ) {
        $headers[] = "From: $from";
        $headers[] = "Reply-To: $from";
    }

    $subject = isset( $form['wpdbbkp_disable_reason'] ) ? $form['wpdbbkp_disable_reason'] : '(no reason given)';

    $subject = $subject.' - Backup for WP Publisher';

    if($subject == 'technical - Backup for WP Publisher'){

          $text = trim($text);

          if(!empty($text)){

            $text = 'technical issue description: '.$text;

          }else{

            $text = 'no description: '.$text;
          }
      
    }

    $success = wp_mail( 'team@magazine3.in', $subject, $text, $headers );

    wp_die();
}
add_action( 'wp_ajax_wpdbbkp_send_feedback', 'wpdbbkp_send_feedback' );
 


add_action( 'admin_enqueue_scripts', 'wpdbbkp_enqueue_makebetter_email_js' );

function wpdbbkp_enqueue_makebetter_email_js(){
 
    if( !is_admin() && !wpdbbkp_is_plugins_page()) {
        return;
    }


    wp_register_script( 'wpdbbkp-make-better-js', WPDB_PLUGIN_URL . '/assets/js/make-better-admin.js', array( 'jquery' ), WPDB_VERSION , true);
    wp_localize_script('wpdbbkp-make-better-js', 'wpdbbkp_pub_script_vars', array(
        'nonce' => wp_create_nonce( 'wpdbbkp-pub-nonce' ))
    );
    wp_enqueue_script( 'wpdbbkp-make-better-js');
    wp_enqueue_style( 'wpdbbkp-make-better-css', WPDB_PLUGIN_URL . '/assets/css/make-better-admin.css', false , WPDB_VERSION);
}

    add_filter('admin_footer', 'wpdbbkp_add_deactivation_feedback_modal');


 /*
 * Read the contents of a file using the WordPress filesystem API.
 *
 * @param string $file_path The path to the file.
 * @return string|false The file contents or false on failure.
 */
function wpdbbkp_read_file_contents($file_path) {
    global $wp_filesystem;

    // Initialize the WordPress filesystem, no more using file_get_contents function
    if (empty($wp_filesystem)) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
    }

    // Check if the file exists and is readable
    if ($wp_filesystem->exists($file_path) && $wp_filesystem->is_readable($file_path)) {
        return $wp_filesystem->get_contents($file_path);
    }

    return false;
}

/**
 * Write data to a file using the WordPress File System API for smaller files and PHP native functions for large files.
 *
 * @param string $filename The path to the file to write data to.
 * @param string $data The data to write.
 * @param bool $append Whether to append the data to the file (default: false).
 */
function wpdbbkp_write_file_contents( $filename, $data, $append = false ) {
    global $wp_filesystem;

    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    if ( ! WP_Filesystem() ) {
        return;
    }

    // Ensure the directory is writable.
    $dir = dirname( $filename );
    if ( ! $wp_filesystem->exists( $dir ) ) {
        $wp_filesystem->mkdir( $dir );
    }


    $file_size = $wp_filesystem->size( $filename );

    // Use the WordPress File System API for small files.
    if ( $file_size < 104857600 ) { // 100 MB as threshold for large files.
        // Check if the file exists; create it if it does not.
        if ( ! $wp_filesystem->exists( $filename ) ) {
            $wp_filesystem->put_contents( $filename, '', FS_CHMOD_FILE );
        }

        // Read current contents if appending.
        $current_content = $append ? $wp_filesystem->get_contents( $filename ) : '';

        // Write the new data.
        $wp_filesystem->put_contents( $filename, $current_content . $data, FS_CHMOD_FILE );
    } else {
        // Use native PHP functions for very large files.
        $mode = $append ? 'a' : 'w';
        //phpcs:ignore -- using native PHP functions for large files.
        $file = fopen( $filename, $mode );

        if ( $file ) {
            //phpcs:ignore -- using native PHP functions for large files.
            fwrite( $file, $data );
            //phpcs:ignore -- using native PHP functions for large files.
            fclose( $file );
        } else {
            error_log( "Failed to open file for writing: $filename" );
        }
    }
}

/**
 * Check if a directory is writable using the WordPress File System API.
 *
 * @param string $dir The path to the directory.
 * @return bool True if the directory is writable, false otherwise.
 */
function wpdbbkp_is_writable( $dir ) {
    global $wp_filesystem;

    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    if ( ! WP_Filesystem() ) {
        return false;
    }


    return $wp_filesystem->is_writable( $dir );
}


/**
 * Check if a file exists using the WordPress File System API.
 *
 * @param string $file The path to the file.
 * @return bool True if the file exists, false otherwise.
 */
function wpdbbkp_file_exists( $file ) {
    global $wp_filesystem;

    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    if ( ! WP_Filesystem() ) {
        return false;
    }

    return $wp_filesystem->exists( $file );
}

/**
 * Filters an array of backup files to ensure only unique filenames are included.
 *
 * @param array $backups Array of backup files.
 * @return array Filtered array of backup files with unique filenames.
 */
function wpdbbkp_filter_unique_filenames( $backups ) {
    $unique_filenames = [];
    $filtered_backups = [];

    if ( ! empty( $backups ) ) {
        foreach ( $backups as $backup ) {
            if ( isset( $backup['filename'] ) && ! in_array( $backup['filename'], $unique_filenames ) ) {
                $unique_filenames[] = $backup['filename'];
                $filtered_backups[] = $backup;
            }
        }
    }

    return $filtered_backups;
}

