<?php
/**
 * Plugin Name: WordPress Username Availability Checker
 * Version: 1.1.8
 * Author: BuddyDev
 * Author URI: https://buddydev.com
 * Plugin URI: https://buddydev.com/buddydev-username-availability-checker/
 * Description: Check the availability of Username on WordPress/BuddyPress registration/add new user screens
 * License : GPL
 */
// No direct access over web.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 0 );
}

class BuddyDev_Username_Availability_Checker {

	/**
	 * Singleton instance.
	 *
	 * @var BuddyDev_Username_Availability_Checker
	 */
	private static $instance = null;
	/**
	 * Absolute path to this plugin directory.
	 *
	 * @var string
	 */
	private $path;

	/**
	 * Absolute url to this plugin directory.
	 *
	 * @var string
	 */
	private $url;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version = '1.1.8';

	/**
	 * Constructor.
	 */
	private function __construct() {

		$this->path = plugin_dir_path( __FILE__ );
		$this->url  = plugin_dir_url( __FILE__ );

		$this->setup();
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return BuddyDev_Username_Availability_Checker
	 */
	public static function get_instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Setup hooks.
	 */
	private function setup() {
		// load translations.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// load css/js on front end.
		add_action( 'wp_head', array( $this, 'add_ajax_url' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'load_js' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'load_css' ) );
		// on wp-login.php for action=register.
		add_action( 'login_enqueue_scripts', array( $this, 'load_js' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'load_css' ) );
		add_action( 'login_head', array( $this, 'add_ajax_url' ) );

		// load assets on admin Add new user screen.
		add_action( 'admin_enqueue_scripts', array( $this, 'load_js' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_css' ) );
		// ajax check.
		// hook to ajax action.
		add_action( 'wp_ajax_check_username', array( $this, 'ajax_check_username' ) );
		// hook to ajax action.
		add_action( 'wp_ajax_nopriv_check_username', array( $this, 'ajax_check_username' ) );

	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'bpdev-username-availability-checker', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Check username via ajax.
	 */
	public function ajax_check_username() {

		if ( empty( $_POST['user_name'] ) ) {

			// if username is empty, the execution will stop here.
			wp_send_json( array(
				'code'    => 'error',
				'message' => __( 'Username Can not be empty!', 'bpdev-username-availability-checker' ),
			) );
		}


		$user_name = sanitize_user( $_POST['user_name'] );

		if ( username_exists( $user_name ) ) {

			$message = array(
				'code'    => 'taken',
				'message' => __( 'This usename is taken, please choose another one.', 'bpdev-username-availability-checker' ),
			);

		} elseif ( is_multisite() ) {
			// for multisite.
			global $wpdb;
			// check for the username in the signups table.
			$user = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE user_login = %s", $user_name ) );

			if ( ! empty( $user ) ) {
				$message = array(
					'code'    => 'registered',
					'message' => __( 'This username is registered but not activated. It may be available within few days if not activated. Please check back again for the availability.', 'bpdev-username-availability-checker' ),
				);
			}
		}

		if ( empty( $message ) ) {
			// so all is well, but now let us validate.
			$check = $this->validate_username( $user_name );

			if ( empty( $check ) ) {
				$message = array(
					'code'    => 'success',
					'message' => __( 'Congrats! The username is available.', 'bpdev-username-availability-checker' ),
				);
			} else {

				$message = array(
					'code'    => 'error',
					'message' => $check,
				);
			}
		}


		wp_send_json( $message );
	}

	/**
	 * Load required js
	 */
	public function load_js() {

		if ( $this->should_load_asset() ) {
			wp_enqueue_script( 'username-availability-checker-js', $this->url . 'assets/username-availability-checker.js', array( 'jquery' ), $this->version );

			$data = array(
				'selectors' => apply_filters( 'buddydev_uachecker_selectors', 'input#signup_username, form#createuser input#user_login, #registerform input#user_login, .lwa-register input#user_login, .woocommerce-form #reg_username, .woocommerce-checkout #account_username' ),
			);

			wp_localize_script( 'username-availability-checker-js', '_BDUAChecker', $data );
		}
	}

	/**
	 * Load css.
	 */
	public function load_css() {

		if ( $this->should_load_asset() ) {
			wp_enqueue_style( 'username-availability-checker-css', $this->url . 'assets/username-availability-checker.css', array(), $this->version );
		}
	}

	/**
	 * Add ajax end point.
	 */
	public function add_ajax_url() {
		?>
        <script type="text/javascript">
            var ajaxurl = "<?php echo admin_url( 'admin-ajax.php' );?>";
        </script>
		<?php
	}

	/**
	 * Check whether to load assets or not?
	 *
	 * @return boolean whether to load assets or not
	 */
	public function should_load_asset() {
		global $pagenow;

		$load = false;

		if ( function_exists( 'bp_is_register_page' ) && bp_is_register_page() ) {
			$load = true;
		} elseif ( is_admin() && function_exists( 'get_current_screen' ) && get_current_screen()->id == 'user' && get_current_screen()->action == 'add' ) {
			$load = true;
		} elseif ( $pagenow === 'wp-login.php' && isset( $_GET['action'] ) && $_GET['action'] == 'register' ) {
			$load = true;
		} elseif ( class_exists( 'LoginWithAjax' ) && ! is_user_logged_in() ) {
			$load = true;
		} elseif ( function_exists( 'is_account_page' ) && is_account_page() || function_exists( 'is_checkout' ) && is_checkout() ) {
			$load = true;
		}

		// sorry I should have renamed it buddydev_uachecker__load_assets but now I can not, my hads are tied.
		return apply_filters( 'buddydev_username_availability_checker_load_assets', $load );

	}


	/**
	 * Helper function to check the username is valid or not,
	 * Thanks to @apeatling, taken from bp-core/bp-core-signup.php and modified for checking only the username
	 * original: bp_core_validate_user_signup()
	 *
	 * @return string nothing if validated else error string
	 * */
	private function validate_username( $user_name ) {
		$errors = new WP_Error();

		$user_name = sanitize_user( $user_name, true );

		if ( empty( $user_name ) ) {
			// must not be empty.
			$errors->add( 'user_name', __( 'Please enter a valid username.', 'bpdev-username-availability-checker' ) );
		}

		if ( function_exists( 'buddypress' ) ) {
			$user_name = preg_replace( '/\s+/', '', $user_name );

		}

		// check blacklist.
		$illegal_names = get_site_option( 'illegal_names' );
		if ( in_array( $user_name, (array) $illegal_names ) ) {
			$errors->add( 'user_name', __( 'That username is not allowed.', 'bpdev-username-availability-checker' ) );
		}

		// see if passed validity check.
		if ( ! validate_username( $user_name ) ) {
			$errors->add( 'user_name', __( 'Usernames can contain only letters, numbers, ., -, and @', 'bpdev-username-availability-checker' ) );
		}

		if ( strlen( $user_name ) < 4 ) {
			$errors->add( 'user_name', __( 'Username must be at least 4 characters', 'bpdev-username-availability-checker' ) );
		} elseif ( mb_strlen( $user_name ) > 60 ) {
			$errors->add( 'user_login_too_long', __( 'Username may not be longer than 60 characters.', 'bpdev-username-availability-checker' ) );
		}

		if ( strpos( ' ' . $user_name, '_' ) != false ) {
			$errors->add( 'user_name', __( 'Sorry, usernames may not contain the character "_"!', 'bpdev-username-availability-checker' ) );
		}

		/* Is the user_name all numeric? */
		$match = array();
		preg_match( '/[0-9]*/', $user_name, $match );

		if ( $match[0] == $user_name ) {
			$errors->add( 'user_name', __( 'Sorry, usernames must have letters too!', 'bpdev-username-availability-checker' ) );
		}

		/**
		 * Filters the list of blacklisted usernames.
		 *
		 * @param array $usernames Array of blacklisted usernames.
		 */
		$illegal_logins = (array) apply_filters( 'illegal_user_logins', array() );

		if ( in_array( strtolower( $user_name ), array_map( 'strtolower', $illegal_logins ) ) ) {
			$errors->add( 'invalid_username', __( 'Sorry, that username is not allowed.', 'bpdev-username-availability-checker' ) );
		}

		// Let others dictate us
		// the divine message to show the users in case of failure
		// success is empty, never forget that.
		return apply_filters( 'buddydev_uachecker_username_error', $errors->has_errors() ? $errors->get_error_message() : '', $user_name );
	}
}

// instantiate.
BuddyDev_Username_Availability_Checker::get_instance();
