<?php
/**
 * Ticket repository.
 *
 * @package WpWoocommerceRaffleTicket
 */

namespace WpWoocommerceRaffleTicket\Ticket;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TicketRepository
 *
 * Handles persistence and retrieval of raffle ticket records in the custom
 * raffle_tickets database table.
 *
 * Tickets may be "assigned" (roll_id IS NOT NULL) or "pending" (roll_id IS NULL).
 * Pending tickets are created when an order is placed but no physical rolls with
 * remaining capacity exist.  They are replaced with assigned tickets when rolls
 * are added and the retroactive assignment process runs.
 */
class TicketRepository {

	/**
	 * Prefix used for pending (unassigned) ticket_number placeholders.
	 * Unique placeholders prevent violating the ticket_number UNIQUE KEY.
	 */
	const PENDING_PREFIX = 'PENDING-';

	/**
	 * Persist an assigned ticket to the database.
	 *
	 * @param TicketNumber $ticket        The ticket number value object.
	 * @param int          $order_id      The WooCommerce order ID.
	 * @param int          $order_item_id The WooCommerce order item ID.
	 * @param int          $customer_id   The customer user ID (0 for guests).
	 * @param int          $product_id    The product ID.
	 * @param int          $roll_id       The physical roll this ticket came from.
	 *
	 * @return void
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 */
	public function save(
		TicketNumber $ticket,
		int $order_id,
		int $order_item_id,
		int $customer_id,
		int $product_id,
		int $roll_id
	): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'raffle_tickets',
			array(
				'product_id'      => $product_id,
				'order_id'        => $order_id,
				'order_item_id'   => $order_item_id,
				'customer_id'     => $customer_id,
				'ticket_number'   => $ticket->getFormatted(),
				'ticket_prefix'   => $ticket->getPrefix(),
				'ticket_sequence' => $ticket->getSequence(),
				'roll_id'         => $roll_id,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%d', '%s' )
		);
	}

	/**
	 * Persist a pending (unassigned) ticket placeholder to the database.
	 *
	 * Used when an order is placed but no physical rolls have remaining capacity.
	 * The roll_id column is left NULL to mark the record as pending.  A unique
	 * placeholder ticket_number is generated to satisfy the UNIQUE KEY constraint.
	 *
	 * @param string $prefix        The product's configured ticket prefix.
	 * @param int    $order_id      The WooCommerce order ID.
	 * @param int    $order_item_id The WooCommerce order item ID.
	 * @param int    $customer_id   The customer user ID (0 for guests).
	 * @param int    $product_id    The product ID.
	 *
	 * @return void
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 */
	public function saveUnassigned(
		string $prefix,
		int $order_id,
		int $order_item_id,
		int $customer_id,
		int $product_id
	): void {
		global $wpdb;

		// Generate a unique placeholder that satisfies the UNIQUE KEY constraint.
		$placeholder = self::PENDING_PREFIX . uniqid( '', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'raffle_tickets',
			array(
				'product_id'      => $product_id,
				'order_id'        => $order_id,
				'order_item_id'   => $order_item_id,
				'customer_id'     => $customer_id,
				'ticket_number'   => $placeholder,
				'ticket_prefix'   => $prefix,
				'ticket_sequence' => 0,
				'roll_id'         => null,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%d', null, '%s' )
		);
	}

	/**
	 * Delete all pending (unassigned) ticket placeholders for an order.
	 *
	 * Called before re-running ticket assignment on an order (retroactive flow),
	 * so that pending placeholders are replaced by proper assigned records.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 *
	 * @return void
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 */
	public function deleteUnassignedForOrder( int $order_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$wpdb->prefix . 'raffle_tickets',
			array(
				'order_id' => $order_id,
				'roll_id'  => null,
			),
			array( '%d', null )
		);
	}

	/**
	 * Retrieve assigned ticket records for a given order, ordered by sequence.
	 *
	 * Only returns rows with a non-NULL roll_id (fully assigned tickets).
	 * Pending placeholders are excluded so that customer-facing views and the
	 * admin order panel do not display meaningless placeholder values.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 *
	 * @return object[] Array of row objects.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 */
	public function findByOrder( int $order_id ): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}raffle_tickets
				 WHERE order_id = %d AND roll_id IS NOT NULL
				 ORDER BY ticket_sequence ASC",
				$order_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $results ?? array();
	}

	/**
	 * Retrieve all ticket records joined with product name for the CSV report.
	 *
	 * Includes both assigned and pending records.  Callers can distinguish them
	 * by checking whether roll_id is NULL.
	 *
	 * @return object[] Array of row objects (includes product_name column).
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 */
	public function findAll(): array {
		global $wpdb;

		$tickets_table = $wpdb->prefix . 'raffle_tickets';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			"SELECT t.*, p.post_title AS product_name
			 FROM {$tickets_table} t
			 LEFT JOIN {$wpdb->posts} p ON p.ID = t.product_id
			 ORDER BY t.order_id ASC, t.ticket_sequence ASC"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $results ?? array();
	}

	/**
	 * Check whether any assigned (roll_id IS NOT NULL) tickets exist for an order.
	 *
	 * Used as an idempotency guard to prevent double-assignment when the
	 * status-change hook fires more than once, and to decide whether an order
	 * needs retroactive assignment.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 *
	 * @return bool True if at least one assigned ticket exists for this order.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 */
	public function hasAssignedTicketsForOrder( int $order_id ): bool {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}raffle_tickets
				 WHERE order_id = %d AND roll_id IS NOT NULL",
				$order_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $count > 0;
	}

	/**
	 * Return distinct product IDs that have at least one pending ticket.
	 *
	 * Used to populate the admin banner warning that sold orders are awaiting
	 * physical roll assignment.
	 *
	 * @return int[] Array of product IDs with pending tickets.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 */
	public function findProductsWithUnassignedTickets(): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_col(
			"SELECT DISTINCT product_id FROM {$wpdb->prefix}raffle_tickets WHERE roll_id IS NULL"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array_map( 'intval', $results ?? array() );
	}
}
