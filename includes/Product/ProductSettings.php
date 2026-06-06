<?php
/**
 * Product raffle settings model.
 *
 * @package WpWoocommerceRaffleTicket
 */

namespace WpWoocommerceRaffleTicket\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ProductSettings
 *
 * Reads per-product raffle ticket configuration stored as WooCommerce product
 * meta.  Acts as a value object: construct via the static factory and then
 * access settings through the getters.
 */
class ProductSettings {

	/** Meta key for the enabled flag — '1' when enabled, '' when disabled. */
	const META_ENABLED = '_raffle_ticket_enabled';

	/** Meta key for the ticket number prefix string. */
	const META_PREFIX = '_raffle_ticket_prefix';

	/** Meta key for the minimum (first) sequence number. */
	const META_MIN_SEQUENCE = '_raffle_ticket_min_sequence';

	/** Meta key for the maximum (last allowed) sequence number. */
	const META_MAX_SEQUENCE = '_raffle_ticket_max_sequence';

	/**
	 * Constructor.
	 *
	 * @param bool   $enabled      Whether raffle tickets are enabled for this product.
	 * @param string $prefix       The ticket number prefix string.
	 * @param int    $min_sequence The minimum (starting) sequence number.
	 * @param int    $max_sequence The maximum sequence number.
	 */
	public function __construct(
		private bool $enabled,
		private string $prefix,
		private int $min_sequence,
		private int $max_sequence
	) {}

	/**
	 * Load raffle settings for the given product.
	 *
	 * Missing or empty meta values fall back to sensible defaults (min=1, max=9999).
	 *
	 * @param int $product_id The WooCommerce product post ID.
	 *
	 * @return self
	 */
	public static function forProduct( int $product_id ): self {
		$enabled      = '1' === get_post_meta( $product_id, self::META_ENABLED, true );
		$prefix       = (string) get_post_meta( $product_id, self::META_PREFIX, true );
		$min_raw      = (int) get_post_meta( $product_id, self::META_MIN_SEQUENCE, true );
		$min_sequence = $min_raw ? $min_raw : 1;
		$max_raw      = (int) get_post_meta( $product_id, self::META_MAX_SEQUENCE, true );
		$max_sequence = $max_raw ? $max_raw : 9999;

		return new self( $enabled, $prefix, $min_sequence, $max_sequence );
	}

	/**
	 * Whether raffle ticket assignment is enabled for this product.
	 *
	 * @return bool
	 */
	public function isEnabled(): bool {
		return $this->enabled;
	}

	/**
	 * Get the ticket number prefix.
	 *
	 * @return string
	 */
	public function getPrefix(): string {
		return $this->prefix;
	}

	/**
	 * Get the minimum (first) sequence number.
	 *
	 * @return int
	 */
	public function getMinSequence(): int {
		return $this->min_sequence;
	}

	/**
	 * Get the maximum sequence number.
	 *
	 * @return int
	 */
	public function getMaxSequence(): int {
		return $this->max_sequence;
	}
}
