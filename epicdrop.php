<?php

/**
 * Plugin Name:     EpicDrop - Importador de Produtos para WooCommerce
 * Description:     Extensão que permite importar produtos de sites externos permitidos pela extensão em lojas WooCommerce.
 * Plugin URI:      https://epicdrop.com.br/
 * Author:          MeuMouse.com
 * Author URI:      https://meumouse.com/
 * Version:         1.0.0
 * WC requires at least: 5.0.0
 * WC tested up to:      7.1.0
 * Text Domain:     epicdrop
 * Domain Path:     /languages
 * License: GPL2
 */

// Exit if accessed directly
if (! defined('ABSPATH')) {
	exit; }

// Define EPICDROP_PLUGIN_FILE.
if ( ! defined( 'EPICDROP_PLUGIN_FILE' ) ) {
	define( 'EPICDROP_PLUGIN_FILE', __FILE__ );
}

if ( ! class_exists( 'Woo_EpicDrop' ) ) {
  
	/**
	 * Main Woo_EpicDrop Class
	 *
	 * @class Woo_EpicDrop
	 * @version 1.0.0
	 * @since 1.0.0
	 * @package MeuMouse.com
	 */
	final class Woo_EpicDrop {

		/**
		 * Woo_EpicDrop The single instance of Woo_EpicDrop.
		 *
		 * @var     object
		 * @since   1.0.0
		 */
		private static $instance = null;

		/**
		 * The token.
		 *
		 * @var     string
		 * @since   1.0.0
		 */
		public $token;

		/**
		 * The version number.
		 *
		 * @var     string
		 * @since   1.0.0
		 */
		public $version;

		/**
		 * Constructor function.
		 *
		 * @since   1.0.0
		 * @return  void
		 */
		public function __construct() {
			$this->token   = 'epicdrop';
			$this->version = '1.0.0';

		add_action( 'init', array( $this, 'load_plugin_textdomain' ), -1 );
		add_action( 'init', array( $this, 'woo_epicdrop_load_checker' ), 5 );
		add_action( 'plugins_loaded', array( $this, 'setup_constants' ), 10 );
		add_action( 'plugins_loaded', array( $this, 'setup_includes' ), 20 );
		add_action( 'plugins_loaded', array( $this, 'epicdrop_update_checker' ), 30 );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'woo_epicdrop_plugin_links' ), 10, 4 );

		}

		/**
		 * Main Woo_EpicDrop Instance
		 *
		 * Ensures only one instance of Woo_EpicDrop is loaded or can be loaded.
		 *
		 * @since 1.0.0
		 * @static
		 * @see Woo_EpicDrop()
		 * @return Main Woo_EpicDrop instance
		 */
		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Setup plugin constants
		 *
		 * @since  1.0.0
		 * @return void
		 */
		public function setup_constants() {

			// Plugin Folder Path.
			if ( ! defined( 'EPICDROP_DIR' ) ) {
				define( 'EPICDROP_DIR', plugin_dir_path( __FILE__ ) );
			}

			// Plugin Folder URL.
			if ( ! defined( 'EPICDROP_URL' ) ) {
				define( 'EPICDROP_URL', plugin_dir_url( __FILE__ ) );
			}

			// Plugin Root File.
			if ( ! defined( 'EPICDROP_FILE' ) ) {
				define( 'EPICDROP_FILE', __FILE__ );
			}

			$this->define( 'EPICDROP_ABSPATH', dirname( EPICDROP_FILE ) . '/' );
			$this->define( 'EPICDROP_VERSION', $this->version );
		}

		/**
		 * Define constant if not already set.
		 *
		 * @param string      $name  Constant name.
		 * @param string|bool $value Constant value.
		 */
		private function define( $name, $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}

		/**
		 * What type of request is this?
		 *
		 * @param  string $type admin, ajax, cron or woocustominstallmentsend.
		 * @return bool
		 */
		private function is_request( $type ) {
			switch ( $type ) {
				case 'admin':
					return is_admin();
				case 'ajax':
					return defined( 'DOING_AJAX' );
				case 'cron':
					return defined( 'DOING_CRON' );
				case 'epicdropend':
					return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' ) && ! $this->is_rest_api_request();
			}
		}

		/**
		 * Include required files
		 *
		 * @since  1.0.0
		 * @return void
		 */
		public function setup_includes() {

			/**
			 * Class init plugin
			 */
			include_once EPICDROP_DIR . '/includes/class-epicdrop-init.php';

		}

		/**
		 * Check requirements for loading plugin
		 * 
		 * @since 1.0.0
		 * @return bool
		 */
		public function woo_epicdrop_load_checker(){

		// Display notice if PHP version is bottom 7.0
		if ( version_compare( phpversion(), '7.0', '<' ) ) {
			add_action( 'admin_notices', array( $this, 'woo_epicdrop_php_version_notice' ) );
			return;
		}

		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Display notice if WooCommerce version is bottom 5.0
		if ( version_compare( WC_VERSION, '5.0', '<' ) ) {
			add_action( 'admin_notices', array( $this, 'woo_epicdrop_wc_version_notice' ) );
			return;
		}
		}

		/**
		 * WooCommerce version notice.
		 */
		public function woo_epicdrop_wc_version_notice() {
		echo '<div class="notice is-dismissible error">
				<p>' . __( 'O plugin EpicDrop - Importador de Produtos para WooCommerce requer a versão do WooCommerce 5.0 ou maior. Faça a atualização do plugin WooCommerce.', 'epicdrop' ) . '</p>
			</div>';
		}

		/**
		 * PHP version notice.
		 */
		public function woo_epicdrop_php_version_notice() {
		echo '<div class="notice is-dismissible error">
				<p>' . __( 'O plugin EpicDrop - Importador de Produtos para WooCommerce requer a versão do PHP 7.0 ou maior. Contate o suporte da sua hospedagem para realizar a atualização.', 'epicdrop' ) . '</p>
			</div>';
		}

		/**
		 * Plugin action links
		 * 
		 * @since 1.0.0
		 * @return array
		 */
		public function woo_epicdrop_plugin_links( $action_links ) {
			$plugins_links = array(
			'<a href="' . admin_url( 'edit.php?post_type=product&page=epicdrop_settings' ) . '">' . __( 'Configurar', 'epicdrop' ) . '</a>',
			'<a href="https://meumouse.com/docs/plugins/" target="_blank">Ajuda</a>',
			'<a href="https://chrome.google.com/webstore/" target="_blank">Extensão do Chrome</a>'
			);
			return array_merge( $plugins_links, $action_links );
		}

		/**
		 * Load the plugin text domain for translation.
		 * 
		 * @return string
		 */
		public static function load_plugin_textdomain() {
			load_plugin_textdomain( 'epicdrop', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

		/**
		 * Get the plugin url.
		 *
		 * @return string
		 */
		public function plugin_url() {
			return untrailingslashit( plugins_url( '/', EPICDROP_PLUGIN_FILE ) );
		}

		/**
		 * Cloning is forbidden.
		 *
		 * @since 1.0.0
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Trapaceando?', 'epicdrop' ), '1.0.0' );
		}

		/**
		 * Unserializing instances of this class is forbidden.
		 *
		 * @since 1.0.0
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Trapaceando?', 'epicdrop' ), '1.0.0' );
		}

		/**
		 * Plugin update checker dependencies (PLEASE DON'T TOUCH HERE!!! | POR FAVOR, NÃO MEXA AQUI!!!)
		 */
		public function epicdrop_update_checker(){
			require EPICDROP_DIR . '/core/update-checker/plugin-update-checker.php';
			$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker( 'https://raw.githubusercontent.com/meumouse/epicdrop/main/epicdrop-update-checker.json', __FILE__, 'epicdrop' );
		}

	}
}

/**
 * Returns the main instance of Woo_EpicDrop to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object Woo_EpicDrop
 */
function Woo_EpicDrop() { //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return Woo_EpicDrop::instance();
}

/**
 * Initialise the plugin
 */
Woo_EpicDrop();