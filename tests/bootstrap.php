<?php
/**
 * PHPUnit bootstrap file.
 *
 * Initialises WP_Mock, defines the WordPress constants and stubs needed by
 * the plugin classes under test, and registers the PSR-4 autoloader so the
 * plugin's own classes can be found without a running WordPress installation.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// ── WordPress constants ──────────────────────────────────────────────────────

if ( ! defined( 'ABSPATH' ) ) {
	// Point at a temp directory; Installer::install() guards require_once with
	// function_exists('dbDelta') so the actual file is never loaded in tests.
	define( 'ABSPATH', sys_get_temp_dir() . '/wp-raffle-test/' );
}

// ── WordPress function stubs used by production code ────────────────────────
// These are defined here so they are always available regardless of which test
// runs first, and so WP_Mock::userFunction() can override them per-test.

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param string $str Input.
	 * @return string
	 */
	function sanitize_text_field( string $str ): string {
		return strip_tags( $str );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * @param mixed $maybeint Value to cast.
	 * @return int
	 */
	function absint( mixed $maybeint ): int {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * @param string $text Text to escape.
	 * @return string
	 */
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * @param string $text Text to escape.
	 * @return string
	 */
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * @param string $url URL to escape.
	 * @return string
	 */
	function esc_url( string $url ): string {
		return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * @param string $text   Text to translate and escape.
	 * @param string $domain Text domain (unused in tests).
	 * @return string
	 */
	function esc_html__( string $text, string $domain = 'default' ): string {
		return esc_html( $text );
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain (unused in tests).
	 * @return string
	 */
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'dbDelta' ) ) {
	/**
	 * Stub for WordPress's dbDelta() that records calls in a global for tests.
	 *
	 * @param string|string[] $queries SQL queries to apply.
	 * @return array
	 */
	function dbDelta( $queries = '' ): array {
		$GLOBALS['wp_raffle_dbdelta_calls'][] = $queries;
		return array();
	}
}

// Ensure the global capture array exists.
$GLOBALS['wp_raffle_dbdelta_calls'] = array();

// ── WordPress / WooCommerce class stubs ──────────────────────────────────────
// Define minimal base classes so test fixtures can extend them and production
// code type-hints (via docblocks) work correctly at runtime.

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Minimal WP_Post stub for tests.
	 */
	class WP_Post {
		/** @var int */
		public int $ID = 0;
	}
}

if ( ! class_exists( 'WC_Order' ) ) {
	/**
	 * Minimal WC_Order stub for tests.
	 */
	class WC_Order {}
}

// ── WP_Mock bootstrap ────────────────────────────────────────────────────────

WP_Mock::bootstrap();
