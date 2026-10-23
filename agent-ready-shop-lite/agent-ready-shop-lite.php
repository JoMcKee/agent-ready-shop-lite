<?php
/**
 * Plugin Name:          Agent-Ready Shop Lite
 * Plugin URI:           https://mckeecreative.store/agent-ready-shop/
 * Description:          Lets AI shopping assistants search your WooCommerce products, read details and check stock over MCP. Read-only.
 * Version:              1.0.2
 * Author:               McKee Creative
 * Author URI:           https://mckeecreative.store/
 * Requires at least:    6.4
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * WC requires at least: 8.0
 * WC tested up to:      11.2
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          agent-ready-shop-lite
 *
 * @package AgentReadyShopLite
 */

defined( 'ABSPATH' ) || exit;

define( 'ARSL_VERSION', '1.0.2' );
define( 'ARSL_FILE', __FILE__ );
define( 'ARSL_DIR', __DIR__ );

require_once ARSL_DIR . '/includes/class-arsl-tokens.php';
require_once ARSL_DIR . '/includes/class-arsl-tools.php';
require_once ARSL_DIR . '/includes/class-arsl-server.php';
require_once ARSL_DIR . '/includes/class-arsl-diagnostics.php';
require_once ARSL_DIR . '/includes/class-arsl-admin.php';

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ARSL_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', ARSL_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		add_action( 'rest_api_init', array( 'ARSL_Server', 'register_routes' ) );
		if ( is_admin() ) {
			ARSL_Admin::init();
		}
	}
);

register_activation_hook(
	__FILE__,
	function () {
		add_option( 'arsl_settings', array( 'rate_limit' => 60 ), '', false );
		add_option( 'arsl_tokens', array(), '', false );
	}
);
