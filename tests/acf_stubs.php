<?php
/**
 * ACF (Advanced Custom Fields) stubs for PHPStan static analysis.
 */

/**
 * Base class for ACF field type objects.
 */
class ACF_Field {
	/** @var string */
	public $name = '';

	/** @var string */
	public $label = '';

	/** @var string */
	public $category = '';

	/** @var string */
	public $key = '';

	/** @var string */
	public $type = '';

	/** @var mixed */
	public $value = null;

	/** @var int|string|false */
	public $parent = false;

	/** @var string */
	public $_name = '';

	/** @var bool */
	public $_valid = true;

	/** @var array */
	public $_prepare = array();

	/** @var array */
	public $defaults = array();

	/** @var string */
	public $instructions = '';

	/** @var bool */
	public $required = false;

	/** @var string */
	public $conditional_logic = '';

	/** @var string */
	public $wrapper = '';

	/** @var array */
	public $settings = array();

	public function __construct( $field = array() ) {}

	public function initialize(): void {}

	public function render_field( $field ): void {}

	public function render_field_settings( $field ): void {}

	public function load_value( $value, $post_id, $field ) {
		return $value;
	}

	public function format_value( $value, $post_id, $field ) {
		return $value;
	}

	public function update_value( $value, $post_id, $field ) {
		return $value;
	}

	public function validate_value( $valid, $value, $field, $input ) {
		return $valid;
	}

	public function input_admin_enqueue_scripts(): void {}

	public function input_admin_head(): void {}

	public function input_admin_footer(): void {}

	public function get_label(): string {
		return $this->label;
	}

	public function get_name(): string {
		return $this->name;
	}
}

class acf_field_file extends ACF_Field {}

class acf_field_image extends ACF_Field {}

class acf_field_post_object extends ACF_Field {}

class acf_field_text extends ACF_Field {}

class acf_field_textarea extends ACF_Field {}

class acf_field_url extends ACF_Field {}

class acf_field_wysiwyg extends ACF_Field {}

// ACF global functions

if ( ! function_exists( 'acf_get_setting' ) ) {
	function acf_get_setting( string $name ) {
		return null;
	}
}

if ( ! function_exists( 'acf_enqueue_uploader' ) ) {
	function acf_enqueue_uploader(): void {}
}

if ( ! function_exists( 'acf_esc_attrs' ) ) {
	function acf_esc_attrs( array $atts ): string {
		return '';
	}
}

if ( ! function_exists( 'acf_hidden_input' ) ) {
	function acf_hidden_input( array $atts ): void {}
}

if ( ! function_exists( 'acf_get_image_size' ) ) {
	function acf_get_image_size( string $size ): array {
		return array();
	}
}

if ( ! function_exists( 'acf_file_input' ) ) {
	/**
	 * Renders a file input element for ACF.
	 *
	 * @param array $atts Input attributes (name, id, key, etc.).
	 */
	function acf_file_input( array $atts ): void {}
}

if ( ! function_exists( 'acf_esc_html' ) ) {
	function acf_esc_html( string $str ): string {
		return $str;
	}
}

if ( ! function_exists( 'acf_clean_atts' ) ) {
	function acf_clean_atts( array $atts ): array {
		return $atts;
	}
}

if ( ! function_exists( 'acf_get_text_input' ) ) {
	function acf_get_text_input( array $args ): string {
		return '';
	}
}

if ( ! function_exists( 'acf_get_user_setting' ) ) {
	function acf_get_user_setting( string $key, $default = null ) {
		return $default;
	}
}

if ( ! function_exists( 'acf_render_field_setting' ) ) {
	function acf_render_field_setting( array $field, array $args ): void {}
}

if ( ! function_exists( 'acf_get_field_type' ) ) {
	/**
	 * Get a field type object.
	 *
	 * @param string $type The field type name.
	 * @return ACF_Field|false
	 */
	function acf_get_field_type( string $type ) {
		return false;
	}
}

if ( ! function_exists( 'acf' ) ) {
	/**
	 * Returns the ACF singleton instance.
	 *
	 * @return object
	 */
	function acf() {
		return new stdClass();
	}
}

if ( ! function_exists( 'get_field' ) ) {
	/**
	 * Returns the value of a specific field.
	 *
	 * @param string           $selector     The field name or key.
	 * @param int|string|false $post_id      The post ID. Defaults to current post.
	 * @param bool             $format_value Whether to apply formatting.
	 * @return mixed
	 */
	function get_field( string $selector, $post_id = false, bool $format_value = true ) {
		return null;
	}
}

if ( ! function_exists( 'the_field' ) ) {
	/**
	 * Displays the value of a specific field.
	 *
	 * @param string           $selector The field name or key.
	 * @param int|string|false $post_id  The post ID.
	 */
	function the_field( string $selector, $post_id = false ): void {}
}

if ( ! function_exists( 'have_rows' ) ) {
	/**
	 * Checks if a Repeater or Flexible Content field has rows.
	 *
	 * @param string           $selector The field name or key.
	 * @param int|string|false $post_id  The post ID.
	 */
	function have_rows( string $selector, $post_id = false ): bool {
		return false;
	}
}

if ( ! function_exists( 'the_row' ) ) {
	/**
	 * Moves the internal pointer to the next row.
	 */
	function the_row(): bool {
		return false;
	}
}

if ( ! function_exists( 'get_sub_field' ) ) {
	/**
	 * Returns the value of a sub field.
	 *
	 * @param string $selector     The sub field name or key.
	 * @param bool   $format_value Whether to apply formatting.
	 * @return mixed
	 */
	function get_sub_field( string $selector, bool $format_value = true ) {
		return null;
	}
}

if ( ! function_exists( 'update_field' ) ) {
	/**
	 * Updates the value of a specific field.
	 *
	 * @param string           $selector The field name or key.
	 * @param mixed            $value    The new field value.
	 * @param int|string|false $post_id  The post ID.
	 */
	function update_field( string $selector, $value, $post_id = false ): bool {
		return false;
	}
}

if ( ! function_exists( 'acf_add_local_field_group' ) ) {
	/**
	 * Registers a local field group.
	 *
	 * @param array $field_group The field group settings.
	 */
	function acf_add_local_field_group( array $field_group ): array {
		return array();
	}
}
