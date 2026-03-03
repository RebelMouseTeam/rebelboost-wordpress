<?php

defined( 'ABSPATH' ) || exit;

class RebelBoost_Admin {

	private $api_client;

	public function __construct( RebelBoost_API_Client $api_client ) {
		$this->api_client = $api_client;
	}

	public function register_hooks() {
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu' ), 100 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_toolbar_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_rebelboost_purge_all', array( $this, 'ajax_purge_all' ) );
		add_action( 'wp_ajax_rebelboost_purge_page', array( $this, 'ajax_purge_page' ) );
	}

	/**
	 * Add RebelBoost menu to the admin bar.
	 */
	public function add_admin_bar_menu( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) || ! RebelBoost::is_connected() ) {
			return;
		}

		$wp_admin_bar->add_node( array(
			'id'    => 'rebelboost',
			'title' => 'RebelBoost',
			'href'  => admin_url( 'options-general.php?page=rebelboost' ),
		) );

		$wp_admin_bar->add_node( array(
			'id'     => 'rebelboost-purge-all',
			'parent' => 'rebelboost',
			'title'  => __( 'Purge All Cache', 'rebelboost' ),
			'href'   => '#',
			'meta'   => array(
				'class' => 'rebelboost-purge-all',
			),
		) );

		// Add "Purge This Page" on singular frontend pages.
		if ( ! is_admin() && is_singular() ) {
			$post = get_queried_object();
			if ( $post ) {
				$path = wp_parse_url( get_permalink( $post ), PHP_URL_PATH );
				if ( $path ) {
					$wp_admin_bar->add_node( array(
						'id'     => 'rebelboost-purge-page',
						'parent' => 'rebelboost',
						'title'  => __( 'Purge This Page', 'rebelboost' ),
						'href'   => '#',
						'meta'   => array(
							'class'    => 'rebelboost-purge-page',
							'data-path' => $path,
						),
					) );
				}
			}
		}

		$wp_admin_bar->add_node( array(
			'id'     => 'rebelboost-settings',
			'parent' => 'rebelboost',
			'title'  => __( 'Settings', 'rebelboost' ),
			'href'   => admin_url( 'options-general.php?page=rebelboost' ),
		) );
	}

	/**
	 * Add meta box to post editor.
	 */
	public function add_meta_boxes() {
		if ( ! RebelBoost::is_connected() ) {
			return;
		}

		$post_types = get_post_types( array( 'public' => true ) );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'rebelboost-cache',
				__( 'RebelBoost Cache', 'rebelboost' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'low'
			);
		}
	}

	/**
	 * Render the cache meta box in the post editor.
	 */
	public function render_meta_box( $post ) {
		$last_purge = get_post_meta( $post->ID, '_rebelboost_last_purge', true );
		$path       = wp_parse_url( get_permalink( $post ), PHP_URL_PATH );

		wp_nonce_field( 'rebelboost_nonce', 'rebelboost_meta_nonce' );
		?>
		<div class="rebelboost-meta-box">
			<p>
				<button type="button"
					class="button rebelboost-purge-post-btn"
					data-path="<?php echo esc_attr( $path ); ?>"
					data-post-id="<?php echo esc_attr( $post->ID ); ?>">
					<?php esc_html_e( 'Purge Cache for This Post', 'rebelboost' ); ?>
				</button>
			</p>
			<?php if ( $last_purge ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: human-readable time difference */
						esc_html__( 'Last purged: %s ago', 'rebelboost' ),
						esc_html( human_time_diff( strtotime( $last_purge ), current_time( 'timestamp' ) ) )
					);
					?>
				</p>
			<?php endif; ?>
			<p class="rebelboost-purge-result"></p>
		</div>
		<?php
	}

	/**
	 * Enqueue admin assets on the settings page and post editors.
	 */
	public function enqueue_admin_assets( $hook ) {
		$load_on = array( 'settings_page_rebelboost', 'post.php', 'post-new.php' );

		if ( ! in_array( $hook, $load_on, true ) && ! RebelBoost::is_connected() ) {
			return;
		}

		wp_enqueue_style(
			'rebelboost-admin',
			REBELBOOST_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			REBELBOOST_VERSION
		);

		wp_enqueue_script(
			'rebelboost-admin',
			REBELBOOST_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			REBELBOOST_VERSION,
			true
		);

		wp_localize_script( 'rebelboost-admin', 'rebelboost', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'rebelboost_nonce' ),
			'i18n'     => array(
				'purging'         => __( 'Purging...', 'rebelboost' ),
				'purge_success'   => __( 'Cache purged successfully.', 'rebelboost' ),
				'purge_error'     => __( 'Failed to purge cache.', 'rebelboost' ),
				'confirm_purge'   => __( 'Purge all RebelBoost cache for this site?', 'rebelboost' ),
				'testing'         => __( 'Testing...', 'rebelboost' ),
				'test_success'    => __( 'Connection successful!', 'rebelboost' ),
				'test_error'      => __( 'Connection failed.', 'rebelboost' ),
			),
		) );
	}

	/**
	 * Enqueue toolbar assets on the frontend (only when admin bar is showing).
	 */
	public function enqueue_toolbar_assets() {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) || ! RebelBoost::is_connected() ) {
			return;
		}

		wp_enqueue_script(
			'rebelboost-admin',
			REBELBOOST_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			REBELBOOST_VERSION,
			true
		);

		wp_localize_script( 'rebelboost-admin', 'rebelboost', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'rebelboost_nonce' ),
			'i18n'     => array(
				'purging'         => __( 'Purging...', 'rebelboost' ),
				'purge_success'   => __( 'Cache purged successfully.', 'rebelboost' ),
				'purge_error'     => __( 'Failed to purge cache.', 'rebelboost' ),
				'confirm_purge'   => __( 'Purge all RebelBoost cache for this site?', 'rebelboost' ),
			),
		) );
	}

	// AJAX handlers.

	public function ajax_purge_all() {
		check_ajax_referer( 'rebelboost_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'rebelboost' ) ) );
		}

		$result = $this->api_client->purge_all();

		if ( true === $result ) {
			wp_send_json_success( array( 'message' => __( 'All cache purged successfully.', 'rebelboost' ) ) );
		} else {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
	}

	public function ajax_purge_page() {
		check_ajax_referer( 'rebelboost_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'rebelboost' ) ) );
		}

		$path    = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '';
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( empty( $path ) ) {
			wp_send_json_error( array( 'message' => __( 'No path specified.', 'rebelboost' ) ) );
		}

		$result = $this->api_client->purge_page( $path );

		if ( true === $result ) {
			if ( $post_id ) {
				update_post_meta( $post_id, '_rebelboost_last_purge', current_time( 'mysql' ) );
			}
			wp_send_json_success( array( 'message' => __( 'Page cache purged successfully.', 'rebelboost' ) ) );
		} else {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
	}
}
