<?php
/**
 * Read-only bearer tokens. Only an HMAC of each token is stored.
 *
 * @package AgentReadyShopLite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Token store.
 */
class ARSL_Tokens {

	/**
	 * HMAC a token with the site's auth salt.
	 *
	 * @param string $token Plain token.
	 * @return string
	 */
	public static function hash( $token ) {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	/**
	 * Create a token. The plain token is returned once and never stored.
	 *
	 * @param string $label Label.
	 * @return array
	 */
	public static function create( $label ) {
		$token        = 'arsl_r_' . bin2hex( random_bytes( 24 ) );
		$all          = get_option( 'arsl_tokens', array() );
		$id           = substr( self::hash( $token ), 0, 12 );
		$all[ $id ]   = array(
			'hash'    => self::hash( $token ),
			'label'   => sanitize_text_field( $label ),
			'scope'   => 'read',
			'created' => time(),
			'last'    => 0,
		);
		update_option( 'arsl_tokens', $all, false );
		return array(
			'id'    => $id,
			'token' => $token,
		);
	}

	/**
	 * Revoke a token.
	 *
	 * @param string $id Token id.
	 */
	public static function revoke( $id ) {
		$all = get_option( 'arsl_tokens', array() );
		unset( $all[ $id ] );
		update_option( 'arsl_tokens', $all, false );
	}

	/**
	 * Verify a token.
	 *
	 * @param string $token Plain token.
	 * @return array|null Token record with id.
	 */
	public static function verify( $token ) {
		if ( ! is_string( $token ) || strlen( $token ) < 20 || strlen( $token ) > 100 ) {
			return null;
		}
		$h = self::hash( $token );
		foreach ( get_option( 'arsl_tokens', array() ) as $id => $rec ) {
			if ( hash_equals( $rec['hash'], $h ) ) {
				if ( time() - (int) $rec['last'] > 300 ) {
					$all                = get_option( 'arsl_tokens', array() );
					$all[ $id ]['last'] = time();
					update_option( 'arsl_tokens', $all, false );
				}
				$rec['id'] = $id;
				return $rec;
			}
		}
		return null;
	}

	/**
	 * Tokens without hashes.
	 *
	 * @return array
	 */
	public static function list_public() {
		$out = array();
		foreach ( get_option( 'arsl_tokens', array() ) as $id => $rec ) {
			$out[] = array(
				'id'        => $id,
				'label'     => $rec['label'],
				'created'   => $rec['created'],
				'last_used' => $rec['last'],
			);
		}
		return $out;
	}
}
