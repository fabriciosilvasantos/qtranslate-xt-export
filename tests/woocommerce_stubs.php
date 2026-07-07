<?php
/**
 * WooCommerce stubs for PHPStan static analysis.
 */

/**
 * Main WooCommerce class stub.
 */
class WooCommerce {
	/** @var WC_Cart */
	public $cart;

	/** @var WC_Session */
	public $session;
}

/**
 * WooCommerce cart class stub.
 */
class WC_Cart {
	/**
	 * Get the cart contents for the session.
	 *
	 * @return array
	 */
	public function get_cart_for_session(): array {
		return array();
	}

	/**
	 * Get items in the cart.
	 *
	 * @return array
	 */
	public function get_cart(): array {
		return array();
	}

	/**
	 * Get cart total.
	 *
	 * @return float
	 */
	public function get_cart_contents_total(): float {
		return 0.0;
	}
}

/**
 * WooCommerce order class stub.
 */
class WC_Order {
	/**
	 * Get order ID.
	 *
	 * @return int
	 */
	public function get_id(): int {
		return 0;
	}

	/**
	 * Get order status.
	 *
	 * @return string
	 */
	public function get_status(): string {
		return '';
	}

	/**
	 * Get order meta.
	 *
	 * @param string $key
	 * @param bool   $single
	 * @return mixed
	 */
	public function get_meta( string $key, bool $single = true ) {
		return '';
	}

	/**
	 * Update order meta.
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	public function update_meta_data( string $key, $value ): void {}

	/**
	 * Save order.
	 *
	 * @return int
	 */
	public function save(): int {
		return 0;
	}
}

/**
 * WooCommerce post types class stub.
 */
class WC_Post_Types {
	/**
	 * Register taxonomies.
	 */
	public static function register_taxonomies(): void {}

	/**
	 * Register post types.
	 */
	public static function register_post_types(): void {}
}

/**
 * WooCommerce session class stub.
 */
class WC_Session {
	/**
	 * Get session variable.
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		return $default;
	}

	/**
	 * Set session variable.
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	public function set( string $key, $value ): void {}
}

// WooCommerce global functions

if ( ! function_exists( 'WC' ) ) {
	/**
	 * Returns the main instance of WooCommerce.
	 *
	 * @return WooCommerce
	 */
	function WC(): WooCommerce {
		return new WooCommerce();
	}
}

if ( ! function_exists( 'wc_setcookie' ) ) {
	/**
	 * Sets a WooCommerce cookie.
	 *
	 * @param string $name   Cookie name.
	 * @param string $value  Cookie value.
	 * @param int    $expire Expiry timestamp.
	 * @param bool   $secure Whether to use HTTPS.
	 */
	function wc_setcookie( string $name, string $value, int $expire = 0, bool $secure = false ): void {}
}

if ( ! function_exists( 'wc_get_order' ) ) {
	/**
	 * Retrieves a WooCommerce order.
	 *
	 * @param int $order_id Order ID.
	 * @return WC_Order|false
	 */
	function wc_get_order( int $order_id ) {
		return false;
	}
}

if ( ! function_exists( 'wc_get_product' ) ) {
	/**
	 * Fake stub for wc_get_product.
	 *
	 * @param int|bool $the_product Product ID or false.
	 * @return mixed
	 */
	function wc_get_product( $the_product = false ) {
		return false;
	}
}
