<?php
/**
 * RebelBoost uninstall handler.
 *
 * Cleans up all plugin data when the plugin is deleted from WordPress.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove all plugin options.
$options = array(
	'rebelboost_api_key',
	'rebelboost_auto_purge',
	'rebelboost_purge_on_comment',
	'rebelboost_surrogate_keys',
	'rebelboost_category_header',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Clean up post meta.
global $wpdb;
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_rebelboost_last_purge' ) );
