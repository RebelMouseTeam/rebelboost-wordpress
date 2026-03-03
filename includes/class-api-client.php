<?php

defined( 'ABSPATH' ) || exit;

class RebelBoost_API_Client {

	private $base_url;
	private $api_key;

	public function __construct() {
		$this->base_url = untrailingslashit( get_option( 'rebelboost_host', '' ) );
		$this->api_key  = get_option( 'rebelboost_api_key', '' );
	}

	/**
	 * Reload credentials from options (useful after settings save).
	 */
	public function reload() {
		$this->base_url = untrailingslashit( get_option( 'rebelboost_host', '' ) );
		$this->api_key  = get_option( 'rebelboost_api_key', '' );
	}

	/**
	 * Test the connection by attempting a purge on a non-existent path.
	 *
	 * @return true|WP_Error
	 */
	public function test_connection() {
		if ( empty( $this->base_url ) || empty( $this->api_key ) ) {
			return new WP_Error( 'rebelboost_not_configured', __( 'RebelBoost host and API key are required.', 'rebelboost' ) );
		}

		$response = $this->request( 'DELETE', '/purge/page/', array( 'path' => '/__rebelboost_connection_test' ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 401 === $code || 403 === $code ) {
			return new WP_Error( 'rebelboost_auth_failed', __( 'Invalid API key.', 'rebelboost' ) );
		}

		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		return new WP_Error(
			'rebelboost_unexpected_response',
			sprintf(
				/* translators: %d: HTTP response code */
				__( 'Unexpected response code: %d', 'rebelboost' ),
				$code
			)
		);
	}

	/**
	 * Purge a specific page by path.
	 *
	 * @param string $path The URL path to purge.
	 * @return true|WP_Error
	 */
	public function purge_page( $path ) {
		return $this->do_purge( 'DELETE', '/purge/page/', array( 'path' => $path ) );
	}

	/**
	 * Purge all pages matching a category.
	 *
	 * @param string $category The category slug.
	 * @return true|WP_Error
	 */
	public function purge_category( $category ) {
		return $this->do_purge( 'DELETE', '/purge/category/', array( 'category' => $category ) );
	}

	/**
	 * Purge the entire cache for this domain.
	 *
	 * @return true|WP_Error
	 */
	public function purge_all() {
		return $this->do_purge( 'DELETE', '/purge/all/', null );
	}

	/**
	 * Execute a purge request with error handling.
	 *
	 * @param string     $method HTTP method.
	 * @param string     $endpoint API endpoint path.
	 * @param array|null $body Request body.
	 * @return true|WP_Error
	 */
	private function do_purge( $method, $endpoint, $body ) {
		if ( empty( $this->base_url ) || empty( $this->api_key ) ) {
			return new WP_Error( 'rebelboost_not_configured', __( 'RebelBoost is not configured.', 'rebelboost' ) );
		}

		$response = $this->request( $method, $endpoint, $body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		return new WP_Error(
			'rebelboost_purge_failed',
			sprintf(
				/* translators: %1$s: endpoint path, %2$d: HTTP response code */
				__( 'Purge failed for %1$s (HTTP %2$d)', 'rebelboost' ),
				$endpoint,
				$code
			)
		);
	}

	/**
	 * Make an HTTP request to the RebelBoost External API.
	 *
	 * @param string     $method HTTP method.
	 * @param string     $endpoint API endpoint path (relative to extapi/v1).
	 * @param array|null $body Request body (will be JSON-encoded).
	 * @return array|WP_Error
	 */
	private function request( $method, $endpoint, $body = null ) {
		$url = $this->base_url . '/_rebelboost/extapi/v1' . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
			'timeout' => 10,
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			// Retry once on connection failure.
			$response = wp_remote_request( $url, $args );
		}

		return $response;
	}
}
