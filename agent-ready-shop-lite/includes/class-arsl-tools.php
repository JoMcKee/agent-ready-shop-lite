<?php
/**
 * MCP tool definitions and handlers (read-only).
 *
 * @package AgentReadyShopLite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read-only catalogue tools.
 */
class ARSL_Tools {

	/**
	 * Tool definitions.
	 *
	 * @return array
	 */
	public static function definitions() {
		$item = array(
			'type'       => 'object',
			'properties' => array(
				'product_id' => array(
					'type'        => 'integer',
					'description' => 'Product or variation ID',
				),
				'quantity'   => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
			),
			'required'   => array( 'product_id', 'quantity' ),
		);
		$ro   = array(
			'readOnlyHint'  => true,
			'openWorldHint' => false,
		);
		return array(
			array(
				'name'        => 'search_products',
				'description' => 'Search this store\'s published products by keyword and/or category. Returns name, price, currency, stock status and URL.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'query'    => array( 'type' => 'string' ),
						'category' => array(
							'type'        => 'string',
							'description' => 'Category slug',
						),
						'limit'    => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 20,
						),
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
				),
				'annotations' => $ro,
			),
			array(
				'name'        => 'get_product',
				'description' => 'Full details for one product, including variations, attributes and stock.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'product_id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'product_id' ),
				),
				'annotations' => $ro,
			),
			array(
				'name'        => 'check_stock',
				'description' => 'Check whether requested quantities are in stock and purchasable, with current prices.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'items' => array(
							'type'     => 'array',
							'items'    => $item,
							'maxItems' => 10,
						),
					),
					'required'   => array( 'items' ),
				),
				'annotations' => $ro,
			),
			array(
				'name'        => 'get_store_info',
				'description' => 'Store name, currency, countries shipped to, and policy URLs.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new stdClass(),
				),
				'annotations' => $ro,
			),
		);
	}

	/**
	 * Run a tool.
	 *
	 * @param string $name Tool name.
	 * @param array  $args Arguments.
	 * @return array
	 * @throws ARSL_Error On bad input.
	 */
	public static function call( $name, $args ) {
		$args = is_array( $args ) ? $args : array();
		switch ( $name ) {
			case 'search_products':
				return self::search_products( $args );
			case 'get_product':
				return self::get_product( absint( $args['product_id'] ?? 0 ) );
			case 'check_stock':
				return self::check_stock( $args['items'] ?? array() );
			case 'get_store_info':
				return self::store_info();
		}
		throw new ARSL_Error( 'Unknown tool', -32602 );
	}

	/**
	 * Only published, visible, non-password products are ever exposed.
	 *
	 * @param mixed $p Product.
	 * @return bool
	 */
	private static function visible( $p ) {
		if ( ! $p instanceof WC_Product ) {
			return false;
		}
		$check = $p->is_type( 'variation' ) ? wc_get_product( $p->get_parent_id() ) : $p;
		return $check && 'publish' === $check->get_status() && '' === $check->get_post_password() && 'hidden' !== $check->get_catalog_visibility();
	}

	/**
	 * Product summary.
	 *
	 * @param WC_Product $p Product.
	 * @return array
	 */
	private static function summary( WC_Product $p ) {
		$desc = $p->get_short_description() ? $p->get_short_description() : $p->get_description();
		return array(
			'id'                => $p->get_id(),
			'name'              => wp_strip_all_tags( $p->get_name() ),
			'type'              => $p->get_type(),
			'sku'               => $p->get_sku(),
			'price'             => wc_format_decimal( $p->get_price(), wc_get_price_decimals() ),
			'regular_price'     => wc_format_decimal( $p->get_regular_price(), wc_get_price_decimals() ),
			'on_sale'           => $p->is_on_sale(),
			'currency'          => get_woocommerce_currency(),
			'stock_status'      => $p->get_stock_status(),
			'stock_quantity'    => $p->managing_stock() ? $p->get_stock_quantity() : null,
			'purchasable'       => $p->is_purchasable() && $p->is_in_stock(),
			'url'               => get_permalink( $p->get_id() ),
			'short_description' => wp_trim_words( wp_strip_all_tags( $desc ), 40 ),
		);
	}

	/**
	 * Search products.
	 *
	 * @param array $a Args.
	 * @return array
	 */
	private static function search_products( $a ) {
		$limit = min( 20, max( 1, absint( $a['limit'] ?? 10 ) ) );
		$page  = max( 1, absint( $a['page'] ?? 1 ) );
		$q     = array(
			'status'     => 'publish',
			'visibility' => 'catalog',
			'limit'      => $limit,
			'page'       => $page,
			'paginate'   => true,
			'type'       => array( 'simple', 'variable' ),
		);
		if ( ! empty( $a['query'] ) ) {
			$q['s'] = sanitize_text_field( substr( (string) $a['query'], 0, 100 ) );
		}
		if ( ! empty( $a['category'] ) ) {
			$q['category'] = array( sanitize_title( $a['category'] ) );
		}
		$res   = wc_get_products( $q );
		$items = array();
		foreach ( $res->products as $p ) {
			if ( self::visible( $p ) ) {
				$items[] = self::summary( $p );
			}
		}
		return array(
			'products' => $items,
			'total'    => (int) $res->total,
			'page'     => $page,
			'pages'    => (int) $res->max_num_pages,
		);
	}

	/**
	 * One product.
	 *
	 * @param int $id Product id.
	 * @return array
	 * @throws ARSL_Error If not visible.
	 */
	private static function get_product( $id ) {
		$p = wc_get_product( $id );
		if ( ! self::visible( $p ) || $p->is_type( 'variation' ) ) {
			throw new ARSL_Error( 'Product not found', -32602 );
		}
		$out                = self::summary( $p );
		$out['description'] = wp_trim_words( wp_strip_all_tags( $p->get_description() ), 200 );
		$terms              = get_the_terms( $id, 'product_cat' );
		$out['categories']  = is_array( $terms ) ? wp_list_pluck( $terms, 'slug' ) : array();
		$out['attributes']  = array();
		foreach ( $p->get_attributes() as $attr ) {
			if ( $attr->get_visible() || $attr->get_variation() ) {
				$out['attributes'][ wc_attribute_label( $attr->get_name() ) ] = $attr->is_taxonomy() ? wc_get_product_terms( $id, $attr->get_name(), array( 'fields' => 'names' ) ) : $attr->get_options();
			}
		}
		if ( $p->is_type( 'variable' ) ) {
			$out['variations'] = array();
			foreach ( $p->get_children() as $vid ) {
				$v = wc_get_product( $vid );
				if ( $v && 'publish' === $v->get_status() ) {
					$s                   = self::summary( $v );
					$s['attributes']     = $v->get_attributes();
					$out['variations'][] = $s;
				}
			}
		}
		return $out;
	}

	/**
	 * Check stock.
	 *
	 * @param array $items Lines.
	 * @return array
	 * @throws ARSL_Error On bad input.
	 */
	private static function check_stock( $items ) {
		if ( ! is_array( $items ) || ! $items || count( $items ) > 10 ) {
			throw new ARSL_Error( 'Provide 1-10 items', -32602 );
		}
		$res = array();
		foreach ( $items as $i ) {
			if ( ! is_array( $i ) ) {
				throw new ARSL_Error( 'Each item needs product_id and quantity', -32602 );
			}
			$id  = absint( $i['product_id'] ?? 0 );
			$qty = (int) ( $i['quantity'] ?? 0 );
			if ( $qty < 1 || $qty > 100 ) {
				throw new ARSL_Error( 'Quantity must be 1-100', -32602 );
			}
			$p = wc_get_product( $id );
			if ( ! self::visible( $p ) ) {
				throw new ARSL_Error( 'Product ' . (int) $id . ' not found', -32602 );
			}
			$ok    = $p->is_purchasable() && $p->is_in_stock() && $p->has_enough_stock( $qty );
			$res[] = array(
				'product_id'     => $p->get_id(),
				'name'           => wp_strip_all_tags( $p->get_name() ),
				'requested'      => $qty,
				'available'      => $ok,
				'stock_quantity' => $p->managing_stock() ? $p->get_stock_quantity() : null,
				'unit_price'     => wc_format_decimal( $p->get_price(), wc_get_price_decimals() ),
			);
		}
		return array(
			'items'    => $res,
			'currency' => get_woocommerce_currency(),
		);
	}

	/**
	 * Store info.
	 *
	 * @return array
	 */
	private static function store_info() {
		$countries = WC()->countries->get_shipping_countries();
		$terms_id  = wc_get_page_id( 'terms' );
		$privacy   = get_privacy_policy_url();
		return array(
			'name'               => get_bloginfo( 'name' ),
			'url'                => home_url( '/' ),
			'currency'           => get_woocommerce_currency(),
			'ships_to'           => array_keys( $countries ),
			'prices_include_tax' => wc_prices_include_tax(),
			'terms_url'          => $terms_id > 0 ? get_permalink( $terms_id ) : null,
			'privacy_policy_url' => $privacy ? $privacy : null,
			'ordering'           => 'Not available in this plugin. Shoppers buy on the store website.',
		);
	}
}

/**
 * JSON-RPC error.
 */
class ARSL_Error extends Exception {}
