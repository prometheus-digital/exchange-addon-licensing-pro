<?php
/*
Plugin Name: iThemes Exchange - Licensing Add-on
Plugin URI: http://ironbounddesigns.com
Description: Sell licenses for your digital products.
Version: 1.0
Author: Iron Bound Designs
Author URI: http://ironbounddesigns.com
License: GPLv2
Text Domain: ibd-exchange-addon-licensing
Domain Path: /lang
*/

/**
 * Class ITELIC
 */
class ITELIC {
	/**
	 * Plugin Version
	 */
	const VERSION = 1.0;
	/**
	 * Translation SLUG
	 */
	const SLUG = 'ibd-exchange-addon-licensing';
	/**
	 * @var string
	 */
	static $dir;
	/**
	 * @var string
	 */
	static $url;

	/**
	 * Constructor.
	 */
	public function __construct() {
		self::$dir = plugin_dir_path( __FILE__ );
		self::$url = plugin_dir_url( __FILE__ );

		spl_autoload_register( array( "ITELIC", "autoload" ) );

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'scripts_and_styles' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts_and_styles' ), 5 );
	}

	/**
	 * Run the upgrade routine if necessary.
	 */
	public static function upgrade() {
		$current_version = get_option( 'itelic_version', 0.1 );

		if ( $current_version != self::VERSION ) {

			/**
			 * Runs when the version upgrades.
			 *
			 * @param $current_version
			 * @param $new_version
			 */
			do_action( 'itelic_upgrade', self::VERSION, $current_version );

			update_option( 'itelic_version', self::VERSION );
		}
	}

	/**
	 * The activation hook.
	 */
	public function activate() {
		do_action( 'itelic_activate' );
	}

	/**
	 * The deactivation hook.
	 */
	public function deactivate() {

	}

	/**
	 * Register admin scripts.
	 *
	 * @since 1.0
	 */
	public function scripts_and_styles() {
		wp_register_style( 'jqueryui-editable', self::$url . 'assets/vendor/jqueryui-editable/css/jqueryui-editable.css', array(), '1.5.1' );
		wp_register_script( 'jqueryui-editable', self::$url . 'assets/vendor/jqueryui-editable/js/jqueryui-editable.js', array(
			'jquery-ui-core',
			'jquery-ui-tooltip',
			'jquery-ui-button',
			'jquery-ui-datepicker'
		), '1.5.1' );

		wp_register_style( 'itelic-add-edit-product', self::$url . 'assets/css/itelic-add-edit-product.css', array(), self::VERSION );
		wp_register_script( 'itelic-add-edit-product', self::$url . 'assets/js/itelic-add-edit-product.js', array( 'jquery' ), self::VERSION );

		wp_register_script( 'itelic-admin-licenses-list', self::$url . 'assets/js/itelic-admin-licenses-list.js', array( 'jquery' ), self::VERSION );
		wp_register_script( 'itelic-admin-license-detail', self::$url . 'assets/js/itelic-admin-license-detail.js', array(
			'jquery',
			'jqueryui-editable'
		), self::VERSION );

		wp_register_style( 'itelic-admin-license-detail', self::$url . 'assets/css/admin-license-detail.css', array( 'jqueryui-editable' ), self::VERSION );

		wp_register_script( 'itelic-super-widget', self::$url . 'assets/js/itelic-super-widget.js', array( 'jquery' ), self::VERSION );
		wp_register_script( 'itelic-checkout', self::$url . 'assets/js/itelic-checkout.js', array( 'jquery' ), self::VERSION );

		wp_register_style( 'itelic-renewal-reminder-edit', self::$url . 'assets/css/itelic-renewal-reminder-edit.css', array(), self::VERSION );
		wp_register_script( 'itelic-renewal-reminder-edit', self::$url . 'assets/js/itelic-renewal-reminder-edit.js', array( 'jquery' ), self::VERSION );

		wp_register_style( 'itelic-account-licenses', self::$url . 'assets/css/itelic-account-licenses.css', array(), self::VERSION );
		wp_register_script( 'itelic-account-licenses', self::$url . 'assets/js/itelic-account-licenses.js', array( 'jquery' ), self::VERSION );
	}

	/**
	 * Autoloader.
	 *
	 * If the class begins with ITECOM, then look for it by breaking the class into pieces by '_'.
	 * Then look in the corresponding directory structure by concatenating the class parts. The filename is then
	 * prefaced with either class, abstract, or interface.
	 *
	 * If the class doesn't begin with ITECOM, then look in the lib/classes, with a filename by replacing
	 * underscores with dashes. Follows the same conventions for filename prefixes.
	 *
	 * @since 1.0
	 *
	 * @param $class_name string
	 */
	public static function autoload( $class_name ) {
		if ( substr( $class_name, 0, 6 ) != "ITELIC" ) {
			if ( substr( $class_name, 0, 12 ) == "IT_Theme_API" ) {
				$path  = self::$dir . "api/theme";
				$class = strtolower( substr( $class_name, 13 ) );
				$name  = str_replace( "_", "-", $class );
			} else {
				$path  = self::$dir . "lib/classes";
				$class = strtolower( $class_name );
				$name  = str_replace( "_", "-", $class );
			}
		} else {
			$path = self::$dir . "lib";

			$class = substr( $class_name, 6 );
			$class = strtolower( $class );

			$parts = explode( "_", $class );
			$name  = array_pop( $parts );

			$path .= implode( "/", $parts );
		}

		$path .= "/class.$name.php";

		if ( file_exists( $path ) ) {
			require( $path );

			return;
		}
		if ( file_exists( str_replace( "class.", "abstract.", $path ) ) ) {
			require( str_replace( "class.", "abstract.", $path ) );

			return;
		}
		if ( file_exists( str_replace( "class.", "interface.", $path ) ) ) {
			require( str_replace( "class.", "interface.", $path ) );

			return;
		}
	}
}

new ITELIC();

/**
 * This registers our add-on
 *
 * @since 1.0
 */
function it_exchange_register_itelic_addon() {
	$options = array(
		'name'              => __( 'Licensing', ITELIC::SLUG ),
		'description'       => __( 'Sell licenses for your digital products.', ITELIC::SLUG ),
		'author'            => 'Iron Bound Designs',
		'author_url'        => 'http://www.ironbounddesigns.com',
		'file'              => dirname( __FILE__ ) . '/init.php',
		'category'          => 'other',
		'settings-callback' => 'itelic_addon_settings',
		'basename'          => plugin_basename( __FILE__ ),
		'labels'            => array(
			'singular_name' => __( 'Licensing', ITELIC::SLUG ),
		)
	);
	it_exchange_register_addon( 'licensing', $options );
}

add_action( 'it_exchange_register_addons', 'it_exchange_register_itelic_addon' );

/**
 * Loads the translation data for WordPress
 *
 * @since 1.0
 */
function it_exchange_itelic_set_textdomain() {
	load_plugin_textdomain( ITELIC::SLUG, false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
}

add_action( 'plugins_loaded', 'it_exchange_itelic_set_textdomain' );
