<?php
/**
 * Ticket number value object.
 *
 * @package WpWoocommerceRaffleTicket
 */

namespace WpWoocommerceRaffleTicket\Ticket;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TicketNumber
 *
 * Immutable value object representing a single formatted raffle ticket number.
 */
class TicketNumber {

	/**
	 * Constructor.
	 *
	 * @param string $prefix    The prefix portion of the ticket number.
	 * @param int    $sequence  The numeric sequence portion.
	 * @param string $formatted The fully formatted ticket string (prefix + padded sequence).
	 */
	public function __construct(
		private string $prefix,
		private int $sequence,
		private string $formatted
	) {}

	/**
	 * Get the ticket prefix.
	 *
	 * @return string
	 */
	public function getPrefix(): string {
		return $this->prefix;
	}

	/**
	 * Get the sequence number.
	 *
	 * @return int
	 */
	public function getSequence(): int {
		return $this->sequence;
	}

	/**
	 * Get the fully formatted ticket number string.
	 *
	 * @return string
	 */
	public function getFormatted(): string {
		return $this->formatted;
	}

	/**
	 * String representation returns the formatted ticket number.
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->formatted;
	}
}
