<?php
/**
 * Settings screen under WooCommerce > Agent-Ready Shop Lite.
 *
 * @package AgentReadyShopLite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin screen.
 */
class ARSL_Admin {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_post_arsl_save', array( __CLASS__, 'save' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ARSL_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Menu entry.
	 */
	public static function menu() {
		add_submenu_page( 'woocommerce', __( 'Agent-Ready Shop Lite', 'agent-ready-shop-lite' ), __( 'Agent Shop Lite', 'agent-ready-shop-lite' ), 'manage_woocommerce', 'arsl', array( __CLASS__, 'render' ) );
	}

	/**
	 * "Set up" link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=arsl' ) ) . '">' . esc_html__( 'Set up', 'agent-ready-shop-lite' ) . '</a>' );
		return $links;
	}

	/**
	 * Save handler.
	 */
	public static function save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No permission', 'agent-ready-shop-lite' ), 403 );
		}
		check_admin_referer( 'arsl_save' );
		$do  = isset( $_POST['arsl_do'] ) ? sanitize_key( wp_unslash( $_POST['arsl_do'] ) ) : '';
		$msg = 'saved';
		if ( 'settings' === $do ) {
			$s               = get_option( 'arsl_settings', array() );
			$s['rate_limit'] = isset( $_POST['rate_limit'] ) ? min( 600, max( 5, absint( wp_unslash( $_POST['rate_limit'] ) ) ) ) : 60;
			update_option( 'arsl_settings', $s, false );
		} elseif ( 'token' === $do ) {
			$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : 'AI assistant';
			$t     = ARSL_Tokens::create( $label );
			set_transient( 'arsl_newtoken_' . get_current_user_id(), $t['token'], 120 );
			$msg = 'token';
		} elseif ( 'revoke' === $do ) {
			ARSL_Tokens::revoke( isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '' );
			$msg = 'revoked';
		}
		wp_safe_redirect( admin_url( 'admin.php?page=arsl&arsl_msg=' . $msg ) );
		exit;
	}

	/**
	 * Open a form.
	 *
	 * @param string $do Action.
	 */
	private static function form_open( $do ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="arsl_save"><input type="hidden" name="arsl_do" value="' . esc_attr( $do ) . '">';
		wp_nonce_field( 'arsl_save' );
	}

	/**
	 * Render the screen.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s   = get_option( 'arsl_settings', array() );
		$new = get_transient( 'arsl_newtoken_' . get_current_user_id() );
		delete_transient( 'arsl_newtoken_' . get_current_user_id() );
		$r = ARSL_Diagnostics::report();
		echo '<div class="wrap"><h1>' . esc_html__( 'Agent-Ready Shop Lite', 'agent-ready-shop-lite' ) . '</h1>';
		echo '<p>' . esc_html__( 'Let AI shopping assistants search your products, read details and check stock. Read-only: assistants send shoppers to your product pages to buy.', 'agent-ready-shop-lite' ) . '</p>';
		echo '<h2>' . esc_html__( '1. Your MCP address (AI connection link)', 'agent-ready-shop-lite' ) . '</h2><p><code>' . esc_html( $r['endpoint'] ) . '</code></p>';
		echo '<p>' . esc_html__( 'Paste this address and an access token where an AI assistant asks for a "custom connector".', 'agent-ready-shop-lite' ) . '</p>';
		if ( $new ) {
			echo '<div class="notice notice-success"><p><strong>' . esc_html__( 'Copy this token now. It will not be shown again:', 'agent-ready-shop-lite' ) . '</strong><br><code>' . esc_html( $new ) . '</code></p></div>';
		}
		echo '<h2>' . esc_html__( '2. Access tokens', 'agent-ready-shop-lite' ) . '</h2><table class="widefat striped" style="max-width:800px"><thead><tr><th>' . esc_html__( 'Label', 'agent-ready-shop-lite' ) . '</th><th>' . esc_html__( 'Last used', 'agent-ready-shop-lite' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $r['tokens'] as $t ) {
			/* translators: %s: time since last use, e.g. "5 mins". */
			$last = $t['last_used'] ? sprintf( __( '%s ago', 'agent-ready-shop-lite' ), human_time_diff( $t['last_used'] ) ) : __( 'never', 'agent-ready-shop-lite' );
			echo '<tr><td>' . esc_html( $t['label'] ) . '</td><td>' . esc_html( $last ) . '</td><td>';
			self::form_open( 'revoke' );
			echo '<input type="hidden" name="id" value="' . esc_attr( $t['id'] ) . '"><button class="button-link-delete">' . esc_html__( 'Revoke', 'agent-ready-shop-lite' ) . '</button></form></td></tr>';
		}
		echo '</tbody></table><p></p>';
		self::form_open( 'token' );
		echo '<input name="label" placeholder="' . esc_attr__( 'e.g. Claude', 'agent-ready-shop-lite' ) . '" required> ';
		submit_button( __( 'Create token', 'agent-ready-shop-lite' ), 'primary', 'submit', false );
		echo '</form>';
		echo '<h2>' . esc_html__( '3. Settings', 'agent-ready-shop-lite' ) . '</h2>';
		self::form_open( 'settings' );
		echo '<table class="form-table"><tr><th>' . esc_html__( 'Requests per minute per token', 'agent-ready-shop-lite' ) . '</th><td><input type="number" name="rate_limit" min="5" max="600" value="' . esc_attr( $s['rate_limit'] ?? 60 ) . '"></td></tr></table>';
		submit_button( __( 'Save settings', 'agent-ready-shop-lite' ) );
		echo '</form>';
		echo '<h2>' . esc_html__( '4. Self-check', 'agent-ready-shop-lite' ) . '</h2><table class="widefat striped" style="max-width:800px">';
		foreach ( $r['checks'] as $c ) {
			echo '<tr><td>' . ( 'pass' === $c['status'] ? '&#9989;' : '&#9888;&#65039;' ) . ' ' . esc_html( $c['id'] ) . '</td><td>' . esc_html( $c['detail'] ) . '</td></tr>';
		}
		echo '</table><p>' . esc_html__( 'For support, copy this report (it contains no secrets):', 'agent-ready-shop-lite' ) . '</p><textarea readonly rows="10" style="width:100%;max-width:800px;font-family:monospace">' . esc_textarea( wp_json_encode( $r, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . '</textarea></div>';
	}
}
