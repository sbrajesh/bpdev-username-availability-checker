<?php
/*
 * Plugin Name: BuddyDev Username Availability Checker
 * Version: 1.1.1
 * Author: Brajesh Singh
 * Author URI: http://buddydev.com
 * Plugin URI: http://buddydev.com/buddydev-username-availability-checker/
 * Description: Check the availability of Username on WordPress/BuddyPress registration/add new user screens
 * Last Modified: October 24, 2015
 * License : GPL 
 */

class BuddyDev_Username_Availability_Checker {
	
	private static $instance = null;
	private $path;
	private $url;
	
	private function __construct() {
		
		$this->path = plugin_dir_path( __FILE__ );
		$this->url = plugin_dir_url( __FILE__ );
		
		$this->setup_hooks();
	}
	
	public static function get_instance() {
		
		if( is_null(self::$instance ) ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
	
	private function setup_hooks() {
		//load translations
		add_action( 'init', array( $this, 'load_textdomain' ) );
		
		//load css/js on front end
		add_action( 'wp_enqueue_scripts', array( $this, 'load_js' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'load_css' ) );
		//on wp-login.php for action=register
		add_action( 'login_enqueue_scripts', array( $this, 'load_js' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'load_css' ) );
		add_action( 'login_head', array( $this, 'add_ajax_url' ) );
		
		//load assets on admin Add new user screen
		add_action( 'admin_enqueue_scripts', array( $this, 'load_js' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_css' ) );
		//ajax check
		add_action( 'wp_ajax_check_username', array( $this, 'ajax_check_username' ) );//hook to ajax action
		add_action( 'wp_ajax_nopriv_check_username', array( $this, 'ajax_check_username' ) );//hook to ajax action
			
	}
	
    public function load_textdomain() {
		
		load_plugin_textdomain( 'bpdev-username-availability-checker', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }
	
	public function ajax_check_username() {
		
		if( empty( $_POST['user_name'] ) ) {
			
			wp_send_json( array(
				'code'		=> 'error',
				'message'	=> __( 'Username Can not be empty!', 'bpdev-username-availability-checker' ) 
			));
			//if uusername is empty, the execution wills top here
		}
		
	
		$user_name = sanitize_user( $_POST['user_name'] ) ;
			
		if( username_exists( $user_name ) ) {
				
			$message = array(
				'code'		=> 'taken',
				'message'	=> __( 'This usename is taken, please choose another one.','bpdev-username-availability-checker' )
			);
				
		} elseif ( is_multisite() ) {
			//for mu
			global $wpdb;
			 //check for the username in the signups table
			$user = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE user_login = %s", $user_name ) );

			if( ! empty( $user ) ) {
				$message = array(
					'code'		=> 'registered',
					'message'	=>__( 'This username is registered but not activated. It may be available within few days if not activated. Please check back again for the availability.', 'bpdev-username-availability-checker' ) 
				);
			}
		}

		if( empty( $message ) ) {//so all is well, but now let us validate
			$check = $this->validate_username( $user_name );

			if ( empty( $check ) ) {
				$message = array(
					'code'		=> 'success',
					'message'	=> __( 'Congrats! The username is available.', 'bpdev-username-availability-checker' ) 
				);
			} else {

				$message = array(
					'code'		=> 'error',
					'message'	=> $check
				);
			}
		}
     
		
	
		wp_send_json( $message );
	}
	
	/**
	 * Load required js
	 */
	public function load_js() {
		
		if( $this->should_load_asset() ) {
			
			wp_enqueue_script( 'username-availability-checker-js', $this->url . "assets/username-availability-checker.js", array( 'jquery' ) );
			
			$data = array( 
				'selectors' => apply_filters( 'buddydev_uachecker_selectors', 'input#signup_username, form#createuser input#user_login, #registerform input#user_login' ) 
			);
			
			wp_localize_script( 'username-availability-checker-js', '_BDUAChecker', $data );
		}
	}
	
	public function load_css() {
		
		if( $this->should_load_asset() ) {
			wp_enqueue_style( 'username-availability-checker-css', $this->url . 'assets/username-availability-checker.css' );
		}
	}
	
	public function add_ajax_url() {
	?>
	<script type="text/javascript">
		var ajaxurl = "<?php echo admin_url('admin-ajax.php');?>";
	</script>	
	<?php 		
	}
	/**
	 * Check whether to load assets or not?
	 * 
	 * @return boolean whether t load assets or not
	 */
	public function should_load_asset() {
		global $pagenow;
		
		$load = false;
		
		if( function_exists( 'bp_is_register_page' ) && bp_is_register_page() ) {
			$load = true;
		} elseif ( is_admin() && get_current_screen()->id == 'user' && get_current_screen()->action =='add' ) {
			$load = true;
		} elseif( $pagenow == 'wp-login.php' && isset( $_GET['action'] ) && $_GET['action'] =='register' ) {
			$load = true;
		}
		//sorry I should have renamed it buddydev_uachecker__load_assets but now I can not, my hads are tied
		return apply_filters( 'buddydev_username_availability_checker_load_assets', $load  );
		
	}
	

	/* Helper function to check the username is valid or not, 
	 * thanks to @apeatling, taken from bp-core/bp-core-signup.php and modified for chacking only the username
	 * original: bp_core_validate_user_signup()
	 *
	 * @return string fnothing if validated else error string 
	 * */
	private function validate_username( $user_name ) {
		
		$error = false;
		$maybe = array();
		
		preg_match( "/[a-z0-9]+/", $user_name, $maybe );

		//$db_illegal_names = get_site_option( 'illegal_names' );
		
		//$filtered_illegal_names = apply_filters( 'bp_core_illegal_usernames', array( 'www', 'web', 'root', 'admin', 'main', 'invite', 'administrator', BP_GROUPS_SLUG, BP_MEMBERS_SLUG, BP_FORUMS_SLUG, BP_BLOGS_SLUG, BP_REGISTER_SLUG, BP_ACTIVATION_SLUG ) );

		$illegal_names = function_exists( 'bp_core_get_illegal_names' ) ? bp_core_get_illegal_names() : array(); //array_merge( (array)$db_illegal_names, (array)$filtered_illegal_names );
		//update_site_option( 'illegal_names', $illegal_names );

		if ( ! validate_username( $user_name ) || in_array( $user_name, ( array ) $illegal_names ) || ( isset( $maybe[0] ) && $user_name != $maybe[0] ) ) {
		   $error= __( 'Only lowercase letters and numbers allowed', 'bpdev-username-availability-checker' );
		}

		if ( strlen( $user_name ) < 4 ) {
		   $error=  __( 'Username must be at least 4 characters', 'buddypress' ) ;
		}
		
		if ( strpos( ' ' . $user_name, '_' ) != false ) {
			$error= __( 'Sorry, usernames may not contain the character "_"!', 'bpdev-username-availability-checker' ) ;
		}
		
		/* Is the user_name all numeric? */
		$match = array();
		
		preg_match( '/[0-9]*/', $user_name, $match );

		if ( $match[0] == $user_name ) {
			$error= __( 'Sorry, usernames must have letters too!', 'bpdev-username-availability-checker' ) ;
		}
		
		//Let others dictate us
		//the devine message to show the users in case of failure
		//success is empty, never forget that.
		return apply_filters( 'buddydev_uachecker_username_error', $error, $user_name );

	}

	
}

//instantiate
BuddyDev_Username_Availability_Checker::get_instance();