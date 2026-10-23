<?php
/**
 * Minimal MCP server (Streamable HTTP, JSON responses, stateless) on /wp-json/arsl/v1/mcp.
 *
 * @package AgentReadyShopLite
 */

defined( 'ABSPATH' ) || exit;

/**
 * MCP endpoint.
 */
class ARSL_Server {
	const PROTOCOL = '2025-06-18';

	/**
	 * Register REST routes.
	 */
	public static function register_routes() {
		register_rest_route(
			'arsl/v1',
			'/mcp',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'handle' ),
					// MCP clients are not WordPress users: the bearer token is checked in authorize().
					'permission_callback' => array( __CLASS__, 'authorize' ),
				),
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_not_allowed' ),
					'permission_callback' => array( __CLASS__, 'authorize' ),
				),
			)
		);
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'auth_headers' ), 10, 3 );
	}

	/**
	 * Token verified by authorize() for this request.
	 *
	 * @var array|null
	 */
	private static $token = null;

	/**
	 * REST permission check: a valid bearer token, with a per-IP limit on failures.
	 *
	 * @param WP_REST_Request $r Request.
	 * @return true|WP_Error
	 */
	public static function authorize( WP_REST_Request $r ) {
		self::$token = ARSL_Tokens::verify( self::bearer( $r ) );
		if ( self::$token ) {
			return true;
		}
		if ( self::limited( 'badauth|' . self::client_ip(), 10 ) ) {
			return new WP_Error( 'arsl_rate_limited', 'rate_limited', array( 'status' => 429 ) );
		}
		return new WP_Error( 'arsl_unauthorized', 'Missing or invalid token', array( 'status' => 401 ) );
	}

	/**
	 * Add WWW-Authenticate and no-store headers to auth failures on our route.
	 *
	 * @param WP_HTTP_Response $res    Response.
	 * @param WP_REST_Server   $server Server.
	 * @param WP_REST_Request  $req    Request.
	 * @return WP_HTTP_Response
	 */
	public static function auth_headers( $res, $server, $req ) {
		if ( $res instanceof WP_REST_Response && 0 === strpos( (string) $req->get_route(), '/arsl/v1/' ) && in_array( $res->get_status(), array( 401, 429 ), true ) ) {
			if ( 401 === $res->get_status() ) {
				$res->header( 'WWW-Authenticate', 'Bearer realm="agent-ready-shop-lite"' );
			}
			self::nocache( $res );
		}
		return $res;
	}

	/**
	 * GET is not supported (no SSE stream).
	 *
	 * @return WP_REST_Response
	 */
	public static function get_not_allowed() {
		return new WP_REST_Response( array( 'error' => 'Use POST (MCP Streamable HTTP, no SSE stream).' ), 405 );
	}

	/**
	 * Client IP for rate limiting.
	 *
	 * @return string
	 */
	private static function client_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
	}

	/**
	 * Fixed-window counter in a transient.
	 *
	 * @param string $key   Bucket.
	 * @param int    $limit Per minute.
	 * @return bool True if over the limit.
	 */
	private static function limited( $key, $limit ) {
		$k = 'arsl_rl_' . md5( $key . '|' . floor( time() / 60 ) );
		$n = (int) get_transient( $k ) + 1;
		set_transient( $k, $n, 70 );
		return $n > $limit;
	}

	/**
	 * Bearer token from the request.
	 *
	 * @param WP_REST_Request $r Request.
	 * @return string
	 */
	private static function bearer( WP_REST_Request $r ) {
		$h = (string) $r->get_header( 'authorization' );
		if ( preg_match( '/^Bearer\s+(\S+)$/i', $h, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * No-cache headers.
	 *
	 * @param WP_REST_Response $res Response.
	 * @return WP_REST_Response
	 */
	private static function nocache( WP_REST_Response $res ) {
		$res->header( 'Cache-Control', 'no-store, private' );
		$res->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
		return $res;
	}

	/**
	 * Handle one JSON-RPC message.
	 *
	 * @param WP_REST_Request $r Request.
	 * @return WP_REST_Response
	 */
	public static function handle( WP_REST_Request $r ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			// Standard constant read by page-cache plugins; must not be prefixed.
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
		}
		$token = self::$token ? self::$token : ARSL_Tokens::verify( self::bearer( $r ) );
		if ( ! $token ) {
			$res = new WP_REST_Response(
				array(
					'jsonrpc' => '2.0',
					'id'      => null,
					'error'   => array(
						'code'    => -32001,
						'message' => 'Missing or invalid token',
					),
				),
				401
			);
			$res->header( 'WWW-Authenticate', 'Bearer realm="agent-ready-shop-lite"' );
			return self::nocache( $res );
		}
		$s = get_option( 'arsl_settings', array() );
		if ( self::limited( 'tok|' . $token['id'], max( 1, (int) ( $s['rate_limit'] ?? 60 ) ) ) ) {
			$res = new WP_REST_Response(
				array(
					'jsonrpc' => '2.0',
					'id'      => null,
					'error'   => array(
						'code'    => -32029,
						'message' => 'Rate limit exceeded, retry in a minute',
					),
				),
				429
			);
			$res->header( 'Retry-After', '60' );
			return self::nocache( $res );
		}
		if ( strlen( $r->get_body() ) > 65536 ) {
			return self::nocache(
				new WP_REST_Response(
					array(
						'jsonrpc' => '2.0',
						'id'      => null,
						'error'   => array(
							'code'    => -32600,
							'message' => 'Request too large',
						),
					),
					413
				)
			);
		}
		$msg = json_decode( $r->get_body(), true );
		if ( ! is_array( $msg ) || isset( $msg[0] ) ) {
			return self::nocache(
				new WP_REST_Response(
					array(
						'jsonrpc' => '2.0',
						'id'      => null,
						'error'   => array(
							'code'    => -32700,
							'message' => 'Parse error (single JSON-RPC message expected)',
						),
					),
					400
				)
			);
		}
		if ( ! isset( $msg['id'] ) ) {
			return self::nocache( new WP_REST_Response( null, 202 ) );
		}
		$id     = $msg['id'];
		$method = (string) ( $msg['method'] ?? '' );
		try {
			$body = array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'result'  => self::dispatch( $method, $msg['params'] ?? array() ),
			);
		} catch ( ARSL_Error $e ) {
			if ( 'tools/call' === $method && -32601 !== $e->getCode() ) {
				$body = array(
					'jsonrpc' => '2.0',
					'id'      => $id,
					'result'  => array(
						'isError' => true,
						'content' => array(
							array(
								'type' => 'text',
								'text' => $e->getMessage(),
							),
						),
					),
				);
			} else {
				$body = array(
					'jsonrpc' => '2.0',
					'id'      => $id,
					'error'   => array(
						'code'    => $e->getCode(),
						'message' => $e->getMessage(),
					),
				);
			}
		} catch ( Throwable $e ) {
			ARSL_Diagnostics::log(
				'error',
				array(
					'msg' => substr( $e->getMessage(), 0, 300 ),
					'at'  => basename( $e->getFile() ) . ':' . $e->getLine(),
				)
			);
			$body = array(
				'jsonrpc' => '2.0',
				'id'      => $id,
				'error'   => array(
					'code'    => -32603,
					'message' => 'Internal error',
				),
			);
		}
		return self::nocache( new WP_REST_Response( $body, 200 ) );
	}

	/**
	 * Dispatch a method.
	 *
	 * @param string $method Method.
	 * @param array  $params Params.
	 * @return mixed
	 * @throws ARSL_Error Unknown method or tool.
	 */
	private static function dispatch( $method, $params ) {
		switch ( $method ) {
			case 'initialize':
				return array(
					'protocolVersion' => self::PROTOCOL,
					'capabilities'    => array( 'tools' => array( 'listChanged' => false ) ),
					'serverInfo'      => array(
						'name'    => 'agent-ready-shop-lite',
						'title'   => get_bloginfo( 'name' ) . ' shop',
						'version' => ARSL_VERSION,
					),
					'instructions'    => 'Shop ' . get_bloginfo( 'name' ) . '. Search products, read details and check stock. To buy, send the shopper to the product URL on the store website.',
				);
			case 'ping':
				return new stdClass();
			case 'tools/list':
				return array( 'tools' => ARSL_Tools::definitions() );
			case 'tools/call':
				$name    = (string) ( $params['name'] ?? '' );
				$allowed = wp_list_pluck( ARSL_Tools::definitions(), 'name' );
				if ( ! in_array( $name, $allowed, true ) ) {
					throw new ARSL_Error( 'Tool not available: ' . esc_html( $name ), -32602 );
				}
				$data = ARSL_Tools::call( $name, $params['arguments'] ?? array() );
				return array(
					'content'           => array(
						array(
							'type' => 'text',
							'text' => wp_json_encode( $data ),
						),
					),
					'structuredContent' => $data,
				);
		}
		throw new ARSL_Error( 'Method not found', -32601 );
	}
}
