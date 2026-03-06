<?php

defined( 'ABSPATH' ) || exit;

class RebelBoost_Settings {

	private $api_client;

	public function __construct( RebelBoost_API_Client $api_client ) {
		$this->api_client = $api_client;
	}

	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_rebelboost_test_connection', array( $this, 'ajax_test_connection' ) );
	}

	public function add_menu_page() {
		add_options_page(
			__( 'RebelBoost Settings', 'rebelboost' ),
			__( 'RebelBoost', 'rebelboost' ),
			'manage_options',
			'rebelboost',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		// Plugin mode section.
		add_settings_section(
			'rebelboost_mode_section',
			__( 'Plugin Mode', 'rebelboost' ),
			array( $this, 'render_mode_section' ),
			'rebelboost'
		);

		add_settings_field( 'rebelboost_mode', __( 'Operating Mode', 'rebelboost' ), array( $this, 'render_mode_field' ), 'rebelboost', 'rebelboost_mode_section' );
		add_settings_field( 'rebelboost_origin_host', __( 'Origin Host', 'rebelboost' ), array( $this, 'render_origin_host_field' ), 'rebelboost', 'rebelboost_mode_section' );

		register_setting( 'rebelboost_settings', 'rebelboost_mode', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_mode' ),
		) );
		register_setting( 'rebelboost_settings', 'rebelboost_origin_host', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_origin_host' ),
		) );

		// Connection section.
		add_settings_section(
			'rebelboost_connection',
			__( 'Connection', 'rebelboost' ),
			array( $this, 'render_connection_section' ),
			'rebelboost'
		);

		add_settings_field( 'rebelboost_api_key', __( 'API Key', 'rebelboost' ), array( $this, 'render_api_key_field' ), 'rebelboost', 'rebelboost_connection' );

		register_setting( 'rebelboost_settings', 'rebelboost_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		) );

		// Cache invalidation section.
		add_settings_section(
			'rebelboost_invalidation',
			__( 'Cache Invalidation', 'rebelboost' ),
			array( $this, 'render_invalidation_section' ),
			'rebelboost'
		);

		add_settings_field( 'rebelboost_auto_purge', __( 'Automatic Purge', 'rebelboost' ), array( $this, 'render_auto_purge_field' ), 'rebelboost', 'rebelboost_invalidation' );
		add_settings_field( 'rebelboost_purge_on_comment', __( 'Purge on Comment', 'rebelboost' ), array( $this, 'render_purge_on_comment_field' ), 'rebelboost', 'rebelboost_invalidation' );

		register_setting( 'rebelboost_settings', 'rebelboost_auto_purge', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
		) );
		register_setting( 'rebelboost_settings', 'rebelboost_purge_on_comment', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
		) );

		// Advanced section.
		add_settings_section(
			'rebelboost_advanced',
			__( 'Advanced', 'rebelboost' ),
			array( $this, 'render_advanced_section' ),
			'rebelboost'
		);

		add_settings_field( 'rebelboost_surrogate_keys', __( 'Surrogate Keys', 'rebelboost' ), array( $this, 'render_surrogate_keys_field' ), 'rebelboost', 'rebelboost_advanced' );
		add_settings_field( 'rebelboost_category_header', __( 'Category Header', 'rebelboost' ), array( $this, 'render_category_header_field' ), 'rebelboost', 'rebelboost_advanced' );
		register_setting( 'rebelboost_settings', 'rebelboost_surrogate_keys', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
		) );
		register_setting( 'rebelboost_settings', 'rebelboost_category_header', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		) );
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'RebelBoost Settings', 'rebelboost' ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'rebelboost_settings' );
				do_settings_sections( 'rebelboost' );
				submit_button();
				?>
			</form>

			<hr>

			<?php if ( 'proxy' !== get_option( 'rebelboost_mode', 'integration' ) ) : ?>
			<h2><?php esc_html_e( 'DNS Setup Guide', 'rebelboost' ); ?></h2>
			<div class="rebelboost-dns-guide">
				<p><?php esc_html_e( 'To enable RebelBoost optimization, point your domain to the RebelBoost proxy:', 'rebelboost' ); ?></p>
				<ol>
					<li><?php esc_html_e( 'Log in to your DNS provider.', 'rebelboost' ); ?></li>
					<li><?php esc_html_e( 'Create a CNAME record for your domain pointing to the RebelBoost proxy hostname provided by your account manager.', 'rebelboost' ); ?></li>
					<li><?php esc_html_e( 'Wait for DNS propagation (usually 5-30 minutes).', 'rebelboost' ); ?></li>
					<li><?php esc_html_e( 'Verify the connection using the "Test Connection" button above.', 'rebelboost' ); ?></li>
				</ol>
			</div>
			<?php else : ?>
			<h2><?php esc_html_e( 'Proxy Mode Active', 'rebelboost' ); ?></h2>
			<p><?php esc_html_e( 'RebelBoost is operating in proxy mode. Asset URLs are being rewritten to route through the RebelBoost CDN. No DNS or CDN changes are required.', 'rebelboost' ); ?></p>
			<?php endif; ?>

			<hr>

			<h2><?php esc_html_e( 'Smart Links & Dashboard', 'rebelboost' ); ?></h2>
			<p>
				<?php esc_html_e( 'Manage smart links, view detailed cache statistics, and configure advanced optimization settings in the RebelBoost dashboard.', 'rebelboost' ); ?>
			</p>
			<?php if ( RebelBoost::is_connected() ) : ?>
				<a href="<?php echo esc_url( RebelBoost::get_host_url() ); ?>" target="_blank" class="button">
					<?php esc_html_e( 'Open RebelBoost Dashboard', 'rebelboost' ); ?> &rarr;
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	// Section descriptions.

	public function render_connection_section() {
		$connected = RebelBoost::is_connected();
		if ( $connected ) {
			echo '<p class="rebelboost-status rebelboost-status--connected">';
			esc_html_e( 'Status: Connected', 'rebelboost' );
			echo '</p>';
		} else {
			echo '<p class="rebelboost-status rebelboost-status--disconnected">';
			esc_html_e( 'Status: Not connected. Enter your API key below.', 'rebelboost' );
			echo '</p>';
		}
	}

	public function render_invalidation_section() {
		echo '<p>' . esc_html__( 'Control when the RebelBoost cache is automatically purged.', 'rebelboost' ) . '</p>';
	}

	public function render_advanced_section() {
		echo '<p>' . esc_html__( 'Advanced settings for cache integration with RebelBoost.', 'rebelboost' ) . '</p>';
	}

	// Field renderers.

	public function render_api_key_field() {
		$value = get_option( 'rebelboost_api_key', '' );
		printf(
			'<input type="password" id="rebelboost_api_key" name="rebelboost_api_key" value="%s" class="regular-text" autocomplete="off">',
			esc_attr( $value )
		);
		echo '<p class="description">' . esc_html__( 'Your RebelBoost API key from the dashboard.', 'rebelboost' ) . '</p>';
		if ( ! empty( $value ) ) {
			echo '<p><button type="button" id="rebelboost-test-connection" class="button">' . esc_html__( 'Test Connection', 'rebelboost' ) . '</button>';
			echo ' <span id="rebelboost-test-result"></span></p>';
		}
	}

	public function render_auto_purge_field() {
		$value = get_option( 'rebelboost_auto_purge', '1' );
		printf(
			'<label><input type="checkbox" name="rebelboost_auto_purge" value="1" %s> %s</label>',
			checked( $value, '1', false ),
			esc_html__( 'Automatically purge cache when content is created, updated, or deleted.', 'rebelboost' )
		);
	}

	public function render_purge_on_comment_field() {
		$value = get_option( 'rebelboost_purge_on_comment', '1' );
		printf(
			'<label><input type="checkbox" name="rebelboost_purge_on_comment" value="1" %s> %s</label>',
			checked( $value, '1', false ),
			esc_html__( 'Purge page cache when a comment is posted or approved.', 'rebelboost' )
		);
	}

	public function render_surrogate_keys_field() {
		$value = get_option( 'rebelboost_surrogate_keys', '1' );
		printf(
			'<label><input type="checkbox" name="rebelboost_surrogate_keys" value="1" %s> %s</label>',
			checked( $value, '1', false ),
			esc_html__( 'Inject Surrogate-Key headers for granular CDN cache invalidation.', 'rebelboost' )
		);
		echo '<p class="description">' . esc_html__( 'Adds metadata tags (post ID, author, taxonomy) to response headers so RebelBoost can purge specific pages at the CDN level.', 'rebelboost' ) . '</p>';
	}

	public function render_category_header_field() {
		$value = get_option( 'rebelboost_category_header', 'X-RM-Categories' );
		printf(
			'<input type="text" name="rebelboost_category_header" value="%s" class="regular-text" placeholder="X-RM-Categories">',
			esc_attr( $value )
		);
		echo '<p class="description">' . esc_html__( 'HTTP header name used to pass WordPress categories to RebelBoost. Must match the category_header config in RebelBoost.', 'rebelboost' ) . '</p>';
	}

	// Sanitizers.

	public function render_mode_section() {
		echo '<p>' . esc_html__( 'Choose how RebelBoost connects to your site.', 'rebelboost' ) . '</p>';
	}

	public function render_mode_field() {
		$current = get_option( 'rebelboost_mode', 'integration' );
		?>
		<fieldset>
			<label style="display:block; margin-bottom:8px;">
				<input type="radio" name="rebelboost_mode" value="integration" <?php checked( $current, 'integration' ); ?>>
				<strong><?php esc_html_e( 'Integration', 'rebelboost' ); ?></strong>
				&mdash; <?php esc_html_e( 'Works with your existing CDN. Handles cache invalidation and surrogate keys. Requires CDN/DNS pointing to RebelBoost.', 'rebelboost' ); ?>
			</label>
			<label style="display:block;">
				<input type="radio" name="rebelboost_mode" value="proxy" <?php checked( $current, 'proxy' ); ?>>
				<strong><?php esc_html_e( 'Proxy', 'rebelboost' ); ?></strong>
				&mdash; <?php esc_html_e( 'Routes assets through RebelBoost via WordPress output rewriting. No DNS or CDN changes needed.', 'rebelboost' ); ?>
			</label>
		</fieldset>
		<?php
	}

	public function render_origin_host_field() {
		$value   = get_option( 'rebelboost_origin_host', '' );
		$current = get_option( 'rebelboost_mode', 'integration' );
		$hidden  = 'integration' !== $current ? ' style="display:none;"' : '';
		printf(
			'<div id="rebelboost-origin-host-row"%s>',
			$hidden // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute value.
		);
		printf(
			'<input type="text" id="rebelboost_origin_host" name="rebelboost_origin_host" value="%s" class="regular-text" placeholder="e.g. 203.0.113.50">',
			esc_attr( $value )
		);
		echo '<p class="description">' . esc_html__( 'Your server\'s public IP address or hostname. Required in integration mode so RebelBoost can reach your origin after DNS is pointed to the proxy.', 'rebelboost' ) . '</p>';
		echo '</div>';
	}

	public function sanitize_mode( $value ) {
		return in_array( $value, array( 'integration', 'proxy' ), true ) ? $value : 'integration';
	}

	public function sanitize_origin_host( $value ) {
		$value = sanitize_text_field( $value );
		// Allow IP addresses and hostnames only.
		$value = preg_replace( '/[^a-zA-Z0-9.\-:]/', '', $value );
		return $value;
	}

	public function sanitize_checkbox( $value ) {
		return '1' === $value ? '1' : '0';
	}

	// AJAX.

	public function ajax_test_connection() {
		check_ajax_referer( 'rebelboost_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'rebelboost' ) ) );
		}

		// Save form values so Test Connection works before clicking Save Changes.
		if ( ! empty( $_POST['api_key'] ) ) {
			update_option( 'rebelboost_api_key', sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) );
		}
		if ( isset( $_POST['mode'] ) ) {
			update_option( 'rebelboost_mode', $this->sanitize_mode( wp_unslash( $_POST['mode'] ) ) );
		}
		if ( isset( $_POST['origin_host'] ) ) {
			update_option( 'rebelboost_origin_host', $this->sanitize_origin_host( wp_unslash( $_POST['origin_host'] ) ) );
		}

		$this->api_client->reload();

		$mode = get_option( 'rebelboost_mode', 'integration' );

		if ( 'proxy' === $mode ) {
			// In proxy mode, verify the API key first.
			$result = $this->api_client->test_connection( true );

			if ( true !== $result ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				return;
			}

			// Still register the origin so the service knows where to
			// fetch content from — but don't fail if it errors (the host
			// may not have a full CDN config yet in proxy mode).
			$this->api_client->register_origin();

			wp_send_json_success( array(
				'message' => __( 'Connected! Proxy mode active — asset URLs will be rewritten.', 'rebelboost' ),
			) );
			return;
		}

		// Integration mode: register the origin first so the host has an
		// origin config before we attempt a purge-based connection test.
		$origin_result = $this->api_client->register_origin();
		if ( true !== $origin_result ) {
			wp_send_json_error( array( 'message' => $origin_result->get_error_message() ) );
			return;
		}

		// Origin registered — verify the full pipeline works.
		$result = $this->api_client->test_connection();

		if ( true !== $result ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			return;
		}

		wp_send_json_success( array(
			'message' => __( 'Connected! Origin server registered.', 'rebelboost' ),
		) );
	}
}
