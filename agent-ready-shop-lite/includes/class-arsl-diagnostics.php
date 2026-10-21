<?php
/**
 * Self-check report readable by a human or a support agent. Contains no secrets.
 *
 * @package AgentReadyShopLite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Diagnostics.
 */
class ARSL_Diagnostics {

	/**
	 * Append an event to the short log (last 50).
	 *
	 * @param string $event Event.
	 * @param array  $data  Data.
	 */
	public static function log( $event, $data = array() ) {
		$log   = get_option( 'arsl_log', array() );
		$log[] = array(
			't' => gmdate( 'c' ),
			'e' => $event,
		) + $data;
		update_option( 'arsl_log', array_slice( $log, -50 ), false );
	}

	/**
	 * Build the report.
	 *
	 * @return array
	 */
	public static function report() {
		$s      = get_option( 'arsl_settings', array() );
		$checks = array();
		$add    = function ( $id, $ok, $detail ) use ( &$checks ) {
			$checks[] = array(
				'id'     => $id,
				'status' => $ok ? 'pass' : 'warn',
				'detail' => $detail,
			);
		};
		$perma  = get_option( 'permalink_structure' );
		$tokens = ARSL_Tokens::list_public();
		$add( 'woocommerce_active', class_exists( 'WooCommerce' ), defined( 'WC_VERSION' ) ? 'WooCommerce ' . WC_VERSION : 'missing' );
		$add( 'https', 0 === strpos( home_url(), 'https://' ), home_url() );
		$add( 'pretty_permalinks', (bool) $perma, 'Permalinks: ' . ( $perma ? $perma : 'plain (REST still works via ?rest_route=)' ) );
		$add( 'tokens_exist', count( $tokens ) > 0, count( $tokens ) . ' token(s)' );
		$add( 'store_public', 'yes' !== get_option( 'woocommerce_coming_soon' ), 'yes' === get_option( 'woocommerce_coming_soon' ) ? 'Store is in "Coming soon" mode' : 'Store is live' );
		$add( 'ships_somewhere', count( WC()->countries->get_shipping_countries() ) > 0, count( WC()->countries->get_shipping_countries() ) . ' countries' );
		$log = get_option( 'arsl_log', array() );
		return array(
			'plugin'             => 'agent-ready-shop-lite',
			'plugin_version'     => ARSL_VERSION,
			'wp'                 => get_bloginfo( 'version' ),
			'php'                => PHP_VERSION,
			'wc'                 => defined( 'WC_VERSION' ) ? WC_VERSION : null,
			'endpoint'           => rest_url( 'arsl/v1/mcp' ),
			'rate_limit_per_min' => (int) ( $s['rate_limit'] ?? 60 ),
			'tokens'             => $tokens,
			'checks'             => $checks,
			'recent_events'      => array_slice( $log, -10 ),
		);
	}
}
