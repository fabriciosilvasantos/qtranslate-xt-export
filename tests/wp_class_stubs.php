<?php
/**
 * PHPStan stub for the WordPress WP class.
 *
 * WordPress adds several properties to the WP object dynamically at runtime
 * (in WP::parse_request()). PHPStan cannot infer these from the core source,
 * so we declare them explicitly to avoid false-positive "undefined property"
 * errors without resorting to a blanket ignoreErrors rule.
 *
 * @see https://developer.wordpress.org/reference/classes/wp/
 */

if ( ! class_exists( 'WP' ) ) {
	class WP {
		/**
		 * The matched rewrite rule's query string.
		 * Set by WP::parse_request().
		 *
		 * @var string
		 */
		public string $matched_query = '';

		/**
		 * The matched rewrite rule.
		 * Set by WP::parse_request().
		 *
		 * @var string
		 */
		public string $matched_rule = '';

		/**
		 * The request path (URI without query string).
		 * Set by WP::parse_request().
		 *
		 * @var string
		 */
		public string $request = '';

		/**
		 * Whether the current request used a permalink.
		 * Set by WP::parse_request().
		 *
		 * @var bool
		 */
		public bool $did_permalink = false;

		/**
		 * Current query variables.
		 * Set by WP::parse_request().
		 *
		 * @var array<string, mixed>
		 */
		public array $query_vars = array();

		/**
		 * @param array<string, mixed> $query_args
		 */
		public function parse_request( $query_args = '' ): void {
		}

		public function send_headers(): void {
		}

		public function query_posts(): void {
		}

		public function handle_404(): void {
		}

		public function register_globals(): void {
		}

		/**
		 * @param array<string, mixed>|string $query_args
		 */
		public function main( $query_args = '' ): void {
		}
	}
}
