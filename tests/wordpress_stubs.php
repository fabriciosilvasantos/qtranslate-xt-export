<?php
/**
 * WordPress stubs for simplified integration tests.
 * These mocks simulate WordPress functions without requiring WordPress.
 */

// ============================================================================
// CORE POST FUNCTIONS
// ============================================================================

if ( ! function_exists( 'wp_insert_post' ) ) {
	function wp_insert_post( $postarr = array(), $wp_error = false ) {
		if ( ! isset( $GLOBALS['qtx_wp_inserted_posts'] ) || ! is_array( $GLOBALS['qtx_wp_inserted_posts'] ) ) {
			$GLOBALS['qtx_wp_inserted_posts'] = array();
		}
		if ( ! isset( $GLOBALS['qtx_wp_next_post_id'] ) ) {
			$GLOBALS['qtx_wp_next_post_id'] = 1;
		}

		$current_post_id = (int) $GLOBALS['qtx_wp_next_post_id'];
		$GLOBALS['qtx_wp_next_post_id'] = $current_post_id + 1;

		$postarr['ID'] = $current_post_id;
		$GLOBALS['qtx_wp_inserted_posts'][ $current_post_id ] = $postarr;

		return $current_post_id;
	}
}

if ( ! function_exists( 'wp_update_post' ) ) {
	function wp_update_post( $postarr = array(), $wp_error = false ) {
		if ( ! isset( $GLOBALS['qtx_wp_updated_posts'] ) || ! is_array( $GLOBALS['qtx_wp_updated_posts'] ) ) {
			$GLOBALS['qtx_wp_updated_posts'] = array();
		}

		$post_id = $postarr['ID'] ?? 1;
		$GLOBALS['qtx_wp_updated_posts'][ $post_id ] = $postarr;

		if ( isset( $GLOBALS['qtx_wp_inserted_posts'][ $post_id ] ) && is_array( $GLOBALS['qtx_wp_inserted_posts'][ $post_id ] ) ) {
			$GLOBALS['qtx_wp_inserted_posts'][ $post_id ] = array_merge(
				$GLOBALS['qtx_wp_inserted_posts'][ $post_id ],
				$postarr
			);
		}

		return $post_id;
	}
}

if ( ! function_exists( 'wp_delete_post' ) ) {
	function wp_delete_post( $postid = 0, $force_delete = false ) {
		return true;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post = null, $output = OBJECT, $filter = 'raw' ) {
	if ( null === $post ) {
		global $post;
	}

		if ( is_object( $post ) ) {
			return $post;
		}

		if ( isset( $GLOBALS['qtx_wp_inserted_posts'][ $post ] ) ) {
			return (object) $GLOBALS['qtx_wp_inserted_posts'][ $post ];
		}

		return (object) array(
			'ID'            => (int) $post,
			'post_title'    => 'Test Post',
			'post_content'  => 'Test Content',
			'post_status'   => 'publish',
			'post_type'     => 'post',
			'post_parent'   => 0,
			'menu_order'    => 0,
			'post_name'     => 'test-post',
		);
	}
}

if ( ! function_exists( 'get_post_type' ) ) {
	function get_post_type( $post = null ) {
		$post_object = get_post( $post );

		return isset( $post_object->post_type ) ? (string) $post_object->post_type : '';
	}
}

if ( ! function_exists( 'qtx_wordpress_stub_reset' ) ) {
	function qtx_wordpress_stub_reset() {
		$GLOBALS['qtx_wp_inserted_posts'] = array();
		$GLOBALS['qtx_wp_next_post_id'] = 1;
		$GLOBALS['qtx_wp_updated_posts'] = array();
		$GLOBALS['qtx_wp_post_meta'] = array();
		$GLOBALS['qtx_wp_deleted_term_relationships'] = array();
		$GLOBALS['qtx_wp_options'] = array();
		$GLOBALS['qtx_wp_current_user_can'] = true;
		$GLOBALS['qtx_wp_verify_nonce_result'] = 1;
		$GLOBALS['qtx_wp_admin_pages'] = array();
		$GLOBALS['qtx_wp_redirects'] = array();
	}
}

if ( ! function_exists( 'qtx_wordpress_stub_get_inserted_posts' ) ) {
	function qtx_wordpress_stub_get_inserted_posts() {
		return $GLOBALS['qtx_wp_inserted_posts'] ?? array();
	}
}

// ============================================================================
// POST META FUNCTIONS
// ============================================================================

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
		if ( ! isset( $GLOBALS['qtx_wp_post_meta'] ) || ! is_array( $GLOBALS['qtx_wp_post_meta'] ) ) {
			$GLOBALS['qtx_wp_post_meta'] = array();
		}

		$GLOBALS['qtx_wp_post_meta'][ $post_id ][ $meta_key ] = $meta_value;
		return true;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $meta_key = '', $single = false ) {
		if ( '' === $meta_key ) {
			return $GLOBALS['qtx_wp_post_meta'][ $post_id ] ?? array();
		}

		$value = $GLOBALS['qtx_wp_post_meta'][ $post_id ][ $meta_key ] ?? null;
		if ( null === $value ) {
			return $single ? '' : array();
		}

		return $single ? $value : array( $value );
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( $post_id, $meta_key, $meta_value = '' ) {
		unset( $GLOBALS['qtx_wp_post_meta'][ $post_id ][ $meta_key ] );
		return true;
	}
}

