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


    wp_register_script( 'wpdbbkp-make-better-js', WPDB_PLUGIN_URL . '/assets/js/make-better-admin.js', array( 'jquery' ), WPDB_VERSION);
    wp_localize_script('wpdbbkp-make-better-js', 'wpdbbkp_pub_script_vars', array(
        'nonce' => wp_create_nonce( 'wpdbbkp-pub-nonce' ),)
    );
    wp_enqueue_script( 'wpdbbkp-make-better-js');
    wp_enqueue_style( 'wpdbbkp-make-better-css', WPDB_PLUGIN_URL . '/assets/css/make-better-admin.css', false , WPDB_VERSION);
}

    add_filter('admin_footer', 'wpdbbkp_add_deactivation_feedback_modal');