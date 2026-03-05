<?php

defined( 'ABSPATH' ) || exit;

class RebelBoost_API_Client {

	private $base_url;
	private $api_key;

	public function __construct() {
		$this->reload();
	}

	/**
	 * Reload credentials from options.
	 */
	public function reload() {
		$this->base_url = RebelBoost::get_host_url();
		$this->api_key  = get_option( 'rebelboost_api_key', '' );
	}

	/**
	 * Test the connection by attempting a purge on a non-existent path.
	 *
	 * @return true|WP_Error
	 */
	/**
	 * Test the connection by attempting a purge on a non-existent path.
	 *
	 * @param bool $proxy_mode When true, accept 400 as valid (host may not
	 *                         have a purge config yet, but the key was not rejected).
	 * @return true|WP_Error
	 */
	public function test_connection( $proxy_mode = false ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'rebelboost_not_configured', __( 'API key is required.', 'rebelboost' ) );
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

		// In proxy mode the host may not have a purge config yet,
		// so 400 is expected. The key was accepted (not 401/403).
		if ( $proxy_mode && 400 === $code ) {
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
	 * Register this server as the origin with RebelBoost.
	 *
	 * Sends the server's IP address so RebelBoost knows where to
	 * proxy requests after DNS is pointed to the proxy.
	 *
	 * @return true|WP_Error
	 */
	public function register_origin() {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'rebelboost_not_configured', __( 'RebelBoost is not configured.', 'rebelboost' ) );
		}

		$site_host = wp_parse_url( site_url(), PHP_URL_HOST );

		// origin_scheme: 0 = HTTPS, 1 = HTTP (matches rebelboost-config convention).
		if ( 'wordpress.local' === $site_host ) {
			$origin_host = 'wordpress';
			$scheme      = 1; // HTTP in local dev.
		} else {
			$origin_host = ! empty( $_SERVER['SERVER_ADDR'] ) ? $_SERVER['SERVER_ADDR'] : gethostbyname( (string) gethostname() );
			$scheme      = ( 'https' === wp_parse_url( site_url(), PHP_URL_SCHEME ) ) ? 0 : 1;
		}

		$response = $this->request( 'POST', '/connect', array(
			'origin_host'   => $origin_host,
			'origin_scheme' => $scheme,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		return new WP_Error(
			'rebelboost_origin_failed',
			sprintf(
				/* translators: %d: HTTP response code */
				__( 'Origin registration failed (HTTP %d)', 'rebelboost' ),
				$code
			)
		);
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
		if ( empty( $this->api_key ) ) {
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

		// Always send the site's domain as the Host header so the proxy
		// can identify the host config, even when base_url differs from
		// the domain (e.g., local dev where the proxy address != domain).
		$site_host = wp_parse_url( site_url(), PHP_URL_HOST );

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization'   => 'Bearer ' . $this->api_key,
				'Content-Type'    => 'application/json',
				'Accept'          => 'application/json',
				'Host'            => $site_host,
				'X-Forwarded-Host' => $site_host,
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