if ( ! function_exists( 'wp_delete_object_term_relationships' ) ) {
	function wp_delete_object_term_relationships( $object_id, $taxonomies ) {
		$GLOBALS['qtx_wp_deleted_term_relationships'][] = array(
			'object_id'  => $object_id,
			'taxonomies' => (array) $taxonomies,
		);

		return true;
	}
}

// ============================================================================
// TERM FUNCTIONS
// ============================================================================

if ( ! function_exists( 'wp_insert_term' ) ) {
	function wp_insert_term( $term, $taxonomy, $args = array() ) {
		static $term_id = 1;
		return array(
			'term_id' => $term_id++,
			'term_taxonomy_id' => $term_id - 1,
		);
	}
}

if ( ! function_exists( 'get_cat_ID' ) ) {
	function get_cat_ID( $cat_name ) {
		return 0;
	}
}

if ( ! function_exists( 'wp_set_post_categories' ) ) {
	function wp_set_post_categories( $post_id = 0, $post_categories = array(), $append = false ) {
		return true;
	}
}

if ( ! function_exists( 'get_term' ) ) {
	function get_term( $term, $taxonomy = '', $output = OBJECT, $filter = 'raw' ) {
		return (object) array(
			'term_id' => (int) $term,
			'name'    => 'Test Term',
			'slug'    => 'test-term',
			'taxonomy' => $taxonomy ?: 'category',
			'parent'  => 0,
		);
	}
}

// ============================================================================
// OPTION FUNCTIONS
// ============================================================================

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return $GLOBALS['qtx_wp_options'][ $option ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		$GLOBALS['qtx_wp_options'][ $option ] = $value;
		return true;
	}
}

// ============================================================================
// DATABASE CLASS (MOCK)
// ============================================================================

if ( ! class_exists( 'wpdb' ) ) {
	class wpdb {
		public $prefix = 'wp_';
		public $options = 'wp_options';
		public $posts = 'wp_posts';
		public $postmeta = 'wp_postmeta';
		public $terms = 'wp_terms';
		public $term_relationships = 'wp_term_relationships';
		public $termmeta = 'wp_termmeta';
		public $term_taxonomy = 'wp_term_taxonomy';
		public $users = 'wp_users';
		public $usermeta = 'wp_usermeta';

		public $last_result = array();

		public function prepare( $query, ...$args ) {
			return vsprintf( $query, $args );
		}

		public function get_results( $query, $output = OBJECT ) {
			return array();
		}

		public function get_var( $query ) {
			return null;
		}

		public function get_row( $query, $output = OBJECT, $offset = 0 ) {
			return null;
		}

		public function query( $query ) {
			return 1;
		}

		public function insert( $table, $data, $format = null ) {
			return 1;
		}

		public function update( $table, $data, $where, $format = null, $where_format = null ) {
			return 1;
		}

		public function delete( $table, $where, $where_format = null ) {
			return 1;
		}
	}
}

if ( ! isset( $wpdb ) ) {
	$wpdb = new wpdb();
}

// ============================================================================
// WP ERROR CLASS
// ============================================================================

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		protected $error_data = array();
		protected $error_codes = array();
		protected $error_messages = array();

		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( ! empty( $code ) ) {
				$this->add( $code, $message, $data );
			}
		}

		public function get_error_codes() {
			return $this->error_codes;
		}

		public function get_error_code() {
			return ! empty( $this->error_codes ) ? $this->error_codes[0] : '';
		}

		public function get_error_messages( $code = '' ) {
			if ( '' !== $code ) {
				return $this->error_messages[ $code ] ?? array();
			}
			return $this->error_messages;
		}

		public function get_error_message( $code = '' ) {
			$code = $code ?: $this->get_error_code();
			return isset( $this->error_messages[ $code ][0] ) ? $this->error_messages[ $code ][0] : '';
		}

		public function get_error_data( $code = '' ) {
			return $this->error_data[ $code ] ?? null;
		}

		public function add( $code, $message, $data = '' ) {
			$this->error_codes[] = $code;
			$this->error_messages[ $code ][] = $message;
			if ( '' !== $data ) {
				$this->error_data[ $code ] = $data;
			}
		}

		public function remove( $code ) {
			$pos = array_search( $code, $this->error_codes, true );
			if ( false !== $pos ) {
				unset( $this->error_codes[ $pos ] );
				unset( $this->error_messages[ $code ] );
				unset( $this->error_data[ $code ] );
			}
		}

		public function has_errors() {
			return ! empty( $this->error_codes );
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

// ============================================================================
// FILTER/ACTION FUNCTIONS
// ============================================================================

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
		static $filters = array();
		$filters[ $tag ][] = array(
			'function'      => $function_to_add,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
		return true;
	}
}

