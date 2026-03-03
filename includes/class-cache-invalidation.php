<?php

defined( 'ABSPATH' ) || exit;

class RebelBoost_Cache_Invalidation {

	private $api_client;

	private $purge_paths      = array();
	private $purge_categories = array();
	private $purge_all        = false;
	private $shutdown_registered = false;

	/**
	 * Post types that should never trigger cache invalidation.
	 */
	private static $ignored_post_types = array(
		'revision',
		'auto-draft',
		'nav_menu_item',
		'customize_changeset',
		'custom_css',
		'oembed_cache',
		'scheduled-action',
	);

	public function __construct( RebelBoost_API_Client $api_client ) {
		$this->api_client = $api_client;
	}

	public function register_hooks() {
		// Post changes.
		add_action( 'transition_post_status', array( $this, 'on_post_transition' ), 10, 3 );

		// Comment changes.
		add_action( 'transition_comment_status', array( $this, 'on_comment_transition' ), 10, 3 );
		add_action( 'comment_post', array( $this, 'on_comment_post' ), 10, 2 );

		// Theme changes.
		add_action( 'switch_theme', array( $this, 'on_theme_switch' ) );
		add_action( 'customize_save_after', array( $this, 'on_customizer_save' ) );

		// Plugin/theme updates.
		add_action( 'activated_plugin', array( $this, 'on_plugin_change' ) );
		add_action( 'deactivated_plugin', array( $this, 'on_plugin_change' ) );
		add_action( 'upgrader_process_complete', array( $this, 'on_upgrade' ) );

		// Term (category/tag/taxonomy) changes.
		add_action( 'edit_term', array( $this, 'on_term_change' ), 10, 3 );
		add_action( 'delete_term', array( $this, 'on_term_change' ), 10, 3 );

		// Menu changes.
		add_action( 'wp_update_nav_menu', array( $this, 'on_menu_update' ) );

		// WooCommerce support.
		add_action( 'plugins_loaded', array( $this, 'maybe_register_woocommerce_hooks' ) );
	}

	/**
	 * Handle post status transitions.
	 */
	public function on_post_transition( $new_status, $old_status, $post ) {
		if ( ! $this->should_auto_purge() ) {
			return;
		}

		if ( wp_is_post_revision( $post ) ) {
			return;
		}

		if ( in_array( $post->post_type, self::$ignored_post_types, true ) ) {
			return;
		}

		// Ignore non-meaningful transitions.
		if ( 'auto-draft' === $new_status ) {
			return;
		}
		if ( 'draft' === $new_status && 'publish' !== $old_status ) {
			return;
		}
		if ( 'inherit' === $new_status ) {
			return;
		}

		// Global invalidation for site-wide content types.
		if ( in_array( $post->post_type, array( 'wp_navigation', 'wp_template', 'wp_template_part' ), true ) ) {
			$this->queue_purge_all();
			return;
		}

		$post_path = $this->get_post_path( $post );
		if ( ! $post_path ) {
			return;
		}

		$involves_publish = 'publish' === $new_status || 'publish' === $old_status;

		if ( ! $involves_publish ) {
			return;
		}

		// Always purge the post itself.
		$this->queue_purge_path( $post_path );

		// Purge home and blog index when publish status changes.
		$status_changed = $new_status !== $old_status;
		if ( $status_changed ) {
			$this->queue_purge_path( '/' );

			$blog_page_id = (int) get_option( 'page_for_posts' );
			if ( $blog_page_id ) {
				$blog_path = $this->get_path_from_url( get_permalink( $blog_page_id ) );
				if ( $blog_path ) {
					$this->queue_purge_path( $blog_path );
				}
			}
		}

		// Purge taxonomy archive pages.
		$this->queue_taxonomy_purges( $post );
	}

	/**
	 * Handle comment status transitions.
	 */
	public function on_comment_transition( $new_status, $old_status, $comment ) {
		if ( ! $this->should_purge_on_comment() ) {
			return;
		}

		$post = get_post( $comment->comment_post_ID );
		if ( $post && 'publish' === $post->post_status ) {
			$path = $this->get_post_path( $post );
			if ( $path ) {
				$this->queue_purge_path( $path );
			}
		}
	}

	/**
	 * Handle new comment posts.
	 */
	public function on_comment_post( $comment_id, $approved ) {
		if ( ! $this->should_purge_on_comment() ) {
			return;
		}

		if ( 1 !== $approved && 'approve' !== $approved ) {
			return;
		}

		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return;
		}

