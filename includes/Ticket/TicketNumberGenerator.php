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

/**
 * Class TicketNumberGenerator
 *
 * Combines a ticket prefix, a physical roll, and a 1-based offset within that
 * roll to produce a formatted TicketNumber value object.
 *
 * Format: {prefix}{zero-padded physical number}
 * Padding width = number of digits in the roll's last printed number.
 *
 * Example: prefix="RAFFLE-", roll start=1001 count=500, offset=1
 *   → physical number = 1001
 *   → last number     = 1500 (4 digits)
 *   → formatted       = "RAFFLE-1001"
 */
class TicketNumberGenerator {

	/**
	 * Generate a TicketNumber for the given roll and 1-based offset.
	 *
	 * @param string     $prefix The product's configured ticket prefix.
	 * @param TicketRoll $roll   The physical roll being consumed.
	 * @param int        $offset 1-based position within the roll (1 = first ticket).
	 *
	 * @return TicketNumber
	 */
	public function generate( string $prefix, TicketRoll $roll, int $offset ): TicketNumber {
		$physical_number = $roll->getStartNumber() + ( $offset - 1 );
		$last_number     = $roll->getLastNumber();
		$pad_length      = strlen( (string) $last_number );
		$padded          = str_pad( (string) $physical_number, $pad_length, '0', STR_PAD_LEFT );
		$formatted       = $prefix . $padded;

		return new TicketNumber( $prefix, $physical_number, $formatted );
	}
}
