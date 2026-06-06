<?php
/**
 * Sequence repository — atomic ticket sequence counter.
 *
 * @package WpWoocommerceRaffleTicket
 */

namespace WpWoocommerceRaffleTicket\Ticket;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use RuntimeException;

/**
 * Class SequenceRepository
 *
 * Manages per-product ticket sequence counters with concurrent-safe atomic
 * increments.  Uses MySQL's connection-scoped LAST_INSERT_ID(expr) trick:
 * the UPDATE sets the value AND stores it in a per-connection register, so
 * the subsequent SELECT LAST_INSERT_ID() retrieves exactly the number this
 * connection reserved — even under high concurrency.
 */
class SequenceRepository {

	/**
	 * Reserve and return the next sequence number for a product.
	 *
	 * Initialises the row on first use (current_sequence = min − 1) so that
	 * the very first increment lands on min.  Throws if the reserved number
	 * exceeds the configured maximum.
	 *
	 * @param int $product_id The product ID.
	 * @param int $min        The configured minimum (first) sequence value.
	 * @param int $max        The configured maximum (last allowed) sequence value.
	 *
	 * @return int The sequence number reserved for this ticket.
	 *
	 * @throws RuntimeException With message 'sold_out' when max is exceeded.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 */
	public function nextSequence( int $product_id, int $min, int $max ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'raffle_ticket_sequences';

		// Ensure a row exists for this product, seeded one below min so that
		// the first increment produces exactly min.  ON DUPLICATE KEY UPDATE
		// is a no-op when the row already exists.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (product_id, current_sequence)
				 VALUES (%d, %d)
				 ON DUPLICATE KEY UPDATE current_sequence = current_sequence",
				$product_id,
				$min - 1
			)
		);

		// Atomic increment.  LAST_INSERT_ID(expr) stores expr in a per-connection
		// register AND returns it, making concurrent increments safe.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET current_sequence = LAST_INSERT_ID(current_sequence + 1)
				 WHERE product_id = %d",
				$product_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sequence = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );

		if ( $sequence > $max ) {
			throw new RuntimeException( 'sold_out' );
		}

		return $sequence;
	}

	/**
	 * Return how many tickets remain available for a product.
	 *
	 * Returns the full range when no sequences have been issued yet.
	 *
	 * @param int $product_id The product ID.
	 * @param int $min        The configured minimum sequence value.
	 * @param int $max        The configured maximum sequence value.
	 *
	 * @return int Remaining capacity (≥ 0).
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 */
	public function remaining( int $product_id, int $min, int $max ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'raffle_ticket_sequences';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT current_sequence FROM {$table} WHERE product_id = %d",
				$product_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( null === $result ) {
			// No row yet — all tickets are available.
			return $max - $min + 1;
		}

		return max( 0, $max - (int) $result );
	}
}
