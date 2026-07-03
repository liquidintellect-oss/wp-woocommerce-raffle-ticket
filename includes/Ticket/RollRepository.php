<?php
/**
 * Roll repository — manages physical ticket roll records.
 *
 * @package WpWoocommerceRaffleTicket
 */

namespace WpWoocommerceRaffleTicket\Ticket;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RollRepository
 *
 * Handles CRUD for raffle_ticket_rolls rows and provides an atomic per-roll
 * sequence counter.
 *
 * Atomic ticket claiming uses the same MySQL LAST_INSERT_ID(expr) trick as the
 * old SequenceRepository, now scoped to individual roll rows.  The SELECT →
 * UPDATE pattern retries on race conditions (another request exhausted the
 * chosen roll between our SELECT and UPDATE).
 */
class RollRepository {

	/** Maximum retry attempts when a concurrent request exhausts a roll first. */
	const MAX_CLAIM_RETRIES = 10;

	/**
	 * Atomically claim the next available ticket slot from the first non-exhausted
	 * roll for the given product.
	 *
	 * Returns an associative array describing the claimed slot, or null when no
	 * roll with remaining capacity exists for the product.
	 *
	 * @param int $product_id The product ID.
	 *
	 * @return array{roll_id:int,start_number:int,ticket_count:int,offset:int}|null
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 */
	public function nextTicket( int $product_id ): ?array {
		global $wpdb;

		$table = $wpdb->prefix . 'raffle_ticket_rolls';

		for ( $attempt = 0; $attempt < self::MAX_CLAIM_RETRIES; $attempt++ ) {
			// Find the first roll with remaining capacity ordered by sort_order, id.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$roll = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, start_number, ticket_count, current_offset, direction
					 FROM {$table}
					 WHERE product_id = %d AND current_offset < ticket_count
					 ORDER BY sort_order ASC, id ASC
					 LIMIT 1",
					$product_id
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( ! $roll ) {
				return null; // No rolls with capacity exist.
			}

			// Atomically increment current_offset, but only if the roll still
			// has capacity (guards against the race between SELECT and UPDATE).
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table}
					 SET current_offset = LAST_INSERT_ID(current_offset + 1)
					 WHERE id = %d AND current_offset < ticket_count",
					(int) $roll->id
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( ! $updated ) {
				// Another request exhausted this roll first — retry with next roll.
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$offset = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );

			return array(
				'roll_id'      => (int) $roll->id,
				'start_number' => (int) $roll->start_number,
				'ticket_count' => (int) $roll->ticket_count,
				'offset'       => $offset, // 1-based: offset 1 = start_number.
				'direction'    => ( isset( $roll->direction ) && 'desc' === $roll->direction ) ? 'desc' : 'asc',
			);
		}

		return null; // Too many retries (extreme concurrency).
	}

	/**
	 * Return the total number of tickets remaining across all rolls for a product.
	 *
	 * @param int $product_id The product ID.
	 *
	 * @return int
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 */
	public function remainingForProduct( int $product_id ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'raffle_ticket_rolls';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(GREATEST(0, ticket_count - current_offset)), 0)
				 FROM {$table}
				 WHERE product_id = %d",
				$product_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $result;
	}

	/**
	 * Return all rolls for a product ordered by sort_order, id.
	 *
	 * @param int $product_id The product ID.
	 *
	 * @return TicketRoll[]
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 */
	public function findByProduct( int $product_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'raffle_ticket_rolls';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE product_id = %d ORDER BY sort_order ASC, id ASC",
				$product_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array_map( array( $this, 'rowToTicketRoll' ), $rows ?? array() );
	}

	/**
	 * Return all rolls for all products, enriched with a product_name column,
	 * ordered by product_id, sort_order, id.
	 *
	 * @return object[] Raw row objects with an extra product_name property.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 */
	public function findAll(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'raffle_ticket_rolls';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT r.*, p.post_title AS product_name
			 FROM {$table} r
			 LEFT JOIN {$wpdb->posts} p ON p.ID = r.product_id
			 ORDER BY r.product_id ASC, r.sort_order ASC, r.id ASC"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $rows ?? array();
	}

	/**
	 * Persist a new roll and return its database ID.
	 *
	 * @param int    $product_id   The product this roll belongs to.
	 * @param string $label        Human-readable label.
	 * @param int    $start_number First printed ticket number.
	 * @param int    $ticket_count Total tickets on the roll.
	 * @param int    $sort_order   Consumption order (lower = first).
	 * @param string $direction    'asc' (numbers increase) or 'desc' (numbers decrease).
	 *
	 * @return int The new roll's database ID.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 */
	public function create( int $product_id, string $label, int $start_number, int $ticket_count, int $sort_order, string $direction = 'asc' ): int {
		global $wpdb;

		$safe_direction = ( 'desc' === $direction ) ? 'desc' : 'asc';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'raffle_ticket_rolls',
			array(
				'product_id'     => $product_id,
				'label'          => $label,
				'start_number'   => $start_number,
				'ticket_count'   => $ticket_count,
				'current_offset' => 0,
				'sort_order'     => $sort_order,
				'direction'      => $safe_direction,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Decrement a roll's current_offset by the given amount, floored at zero.
	 *
	 * Called after tickets are deleted in the overwrite flow so that the freed
	 * slots can be re-claimed by the next nextTicket() call instead of being
	 * permanently burned.
	 *
	 * @param int $roll_id The roll whose offset to decrement.
	 * @param int $amount  Number of slots to release (must be positive).
	 *
	 * @return void
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 */
	public function decrementOffset( int $roll_id, int $amount ): void {
		if ( $amount <= 0 ) {
			return;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'raffle_ticket_rolls';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$table}
				 SET current_offset = GREATEST(0, current_offset - %d)
				 WHERE id = %d",
				$amount,
				$roll_id
			)
		);
	}

	/**
	 * Delete a roll by its database ID.
	 *
	 * @param int $roll_id The roll ID to delete.
	 *
	 * @return void
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 */
	public function delete( int $roll_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$wpdb->prefix . 'raffle_ticket_rolls',
			array( 'id' => $roll_id ),
			array( '%d' )
		);
	}

	/**
	 * Convert a raw database row object to a TicketRoll value object.
	 *
	 * @param object $row Raw database row.
	 *
	 * @return TicketRoll
	 */
	private function rowToTicketRoll( object $row ): TicketRoll {
		$direction = isset( $row->direction ) && 'desc' === $row->direction ? 'desc' : 'asc';

		return new TicketRoll(
			(int) $row->id,
			(int) $row->product_id,
			(string) $row->label,
			(int) $row->start_number,
			(int) $row->ticket_count,
			(int) $row->current_offset,
			(int) $row->sort_order,
			$direction
		);
	}
}
