<?php
/*
 * Plugin Name: User Name Availability Checker for wordpress/buddypress
 * Version: 1.0.1
 * Author: Brajesh Singh
 * Author URI: http://buddydev.com
 * Plugin URI: http://buddydev.com/buddypress/creating-a-buddypress-wordpress-username-availability-checker-for-your-site
 * Description: Check the availability of Username on registration page
 * Last Modified: 21st september 2010
 * License : GPL 
 */

 /* I am putting the text domain to buddypress for now, so if you want, you can customize the text by putting it in your bp languages file, hmm, just I am a little bit lazy to put the code here as It is a tiny plugin*/
	
$uac_dir =str_replace(basename( __FILE__),"",plugin_basename(__FILE__));
define("UAC_DIR_NAME",$uac_dir);//the directory name of availability Checker
define("UAC_PLUGIN_DIR",WP_PLUGIN_DIR."/".UAC_DIR_NAME);
define("UAC_PLUGIN_URL",WP_PLUGIN_URL."/".UAC_DIR_NAME);

 
function bpdev_ua_enqueue_script(){
if(bp_is_page(BP_REGISTER_SLUG)){
	wp_enqueue_script("jquery");
	wp_enqueue_script("json2");
	wp_enqueue_script("uachecker",UAC_PLUGIN_URL."script.js");

}

}
add_action("wp_print_scripts", "bpdev_ua_enqueue_script");
/*include the css file, I suggest including in your theme rather than this plugin, why load an extra css file ? */
add_action("wp_print_styles", "bpdev_ua_enqueue_style");
function bpdev_ua_enqueue_style(){
if(bp_is_page(BP_REGISTER_SLUG))
wp_enqueue_style("ua-css",UAC_PLUGIN_URL."/style.css");
}
//ajax for checking the availability

//return success/error and info
function bpdev_ua_check_username(){
    include_once(ABSPATH.WPINC."/registration.php");//we need o include this other wise username_exists will not favor us :)
    if(!empty($_POST["user_name"])){
        $user_name=sanitize_user($_POST["user_name"]);
        if(username_exists($user_name))
            $msg=array("code"=>"taken","message"=>__("This usename is taken, please choose another one.","buddypress"));
        else if(function_exists("get_current_site")){//for mu
            global $wpdb;
            //check for the username in the signups table
            $user=$wpdb->get_row( $wpdb->prepare("SELECT * FROM $wpdb->signups WHERE user_login = %s", $user_name) );
            if(!empty($user))
                $msg=array("code"=>"registered","message"=>__("This username is registered but not activated. It may be available within few days if not activated. Please check back again for the availability.","buddypress"));
        }

    if(empty ($msg)){//so all is well, but now let us validate
        $check=bpdev_validate_username($user_name);
        if(empty($check))
        $msg=array("code"=>"success","message"=>__("Congrats! The username is available.","buddypress"));
        else
          $msg=array("code"=>"error","message"=>$check);
        }
     
    }
else
 $msg=array("code"=>"error","message"=>__("Username Can not be empty!","buddypress"));
     
echo json_encode($msg);	
}

add_action("wp_ajax_check_username","bpdev_ua_check_username");//hook to ajax action

/* helper function to check the username is valid or not, thanks to @apeatling, taken from bp-core/bp-core-signup.php and modified for chacking only the username
 * original:bp_core_validate_user_signup()
 *
 *  */
function bpdev_validate_username( $user_name) {
	global $wpdb;

	$errors = new WP_Error();
	$maybe = array();
	preg_match( "/[a-z0-9]+/", $user_name, $maybe );

	$db_illegal_names = get_site_option( 'illegal_names' );
	$filtered_illegal_names = apply_filters( 'bp_core_illegal_usernames', array( 'www', 'web', 'root', 'admin', 'main', 'invite', 'administrator', BP_GROUPS_SLUG, BP_MEMBERS_SLUG, BP_FORUMS_SLUG, BP_BLOGS_SLUG, BP_REGISTER_SLUG, BP_ACTIVATION_SLUG ) );

	$illegal_names = array_merge( (array)$db_illegal_names, (array)$filtered_illegal_names );
	update_site_option( 'illegal_names', $illegal_names );

	if ( !validate_username( $user_name ) || in_array( $user_name, (array)$illegal_names ) || $user_name != $maybe[0] )
	   $error= __( 'Only lowercase letters and numbers allowed', 'buddypress' );

	if( strlen( $user_name ) < 4 )
	   $error=  __( 'Username must be at least 4 characters', 'buddypress' ) ;

	if ( strpos( ' ' . $user_name, '_' ) != false )
	$error= __( 'Sorry, usernames may not contain the character "_"!', 'buddypress' ) ;

	/* Is the user_name all numeric? */
	$match = array();
	preg_match( '/[0-9]*/', $user_name, $match );

	if ( $match[0] == $user_name )
		$error= __( 'Sorry, usernames must have letters too!', 'buddypress' ) ;

	
	
	return $error;

}
?>