if ( ! function_exists( 'remove_filter' ) ) {
	function remove_filter( $tag, $function_to_remove, $priority = 10 ) {
		static $filters = array();
		foreach ( $filters[ $tag ] ?? array() as $key => $filter ) {
			if ( $filter['function'] === $function_to_remove && $filter['priority'] === $priority ) {
				unset( $filters[ $tag ][ $key ] );
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		static $filters = array();
		$callbacks = $filters[ $tag ] ?? array();
		foreach ( $callbacks as $filter ) {
			$value = call_user_func_array( $filter['function'], array_merge( array( $value ), $args ) );
		}
		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $tag, $function_to_add, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $tag, ...$args ) {
		static $actions = array();
		$callbacks = $actions[ $tag ] ?? array();
		foreach ( $callbacks as $callback ) {
			call_user_func_array( $callback['function'], $args );
		}
	}
}

// ============================================================================
// ESCAPE/SECURITY FUNCTIONS
// ============================================================================

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( '_e' ) ) {
	function _e( $text, $domain = 'default' ) {
		echo $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return esc_html( __( $text, $domain ) );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return esc_attr( __( $text, $domain ) );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo esc_html__( $text, $domain );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo esc_attr__( $text, $domain );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return strip_tags( $str );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
	}
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

if ( ! function_exists( 'wp_get_current_user' ) ) {
	function wp_get_current_user() {
		return (object) array(
			'ID' => 1,
			'user_login' => 'admin',
			'user_email' => 'admin@example.com',
		);
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 1;
	}
}

if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( $actionurl, $action = -1, $name = '_wpnonce' ) {
		return $actionurl;
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
		return '<input type="hidden" name="' . $name . '" value="test" />';
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		// Defaults to "valid" (matches historical stub behavior); tests that
		// need to simulate an invalid/missing nonce can set
		// $GLOBALS['qtx_wp_verify_nonce_result'] to a falsy value.
		return $GLOBALS['qtx_wp_verify_nonce_result'] ?? 1;
	}
}

if ( ! class_exists( 'QTX_WP_Die_Stub_Exception' ) ) {
	/**
	 * Thrown by the wp_die() stub below instead of terminating the PHP
	 * process, so tests can assert that wp_die() was reached without
	 * killing the PHPUnit run.
	 */
	class QTX_WP_Die_Stub_Exception extends RuntimeException {
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = array() ) {
		throw new QTX_WP_Die_Stub_Exception( is_string( $message ) ? $message : 'wp_die' );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		$override = $GLOBALS['qtx_wp_current_user_can'] ?? true;

		if ( is_array( $override ) ) {
			return (bool) ( $override[ $capability ] ?? true );
		}

		return (bool) $override;
	}
}

if ( ! function_exists( 'add_management_page' ) ) {
	function add_management_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '' ) {
		if ( ! isset( $GLOBALS['qtx_wp_admin_pages'] ) || ! is_array( $GLOBALS['qtx_wp_admin_pages'] ) ) {
			$GLOBALS['qtx_wp_admin_pages'] = array();
		}

		$GLOBALS['qtx_wp_admin_pages'][] = array(
			'page_title' => $page_title,
			'menu_title' => $menu_title,
			'capability' => $capability,
			'menu_slug'  => $menu_slug,
			'callback'   => $callback,
		);

		return 'tools_page_' . $menu_slug;
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $location, $status = 302 ) {
		if ( ! isset( $GLOBALS['qtx_wp_redirects'] ) || ! is_array( $GLOBALS['qtx_wp_redirects'] ) ) {
			$GLOBALS['qtx_wp_redirects'] = array();
		}

		$GLOBALS['qtx_wp_redirects'][] = array(
			'location' => $location,
			'status'   => $status,
		);

		return true;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'http://example.com/wp-admin/' . $path;
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'http://example.com/' . $path;
	}
}

if ( ! function_exists( 'site_url' ) ) {
	function site_url( $path = '' ) {
		return 'http://example.com/' . $path;
	}
}

if ( ! function_exists( 'plugins_url' ) ) {
	function plugins_url( $path = '', $plugin = '' ) {
		return 'http://example.com/wp-content/plugins/' . $path;
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return rtrim( $string, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $string ) {
		return rtrim( $string, '/\\' );
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( $defaults, (array) $args );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'wp_rand' ) ) {
	function wp_rand( $min = 0, $max = 0 ) {
		return rand( $min, $max );
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '', $filter = 'raw' ) {
		return 'Test Blog';
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $option ) {
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $transient ) {
		return false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $transient, $value, $expiration = 0 ) {
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $transient ) {
		return true;
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $target ) {
		return mkdir( $target, 0755, true );
	}
}

if ( ! function_exists( 'wp_handle_upload' ) ) {
	function wp_handle_upload( &$file, $overrides, $time = null ) {
		return array(
			'file' => '/tmp/uploaded_file.txt',
			'url'  => 'http://example.com/uploads/uploaded_file.txt',
			'type' => 'text/plain',
		);
	}
}
