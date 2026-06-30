<?php
/**
 * Ticket roll value object.
 *
 * @package WpWoocommerceRaffleTicket
 */

namespace WpWoocommerceRaffleTicket\Ticket;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TicketRoll
 *
 * Represents a physical roll of pre-printed raffle tickets.  Each roll belongs
 * to a product and spans a contiguous range of printed numbers starting at
 * start_number and covering ticket_count tickets.
 *
 * The current_offset tracks how many tickets from this roll have been claimed
 * (0 = untouched, ticket_count = fully exhausted).
 */
class TicketRoll {

	/**
	 * Constructor.
	 *
	 * @param int    $id             Database row ID.
	 * @param int    $product_id     Associated WooCommerce product ID.
	 * @param string $label          Human-readable label for the roll (e.g. "Roll A").
	 * @param int    $start_number   First printed number on the roll.
	 * @param int    $ticket_count   Total tickets on the roll.
	 * @param int    $current_offset Number of tickets already claimed (0-based).
	 * @param int    $sort_order     Admin-defined consumption order (lower = first).
	 */
	public function __construct(
		private int $id,
		private int $product_id,
		private string $label,
		private int $start_number,
		private int $ticket_count,
		private int $current_offset,
		private int $sort_order
	) {}

	/**
	 * Get the roll database ID.
	 *
	 * @return int
	 */
	public function getId(): int {
		return $this->id;
	}

	/**
	 * Get the associated product ID.
	 *
	 * @return int
	 */
	public function getProductId(): int {
		return $this->product_id;
	}

	/**
	 * Get the human-readable label.
	 *
	 * @return string
	 */
	public function getLabel(): string {
		return $this->label;
	}

	/**
	 * Get the first printed number on the roll.
	 *
	 * @return int
	 */
	public function getStartNumber(): int {
		return $this->start_number;
	}

	/**
	 * Get the total number of tickets on the roll.
	 *
	 * @return int
	 */
	public function getTicketCount(): int {
		return $this->ticket_count;
	}

	/**
	 * Get the number of tickets already claimed.
	 *
	 * @return int
	 */
	public function getCurrentOffset(): int {
		return $this->current_offset;
	}

	/**
	 * Get the admin-defined consumption order.
	 *
	 * @return int
	 */
	public function getSortOrder(): int {
		return $this->sort_order;
	}

	/**
	 * Get the last printed number on the roll (inclusive).
	 *
	 * @return int
	 */
	public function getLastNumber(): int {
		return $this->start_number + $this->ticket_count - 1;
	}

	/**
	 * Get the number of tickets still available on this roll.
	 *
	 * @return int
	 */
	public function getRemainingCapacity(): int {
		return max( 0, $this->ticket_count - $this->current_offset );
	}

	/**
	 * Whether this roll has been fully consumed.
	 *
	 * @return bool
	 */
	public function isExhausted(): bool {
		return $this->current_offset >= $this->ticket_count;
	}
}
