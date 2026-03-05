<?php

defined( 'ABSPATH' ) || exit;

/**
 * Proxy mode — serves optimized pages via the RebelBoost service.
 *
 * For regular browser requests, this class intercepts as early as possible
 * (at `plugins_loaded`) and fetches the optimized page from RebelBoost.
 * If the page is cached at RebelBoost, WordPress never builds the page
 * at all — giving the best possible TTFB.
 *
 * On cache miss, RebelBoost fetches from the WordPress origin. The plugin
 * detects the origin fetch via a loop token header and lets WordPress
 * serve normally (single page build).
 */
class RebelBoost_Proxy_Mode {

	private $site_host;
	private $base_url;

	/**
	 * Secret token to detect origin fetches (loop prevention).
	 * Derived from the API key so it's unique per site.
	 */
	private $loop_token;

	public function __construct() {
		$this->site_host  = wp_parse_url( site_url(), PHP_URL_HOST );
		$this->base_url   = RebelBoost::get_host_url();
		$this->loop_token = substr( md5( 'rebelboost_proxy_' . get_option( 'rebelboost_api_key', '' ) ), 0, 16 );
	}

	public function register_hooks() {
		if ( ! $this->should_proxy() ) {
			return;
		}

		// Hook as early as possible. plugins_loaded fires before init,
		// parse_request, wp, template_redirect — so WordPress hasn't
		// built the page yet. On cache hit, we serve and exit immediately.
		add_action( 'plugins_loaded', array( $this, 'serve_optimized' ), 0 );
	}

	/**
	 * Determine if this request should be proxied through RebelBoost.
	 *
	 * Runs at plugin load time, so we use low-level checks only
	 * (no is_admin() which depends on full WP bootstrap).
	 */
	private function should_proxy() {
		// Mode check.
		if ( 'proxy' !== get_option( 'rebelboost_mode', 'integration' ) ) {
			return false;
		}

		// Must be connected.
		if ( ! RebelBoost::is_connected() ) {
			return false;
		}

		// Skip admin requests.
		if ( is_admin() ) {
			return false;
		}

		// Skip AJAX.
		if ( wp_doing_ajax() ) {
			return false;
		}

		// Skip cron.
		if ( wp_doing_cron() ) {
			return false;
		}

		// Skip REST API.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		// Skip WP-CLI.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		// Skip XML-RPC.
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return false;
		}

		// Skip origin fetches from RebelBoost (loop prevention).
		if ( $this->is_loopback() ) {
			return false;
		}

		// Skip wp-login, wp-signup, wp-cron.php etc.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		if ( preg_match( '#/wp-(login|signup|cron|admin|json)#', $request_uri ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Detect if this is an origin fetch triggered by our own proxy request.
	 */
	private function is_loopback() {
		$token = isset( $_SERVER['HTTP_X_REBELBOOST_LOOP_TOKEN'] )
			? $_SERVER['HTTP_X_REBELBOOST_LOOP_TOKEN']
			: '';
		return $token === $this->loop_token;
	}

	/**
	 * Fetch the optimized page from the RebelBoost service and serve it.
	 *
	 * If RebelBoost has the page cached, this returns immediately without
	 * WordPress ever building the page (best TTFB). On cache miss,
	 * RebelBoost fetches from origin (one WordPress page build).
	 */
	public function serve_optimized() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/';
		$url         = $this->base_url . $request_uri;

		$headers = array(
			'Host'                      => $this->site_host,
			'X-Forwarded-Host'          => $this->site_host,
			'X-Forwarded-For'           => isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '',
			'X-Rebelboost-Loop-Token'   => $this->loop_token,
			'Accept'                    => isset( $_SERVER['HTTP_ACCEPT'] ) ? $_SERVER['HTTP_ACCEPT'] : '*/*',
			'Accept-Encoding'           => 'identity',
			'User-Agent'                => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '',
		);

		// Forward cookies for authenticated/personalized content.
		if ( ! empty( $_SERVER['HTTP_COOKIE'] ) ) {
			$headers['Cookie'] = $_SERVER['HTTP_COOKIE'];
		}

		$response = wp_remote_get( $url, array(
			'headers'     => $headers,
			'timeout'     => 15,
			'redirection' => 0,
			'decompress'  => true,
		) );

		if ( is_wp_error( $response ) ) {
			// Proxy unreachable — fall back to normal WordPress.
			return;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );

		// Forward relevant response headers.
		$passthrough_headers = array(
			'content-type',
			'cache-control',
			'x-rebelboost-cache',
			'x-rebelboost-optimized',
			'x-rebelmouse-origin-timing',
			'vary',
			'link',
		);

		foreach ( $passthrough_headers as $name ) {
			$value = wp_remote_retrieve_header( $response, $name );
			if ( ! empty( $value ) ) {
				header( $name . ': ' . $value );
			}
		}

		http_response_code( $status );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
