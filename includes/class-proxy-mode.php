<?php

defined( 'ABSPATH' ) || exit;

/**
 * Proxy mode — serves optimized pages via the RebelBoost service.
 *
 * For regular browser requests, this class fetches the page from the
 * RebelBoost proxy (which applies optimizations like image compression,
 * lazy loading, script deferral, etc.) and serves the optimized response.
 *
 * When the RebelBoost service fetches back from WordPress (origin fetch),
 * we detect this via a secret header token and let WordPress serve normally
 * to avoid an infinite loop.
 */
class RebelBoost_Proxy_Mode {

	private $site_host;
	private $base_url;

	/**
	 * Secret token used to identify origin fetches from the plugin's own
	 * proxy requests vs. the RebelBoost service fetching from origin.
	 * Derived from the API key so it's unique per site but not guessable.
	 */
	private $loop_token;

	public function __construct() {
		$this->site_host  = wp_parse_url( site_url(), PHP_URL_HOST );
		$override         = get_option( 'rebelboost_host', '' );
		$this->base_url   = ! empty( $override ) ? untrailingslashit( $override ) : 'https://ingressv2.rebelboost.com';
		$this->loop_token = substr( md5( 'rebelboost_proxy_' . get_option( 'rebelboost_api_key', '' ) ), 0, 16 );
	}

	public function register_hooks() {
		if ( ! $this->is_active() ) {
			return;
		}

		// If this request was made by our own proxy (loopback from RebelBoost
		// service fetching the origin), let WordPress serve normally.
		if ( $this->is_loopback() ) {
			return;
		}

		// For regular browser requests, proxy through RebelBoost.
		add_action( 'template_redirect', array( $this, 'serve_optimized' ), 0 );
	}

	public function is_active() {
		return 'proxy' === get_option( 'rebelboost_mode', 'integration' )
			&& RebelBoost::is_connected()
			&& ! is_admin()
			&& ! wp_doing_ajax()
			&& ! wp_doing_cron()
			&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST );
	}

	/**
	 * Detect if this is an origin fetch triggered by our own proxy request.
	 *
	 * The RebelBoost service forwards all request headers to the origin.
	 * We add a custom header (X-Rebelboost-Loop-Token) when proxying,
	 * which gets forwarded back to us on the origin fetch.
	 */
	private function is_loopback() {
		$token = isset( $_SERVER['HTTP_X_REBELBOOST_LOOP_TOKEN'] )
			? $_SERVER['HTTP_X_REBELBOOST_LOOP_TOKEN']
			: '';
		return $token === $this->loop_token;
	}

	/**
	 * Fetch the optimized page from the RebelBoost service and serve it.
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
			// If the proxy is unreachable, fall back to normal WordPress output.
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