		$post = get_post( $comment->comment_post_ID );
		if ( $post && 'publish' === $post->post_status ) {
			$path = $this->get_post_path( $post );
			if ( $path ) {
				$this->queue_purge_path( $path );
			}
		}
	}

	/**
	 * Handle theme switch — purge all.
	 */
	public function on_theme_switch() {
		if ( $this->should_auto_purge() ) {
			$this->queue_purge_all();
		}
	}

	/**
	 * Handle customizer save — purge all.
	 */
	public function on_customizer_save() {
		if ( $this->should_auto_purge() ) {
			$this->queue_purge_all();
		}
	}

	/**
	 * Handle plugin activation/deactivation — purge all.
	 */
	public function on_plugin_change() {
		if ( $this->should_auto_purge() ) {
			$this->queue_purge_all();
		}
	}

	/**
	 * Handle plugin/theme upgrades — purge all.
	 */
	public function on_upgrade() {
		if ( $this->should_auto_purge() ) {
			$this->queue_purge_all();
		}
	}

	/**
	 * Handle term (category/tag) changes.
	 */
	public function on_term_change( $term_id, $tt_id, $taxonomy ) {
		if ( ! $this->should_auto_purge() ) {
			return;
		}

		$term_link = get_term_link( (int) $term_id, $taxonomy );
		if ( ! is_wp_error( $term_link ) ) {
			$path = $this->get_path_from_url( $term_link );
			if ( $path ) {
				$this->queue_purge_path( $path );
			}
		}
	}

	/**
	 * Handle nav menu updates — purge all (menus appear on every page).
	 */
	public function on_menu_update() {
		if ( $this->should_auto_purge() ) {
			$this->queue_purge_all();
		}
	}

	/**
	 * Register WooCommerce-specific hooks if WooCommerce is active.
	 */
	public function maybe_register_woocommerce_hooks() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action( 'woocommerce_update_product', array( $this, 'on_wc_product_update' ) );
		add_action( 'woocommerce_new_order', array( $this, 'on_wc_order' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_wc_order' ) );
	}

	/**
	 * Handle WooCommerce product update.
	 */
	public function on_wc_product_update( $product_id ) {
		if ( ! $this->should_auto_purge() ) {
			return;
		}

		$product_path = $this->get_path_from_url( get_permalink( $product_id ) );
		if ( $product_path ) {
			$this->queue_purge_path( $product_path );
		}

		// Purge shop page.
		$shop_page_id = wc_get_page_id( 'shop' );
		if ( $shop_page_id > 0 ) {
			$shop_path = $this->get_path_from_url( get_permalink( $shop_page_id ) );
			if ( $shop_path ) {
				$this->queue_purge_path( $shop_path );
			}
		}
	}

	/**
	 * Handle WooCommerce order changes.
	 */
	public function on_wc_order( $order_id ) {
		if ( ! $this->should_auto_purge() ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$product_id   = $item->get_product_id();
			$product_path = $this->get_path_from_url( get_permalink( $product_id ) );
			if ( $product_path ) {
				$this->queue_purge_path( $product_path );
			}
		}
	}

	/**
	 * Queue a path for purging at shutdown.
	 */
	public function queue_purge_path( $path ) {
		$this->purge_paths[ $path ] = true;
		$this->ensure_shutdown_registered();
	}

	/**
	 * Queue a category for purging at shutdown.
	 */
	public function queue_purge_category( $category ) {
		$this->purge_categories[ $category ] = true;
		$this->ensure_shutdown_registered();
	}

	/**
	 * Queue a full cache purge at shutdown.
	 */
	public function queue_purge_all() {
		$this->purge_all = true;
		$this->ensure_shutdown_registered();
	}

	/**
	 * Execute all queued purges. Called at shutdown.
	 */
	public function execute_purges() {
		if ( ! RebelBoost::is_connected() ) {
			return;
		}

		// If purge_all is set, just do that and skip individual purges.
		if ( $this->purge_all ) {
			$this->api_client->purge_all();
			return;
		}

		foreach ( array_keys( $this->purge_categories ) as $category ) {
			$this->api_client->purge_category( $category );
		}

		foreach ( array_keys( $this->purge_paths ) as $path ) {
			$this->api_client->purge_page( $path );
		}
	}

	/**
	 * Register the shutdown function once per request.
	 */
	private function ensure_shutdown_registered() {
		if ( ! $this->shutdown_registered ) {
			register_shutdown_function( array( $this, 'execute_purges' ) );
			$this->shutdown_registered = true;
		}
	}

	/**
	 * Queue purges for taxonomy archive pages associated with a post.
	 */
	private function queue_taxonomy_purges( $post ) {
		$taxonomies = get_object_taxonomies( $post->post_type );

		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_the_terms( $post->ID, $taxonomy );
			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$term_link = get_term_link( $term );
				if ( ! is_wp_error( $term_link ) ) {
					$path = $this->get_path_from_url( $term_link );
					if ( $path ) {
						$this->queue_purge_path( $path );
					}
				}
			}
		}
	}

	/**
	 * Get the URL path for a post.
	 */
	private function get_post_path( $post ) {
		$permalink = get_permalink( $post );
		if ( ! $permalink ) {
			return false;
		}
		return $this->get_path_from_url( $permalink );
	}

	/**
	 * Extract the path component from a full URL.
	 */
	private function get_path_from_url( $url ) {
		$parsed = wp_parse_url( $url );
		if ( ! $parsed || empty( $parsed['path'] ) ) {
			return '/';
		}
		return $parsed['path'];
	}

	private function should_auto_purge() {
		return RebelBoost::is_connected() && '1' === get_option( 'rebelboost_auto_purge', '1' );
	}

	private function should_purge_on_comment() {
		return $this->should_auto_purge() && '1' === get_option( 'rebelboost_purge_on_comment', '1' );
	}
}
