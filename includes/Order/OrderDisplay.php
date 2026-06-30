<?php
/**
 * Order display — raffle tickets in order detail views.
 *
 * @package WpWoocommerceRaffleTicket
 */

namespace WpWoocommerceRaffleTicket\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WpWoocommerceRaffleTicket\Ticket\TicketRepository;

/**
 * Class OrderDisplay
 *
 * Renders raffle ticket numbers on both the customer-facing order details page
 * and the WordPress admin order view.
 */
class OrderDisplay {

	/**
	 * Constructor.
	 *
	 * @param TicketRepository $ticket_repo Ticket retrieval layer.
	 * @param string           $label       Configurable display label (e.g. "Raffle Tickets").
	 */
	public function __construct(
		private TicketRepository $ticket_repo,
		private string $label
	) {}

	/**
	 * Render the raffle ticket list on the customer order details page.
	 *
	 * Hooked to `woocommerce_order_details_after_order_table`.
	 *
	 * @param \WC_Order $order The WooCommerce order object.
	 *
	 * @return void
	 */
	public function renderCustomer( $order ): void {
		$tickets = $this->ticket_repo->findByOrder( (int) $order->get_id() );

		if ( empty( $tickets ) ) {
			return;
		}

		/* translators: %s: configurable ticket label, e.g. "Raffle Tickets" */
		echo '<h2 class="raffle-tickets-heading">' . esc_html( sprintf( __( 'Your %s', 'wp-woocommerce-raffle-ticket' ), $this->label ) ) . '</h2>';
		echo '<ul class="raffle-tickets">';
		foreach ( $tickets as $ticket ) {
			echo '<li>' . esc_html( $ticket->ticket_number ) . '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Render the raffle ticket list in the admin order view.
	 *
	 * Hooked to `woocommerce_admin_order_data_after_order_details`.
	 *
	 * Each ticket is displayed with its roll identifier so the admin can
	 * cross-reference the physical roll.  The roll is shown as:
	 *   "{label}  ({start}–{last})"  when the roll has a label, or
	 *   "#{roll_id}  ({start}–{last})"  when no label is set.
	 *
	 * @param \WC_Order $order The WooCommerce order object.
	 *
	 * @return void
	 */
	public function renderAdmin( $order ): void {
		$tickets = $this->ticket_repo->findByOrder( (int) $order->get_id() );

		if ( empty( $tickets ) ) {
			return;
		}

		echo '<div class="order_data_column raffle-tickets-admin">';
		echo '<h4>' . esc_html( $this->label ) . '</h4>';
		echo '<ul>';
		foreach ( $tickets as $ticket ) {
			$roll_label = $this->formatRollLabel( $ticket );
			echo '<li>';
			echo '<strong>' . esc_html( $ticket->ticket_number ) . '</strong>';
			if ( '' !== $roll_label ) {
				echo ' &mdash; <span class="raffle-roll-label">' . esc_html( $roll_label ) . '</span>';
			}
			echo '</li>';
		}
		echo '</ul>';
		echo '</div>';
	}

	/**
	 * Build a human-readable roll identifier for an admin ticket row.
	 *
	 * Returns an empty string when the ticket has no roll info (should not
	 * happen for assigned tickets, but guards against unexpected null data).
	 *
	 * @param object $ticket A ticket row object from TicketRepository::findByOrder().
	 *
	 * @return string
	 */
	private function formatRollLabel( object $ticket ): string {
		if ( ! isset( $ticket->roll_id ) || null === $ticket->roll_id ) {
			return '';
		}

		$range = isset( $ticket->roll_start, $ticket->roll_last )
			? sprintf( '%s\u{2013}%s', $ticket->roll_start, $ticket->roll_last )
			: '';

		$name = ( isset( $ticket->roll_label ) && '' !== (string) $ticket->roll_label )
			? (string) $ticket->roll_label
			/* translators: %d: roll database ID */
			: sprintf( __( 'Roll #%d', 'wp-woocommerce-raffle-ticket' ), (int) $ticket->roll_id );

		return '' !== $range
			? sprintf( '%s (%s)', $name, $range )
			: $name;
	}
}
