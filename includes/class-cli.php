<?php

defined( 'ABSPATH' ) || exit;

class RebelBoost_CLI {

	private static $api_client;

	public static function register( RebelBoost_API_Client $api_client ) {
		self::$api_client = $api_client;
		WP_CLI::add_command( 'rebelboost', __CLASS__ );
	}

	/**
	 * Purge RebelBoost cache.
	 *
	 * ## OPTIONS
	 *
	 * <type>
	 * : What to purge. One of: all, page, category.
	 *
	 * [<value>]
	 * : The path (for page) or category slug (for category).
	 *
	 * ## EXAMPLES
	 *
	 *     wp rebelboost purge all
	 *     wp rebelboost purge page /blog/my-post/
	 *     wp rebelboost purge category news
	 *
	 * @subcommand purge
	 */
	public function purge( $args ) {
		$type  = $args[0];
		$value = isset( $args[1] ) ? $args[1] : '';

		if ( ! RebelBoost::is_connected() ) {
			WP_CLI::error( 'RebelBoost is not configured. Set host and API key first.' );
		}

		switch ( $type ) {
			case 'all':
				$result = self::$api_client->purge_all();
				if ( true === $result ) {
					WP_CLI::success( 'All cache purged.' );
				} else {
					WP_CLI::error( $result->get_error_message() );
				}
				break;

			case 'page':
				if ( empty( $value ) ) {
					WP_CLI::error( 'Please provide a path. Example: wp rebelboost purge page /blog/my-post/' );
				}
				$result = self::$api_client->purge_page( $value );
				if ( true === $result ) {
					WP_CLI::success( "Page cache purged for: {$value}" );
				} else {
					WP_CLI::error( $result->get_error_message() );
				}
				break;

			case 'category':
				if ( empty( $value ) ) {
					WP_CLI::error( 'Please provide a category slug. Example: wp rebelboost purge category news' );
				}
				$result = self::$api_client->purge_category( $value );
				if ( true === $result ) {
					WP_CLI::success( "Category cache purged for: {$value}" );
				} else {
					WP_CLI::error( $result->get_error_message() );
				}
				break;

			default:
				WP_CLI::error( "Unknown purge type: {$type}. Use: all, page, or category." );
		}
	}

	/**
	 * Show RebelBoost connection status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp rebelboost status
	 *
	 * @subcommand status
	 */
	public function status() {
		$host    = get_option( 'rebelboost_host', '' );
		$api_key = get_option( 'rebelboost_api_key', '' );

		if ( empty( $host ) || empty( $api_key ) ) {
			WP_CLI::warning( 'RebelBoost is not configured.' );
			return;
		}

		WP_CLI::log( "Host:     {$host}" );
		WP_CLI::log( 'API Key:  ' . substr( $api_key, 0, 8 ) . str_repeat( '*', max( 0, strlen( $api_key ) - 8 ) ) );

		$auto_purge  = get_option( 'rebelboost_auto_purge', '1' );
		$surr_keys   = get_option( 'rebelboost_surrogate_keys', '1' );

		WP_CLI::log( 'Auto purge:     ' . ( '1' === $auto_purge ? 'enabled' : 'disabled' ) );
		WP_CLI::log( 'Surrogate keys: ' . ( '1' === $surr_keys ? 'enabled' : 'disabled' ) );

		WP_CLI::success( 'RebelBoost is configured.' );
	}

	/**
	 * Test the connection to RebelBoost.
	 *
	 * ## EXAMPLES
	 *
	 *     wp rebelboost test
	 *
	 * @subcommand test
	 */
	public function test() {
		if ( ! RebelBoost::is_connected() ) {
			WP_CLI::error( 'RebelBoost is not configured. Set host and API key first.' );
		}

		WP_CLI::log( 'Testing connection...' );

		$result = self::$api_client->test_connection();

		if ( true === $result ) {
			WP_CLI::success( 'Connection successful!' );
		} else {
			WP_CLI::error( $result->get_error_message() );
		}
	}
}
