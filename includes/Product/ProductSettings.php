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
 *
 * As of 1.1.0, per-product min/max sequence ranges have been replaced by
 * physical ticket rolls managed through RollRepository.  The deprecated meta
 * key constants are kept to allow read-back for any custom migration code.
 */
class ProductSettings {

	/** Meta key for the enabled flag — '1' when enabled, '' when disabled. */
	const META_ENABLED = '_raffle_ticket_enabled';

	/** Meta key for the ticket number prefix string. */
	const META_PREFIX = '_raffle_ticket_prefix';

	/**
	 * Deprecated: minimum sequence meta key. Sequence ranges are now managed per physical roll.
	 *
	 * @deprecated 1.1.0
	 */
	const META_MIN_SEQUENCE = '_raffle_ticket_min_sequence';

	/**
	 * Deprecated: maximum sequence meta key. Sequence ranges are now managed per physical roll.
	 *
	 * @deprecated 1.1.0
	 */
	const META_MAX_SEQUENCE = '_raffle_ticket_max_sequence';

	/**
	 * Constructor.
	 *
	 * @param bool   $enabled Whether raffle tickets are enabled for this product.
	 * @param string $prefix  The ticket number prefix string.
	 */
	public function __construct(
		private bool $enabled,
		private string $prefix
	) {}

	/**
	 * Load raffle settings for the given product.
	 *
	 * @param int $product_id The WooCommerce product post ID.
	 *
	 * @return self
	 */
	public static function forProduct( int $product_id ): self {
		$enabled = '1' === get_post_meta( $product_id, self::META_ENABLED, true );
		$prefix  = (string) get_post_meta( $product_id, self::META_PREFIX, true );

		return new self( $enabled, $prefix );
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
}
