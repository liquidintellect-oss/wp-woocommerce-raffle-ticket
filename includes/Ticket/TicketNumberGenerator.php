<?php
/**
 * Ticket number generator.
 *
 * @package WpWoocommerceRaffleTicket
 */

namespace WpWoocommerceRaffleTicket\Ticket;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WpWoocommerceRaffleTicket\Product\ProductSettings;

/**
 * Class TicketNumberGenerator
 *
 * Combines a product's raffle settings with a reserved sequence number to
 * produce a formatted TicketNumber value object.
 *
 * Format: {prefix}{zero-padded sequence}
 * The padding width equals the number of digits in max_sequence.
 * Example: prefix="RAFFLE-", max=9999, sequence=42 → "RAFFLE-0042"
 */
class TicketNumberGenerator {

	/**
	 * Generate a TicketNumber for the given settings and reserved sequence.
	 *
	 * @param ProductSettings $settings The product's raffle configuration.
	 * @param int             $sequence The reserved sequence number.
	 *
	 * @return TicketNumber
	 */
	public function generate( ProductSettings $settings, int $sequence ): TicketNumber {
		$pad_length = strlen( (string) $settings->getMaxSequence() );
		$padded     = str_pad( (string) $sequence, $pad_length, '0', STR_PAD_LEFT );
		$formatted  = $settings->getPrefix() . $padded;

		return new TicketNumber( $settings->getPrefix(), $sequence, $formatted );
	}
}
