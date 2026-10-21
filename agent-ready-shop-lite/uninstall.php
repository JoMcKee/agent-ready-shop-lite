<?php
/**
 * Removes the plugin's options and transients.
 *
 * @package AgentReadyShopLite
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'arsl_settings' );
delete_option( 'arsl_tokens' );
delete_option( 'arsl_log' );
global $wpdb;
// Rate-limit counters are short-lived transients with a fixed prefix; there is no API to delete by prefix.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_arsl_' ) . '%', $wpdb->esc_like( '_transient_timeout_arsl_' ) . '%' ) );
