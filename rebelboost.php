<?php
/**
 * Plugin Name:  RebelBoost
 * Plugin URI:   https://rebelboost.io
 * Description:  Automatic cache invalidation and optimization integration for RebelBoost page optimizer.
 * Version:      0.6.4
 * Author:       RebelMouse
 * Author URI:   https://www.rebelmouse.com/
 * License:      GPL2
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  rebelboost
 */

defined( 'ABSPATH' ) || exit;

define( 'REBELBOOST_VERSION', '0.6.4' );
define( 'REBELBOOST_PLUGIN_FILE', __FILE__ );
define( 'REBELBOOST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'REBELBOOST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once REBELBOOST_PLUGIN_DIR . 'includes/class-rebelboost.php';

RebelBoost::init();

register_activation_hook( __FILE__, array( 'RebelBoost', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RebelBoost', 'deactivate' ) );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=rebelboost' ) ) . '">' . __( 'Settings', 'rebelboost' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
} );
