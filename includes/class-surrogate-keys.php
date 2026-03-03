<?php

defined( 'ABSPATH' ) || exit;

class RebelBoost_Surrogate_Keys {

	private $tags          = array();
	private $headers_sent  = false;
	private $categories    = array();

	public function register_hooks() {
		add_action( 'wp', array( $this, 'on_wp' ), 1 );
		add_action( 'the_post', array( $this, 'on_the_post' ) );
		add_action( 'send_headers', array( $this, 'send_headers' ) );
		add_filter( 'template_redirect', array( $this, 'on_template_redirect' ), 1 );
	}

	/**
	 * Early check: skip if not applicable.
	 */
	public function on_template_redirect() {
		if ( ! $this->should_inject() ) {
			remove_action( 'the_post', array( $this, 'on_the_post' ) );
			remove_action( 'send_headers', array( $this, 'send_headers' ) );
		}
	}

	/**
	 * Collect page-level tags based on the main query.
	 */
	public function on_wp() {
		if ( ! $this->should_inject() ) {
			return;
		}

		if ( is_front_page() && is_home() ) {
			$this->add_tag( 'pageType:home' );
		} elseif ( is_front_page() ) {
			$this->add_tag( 'pageType:home' );
			$page_id = get_queried_object_id();
			if ( $page_id ) {
				$this->add_tag( 'single:' . $page_id );
			}
		} elseif ( is_home() ) {
			$this->add_tag( 'pageType:blogindex' );
			$page_id = get_queried_object_id();
			if ( $page_id ) {
				$this->add_tag( 'single:' . $page_id );
			}
		} elseif ( is_singular() ) {
			$post = get_queried_object();
			if ( $post ) {
				$this->add_tag( 'single:' . $post->ID );
				$this->add_tag( 'pageType:' . $post->post_type );
				$this->add_tag( 'author:' . $post->post_author );
				$this->collect_post_taxonomy_tags( $post );
				$this->collect_post_categories( $post );
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term ) {
				$this->add_tag( 'tax:' . $term->term_taxonomy_id );
				$this->add_tag( 'pageType:' . $term->taxonomy );
				$this->add_category( $term->slug );
			}
		} elseif ( is_author() ) {
			$author = get_queried_object();
			if ( $author ) {
				$this->add_tag( 'pageType:author' );
				$this->add_tag( 'author:' . $author->ID );
			}
		} elseif ( is_search() ) {
			$this->add_tag( 'pageType:search' );
		} elseif ( is_archive() ) {
			$this->add_tag( 'pageType:archive' );
		}
	}

	/**
	 * Collect tags for each post rendered in a loop.
	 */
	public function on_the_post( $post ) {
		if ( ! $this->should_inject() ) {
			return;
		}

		$this->add_tag( 'post:' . $post->ID );
		$this->add_tag( 'author:' . $post->post_author );
	}

	/**
	 * Inject Surrogate-Key and category headers.
	 */
	public function send_headers() {
		if ( $this->headers_sent || ! $this->should_inject() ) {
			return;
		}

		$this->headers_sent = true;

		if ( ! empty( $this->tags ) ) {
			$value = implode( ' ', array_keys( $this->tags ) );
			header( 'Surrogate-Key: ' . $value, false );
		}

		if ( ! empty( $this->categories ) ) {
			$header_name = get_option( 'rebelboost_category_header', 'X-RM-Categories' );
			if ( ! empty( $header_name ) ) {
				$value = implode( ', ', array_keys( $this->categories ) );
				header( $header_name . ': ' . $value, false );
			}
		}
	}

	private function add_tag( $tag ) {
		$this->tags[ $tag ] = true;
	}

	private function add_category( $slug ) {
		$this->categories[ $slug ] = true;
	}

	/**
	 * Collect taxonomy term tags for a post.
	 */
	private function collect_post_taxonomy_tags( $post ) {
		$taxonomies = get_object_taxonomies( $post->post_type );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_the_terms( $post->ID, $taxonomy );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$this->add_tag( 'tax:' . $term->term_taxonomy_id );
				}
			}
		}
	}

	/**
	 * Collect WordPress categories/tags as cache categories for a post.
	 */
	private function collect_post_categories( $post ) {
		$category_taxonomies = array( 'category', 'post_tag' );

		foreach ( $category_taxonomies as $taxonomy ) {
			$terms = get_the_terms( $post->ID, $taxonomy );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$this->add_category( $term->slug );
				}
			}
		}
	}

	/**
	 * Determine if surrogate key injection should happen.
	 */
	private function should_inject() {
		if ( '1' !== get_option( 'rebelboost_surrogate_keys', '1' ) ) {
			return false;
		}

		if ( ! RebelBoost::is_connected() ) {
			return false;
		}

		if ( is_admin() ) {
			return false;
		}

		if ( wp_doing_ajax() ) {
			return false;
		}

		if ( wp_doing_cron() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		return true;
	}
}
