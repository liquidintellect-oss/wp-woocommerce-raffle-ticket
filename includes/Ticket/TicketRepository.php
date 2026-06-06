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
 */
class TicketRepository {

	/**
	 * Persist a ticket assignment to the database.
	 *
	 * @param TicketNumber $ticket        The ticket number value object.
	 * @param int          $order_id      The WooCommerce order ID.
	 * @param int          $order_item_id The WooCommerce order item ID.
	 * @param int          $customer_id   The customer user ID (0 for guests).
	 * @param int          $product_id    The product ID.
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
		int $product_id
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
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Retrieve all ticket records for a given order, ordered by sequence.
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
				"SELECT * FROM {$wpdb->prefix}raffle_tickets WHERE order_id = %d ORDER BY ticket_sequence ASC",
				$order_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $results ?? array();
	}

	/**
	 * Retrieve all ticket records joined with product name for the CSV report.
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
	 * Check whether any tickets have already been assigned for an order.
	 *
	 * Used as an idempotency guard to prevent double-assignment when the
	 * status-change hook fires more than once.
	 *
	 * @param int $order_id The WooCommerce order ID.
	 *
	 * @return bool True if tickets exist for this order.
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 */
	public function hasTicketsForOrder( int $order_id ): bool {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}raffle_tickets WHERE order_id = %d",
				$order_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $count > 0;
	}
}
