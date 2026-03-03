<?php

defined( 'ABSPATH' ) || exit;

class RebelBoost {

	private static $instance = null;

	private $api_client;
	private $cache_invalidation;
	private $surrogate_keys;
	private $settings;
	private $admin;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function init() {
		$instance = self::get_instance();
		$instance->load_dependencies();
		$instance->register_hooks();
	}

	private function load_dependencies() {
		require_once REBELBOOST_PLUGIN_DIR . 'includes/class-api-client.php';
		require_once REBELBOOST_PLUGIN_DIR . 'includes/class-cache-invalidation.php';
		require_once REBELBOOST_PLUGIN_DIR . 'includes/class-surrogate-keys.php';
		require_once REBELBOOST_PLUGIN_DIR . 'includes/class-settings.php';
		require_once REBELBOOST_PLUGIN_DIR . 'includes/class-admin.php';

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once REBELBOOST_PLUGIN_DIR . 'includes/class-cli.php';
		}

		$this->api_client         = new RebelBoost_API_Client();
		$this->cache_invalidation = new RebelBoost_Cache_Invalidation( $this->api_client );
		$this->surrogate_keys     = new RebelBoost_Surrogate_Keys();
		$this->settings           = new RebelBoost_Settings( $this->api_client );
		$this->admin              = new RebelBoost_Admin( $this->api_client );
	}

	private function register_hooks() {
		$this->cache_invalidation->register_hooks();
		$this->surrogate_keys->register_hooks();
		$this->settings->register_hooks();
		$this->admin->register_hooks();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			RebelBoost_CLI::register( $this->api_client );
		}

		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'rebelboost', false, basename( REBELBOOST_PLUGIN_DIR ) . '/languages/' );
	}

	public function get_api_client() {
		return $this->api_client;
	}

	public static function is_connected() {
		$api_key = get_option( 'rebelboost_api_key', '' );
		return ! empty( $api_key );
	}

	public static function activate() {
		$defaults = array(
			'rebelboost_auto_purge'       => '1',
			'rebelboost_purge_on_comment' => '1',
			'rebelboost_surrogate_keys'   => '1',
			'rebelboost_category_header'  => 'X-RM-Categories',
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}

	public static function deactivate() {
		// Preserve settings for re-activation.
	}
}
